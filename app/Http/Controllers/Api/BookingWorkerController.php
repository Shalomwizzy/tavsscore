<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingCode;
use App\Services\Booking\BetslipSpecService;
use App\Services\Booking\BookingCodeLedgerService;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request, BookingCodeLedgerService $ledger, TelegramService $telegram, OneSignalService $oneSignal): JsonResponse
    {
        $data = $request->validate([
            'platform'   => ['required', 'string', 'max:40'],
            'code'       => ['required', 'string', 'max:60'],
            'link'       => ['nullable', 'url', 'max:500'],
            'slip_ref'   => ['nullable', 'string', 'max:60'],
            'fixtures'   => ['nullable', 'array'],
            // Failed worker attempts are logged without an odds value; every
            // publishable ticket itself must clear the 2.00 minimum.
            'total_odds' => ['nullable', 'numeric', 'min:2', 'max:500', 'required_if:status,published'],
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
                'settled_at' => null,
            ]
        );

        if (($data['status'] ?? 'published') === 'published') {
            $ledger->syncLegs($code);
        }

        // Push each newly-published code to Telegram + OneSignal immediately.
        // wasRecentlyCreated / wasChanged('code') means an idempotent re-run
        // with the same code never re-notifies; a changed code does.
        if (($data['status'] ?? 'published') === 'published'
            && ($code->wasRecentlyCreated || $code->wasChanged('code'))) {
            $this->announce($code, $telegram, $oneSignal);
        }

        return response()->json(['ok' => true, 'id' => $code->id], 201);
    }

    private function announce(BookingCode $code, TelegramService $telegram, OneSignalService $oneSignal): void
    {
        $label = $code->note ?: ($code->slip_ref ?: 'Booking Code');
        $odds  = $code->total_odds ? ' @ '.number_format((float) $code->total_odds, 2) : '';

        try {
            $telegram->sendBookingCode(
                $code->platform,
                strtoupper($code->code),
                (string) ($code->note ?? ''),
                config('app.url'),
                ticketUrl: $code->link,
            );
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $oneSignal->sendMatchAlert(
                '🎟️ '.$label.' — '.strtoupper($code->platform),
                'Booking code '.strtoupper($code->code).$odds.' — tap for today’s codes.',
                '/booking-codes',
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
