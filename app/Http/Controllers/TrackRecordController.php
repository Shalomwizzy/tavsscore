<?php

namespace App\Http\Controllers;

use App\Models\CalibrationSnapshot;
use App\Models\DcLeagueParams;
use App\Models\Prediction;
use App\Models\PredictionLog;
use App\Services\CalibrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TrackRecordController extends Controller
{
    public function __construct(private readonly CalibrationService $calibration) {}

    public function index(): View
    {
        $tz = config('app.timezone', 'Africa/Lagos');

        // All resolved daily picks, ordered chronologically
        $allPicks = Prediction::query()
            ->where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->whereNotNull('confidence')
            ->orderBy('created_at')
            ->get(['confidence', 'was_correct', 'created_at', 'predicted_outcome']);

        $total   = $allPicks->count();
        $correct = $allPicks->where('was_correct', true)->count();
        $overall = $total > 0 ? round($correct / $total * 100, 1) : null;

        // ── Monthly accuracy timeline ─────────────────────────────────
        $monthly = $allPicks
            ->groupBy(fn ($p) => $p->created_at->format('Y-m'))
            ->map(function ($g, $key) {
                $t = $g->count();
                $c = $g->where('was_correct', true)->count();
                return [
                    'key'     => $key,
                    'label'   => $g->first()->created_at->format('M Y'),
                    'total'   => $t,
                    'correct' => $c,
                    'pct'     => $t > 0 ? round($c / $t * 100, 1) : null,
                ];
            })
            ->values();

        // ── Improvement story: early vs recent ───────────────────────
        // Compare the system's first 30 days of picks to the most recent 30 days.
        // If the recent window is better, we have a concrete improvement story.
        $improvement = null;
        if ($total >= 20 && $allPicks->first()) {
            $origin     = $allPicks->first()->created_at;
            $early30    = $allPicks->filter(fn ($p) => $p->created_at->lt($origin->copy()->addDays(30)));
            $recent30   = $allPicks->filter(fn ($p) => $p->created_at->gte(now($tz)->subDays(30)));

            $earlyPct  = $early30->count()  > 0 ? round($early30->where('was_correct', true)->count()  / $early30->count()  * 100, 1) : null;
            $recentPct = $recent30->count() > 0 ? round($recent30->where('was_correct', true)->count() / $recent30->count() * 100, 1) : null;

            if ($earlyPct !== null && $recentPct !== null) {
                $improvement = [
                    'early_pct'    => $earlyPct,
                    'recent_pct'   => $recentPct,
                    'early_picks'  => $early30->count(),
                    'recent_picks' => $recent30->count(),
                    'delta'        => round($recentPct - $earlyPct, 1),
                    'is_better'    => $recentPct > $earlyPct,
                ];
            }
        }

        // ── Confidence calibration bands ─────────────────────────────
        try {
            $calibration = $this->calibration->bands();
        } catch (\Throwable) {
            $calibration = collect();
        }

        // ── Monthly snapshots (system evolution over time) ───────────
        try {
            $snapshots = CalibrationSnapshot::orderBy('period_label')->get();
        } catch (\Throwable) {
            $snapshots = collect();
        }

        // ── Best and worst months ─────────────────────────────────────
        $qualified  = $monthly->filter(fn ($m) => $m['total'] >= 5);
        $bestMonth  = $qualified->sortByDesc('pct')->first();
        $worstMonth = $qualified->sortBy('pct')->first();

        // ── Current streak ────────────────────────────────────────────
        $recent = Prediction::query()
            ->where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['was_correct', 'created_at']);

        try {
            $streak = \App\Support\PickHelpers::streakFromResolved($recent);
        } catch (\Throwable) {
            $streak = ['type' => 'none', 'count' => 0, 'best' => 0];
        }

        // ── Dixon-Coles 2.0 ship-gate proof (Phase 4 backtest) ──────────
        // The v2 launch banner promises measured accuracy improvements
        // "backtested on 9,691 matches across 9 top European leagues".
        // Deliver the proof: per-league Brier + hit-rate for DC vs the
        // naive baseline. Silent-hides if the backtest hasn't run yet.
        $dc = $this->dcShipGateSummary();

        return view('track-record.index', compact(
            'total', 'correct', 'overall',
            'monthly', 'improvement',
            'calibration', 'snapshots',
            'bestMonth', 'worstMonth', 'streak',
            'dc',
        ));
    }

    /**
     * Per-league summary of the walk-forward backtest for the public
     * track record. Reads dc-v1.0-backtest and naive-league-avg-v0-backtest
     * rows from prediction_logs and computes the Brier + hit-rate gap.
     *
     * @return array|null
     */
    private function dcShipGateSummary(): ?array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('prediction_logs')) return null;

        $dcVersion    = 'dc-v1.0-backtest';
        $naiveVersion = 'naive-league-avg-v0-backtest';

        $exists = PredictionLog::where('model_version', $dcVersion)->exists();
        if (! $exists) return null;

        $leagueNames = [
            39  => 'Premier League',
            140 => 'La Liga',
            135 => 'Serie A',
            78  => 'Bundesliga',
            179 => 'Scottish Premiership',
            94  => 'Primeira Liga',
            61  => 'Ligue 1',
            88  => 'Eredivisie',
            144 => 'Belgian Pro League',
        ];

        $rows = DB::table('prediction_logs')
            ->selectRaw("
                model_version, league_id, market,
                COUNT(*) AS n,
                SUM(CASE WHEN actual_result='WIN'  THEN 1 ELSE 0 END) AS wins,
                AVG(CASE WHEN actual_result='WIN'  THEN POWER(1 - p_outcome, 2)
                         WHEN actual_result='LOSS' THEN POWER(p_outcome, 2)
                    END) AS brier
            ")
            ->whereIn('model_version', [$dcVersion, $naiveVersion])
            ->whereIn('actual_result', ['WIN', 'LOSS'])
            ->where('market', PredictionLog::MARKET_1X2)  // 1X2 is the ship-gate win
            ->groupBy('model_version', 'league_id', 'market')
            ->get();

        $byLeague = [];
        foreach ($rows as $r) {
            $byLeague[$r->league_id][$r->model_version] = [
                'n'     => (int) $r->n,
                'hr'    => $r->n > 0 ? $r->wins / $r->n : 0,
                'brier' => (float) $r->brier,
            ];
        }

        $leaguesOut = [];
        $totalDc    = 0;
        $totalDcWin = 0;
        $totalNv    = 0;
        $totalNvWin = 0;

        foreach ($leagueNames as $lid => $name) {
            if (! isset($byLeague[$lid][$dcVersion], $byLeague[$lid][$naiveVersion])) continue;
            $d = $byLeague[$lid][$dcVersion];
            $n = $byLeague[$lid][$naiveVersion];
            $leaguesOut[] = [
                'league_id'  => $lid,
                'name'       => $name,
                'n'          => $d['n'],
                'dc_hr'      => $d['hr'],
                'naive_hr'   => $n['hr'],
                'delta_hr'   => $d['hr'] - $n['hr'],
                'dc_brier'   => $d['brier'],
                'naive_brier'=> $n['brier'],
                'delta_brier'=> $d['brier'] - $n['brier'],
            ];
            $totalDc    += $d['n']; $totalDcWin += $d['n'] * $d['hr'];
            $totalNv    += $n['n']; $totalNvWin += $n['n'] * $n['hr'];
        }

        if (empty($leaguesOut)) return null;

        usort($leaguesOut, fn ($a, $b) => $b['delta_hr'] <=> $a['delta_hr']);

        return [
            'leagues'     => $leaguesOut,
            'total_n'     => $totalDc,
            'dc_avg_hr'   => $totalDc > 0 ? $totalDcWin / $totalDc : 0,
            'naive_avg_hr'=> $totalNv > 0 ? $totalNvWin / $totalNv : 0,
            'leagues_fit' => DcLeagueParams::count(),
            'last_fit'    => DcLeagueParams::max('fit_at'),
        ];
    }
}
