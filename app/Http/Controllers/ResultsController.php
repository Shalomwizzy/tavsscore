<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Support\PickHelpers;
use Illuminate\View\View;

class ResultsController extends Controller
{
    public function index(): View
    {
        $tz = config('app.timezone');

        $since = now($tz)->subDays(14)->startOfDay();
        $until = now($tz)->startOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$since, $until])
            )
            ->orderByDesc('created_at')
            ->orderBy('pick_rank')
            ->get();

        $byDay = $picks->groupBy(fn ($p) => $p->match?->match_time?->copy()->setTimezone($tz)->format('Y-m-d') ?? 'unknown')
            ->sortKeysDesc()
            ->map(function ($day) {
                $resolved = $day->whereNotNull('was_correct');
                return [
                    'picks'   => $day->map(fn ($p) => [
                        'rank'         => $p->pick_rank,
                        'home'         => $p->match?->home_team ?? '?',
                        'away'         => $p->match?->away_team ?? '?',
                        'league'       => \App\Support\LeagueCoverage::formatName($p->match?->league, $p->match?->league_country),
                        'time'         => $p->match?->match_time?->format('H:i'),
                        'status'       => $p->match?->status,
                        'home_score'   => $p->match?->home_score,
                        'away_score'   => $p->match?->away_score,
                        'outcome'      => $p->predicted_outcome,
                        'confidence'   => round(PickHelpers::confidencePct($p)),
                        'was_correct'  => $p->was_correct,
                    ]),
                    'correct' => $resolved->where('was_correct', true)->count(),
                    'total'   => $resolved->count(),
                    'pending' => $day->whereNull('was_correct')->count(),
                ];
            });

        $resolvedAll = $picks->whereNotNull('was_correct');
        $summary = [
            'total'   => $resolvedAll->count(),
            'correct' => $resolvedAll->where('was_correct', true)->count(),
        ];
        $summary['accuracy'] = $summary['total'] > 0
            ? round($summary['correct'] / $summary['total'] * 100, 1)
            : null;

        return view('results.index', compact('byDay', 'summary'));
    }
}
