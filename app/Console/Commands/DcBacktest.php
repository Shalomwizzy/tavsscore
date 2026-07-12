<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\PredictionLog;
use App\Services\DixonColes\Fitter;
use App\Services\DixonColes\Model;
use App\Services\DixonColes\TeamNameNormalizer;
use App\Support\PickHelpers;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Walk-forward backtest for Dixon-Coles (Phase 4 ship gate).
 *
 * For each covered league, step through history month by month. At each
 * step: fit DC on every finished match BEFORE the current month, predict
 * every match IN the current month, log both DC predictions and a
 * naive-league-average baseline into prediction_logs with distinct
 * model_versions. Settle immediately from the known FT scores.
 *
 * The result is a per-league Brier / hit-rate comparison of DC vs the
 * naive baseline on real held-out matches. DC ships for a league only
 * if it beats the baseline — that's the non-negotiable ship gate.
 *
 * Baseline choice: `naive-league-avg-v0` uses that league's historical
 * home-win / draw / away-win / over-2.5 / BTTS frequencies (from the
 * same pre-current-month training window). It's the honest "no-model
 * information available" reference. The spec permits it when legacy
 * groq-poisson-v0 predictions don't exist for these matches — which
 * they don't, because most of these matches predate the app.
 */
class DcBacktest extends Command
{
    protected $signature   = 'dc:backtest
                              {--league= : Comma-separated league IDs (default: leagues.season_priority)}
                              {--start=2023-08-01 : First month to predict}
                              {--end= : Last month to predict (default: now)}
                              {--min-training-matches=200 : Skip months whose training window has fewer matches than this}
                              {--dc-version=dc-v1.0-backtest}
                              {--naive-version=naive-league-avg-v0-backtest}
                              {--iterations=150}
                              {--learning-rate=0.05}
                              {--half-life=270}
                              {--skip-existing : Skip months already logged (resume a partial run)}';
    protected $description = 'Monthly walk-forward backtest of Dixon-Coles vs a naive baseline. Populates prediction_logs.';

    public function handle(): int
    {
        $leagues = $this->parseIntCsv($this->option('league'))
            ?: (array) config('leagues.season_priority', []);
        $start = Carbon::parse($this->option('start'))->startOfMonth();
        $end   = $this->option('end')
            ? Carbon::parse($this->option('end'))->startOfMonth()
            : now()->startOfMonth();
        $minTrain    = (int) $this->option('min-training-matches');
        $dcVersion   = (string) $this->option('dc-version');
        $naiveVer    = (string) $this->option('naive-version');
        $maxIter     = (int) $this->option('iterations');
        $lr          = (float) $this->option('learning-rate');
        $halfLife    = (float) $this->option('half-life');
        $skipExist   = (bool) $this->option('skip-existing');

        $totalDcRows    = 0;
        $totalNaiveRows = 0;

        foreach ($leagues as $leagueId) {
            $this->newLine();
            $this->info("═══ League {$leagueId} ═══");

            $cursor = $start->copy();
            while ($cursor->lt($end)) {
                $monthStart = $cursor->copy();
                $monthEnd   = $cursor->copy()->endOfMonth();
                $label      = $cursor->format('Y-m');

                // Skip if this (league, month) already backtested
                if ($skipExist && $this->alreadyLoggedForMonth($leagueId, $dcVersion, $monthStart, $monthEnd)) {
                    $this->line("  ⊘ {$label}: already logged, skipping.");
                    $cursor->addMonth();
                    continue;
                }

                // Training set: finished matches strictly before this month
                $trainingRows = FootballMatch::query()
                    ->where('league_id', $leagueId)
                    ->whereIn('status', ['FT', 'AET', 'PEN'])
                    ->whereNotNull('home_score')
                    ->whereNotNull('away_score')
                    ->where('held_for_review', false)
                    ->where('match_time', '<', $monthStart)
                    ->orderBy('match_time')
                    ->get(['home_team', 'away_team', 'home_score', 'away_score', 'match_time']);

                if ($trainingRows->count() < $minTrain) {
                    $this->line("  ⊘ {$label}: only {$trainingRows->count()} training matches (< {$minTrain}), skipping.");
                    $cursor->addMonth();
                    continue;
                }

                // Test set: finished matches in this month
                $testRows = FootballMatch::query()
                    ->where('league_id', $leagueId)
                    ->whereIn('status', ['FT', 'AET', 'PEN'])
                    ->whereNotNull('home_score')
                    ->whereNotNull('away_score')
                    ->where('held_for_review', false)
                    ->whereBetween('match_time', [$monthStart, $monthEnd])
                    ->get();

                if ($testRows->isEmpty()) {
                    $this->line("  · {$label}: no matches to predict, skipping.");
                    $cursor->addMonth();
                    continue;
                }

                // Fit DC on training data
                $training = $trainingRows->map(fn ($m) => [
                    'home'       => TeamNameNormalizer::key((string) $m->home_team),
                    'away'       => TeamNameNormalizer::key((string) $m->away_team),
                    'home_goals' => (int) $m->home_score,
                    'away_goals' => (int) $m->away_score,
                    'date'       => $m->match_time?->format('Y-m-d') ?? '1970-01-01',
                ]);

                $started = microtime(true);
                $fitter  = new Fitter($training, halfLifeDays: $halfLife);
                $fit     = $fitter->fit(maxIterations: $maxIter, learningRate: $lr);
                $fitElapsed = round(microtime(true) - $started, 1);

                // Naive baseline: per-league historical frequencies over the same
                // training window. One set of five numbers for the whole month.
                $naive = $this->naiveBaseline($trainingRows);

                // Predict and log every match in the test window
                $dcRows    = 0;
                $naiveRows = 0;
                foreach ($testRows as $match) {
                    $homeKey = TeamNameNormalizer::key($match->home_team);
                    $awayKey = TeamNameNormalizer::key($match->away_team);

                    if (! isset($fit['teams'][$homeKey]) || ! isset($fit['teams'][$awayKey])) {
                        // NO_PREDICTION — team missing from training window
                        continue;
                    }

                    $home = $fit['teams'][$homeKey];
                    $away = $fit['teams'][$awayKey];

                    $lambdaHome = exp($home['attack'] + $away['defense'] + $fit['gamma']);
                    $lambdaAway = exp($away['attack'] + $home['defense']);
                    $matrix     = Model::matrix($lambdaHome, $lambdaAway, $fit['rho']);
                    $race       = Model::oneXTwo($matrix);
                    $over25     = Model::overGoals($matrix, 2.5);
                    $btts       = Model::btts($matrix);

                    $dcRows    += $this->logAndSettle($match, $dcVersion,   $race, $over25, $btts);
                    $naiveRows += $this->logAndSettle($match, $naiveVer,    $naive, $naive['over_25'], $naive['btts']);
                }

                $this->line(sprintf(
                    "  ✓ %s: trained on %d matches (%ss), logged %d DC + %d naive rows.",
                    $label, $trainingRows->count(), $fitElapsed, $dcRows, $naiveRows,
                ));

                $totalDcRows    += $dcRows;
                $totalNaiveRows += $naiveRows;

                $cursor->addMonth();
            }
        }

        $this->newLine();
        $this->info("Backtest complete. DC rows: {$totalDcRows}. Naive rows: {$totalNaiveRows}.");
        $this->info('Review results at /admin/model-metrics with model_version filters.');
        return self::SUCCESS;
    }

