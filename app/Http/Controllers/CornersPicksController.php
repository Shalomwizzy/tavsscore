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

class CornersPicksController extends Controller
{
    use ResolvesDateNav;

    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(Request $request): View
    {
        $tz       = config('app.timezone');
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, 'corners-picks.index');
        $today    = $date->copy()->startOfDay();
        $cutoff   = $date->copy()->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_corners_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('corners_rank')
            ->get();

        if ($picks->isEmpty() && $dateMeta['is_today']) {
            $picks = $this->predictionService->selectCornersPicks();
        }

        $this->autoResolve($picks);
        $formatted = $picks->map(fn ($p) => $this->formatPick($p));

        // 7-day accuracy — graded from fixture statistics (total corners).
        $recentPicks = Prediction::query()
            ->where('is_corners_pick', true)
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

        $offWindow = $this->offWindowState($date, $tz);

        return view('corners-picks.index', compact('formatted', 'accuracy', 'dateMeta', 'offWindow'));
    }

    private function autoResolve(EloquentCollection $picks): void
    {
        foreach ($picks as $p) {
            if ($p->was_correct !== null) continue;
            if (! in_array($p->match?->status, ['FT', 'AET', 'PEN'], true)) continue;

            // Graded from fixture stats against the stored corners line. Returns
            // null until stats are fetched — stays pending, never a false loss.
            $result = PickHelpers::resolveForMatch($p->match, $p->corners_label);
            if ($result !== null) {
                $p->update(['was_correct' => $result]);
                $p->was_correct = $result;
            }
        }
    }

    private function formatPick(Prediction $p): array
    {
        $isAi = ! blank($p->analysis)
            && $p->analysis !== GroqService::FALLBACK_ANALYSIS
            && $p->analysis !== 'Prediction pending';

        $board = is_array($p->market_board) ? $p->market_board : [];
        $prob  = isset($board[$p->corners_label]) ? round((float) $board[$p->corners_label]) : null;

        $isFt   = in_array($p->match?->status, ['FT', 'AET', 'PEN'], true);
        $isLive = in_array($p->match?->status, ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'], true);

        $liveScore = null;
        if (($isLive || $isFt) && $p->match && $p->match->home_score !== null) {
            $liveScore = $p->match->home_score . '–' . ($p->match->away_score ?? 0);
        }

        return [
            'id'          => $p->id,
            'rank'        => $p->corners_rank,
            'market'      => $p->corners_label ?? 'Corners',
            'prob'        => $prob,
            'analysis'    => $p->analysis,
            'is_ai'       => $isAi,
            'was_correct' => $p->was_correct,
            'live_score'  => $liveScore,
            'match' => [
                'home'       => $p->match?->home_team ?? 'Home',
                'away'       => $p->match?->away_team ?? 'Away',
                'league'     => \App\Support\LeagueCoverage::formatName($p->match?->league, $p->match?->league_country),
                'time'       => $p->match?->match_time?->format('H:i'),
                'status'     => $p->match?->status ?? '',
                'home_score' => $p->match?->home_score,
                'away_score' => $p->match?->away_score,
            ],
        ];
    }
}
