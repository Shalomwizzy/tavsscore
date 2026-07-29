<?php

namespace App\Console\Commands;

use App\Models\BookingCode;
use App\Models\FootballMatch;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use App\Support\PickHelpers;
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

    private const FINISHED = ['FT', 'AET', 'PEN'];

    public function handle(TelegramService $telegram, OneSignalService $oneSignal): int
    {
        $codes = BookingCode::query()
            ->where('status', 'published')
            ->whereNull('settled_at')
            ->whereNotNull('fixtures')
            ->where('pick_date', '>=', now('Africa/Lagos')->subDays(10)->toDateString())
            ->get();

        $settled = 0;

        foreach ($codes as $code) {
            $legs = is_array($code->fixtures) ? $code->fixtures : [];
            if ($legs === []) {
                continue;
            }

            $anyLost = false;
            $pending = false;
            $decided = 0;

            foreach ($legs as $leg) {
                $match = ! empty($leg['match_id']) ? FootballMatch::find($leg['match_id']) : null;
                if (! $match) {
                    // Keep checking rather than marking an accumulator won while
                    // a saved leg cannot yet be matched to a final result.
                    $pending = true;
                    continue;
                }
                if (! in_array($match->status, self::FINISHED, true)) {
                    $pending = true;
                    continue;
                }
                $result = PickHelpers::resolveForMatch($match, $leg['market'] ?? null);
                if ($result === null) {
                    // A void/ungradeable leg needs review; never silently turn a
                    // partly-known code into a false "won" result.
                    $pending = true;
                    continue;
                }
                $decided++;
                if ($result === false) {
                    $anyLost = true;
                }
            }

            // A dead leg kills the accumulator even while others are pending.
            if ($anyLost) {
                $code->update(['status' => 'lost', 'settled_at' => now()]);
                $this->announce($code, false, $telegram, $oneSignal);
                $settled++;
            } elseif (! $pending && $decided > 0) {
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
