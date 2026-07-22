<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Models\Prediction;
use App\Services\GroqService;
use App\Services\PredictionService;
use App\Support\PickHelpers;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Over15PicksController extends Controller
{
    use ResolvesDateNav;

    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(Request $request): View
    {
        $tz       = config('app.timezone');
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, 'over15-picks.index');
        $today    = $date->copy()->startOfDay();
        $cutoff   = $date->copy()->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_over15_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('over15_rank')
            ->get();

        if ($picks->isEmpty() && $dateMeta['is_today']) {
            $picks = $this->predictionService->selectOver15Picks();
        }

        $this->autoResolve($picks);
        $formatted = $picks->map(fn ($p) => $this->formatPick($p));

        $recentPicks = Prediction::query()
            ->where('is_over15_pick', true)
            ->whereNotNull('over15_notified')
            ->where('over15_notified', true)
            ->whereHas('match', fn ($q) => $q
                ->where('match_time', '>=', now($tz)->subDays(7)->startOfDay())
                ->where('match_time', '<=', now($tz)->endOfDay())
            )
            ->with('match')
            ->get();

        $correct = $recentPicks->filter(fn ($p) => ((int)$p->match?->home_score + (int)$p->match?->away_score) >= 2)->count();
        $total   = $recentPicks->count();
        $accuracy = [
            'total'   => $total,
            'correct' => $correct,
            'pct'     => $total > 0 ? round($correct / $total * 100, 1) : null,
        ];

        $offWindow = $this->offWindowState($date, $tz);

        return view('over15-picks.index', compact('formatted', 'accuracy', 'dateMeta', 'offWindow'));
    }

    private function autoResolve(EloquentCollection $picks): void
    {
        foreach ($picks as $p) {
            if (! in_array($p->match?->status, ['FT', 'AET', 'PEN'], true)) continue;
            if ($p->match?->home_score === null) continue;
            $total = (int)$p->match->home_score + (int)$p->match->away_score;
            $won   = $total >= 2;
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

        $isFt    = in_array($p->match?->status, ['FT', 'AET', 'PEN'], true);
        $isLive  = in_array($p->match?->status, ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'], true);
        $total   = $isFt && $p->match ? (int)$p->match->home_score + (int)$p->match->away_score : null;
        $won     = $isFt ? ($total >= 2) : null;

        $liveScore = null;
        if (($isLive || $isFt) && $p->match && $p->match->home_score !== null) {
            $liveScore = $p->match->home_score . '–' . ($p->match->away_score ?? 0);
        }

        return [
            'id'          => $p->id,
            'rank'        => $p->over15_rank,
            'prob'        => round((float)($p->over_15_prob ?? 0)),
            'analysis'    => $p->analysis,
            'is_ai'       => $isAi,
            'was_correct' => $won,
            'live_score'  => $liveScore,
            'hw'          => (float)$p->home_win_prob,
            'd'           => (float)$p->draw_prob,
            'aw'          => (float)$p->away_win_prob,
            'over25_prob' => round((float)($p->over_25_prob ?? 0)),
            'btts_prob'   => round((float)($p->btts_prob ?? 0)),
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
