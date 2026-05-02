<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function index(): View
    {
        $tz = config('app.timezone');

        // All resolved daily picks (with match for league info)
        $allPicks = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->whereNotNull('predicted_outcome')
            ->orderByDesc('created_at')
            ->get();

        $total   = $allPicks->count();
        $correct = $allPicks->where('was_correct', true)->count();
        $overall = $total > 0 ? round($correct / $total * 100, 1) : null;

        // ── By outcome type ───────────────────────────────────────
        $byOutcome = $allPicks
            ->groupBy('predicted_outcome')
            ->map(fn ($g) => $this->stat($g))
            ->sortByDesc('total');

        // ── By league ─────────────────────────────────────────────
        // Group by country + league so "Premier League" rows from England,
        // Egypt and Kenya don't get merged into a single misleading row.
        $byLeague = $allPicks
            ->groupBy(fn ($p) => \App\Support\LeagueCoverage::formatName($p->match?->league, $p->match?->league_country))
            ->map(fn ($g) => $this->stat($g))
            ->filter(fn ($v) => $v['total'] >= 2)
            ->sortByDesc('correct');

        // ── Last 8 weeks weekly trend ─────────────────────────────
        $weekly = collect(range(7, 0))->map(function (int $i) use ($allPicks, $tz) {
            $start = now($tz)->subWeeks($i)->startOfWeek();
            $end   = now($tz)->subWeeks($i)->endOfWeek();
            $week  = $allPicks->filter(
                fn ($p) => $p->created_at >= $start && $p->created_at <= $end
            );
            $t = $week->count();
            $c = $week->where('was_correct', true)->count();
            return [
                'label'   => $start->format('M d'),
                'total'   => $t,
                'correct' => $c,
                'pct'     => $t > 0 ? round($c / $t * 100, 1) : null,
            ];
        });

        // ── Recent 10 picks for the result log ────────────────────
        $recentLog = $allPicks->take(15)->map(fn ($p) => [
            'match'      => ($p->match?->home_team ?? '?') . ' vs ' . ($p->match?->away_team ?? '?'),
            'league'     => \App\Support\LeagueCoverage::formatName($p->match?->league, $p->match?->league_country),
            'outcome'    => $p->predicted_outcome,
            'correct'    => $p->was_correct,
            'date'       => $p->created_at?->format('M d'),
        ]);

        // ── 7-day vs 30-day vs all-time ───────────────────────────
        $periods = [
            '7d'  => $allPicks->filter(fn ($p) => $p->created_at >= now($tz)->subDays(7)),
            '30d' => $allPicks->filter(fn ($p) => $p->created_at >= now($tz)->subDays(30)),
            'all' => $allPicks,
        ];

        $periodStats = collect($periods)->map(fn ($g) => $this->stat($g));

        // ── Per-market accuracy ───────────────────────────────────
        // Group by market category (1X2 / Goals / BTTS / Handicap / etc.)
        // and by exact market label, then show hit rate. This tells users
        // which markets to trust — instead of trusting the AI blindly.
        $byCategory = $allPicks
            ->groupBy(fn ($p) => $this->marketCategory($p->predicted_outcome))
            ->map(fn ($g) => $this->stat($g))
            ->filter(fn ($v) => $v['total'] >= 1)
            ->sortByDesc('total');

        // ── Confidence calibration: do 80%+ tips actually hit 80%+? ──
        $calibration = collect([
            ['label' => '50–59%',  'min' => 50, 'max' => 59],
            ['label' => '60–69%',  'min' => 60, 'max' => 69],
            ['label' => '70–79%',  'min' => 70, 'max' => 79],
            ['label' => '80–89%',  'min' => 80, 'max' => 89],
            ['label' => '90–100%', 'min' => 90, 'max' => 100],
        ])->map(function (array $bucket) use ($allPicks) {
            $g = $allPicks->filter(fn ($p) => $p->confidence !== null
                && $p->confidence >= $bucket['min']
                && $p->confidence <= $bucket['max']);
            $stat = $this->stat($g);
            return array_merge($bucket, $stat);
        });

        return view('stats.index', compact(
            'total', 'correct', 'overall',
            'byOutcome', 'byLeague', 'weekly',
            'recentLog', 'periodStats',
            'byCategory', 'calibration',
        ));
    }

    /**
     * Map a market label like "Over 8.5 Corners" to a coarse category.
     */
    private function marketCategory(?string $outcome): string
    {
        if (! $outcome) return 'Other';
        if (in_array($outcome, ['Home Win', 'Draw', 'Away Win'], true)) return '1X2';
        if (str_contains($outcome, '(1X)') || str_contains($outcome, '(X2)') || str_contains($outcome, '(12)')) return 'Double Chance';
        if (str_contains($outcome, 'Goals') && (str_contains($outcome, 'First Half') || str_contains($outcome, 'Both Halves'))) return 'Half-time / Halves';
        if (str_contains($outcome, 'Goals')) return 'Goal Lines';
        if (str_contains($outcome, 'BTTS') || str_contains($outcome, 'Both Teams Score')) return 'BTTS';
        if (str_contains($outcome, 'Corners')) return 'Corners';
        if (str_contains($outcome, 'Cards')) return 'Cards';
        if (str_contains($outcome, 'Handicap')) return 'Asian Handicap';
        if (str_contains($outcome, 'HT')) return 'Half-time / Halves';
        if (str_contains($outcome, 'Clean Sheet') || str_contains($outcome, 'to Nil') || str_contains($outcome, 'to Score')) return 'Score-Related';
        return 'Other';
    }

    private function stat(Collection $group): array
    {
        $t = $group->count();
        $c = $group->where('was_correct', true)->count();
        return [
            'total'   => $t,
            'correct' => $c,
            'wrong'   => $t - $c,
            'pct'     => $t > 0 ? round($c / $t * 100, 1) : null,
        ];
    }
}
