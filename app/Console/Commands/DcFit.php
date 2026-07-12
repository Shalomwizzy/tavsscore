<?php

namespace App\Console\Commands;

use App\Models\DcLeagueParams;
use App\Models\DcTeamParams;
use App\Models\FootballMatch;
use App\Models\ModelRun;
use App\Services\DixonColes\Fitter;
use App\Services\DixonColes\TeamNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Fit Dixon-Coles per league on all finished matches within the training
 * window, persist per-team parameters and league-wide γ/ρ, and record a
 * ModelRun. Idempotent: replaces the (league_id, model_version) params.
 *
 * Weekly cron target once proven. Manual invocation for now:
 *   php artisan dc:fit --league=39
 *   php artisan dc:fit --version=dc-v1.0-tuned --half-life=180
 */
class DcFit extends Command
{
    protected $signature   = 'dc:fit
                              {--league= : Comma-separated league IDs (default: leagues.season_priority)}
                              {--model-version=dc-v1.0 : model_version tag written to params + model_runs}
                              {--half-life=270 : Time-decay half-life in days}
                              {--min-matches=10 : Teams with fewer training matches get shrunk to league mean}
                              {--iterations=400 : Max gradient-ascent iterations}
                              {--learning-rate=0.02}';
    protected $description = 'Fit Dixon-Coles model per league from historical matches and persist parameters.';

    public function handle(): int
    {
        $leagues = $this->parseIntCsv($this->option('league'))
            ?: (array) config('leagues.season_priority', []);
        $version    = (string) $this->option('model-version');
        $halfLife   = (float) $this->option('half-life');
        $minMatches = (int) $this->option('min-matches');
        $maxIter    = (int) $this->option('iterations');
        $lr         = (float) $this->option('learning-rate');

        if (empty($leagues)) {
            $this->error('No leagues specified.');
            return self::FAILURE;
        }

        $anyFit = false;

        foreach ($leagues as $leagueId) {
            $this->info("→ Fitting league {$leagueId} as {$version}…");

            $matches = FootballMatch::query()
                ->where('league_id', $leagueId)
                ->whereIn('status', ['FT', 'AET', 'PEN'])
                ->whereNotNull('home_score')
                ->whereNotNull('away_score')
                ->where('held_for_review', false)
                ->orderBy('match_time')
                ->get(['home_team', 'away_team', 'home_score', 'away_score', 'match_time']);

            if ($matches->count() < 100) {
                $this->warn("  ⚠ only {$matches->count()} finished matches — need at least 100. Skipping.");
                continue;
            }

            $training = $matches->map(fn ($m) => [
                'home'       => TeamNameNormalizer::key((string) $m->home_team),
                'away'       => TeamNameNormalizer::key((string) $m->away_team),
                'home_goals' => (int) $m->home_score,
                'away_goals' => (int) $m->away_score,
                'date'       => $m->match_time?->format('Y-m-d') ?? '1970-01-01',
            ]);

            $fitter = new Fitter($training, halfLifeDays: $halfLife, minMatchesPerTeam: $minMatches);
            $started = microtime(true);
            $result = $fitter->fit(maxIterations: $maxIter, learningRate: $lr);
            $elapsed = round(microtime(true) - $started, 1);

            $this->line(sprintf(
                "  ✓ %d iterations in %ss (converged=%s), γ=%.3f ρ=%.3f, %d teams, LL=%.1f",
                $result['iterations'], $elapsed, $result['converged'] ? 'yes' : 'no',
                $result['gamma'], $result['rho'], count($result['teams']), $result['final_ll'],
            ));

            DB::transaction(function () use ($leagueId, $version, $halfLife, $result) {
                DcLeagueParams::updateOrCreate(
                    ['league_id' => $leagueId, 'model_version' => $version],
                    [
                        'gamma'                => $result['gamma'],
                        'rho'                  => $result['rho'],
                        'half_life_days'       => $halfLife,
                        'fit_at'               => now(),
                        'training_start'       => $result['training_start'],
                        'training_end'         => $result['training_end'],
                        'training_matches'     => $result['n_matches'],
                        'final_log_likelihood' => $result['final_ll'],
                        'iterations'           => $result['iterations'],
                        'converged'            => $result['converged'],
                    ],
                );

                DcTeamParams::where('league_id', $leagueId)->where('model_version', $version)->delete();
                foreach ($result['teams'] as $team => $params) {
                    DcTeamParams::create([
                        'league_id'     => $leagueId,
                        'model_version' => $version,
                        'team_name'     => $team,
                        'attack'        => $params['attack'],
                        'defense'       => $params['defense'],
                        'matches_used'  => $params['matches'],
                        'is_shrunk'     => $params['shrunk'],
                    ]);
                }

                Cache::forget("dc_league_{$leagueId}_{$version}");
                // Per-team caches expire naturally in 30 min; we don't try to
                // enumerate + flush them here.
            });

            $anyFit = true;
        }

        if ($anyFit) {
            ModelRun::updateOrCreate(
                ['model_version' => $version],
                [
                    'trained_at'      => now(),
                    'hyperparameters' => [
                        'half_life_days'      => $halfLife,
                        'min_matches_per_team' => $minMatches,
                        'max_iterations'      => $maxIter,
                        'learning_rate'       => $lr,
                    ],
                    'notes' => 'Dixon-Coles fit via numerical MLE. See dc_league_params for per-league training-set metadata.',
                ],
            );
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function parseIntCsv(?string $csv): array
    {
        if (blank($csv)) return [];
        return collect(explode(',', $csv))->map(fn ($v) => (int) trim($v))->filter()->values()->all();
    }
}
