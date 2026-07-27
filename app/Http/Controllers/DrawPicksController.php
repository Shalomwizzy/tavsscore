<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Models\Prediction;
use App\Services\GroqService;
use App\Services\PredictionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DrawPicksController extends Controller
{
    use ResolvesDateNav;

    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(Request $request): View
    {
        $tz       = config('app.timezone');
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, 'draw-picks.index');
        $today    = $date->copy()->startOfDay();
        $cutoff   = $date->copy()->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_draw_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('draw_rank')
            ->get();

        if ($picks->isEmpty() && $dateMeta['is_today']) {
            $picks = $this->predictionService->selectDrawPicks();
        }

        $formatted = $picks->map(fn ($p) => $this->formatPick($p));

        // 7-day draw accuracy — graded directly from the score (home == away),
        // NOT the shared was_correct column which tracks the headline market.
        $recentPicks = Prediction::query()
            ->where('is_draw_pick', true)
            ->whereHas('match', fn ($q) => $q
                ->whereIn('status', ['FT', 'AET', 'PEN'])
                ->whereNotNull('home_score')
                ->where('match_time', '>=', now($tz)->subDays(7)->startOfDay())
                ->where('match_time', '<=', now($tz)->endOfDay())
            )
            ->get();

        $graded  = $recentPicks->filter(fn ($p) => self::drawResult($p) !== null);
        $correct = $graded->filter(fn ($p) => self::drawResult($p) === true)->count();

        $accuracy = [
            'total'   => $graded->count(),
            'correct' => $correct,
            'pct'     => $graded->count() > 0 ? round($correct / $graded->count() * 100, 1) : null,
        ];

        $offWindow = $this->offWindowState($date, $tz);

        return view('draw-picks.index', compact('formatted', 'accuracy', 'dateMeta', 'offWindow'));
    }

    /**
     * Grade the Draw market straight from the final score (home == away).
     * Returns true/false when finished, null otherwise. Does NOT read the
     * shared was_correct column (that tracks the headline market).
     */
    private static function drawResult(Prediction $p): ?bool
    {
        $m = $p->match;
        if (! $m || ! in_array($m->status, ['FT', 'AET', 'PEN'], true) || $m->home_score === null || $m->away_score === null) {
            return null;
        }
        return (int) $m->home_score === (int) $m->away_score;
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
            'rank'           => $p->draw_rank,
            'outcome'        => 'Draw',
            'confidence_pct' => $p->confidence,
            'tips'           => is_array($p->tips) ? $p->tips : [],
            'hw'             => (float) $p->home_win_prob,
            'd'              => (float) $p->draw_prob,
            'aw'             => (float) $p->away_win_prob,
            'analysis'       => $p->analysis,
            'analysis_pidgin'  => $p->analysis_pidgin,
            'analysis_swahili' => $p->analysis_swahili,
            'is_ai'          => $isAi,
            'was_correct'    => self::drawResult($p),
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
