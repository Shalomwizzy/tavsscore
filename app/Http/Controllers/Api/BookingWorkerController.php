<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingCode;
use App\Services\Booking\BetslipSpecService;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Endpoints the external automation worker talks to (behind worker.token):
 *  - GET  betslip-spec  → today's selections to build codes for
 *  - POST booking-codes → store a generated code + shareable link
 *
 * The worker never receives or needs any user data. Codes are public betslips.
 */
class BookingWorkerController extends Controller
{
    public function spec(BetslipSpecService $specs): JsonResponse
    {
        return response()->json($specs->today());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform'   => ['required', 'string', 'max:40'],
            'code'       => ['required', 'string', 'max:60'],
            'link'       => ['nullable', 'url', 'max:500'],
            'slip_ref'   => ['nullable', 'string', 'max:60'],
            'fixtures'   => ['nullable', 'array'],
            'total_odds' => ['nullable', 'numeric', 'min:1'],
            'status'     => ['nullable', 'in:pending,published,failed,expired'],
            'note'       => ['nullable', 'string', 'max:500'],
            'pick_date'  => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $platform = strtolower(trim($data['platform']));
        $pickDate = $data['pick_date'] ?? now('Africa/Lagos')->toDateString();

        // Idempotent per platform + slip + day so re-runs update rather than duplicate.
        $code = BookingCode::query()->updateOrCreate(
            [
                'platform'  => $platform,
                'slip_ref'  => $data['slip_ref'] ?? null,
                'pick_date' => $pickDate,
            ],
            [
                'code'       => $data['code'],
                'link'       => $data['link'] ?? null,
                'fixtures'   => $data['fixtures'] ?? null,
                'total_odds' => $data['total_odds'] ?? null,
                'status'     => $data['status'] ?? 'published',
                'note'       => $data['note'] ?? null,
                'source'     => 'auto',
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        return response()->json(['ok' => true, 'id' => $code->id], 201);
    }

    /**
     * Push today's published booking codes to Telegram + OneSignal, once. The
     * worker calls this after posting all codes; the daily cache guard means
     * retries or extra runs never re-send.
     */
    public function notify(TelegramService $telegram, OneSignalService $oneSignal): JsonResponse
    {
        $date = now('Africa/Lagos')->toDateString();
        if (! Cache::add("booking_notified_{$date}", true, 86400)) {
            return response()->json(['ok' => true, 'skipped' => 'already notified today']);
        }

        $codes = BookingCode::query()
            ->where('pick_date', $date)
            ->where('status', 'published')
            ->orderByDesc('total_odds')
            ->get();

        if ($codes->isEmpty()) {
            Cache::forget("booking_notified_{$date}");
            return response()->json(['ok' => true, 'skipped' => 'no published codes']);
        }

        try {
            $telegram->sendBookingCodesDigest($codes, config('app.url'));
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $platform = ucfirst(strtolower((string) $codes->first()->platform));
            $oneSignal->sendMatchAlert(
                '🎟️ Booking Codes Ready',
                $codes->count()." {$platform} booking codes are live today — tap to view.",
                '/booking-codes',
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true, 'notified' => $codes->count()]);
    }
}