    /**
     * Log 4 market rows (1X2 argmax, Over 1.5, Over 2.5, BTTS) for a match
     * under the given model_version and immediately settle against the
     * known FT score. Returns count of rows written.
     */
    private function logAndSettle(FootballMatch $match, string $version, array $race, float $over25, float $btts): int
    {
        // Determine actual outcomes from the FT score
        $home = (int) $match->home_score;
        $away = (int) $match->away_score;

        $actualRace = match (true) {
            $home > $away => 'Home Win',
            $away > $home => 'Away Win',
            default       => 'Draw',
        };

        // 1X2 argmax
        $raceProbs = [
            'Home Win' => $race['home_win'],
            'Draw'     => $race['draw'],
            'Away Win' => $race['away_win'],
        ];
        $argmax = array_search(max($raceProbs), $raceProbs, true);

        $written = 0;

        $written += $this->upsertAndSettle($match, PredictionLog::MARKET_1X2, $argmax, $raceProbs[$argmax], $race, $version,
            $argmax === $actualRace ? PredictionLog::RESULT_WIN : PredictionLog::RESULT_LOSS);

        $written += $this->upsertAndSettle($match, PredictionLog::MARKET_OVER25, 'Over 2.5 Goals', $over25, $race, $version,
            ($home + $away) > 2 ? PredictionLog::RESULT_WIN : PredictionLog::RESULT_LOSS);

        $btts_hit = ($home >= 1 && $away >= 1);
        $written += $this->upsertAndSettle($match, PredictionLog::MARKET_GG, 'Both Teams Score', $btts, $race, $version,
            $btts_hit ? PredictionLog::RESULT_WIN : PredictionLog::RESULT_LOSS);

        return $written;
    }

    private function upsertAndSettle(FootballMatch $match, string $market, string $outcome, float $p, array $race, string $version, string $result): int
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
                'p_home'            => max(0.0, min(1.0, $race['home_win'])),
                'p_draw'            => max(0.0, min(1.0, $race['draw'])),
                'p_away'            => max(0.0, min(1.0, $race['away_win'])),
                'is_backfill'       => true,  // backtest rows are retroactive
                'kickoff_at'        => $match->match_time,
                'created_at'        => $match->match_time,
                'actual_result'     => $result,
                'settled_at'        => now(),
            ],
        );
        return 1;
    }

    /**
     * Compute the naive league-average baseline over the training rows.
     * Same numbers used for every match in the test month.
     */
    private function naiveBaseline($rows): array
    {
        $n = $rows->count();
        $h = $d = $a = $over25 = $btts = 0;

        foreach ($rows as $m) {
            $hs = (int) $m->home_score;
            $as = (int) $m->away_score;
            if ($hs > $as)       $h++;
            elseif ($hs < $as)   $a++;
            else                  $d++;
            if (($hs + $as) > 2) $over25++;
            if ($hs >= 1 && $as >= 1) $btts++;
        }

        return [
            'home_win' => $h / $n,
            'draw'     => $d / $n,
            'away_win' => $a / $n,
            'over_25'  => $over25 / $n,
            'btts'     => $btts / $n,
        ];
    }

    private function alreadyLoggedForMonth(int $leagueId, string $version, Carbon $start, Carbon $end): bool
    {
        return PredictionLog::query()
            ->where('league_id', $leagueId)
            ->where('model_version', $version)
            ->whereBetween('kickoff_at', [$start, $end])
            ->exists();
    }

    private function parseIntCsv(?string $csv): array
    {
        if (blank($csv)) return [];
        return collect(explode(',', $csv))->map(fn ($v) => (int) trim($v))->filter()->values()->all();
    }
}
