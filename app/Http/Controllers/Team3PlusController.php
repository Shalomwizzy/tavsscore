<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Services\GroqService;
use App\Services\PredictionService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\View\View;

class Team3PlusController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(): View
    {
        $tz     = config('app.timezone');
        $today  = now($tz)->startOfDay();
        $cutoff = now($tz)->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_team3plus_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('team3plus_rank')
            ->get();

        if ($picks->isEmpty()) {
            $picks = $this->predictionService->selectTeam3PlusPicks();
        }

        $this->autoResolve($picks);
        $formatted = $picks->map(fn ($p) => $this->formatPick($p));

        $recentPicks = Prediction::query()
            ->where('is_team3plus_pick', true)
            ->where('team3plus_notified', true)
            ->whereHas('match', fn ($q) => $q
                ->where('match_time', '>=', now($tz)->subDays(7)->startOfDay())
                ->where('match_time', '<=', now($tz)->endOfDay())
            )
            ->with('match')
            ->get();

        $correct = $recentPicks->filter(function ($p) {
            if (! $p->match) return false;
            $label = $p->team3plus_label ?? 'Home';
            return $label === 'Home' ? (int)$p->match->home_score < 3 : (int)$p->match->away_score < 3;
        })->count();
        $total   = $recentPicks->count();
        $accuracy = [
            'total'   => $total,
            'correct' => $correct,
            'pct'     => $total > 0 ? round($correct / $total * 100, 1) : null,
        ];

        return view('team3plus.index', compact('formatted', 'accuracy'));
    }

    private function autoResolve(EloquentCollection $picks): void
    {
        foreach ($picks as $p) {
            if (! in_array($p->match?->status, ['FT', 'AET', 'PEN'], true)) continue;
            if ($p->match?->home_score === null) continue;
            $label = $p->team3plus_label ?? 'Home';
            $won   = $label === 'Home' ? (int)$p->match->home_score < 3 : (int)$p->match->away_score < 3;
            if ($p->was_correct === null) {
                $p->update(['was_correct' => $won]);
                $p->was_correct = $won;
            }
        }
    }

    private function formatPick(Prediction $p): array
    {
        $isAi = ! blank($p->analysis)
            && $p->analysis !== GroqService::FALLBACK_ANALYSIS
            && $p->analysis !== 'Prediction pending';

        $label    = $p->team3plus_label ?? 'Home';
        $home3    = (float)($p->home_3plus_prob ?? 0);
        $away3    = (float)($p->away_3plus_prob ?? 0);
        $prob     = $label === 'Home' ? $home3 : $away3;   // the named team's 3+ prob (low = confident NO)
        $teamName = $label === 'Home' ? ($p->match?->home_team ?? 'Home') : ($p->match?->away_team ?? 'Away');
        $isFt     = in_array($p->match?->status, ['FT', 'AET', 'PEN'], true);
        $isLive   = in_array($p->match?->status, ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'], true);
        $won      = $isFt ? ($label === 'Home' ? (int)$p->match->home_score < 3 : (int)$p->match->away_score < 3) : null;

        $liveScore = null;
        if (($isLive || $isFt) && $p->match && $p->match->home_score !== null) {
            $liveScore = $p->match->home_score . '–' . ($p->match->away_score ?? 0);
        }

        return [
            'id'          => $p->id,
            'rank'        => $p->team3plus_rank,
            'label'       => $label,
            'team_name'   => $teamName,
            'prob'        => round($prob, 1),   // named team's 3+ prob (low = confident NO)
            'analysis'    => $p->analysis,
            'is_ai'       => $isAi,
            'was_correct' => $won,
            'live_score'  => $liveScore,
            'home_3plus'  => round($home3, 1),
            'away_3plus'  => round($away3, 1),
            'over25_prob' => round((float)($p->over_25_prob ?? 0)),
            'match' => [
                'home'       => $p->match?->home_team ?? 'Home',
                'away'       => $p->match?->away_team ?? 'Away',
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
