<?php

namespace App\Console\Commands;

use App\Models\BookingCode;
use App\Services\Booking\BookingCodeLedgerService;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Grades published booking codes as an accumulator: every leg must win. One
 * losing leg settles the whole code as LOST immediately; all legs winning (none
 * still pending) settles it WON. Idempotent via settled_at. Pushes the outcome
 * to Telegram + OneSignal so a won/lost history builds on /booking-codes.
 */
class GradeBookingCodes extends Command
{
    protected $signature = 'booking:grade';

    protected $description = 'Settle booking codes (accumulator win/loss) and notify the outcome.';

    public function handle(BookingCodeLedgerService $ledger, TelegramService $telegram, OneSignalService $oneSignal): int
    {
        $codes = BookingCode::query()
            ->where('status', 'published')
            ->whereNull('settled_at')
            ->whereNotNull('fixtures')
            ->where('pick_date', '>=', now('Africa/Lagos')->subDays(10)->toDateString())
            ->get();

        $settled = 0;

        foreach ($codes as $code) {
            $result = $ledger->grade($code);

            // A dead leg kills the accumulator even while other saved legs are pending.
            if ($result['settled'] && ! $result['won']) {
                $code->update(['status' => 'lost', 'settled_at' => now()]);
                $this->announce($code, false, $telegram, $oneSignal);
                $settled++;
            } elseif ($result['settled'] && $result['won']) {
                $code->update(['status' => 'won', 'settled_at' => now()]);
                $this->announce($code, true, $telegram, $oneSignal);
                $settled++;
            }
        }

        $this->info("Graded {$settled} booking code(s).");

        return self::SUCCESS;
    }

    private function announce(BookingCode $code, bool $won, TelegramService $telegram, OneSignalService $oneSignal): void
    {
        $label = $code->note ?: ($code->slip_ref ?: 'Booking code');

        try {
            $telegram->sendBookingOutcome($code->platform, strtoupper($code->code), (string) ($code->note ?? ''), $won, config('app.url'));
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $oneSignal->sendMatchAlert(
                $won ? '✅ Booking Code Won' : '❌ Booking Code Lost',
                $label.' ('.strtoupper($code->code).') '.($won ? 'won! 🎉' : 'lost.').' Tap for history.',
                '/booking-codes',
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
