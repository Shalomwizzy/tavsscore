<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingCode;
use App\Services\Booking\BetslipSpecService;
use App\Services\Booking\BookingCodeGenerationRequest;
use App\Services\Booking\BookingCodeLedgerService;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use App\Services\ImageWatermarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    /** Read the latest admin “Generate codes” request from the Mac worker. */
    public function generationRequest(BookingCodeGenerationRequest $generationRequests): JsonResponse
    {
        $request = $generationRequests->pending();

        return response()->json([
            'requested' => $request !== null,
            'request' => $request,
        ]);
    }

    /** Clear a request only after the Mac worker completed that exact run. */
    public function completeGenerationRequest(Request $request, BookingCodeGenerationRequest $generationRequests): JsonResponse
    {
        $data = $request->validate(['request_id' => ['required', 'uuid']]);

        return response()->json(['ok' => $generationRequests->complete($data['request_id'])]);
    }

    public function store(Request $request, BookingCodeLedgerService $ledger, TelegramService $telegram, OneSignalService $oneSignal): JsonResponse
    {
        $data = $request->validate([
            'platform'   => ['required', 'string', 'max:40'],
            'code'       => ['required', 'string', 'max:60'],
            'link'       => ['nullable', 'url', 'max:500'],
            'slip_ref'   => ['nullable', 'string', 'max:60'],
            'fixtures'   => ['nullable', 'array'],
            // JPEG/PNG captured by the authenticated Mac worker after SportyBet
            // visibly loads the actual shared ticket. It is optional: a ticket
            // is still published if the bookmaker blocks the screenshot page.
            'ticket_screenshot' => ['nullable', 'string', 'max:5000000'],
            // Every publishable ticket itself must clear the 2.00 minimum.
            'total_odds' => ['nullable', 'numeric', 'min:2', 'max:500', 'required_if:status,published'],
            // The browser worker retries transient errors locally. It must
            // never turn an unsuccessful attempt into a user-visible record.
            'status'     => ['nullable', 'in:pending,published,expired'],
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

        if (filled($data['ticket_screenshot'] ?? null)) {
            try {
                $path = $this->storeTicketScreenshot((string) $data['ticket_screenshot'], $code);
                if ($path) {
                    $code->update(['ticket_image_path' => $path]);
                }
            } catch (\Throwable $e) {
                // A screenshot is proof/extra presentation only; never discard
                // a real booking code because image storage failed.
                report($e);
            }
        }

        if (($data['status'] ?? 'published') === 'published') {
            $ledger->syncLegs($code);
        }

        // Push each newly-published code to Telegram + OneSignal immediately.
        // An idempotent re-run with the same code never re-notifies. A changed
        // code or a newly captured real-ticket image does re-notify so Telegram
        // receives the proof photo as soon as it is available.
        if (($data['status'] ?? 'published') === 'published'
            && ($code->wasRecentlyCreated || $code->wasChanged('code') || $code->wasChanged('ticket_image_path'))) {
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
                ticketImagePath: $code->ticket_image_path,
                totalOdds: $code->total_odds,
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

    private function storeTicketScreenshot(string $encoded, BookingCode $code): ?string
    {
        if (! preg_match('#^data:image/(png|jpe?g);base64,([A-Za-z0-9+/=\s]+)$#i', $encoded, $matches)) {
            return null;
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
        if ($binary === false || strlen($binary) < 16 || strlen($binary) > 3_500_000) {
            return null;
        }

        $isJpeg = str_starts_with($binary, "\xFF\xD8\xFF");
        $isPng = str_starts_with($binary, "\x89PNG\r\n\x1A\n");
        if (! $isJpeg && ! $isPng) {
            return null;
        }

        $extension = $isPng ? 'png' : 'jpg';
        $date = ($code->pick_date ?? now('Africa/Lagos'))->format('Y-m-d');
        $path = "booking-codes/{$date}/ticket-{$code->id}.{$extension}";
        $binary = app(ImageWatermarkService::class)->stamp($binary);
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
