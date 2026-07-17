<?php

namespace App\Console\Commands;

use App\Models\MetricsSnapshot;
use App\Models\PredictionLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5 — weekly Brier / hit-rate snapshot per (model_version, market, league).
 *
 * Records the last 7 days of *live* prediction performance (is_backfill=false)
 * for every observed combination. Historical rows accumulate so the admin
 * can chart drift; an in-command alert fires if any live combo's Brier
 * degrades > 10% versus the backtest baseline.
 */
class SnapshotWeeklyMetrics extends Command
{
    protected $signature   = 'metrics:snapshot {--days=7} {--alert-threshold=0.10}';
    protected $description = 'Weekly Brier / hit-rate snapshot per model_version × market × league.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $end  = now()->startOfDay();
        $start = $end->copy()->subDays($days);

        $rows = DB::table('prediction_logs')
            ->selectRaw("
                model_version, market, league_id,
                COUNT(*) AS n,
                SUM(CASE WHEN actual_result='WIN' THEN 1 ELSE 0 END) AS wins,
                AVG(CASE WHEN actual_result='WIN'  THEN POWER(1 - p_outcome, 2)
                         WHEN actual_result='LOSS' THEN POWER(p_outcome, 2)
                    END) AS brier,
                AVG(CASE WHEN actual_result='WIN'  THEN -LN(GREATEST(p_outcome, 0.001))
                         WHEN actual_result='LOSS' THEN -LN(GREATEST(1 - p_outcome, 0.001))
                    END) AS log_loss
            ")
            ->where('is_backfill', false)
            ->whereIn('actual_result', ['WIN', 'LOSS'])
            ->whereBetween('settled_at', [$start, $end])
            ->groupBy('model_version', 'market', 'league_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("No settled live predictions in the last {$days} days.");
            return self::SUCCESS;
        }

        $written = 0;
        foreach ($rows as $r) {
            MetricsSnapshot::updateOrCreate(
                [
                    'period_start'  => $start->toDateString(),
                    'model_version' => $r->model_version,
                    'market'        => $r->market,
                    'league_id'     => $r->league_id,
                ],
                [
                    'n'        => $r->n,
                    'wins'     => $r->wins,
                    'brier'    => $r->brier,
                    'log_loss' => $r->log_loss,
                ],
            );
            $written++;
        }

        $this->info("Snapshotted {$written} rows for the week starting {$start->toDateString()}.");

        // ── Degradation alerting ─────────────────────────────────────
        // Compare each live combo's Brier to the backtest baseline for the
        // same (market, league). If live Brier is > alertThreshold worse
        // than backtest, log a warning that the admin dashboard surfaces.
        $alertThreshold = (float) $this->option('alert-threshold');
        $baselines = DB::table('prediction_logs')
            ->selectRaw("
                market, league_id,
                AVG(CASE WHEN actual_result='WIN'  THEN POWER(1 - p_outcome, 2)
                         WHEN actual_result='LOSS' THEN POWER(p_outcome, 2)
                    END) AS backtest_brier
            ")
            ->where('model_version', 'dc-v1.0-backtest')
            ->whereIn('actual_result', ['WIN', 'LOSS'])
            ->groupBy('market', 'league_id')
            ->get()
            ->mapWithKeys(fn ($r) => ["{$r->market}|{$r->league_id}" => (float) $r->backtest_brier]);

        $alerts = [];
        foreach ($rows as $r) {
            if ($r->n < 20) continue; // need enough live data
            if (! str_starts_with($r->model_version, 'dc-')) continue; // only alert on DC drift
            $key = "{$r->market}|{$r->league_id}";
            $expected = $baselines->get($key);
            if ($expected === null || $expected <= 0) continue;

            $delta = ($r->brier - $expected) / $expected;
            if ($delta > $alertThreshold) {
                $alerts[] = [
                    'model' => $r->model_version, 'market' => $r->market, 'league' => $r->league_id,
                    'live_brier' => round($r->brier, 4), 'backtest_brier' => round($expected, 4),
                    'delta_pct' => round($delta * 100, 1),
                ];
            }
        }

        if (! empty($alerts)) {
            $this->error(sprintf('⚠️  %d degradation alert(s) — live Brier > %d%% worse than backtest:', count($alerts), (int) ($alertThreshold * 100)));
            foreach ($alerts as $a) {
                $this->line(sprintf('  • %s / %s / league %s : %.4f live vs %.4f backtest (+%.1f%%)',
                    $a['model'], $a['market'], $a['league'] ?? '—', $a['live_brier'], $a['backtest_brier'], $a['delta_pct']));
            }
            Log::warning('MetricsSnapshot: DC degradation detected', ['alerts' => $alerts]);
        } else {
            $this->info('✅ No degradation alerts — live DC performance within tolerance.');
        }

        return self::SUCCESS;
    }
}
