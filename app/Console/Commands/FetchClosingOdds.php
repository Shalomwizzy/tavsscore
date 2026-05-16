<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\OddsService;
use App\Support\LeagueCoverage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Fetch updated bookmaker odds for today's daily picks and store them as
 * closing_odds. Running this 2-3 hours before typical kickoffs gives a
 * meaningful closing-line snapshot without waiting until the last minute.
 *
 * Movement (closing vs opening) is used on pick cards to show whether the
 * market is drifting toward or away from our AI pick.
 */
class FetchClosingOdds extends Command
{
    protected $signature   = 'picks:fetch-closing-odds';
    protected $description = 'Fetch near-closing bookmaker odds for today\'s daily picks to track market movement.';

    public function handle(OddsService $odds): int
    {
        $today = now('Africa/Lagos');

        $picks = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereNotNull('opening_odds')
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today->startOfDay(), $today->endOfDay()])
                ->whereNotIn('status', ['FT', 'AET', 'PEN', 'CANC', 'PST', 'ABD', 'AWD', 'WO'])
                ->where(fn ($w) => LeagueCoverage::scopeCovered($w))
            )
            ->get();

        if ($picks->isEmpty()) {
            $this->info('No picks need closing odds today.');
            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($picks as $pick) {
            $match = $pick->match;
            if (! $match?->api_id) continue;

            // Bust the 60-minute OddsService cache to force a fresh fetch
            Cache::forget('odds_implied_' . $match->api_id);

            try {
                $closing = $odds->impliedProbabilities($match);
            } catch (\Throwable $e) {
                $this->warn("  ⚠️  {$match->home_team} vs {$match->away_team}: {$e->getMessage()}");
                continue;
            }

            if (! $closing) continue;

            $pick->update(['closing_odds' => $closing]);
            $updated++;

            $opening  = $pick->opening_odds ?? [];
            $hwDelta  = round(($closing['home_win'] ?? 0) - ($opening['home_win'] ?? 0), 1);
            $this->line(sprintf(
                '  📊  %s vs %s | HW: %+.1fpp | D: %+.1fpp | AW: %+.1fpp',
                $match->home_team,
                $match->away_team,
                $hwDelta,
                round(($closing['draw']     ?? 0) - ($opening['draw']     ?? 0), 1),
                round(($closing['away_win'] ?? 0) - ($opening['away_win'] ?? 0), 1),
            ));
        }

        $this->info("Closing odds updated for {$updated} pick(s).");
        return self::SUCCESS;
    }
}
