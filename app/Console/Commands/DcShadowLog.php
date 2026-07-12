<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\PredictionLog;
use App\Services\DixonColes\Predictor;
use Illuminate\Console\Command;

/**
 * Shadow-log Dixon-Coles predictions into prediction_logs for upcoming
 * fixtures without displaying anything to users. Lets the /admin/model-metrics
 * dashboard compare dc-v1.0 vs groq-poisson-v0 vs market-closing on the
 * same match set once these games finish and settle.
 *
 * Runs the same idempotent unique-key upsert as the observer for
 * groq-poisson-v0, keyed by (match, market, model_version, stage).
 * Stage is always pre_lineup — the DC model doesn't use lineup data yet.
 *
 * Recommended cron: hourly, or right after the 03:00 pick selection.
 */
class DcShadowLog extends Command
{
    protected $signature   = 'dc:shadow-log
                              {--model-version=dc-v1.0 : model_version used to look up params + tag logs}
                              {--hours-ahead=48 : Log fixtures kicking off within this window}';
    protected $description = 'Shadow-log Dixon-Coles predictions into prediction_logs (not displayed to users).';

    public function handle(Predictor $predictor): int
    {
        $version    = (string) $this->option('model-version');
        $hoursAhead = (int) $this->option('hours-ahead');

        $matches = FootballMatch::query()
            ->where('match_time', '>=', now())
            ->where('match_time', '<=', now()->addHours($hoursAhead))
            ->whereNotIn('status', ['FT', 'AET', 'PEN', 'CANC', 'PST', 'ABD'])
            ->where('held_for_review', false)
            ->get();

        if ($matches->isEmpty()) {
            $this->info('No upcoming fixtures in the window.');
            return self::SUCCESS;
        }

        $logged = 0;
        $skipped = 0;

        foreach ($matches as $match) {
            $forecast = $predictor->predict($match, $version);
            if (! $forecast) {
                $skipped++;
                continue;
            }

            $race = [
                'Home Win' => $forecast['home_win'],
                'Draw'     => $forecast['draw'],
                'Away Win' => $forecast['away_win'],
            ];
            $argmax = array_search(max($race), $race, true);

            $this->upsert($match, PredictionLog::MARKET_1X2, $argmax, $race[$argmax], $forecast, $version);
            $logged++;

            $this->upsert($match, PredictionLog::MARKET_OVER25, 'Over 2.5 Goals', $forecast['over_25'], $forecast, $version);
            $logged++;

            $this->upsert($match, PredictionLog::MARKET_OVER15, 'Over 1.5 Goals', $forecast['over_15'], $forecast, $version);
            $logged++;

            $this->upsert($match, PredictionLog::MARKET_GG, 'Both Teams Score', $forecast['btts'], $forecast, $version);
            $logged++;
        }

        $this->info("Shadow-logged {$logged} rows across {$matches->count()} fixtures. Skipped: {$skipped} (missing DC params).");
        return self::SUCCESS;
    }

    private function upsert(FootballMatch $match, string $market, string $outcome, float $p, array $forecast, string $version): void
    {
        PredictionLog::updateOrCreate(
            [
                'match_id'         => $match->id,
                'market'           => $market,
                'model_version'    => $version,
                'prediction_stage' => PredictionLog::STAGE_PRE_LINEUP,
            ],
            [
                'prediction_id'     => null,
                'league_id'         => $match->league_id,
                'predicted_outcome' => $outcome,
                'p_outcome'         => max(0.0, min(1.0, $p)),
                'p_home'            => max(0.0, min(1.0, $forecast['home_win'])),
                'p_draw'            => max(0.0, min(1.0, $forecast['draw'])),
                'p_away'            => max(0.0, min(1.0, $forecast['away_win'])),
                'is_backfill'       => false,
                'kickoff_at'        => $match->match_time,
                'created_at'        => now(),
            ],
        );
    }
}
