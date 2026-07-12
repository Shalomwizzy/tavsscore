<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PredictionLog;
use App\Services\MarketClosingLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Model metrics dashboard — the ship gate for Phase 4.
 *
 * Compares Brier, log-loss, hit rate, and calibration across model_versions.
 * Filters like-for-like on prediction_stage (never mixes pre_lineup with
 * post_lineup) and can exclude retro-materialized rows via is_backfill.
 *
 * Everything is computed from `prediction_logs`; the operational
 * `predictions` table is not read here.
 */
class ModelMetricsController extends Controller
{
    /**
     * Log-loss clamp — MySQL LN(0) is -inf and LN() of a negative is undefined,
     * so we clamp p away from {0, 1} the way Brier / log-loss libraries do.
     */
    private const EPS = '0.001';

    public function index(Request $request): View
    {
        $stage        = $request->query('stage', PredictionLog::STAGE_PRE_LINEUP);
        $includeBackfill = $request->boolean('include_backfill', true);
        $bucketVersion = $request->query('bucket_version');
        $bucketMarket  = $request->query('bucket_market');

        $baseFilters = [
            'prediction_stage' => $stage,
        ];
        if (! $includeBackfill) {
            $baseFilters['is_backfill'] = false;
        }

        $overview = $this->overview($baseFilters);

        $byMarket = $this->groupedMetrics(
            $baseFilters,
            groupBy: ['model_version', 'market'],
            orderBy: ['model_version', 'market'],
        );
        $this->attachMarketDelta($byMarket, $baseFilters, ['model_version', 'market']);

        $byLeague = $this->groupedMetrics(
            $baseFilters,
            groupBy: ['model_version', 'league_id'],
            orderBy: ['model_version', 'league_id'],
            having: 'settled_n >= 20',
        );
        $this->attachMarketDelta($byLeague, $baseFilters, ['model_version', 'league_id']);

        $calibration = ($bucketVersion && $bucketMarket)
            ? $this->calibrationBuckets($baseFilters, $bucketVersion, $bucketMarket)
            : [];

        $versions = PredictionLog::select('model_version')
            ->distinct()
            ->orderBy('model_version')
            ->pluck('model_version');

        $markets = PredictionLog::select('market')
            ->distinct()
            ->orderBy('market')
            ->pluck('market');

        return view('admin.model-metrics.index', compact(
            'overview', 'byMarket', 'byLeague', 'calibration',
            'stage', 'includeBackfill', 'bucketVersion', 'bucketMarket',
            'versions', 'markets',
        ));
    }

