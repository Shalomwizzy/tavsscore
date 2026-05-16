<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Services\GroqService;
use App\Services\PredictionService;
use App\Support\PickHelpers;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\View\View;

class GGPicksController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(): View
    {
        $tz     = config('app.timezone');
        $today  = now($tz)->startOfDay();
        $cutoff = now($tz)->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_gg_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('gg_rank')
            ->get();

        if ($picks->isEmpty()) {
            $picks = $this->predictionService->selectGGPicks();
        }

        $this->autoResolve($picks);

        $formatted = $picks->map(fn ($p) => $this->formatPick($p));

        // 7-day GG accuracy
        $recentPicks = Prediction::query()
            ->where('is_gg_pick', true)
            ->whereNotNull('was_correct')
            ->whereHas('match', fn ($q) => $q
                ->where('match_time', '>=', now($tz)->subDays(7)->startOfDay())
                ->where('match_time', '<=', now($tz)->endOfDay())
            )
            ->get();

        $accuracy = [
            'total'   => $recentPicks->count(),
            'correct' => $recentPicks->where('was_correct', true)->count(),
            'pct'     => $recentPicks->count() > 0
                ? round($recentPicks->where('was_correct', true)->count() / $recentPicks->count() * 100, 1)
                : null,
        ];

        return view('gg-picks.index', compact('formatted', 'accuracy'));
    }

    private function autoResolve(EloquentCollection $picks): void
    {
        $finished = ['FT', 'AET', 'PEN'];
        foreach ($picks as $p) {
            if ($p->was_correct !== null) continue;
            if (! in_array($p->match?->status, $finished, true)) continue;
            if ($p->match?->home_score === null) continue;

            $result = PickHelpers::resolveOutcome($p);
            if ($result !== null) {
                $p->update(['was_correct' => $result]);
                $p->was_correct = $result;
            }
        }
    }

    private function formatPick(Prediction $p): array
    {
        $homeName = $p->match?->home_team ?? 'Home';
        $awayName = $p->match?->away_team ?? 'Away';

        $isAi = ! blank($p->analysis)
            && $p->analysis !== GroqService::FALLBACK_ANALYSIS
            && $p->analysis !== 'Prediction pending';

        $liveScore = null;
        $isLive    = in_array($p->match?->status, ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'], true);
        $isFt      = in_array($p->match?->status, ['FT', 'AET', 'PEN'], true);
        if (($isLive || $isFt) && $p->match && $p->match->home_score !== null) {
            $liveScore = $p->match->home_score . '–' . ($p->match->away_score ?? 0);
        }

        return [
            'id'             => $p->id,
            'rank'           => $p->gg_rank,
            'outcome'        => 'Both Teams Score',
            'confidence_pct' => $p->confidence,
            'tips'           => is_array($p->tips) ? $p->tips : [],
            'hw'             => (float) $p->home_win_prob,
            'd'              => (float) $p->draw_prob,
            'aw'             => (float) $p->away_win_prob,
            'btts'           => (float) ($p->btts_prob ?? 0),
            'analysis'       => $p->analysis,
            'analysis_pidgin'  => $p->analysis_pidgin,
            'analysis_swahili' => $p->analysis_swahili,
            'is_ai'          => $isAi,
            'was_correct'    => $p->was_correct,
            'live_score'     => $liveScore,
            'match'          => [
                'home'       => $homeName,
                'away'       => $awayName,
                'league'     => \App\Support\LeagueCoverage::formatName($p->match?->league, $p->match?->league_country),
                'time'       => $p->match?->match_time?->format('H:i'),
                'status'     => $p->match?->status ?? '',
                'home_score' => $p->match?->home_score,
                'away_score' => $p->match?->away_score,
                'elapsed'    => $p->match?->elapsed,
            ],
        ];
    }
}
