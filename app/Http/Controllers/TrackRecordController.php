<?php

namespace App\Http\Controllers;

use App\Models\CalibrationSnapshot;
use App\Models\Prediction;
use App\Services\CalibrationService;
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

        $streak = \App\Support\PickHelpers::streakFromResolved($recent);

        return view('track-record.index', compact(
            'total', 'correct', 'overall',
            'monthly', 'improvement',
            'calibration', 'snapshots',
            'bestMonth', 'worstMonth', 'streak',
        ));
    }
}
