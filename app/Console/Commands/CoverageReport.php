<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\PredictionLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Per-league ingestion / prediction coverage report (Phase 1.5.2).
 *
 * Silent gaps in match ingestion skew every team-strength estimate. This
 * surfaces per-league counts: matches ingested, matches finished, predictions
 * logged, held-for-review, integrity-flagged. A league with a sudden dip
 * relative to its rolling baseline warrants investigation.
 */
class CoverageReport extends Command
{
    protected $signature   = 'coverage:report {--days=30 : Look-back window in days}';
    protected $description = 'Report per-league match ingestion vs prediction coverage over the last N days.';

    public function handle(): int
    {
        $days  = (int) $this->option('days');
        $since = now()->subDays($days)->startOfDay();

        $rows = DB::table('matches')
            ->leftJoin('prediction_logs', 'prediction_logs.match_id', '=', 'matches.id')
            ->where('matches.match_time', '>=', $since)
            ->selectRaw("
                matches.league_id,
                matches.league,
                COUNT(DISTINCT matches.id) AS ingested,
                COUNT(DISTINCT CASE WHEN matches.status IN ('FT','AET','PEN') THEN matches.id END) AS finished,
                COUNT(DISTINCT CASE WHEN matches.held_for_review = 1 THEN matches.id END) AS held,
                COUNT(DISTINCT CASE WHEN matches.integrity_flags IS NOT NULL THEN matches.id END) AS flagged,
                COUNT(DISTINCT CASE WHEN prediction_logs.id IS NOT NULL THEN matches.id END) AS predicted
            ")
            ->groupBy('matches.league_id', 'matches.league')
            ->orderBy('matches.league')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("No matches found in the last {$days} days.");
            return self::SUCCESS;
        }

        $this->info("Coverage report — last {$days} days");
        $this->line('');

        $this->table(
            ['League', 'League ID', 'Ingested', 'Finished', 'Predicted', 'Coverage', 'Held', 'Flagged'],
            $rows->map(function ($r) {
                $coverage = $r->ingested > 0 ? round($r->predicted / $r->ingested * 100, 1) . '%' : '—';
                return [
                    $r->league,
                    $r->league_id ?? '—',
                    number_format($r->ingested),
                    number_format($r->finished),
                    number_format($r->predicted),
                    $coverage,
                    number_format($r->held),
                    number_format($r->flagged),
                ];
            })->all(),
        );

        $totals = [
            'ingested'  => $rows->sum('ingested'),
            'finished'  => $rows->sum('finished'),
            'predicted' => $rows->sum('predicted'),
            'held'      => $rows->sum('held'),
            'flagged'   => $rows->sum('flagged'),
        ];

        $this->line('');
        $this->info(sprintf(
            'Totals: %d ingested, %d finished, %d with predictions, %d held, %d flagged.',
            $totals['ingested'], $totals['finished'], $totals['predicted'],
            $totals['held'], $totals['flagged'],
        ));

        // Callout when >5% of finished matches are held — data quality signal.
        if ($totals['finished'] > 0 && $totals['held'] / $totals['finished'] > 0.05) {
            $this->warn('Held-for-review rate exceeds 5% — data quality investigation recommended.');
        }

        // Prediction coverage gap: leagues we ingest but rarely predict may be
        // uncovered or unfunded — worth surfacing.
        $gap = PredictionLog::query()->count() > 0
            ? $totals['predicted'] / max($totals['ingested'], 1)
            : 0;
        if ($gap > 0 && $gap < 0.5) {
            $this->warn(sprintf('Only %.1f%% of ingested matches have predictions logged — check LeagueCoverage.', $gap * 100));
        }

        return self::SUCCESS;
    }
}