    /**
     * Top-of-page counts: total logged, settled, pending, voided.
     */
    private function overview(array $filters): array
    {
        $q = PredictionLog::query();
        foreach ($filters as $k => $v) {
            $q->where($k, $v);
        }

        $rows = (clone $q)
            ->selectRaw("
                model_version,
                COUNT(*) AS total,
                SUM(CASE WHEN actual_result IN ('WIN','LOSS') THEN 1 ELSE 0 END) AS settled_n,
                SUM(CASE WHEN actual_result = 'VOID' THEN 1 ELSE 0 END) AS void_n,
                SUM(CASE WHEN actual_result IS NULL THEN 1 ELSE 0 END) AS pending_n
            ")
            ->groupBy('model_version')
            ->orderBy('model_version')
            ->get();

        return $rows->all();
    }

    /**
     * @param  array<string,mixed>  $filters
     * @param  array<int,string>    $groupBy
     * @param  array<int,string>    $orderBy
     */
    private function groupedMetrics(array $filters, array $groupBy, array $orderBy, ?string $having = null): array
    {
        $groupBySql = implode(', ', $groupBy);
        $orderBySql = implode(', ', $orderBy);
        $selectCols = implode(', ', $groupBy);
        $eps = self::EPS;

        $q = PredictionLog::query()
            ->selectRaw("
                {$selectCols},
                COUNT(*) AS logged_n,
                SUM(CASE WHEN actual_result IN ('WIN','LOSS') THEN 1 ELSE 0 END) AS settled_n,
                SUM(CASE WHEN actual_result = 'WIN'  THEN 1 ELSE 0 END) AS wins,
                SUM(CASE WHEN actual_result = 'LOSS' THEN 1 ELSE 0 END) AS losses,
                AVG(CASE
                    WHEN actual_result = 'WIN'  THEN POWER(1 - p_outcome, 2)
                    WHEN actual_result = 'LOSS' THEN POWER(p_outcome, 2)
                END) AS brier,
                AVG(CASE
                    WHEN actual_result = 'WIN'  THEN -LN(GREATEST(p_outcome, {$eps}))
                    WHEN actual_result = 'LOSS' THEN -LN(GREATEST(1 - p_outcome, {$eps}))
                END) AS log_loss,
                AVG(p_outcome) AS avg_stated_prob
            ")
            ->whereNotNull('actual_result')
            ->where('actual_result', '!=', PredictionLog::RESULT_VOID);

        foreach ($filters as $k => $v) {
            $q->where($k, $v);
        }

        $q->groupByRaw($groupBySql)->orderByRaw($orderBySql);
        if ($having) {
            $q->havingRaw($having);
        }

        return $q->get()->all();
    }

    /**
     * For each grouped row, compute the average per-match Brier delta versus
     * the market-closing row for the same (match, market, stage). Negative
     * delta = the model beats the market on that group's match set.
     *
     * Restricted to matches where BOTH sides are settled WIN/LOSS so we're
     * always averaging like-for-like. Attaches `delta_vs_market` and
     * `paired_n` in place on each row.
     *
     * @param  array<int,object>  $rows
     * @param  array<int,string>  $groupBy
     */
    private function attachMarketDelta(array $rows, array $baseFilters, array $groupBy): void
    {
        if (empty($rows)) return;

        $selectCols = implode(', ', array_map(fn ($c) => "pl.{$c}", $groupBy));
        $groupBySql = implode(', ', array_map(fn ($c) => "pl.{$c}", $groupBy));

        $q = DB::table('prediction_logs as pl')
            ->join('prediction_logs as mc', function ($j) {
                $j->on('mc.match_id', '=', 'pl.match_id')
                  ->on('mc.market', '=', 'pl.market')
                  ->on('mc.prediction_stage', '=', 'pl.prediction_stage')
                  ->where('mc.model_version', '=', MarketClosingLogger::MODEL_VERSION);
            })
            ->selectRaw("
                {$selectCols},
                COUNT(*) AS paired_n,
                AVG(
                    (CASE WHEN pl.actual_result = 'WIN' THEN POWER(1 - pl.p_outcome, 2)
                          ELSE POWER(pl.p_outcome, 2) END)
                  - (CASE WHEN mc.actual_result = 'WIN' THEN POWER(1 - mc.p_outcome, 2)
                          ELSE POWER(mc.p_outcome, 2) END)
                ) AS delta_vs_market
            ")
            ->whereIn('pl.actual_result', [PredictionLog::RESULT_WIN, PredictionLog::RESULT_LOSS])
            ->whereIn('mc.actual_result', [PredictionLog::RESULT_WIN, PredictionLog::RESULT_LOSS])
            ->where('pl.model_version', '!=', MarketClosingLogger::MODEL_VERSION);

        foreach ($baseFilters as $k => $v) {
            $q->where("pl.{$k}", $v);
        }

        $deltas = $q->groupByRaw($groupBySql)->get();

        $lookup = [];
        foreach ($deltas as $d) {
            $key = implode('|', array_map(fn ($c) => (string) $d->{$c}, $groupBy));
            $lookup[$key] = $d;
        }

        foreach ($rows as $row) {
            $key = implode('|', array_map(fn ($c) => (string) $row->{$c}, $groupBy));
            $row->delta_vs_market = $lookup[$key]->delta_vs_market ?? null;
            $row->paired_n        = (int) ($lookup[$key]->paired_n ?? 0);
        }
    }

    /**
     * 10-percentage-point calibration buckets: for predictions in the 60-70%
     * band, what fraction actually won? Well-calibrated systems trend toward
     * the diagonal.
     */
    private function calibrationBuckets(array $filters, string $modelVersion, string $market): array
    {
        $q = PredictionLog::query()
            ->selectRaw("
                FLOOR(p_outcome * 10) AS bucket,
                COUNT(*) AS n,
                AVG(p_outcome) AS stated,
                AVG(CASE WHEN actual_result = 'WIN' THEN 1.0 ELSE 0.0 END) AS realized
            ")
            ->where('model_version', $modelVersion)
            ->where('market', $market)
            ->whereIn('actual_result', [PredictionLog::RESULT_WIN, PredictionLog::RESULT_LOSS]);

        foreach ($filters as $k => $v) {
            $q->where($k, $v);
        }

        return $q->groupBy('bucket')->orderBy('bucket')->get()->all();
    }
}
