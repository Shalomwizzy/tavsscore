<?php

namespace App\Services;

use App\Models\Prediction;
use App\Models\TennisPrediction;
use Illuminate\Support\Facades\Artisan;

/**
 * Admin-triggered, verified recovery for predictions that were left pending
 * after a provider outage or a quota interruption. It never invents results:
 * a prediction is graded only after a final score exists on its match.
 */
class PendingPredictionRecoveryService
{
    private const HISTORY_DAYS = 365;
    private const RECENT_API_RETRY_DAYS = 14;

    /** @return array{football_settled:int, tennis_settled:int, football_pending:int, tennis_pending:int, warnings:list<string>} */
    public function recover(): array
    {
        $football = $this->recoverFootball();
        $tennisBefore = $this->pendingTennis();
        $warnings = $football['warnings'];

        try {
            Artisan::call('tennis:settle-results');
        } catch (\Throwable $exception) {
            $warnings[] = 'Tennis recovery could not complete. It will retry automatically.';
        }

        $tennisAfter = $this->pendingTennis();

        return [
            'football_settled' => $football['settled'],
            'tennis_settled' => max(0, $tennisBefore - $tennisAfter),
            'football_pending' => $football['pending'],
            'tennis_pending' => $tennisAfter,
            'warnings' => $warnings,
        ];
    }

    /** @return array{settled:int, pending:int, warnings:list<string>} */
    public function recoverFootball(): array
    {
        $before = $this->pendingFootball(self::HISTORY_DAYS);
        $warnings = [];

        try {
            // First grade any final scores that are already stored locally.
            Artisan::call('predictions:check-outcomes', ['--days' => self::HISTORY_DAYS]);

            // Then fill missing scores from the free fallback sources. ESPN's
            // global board covers many competitions when API-Football is capped.
            Artisan::call('results:fallback', ['--days' => self::HISTORY_DAYS]);
            Artisan::call('predictions:check-outcomes', ['--days' => self::HISTORY_DAYS]);

            // A small recent retry uses API-Football only when its quota is
            // available. The command skips its fetch safely when it is not.
            if ($this->pendingFootball(self::RECENT_API_RETRY_DAYS) > 0) {
                Artisan::call('results:catch-up', ['--days' => self::RECENT_API_RETRY_DAYS]);
            }
        } catch (\Throwable $exception) {
            $warnings[] = 'Football recovery could not complete every source. It will retry automatically.';
        }

        $after = $this->pendingFootball(self::HISTORY_DAYS);

        return [
            'settled' => max(0, $before - $after),
            'pending' => $after,
            'warnings' => $warnings,
        ];
    }

    private function pendingFootball(int $days): int
    {
        return Prediction::query()
            ->whereNull('was_correct')
            ->whereNotNull('predicted_outcome')
            ->where('predicted_outcome', '!=', 'Competitive Match')
            ->whereHas('match', fn ($query) => $query
                ->where('match_time', '<', now()->subMinutes(150))
                ->where('match_time', '>=', now()->subDays($days)))
            ->count();
    }

    private function pendingTennis(): int
    {
        return TennisPrediction::query()
            ->whereNull('was_correct')
            ->whereHas('match', fn ($query) => $query
                ->whereDate('match_date', '<', now('Africa/Lagos')->toDateString()))
            ->count();
    }
}
