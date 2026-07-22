<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Models\Prediction;
use App\Services\GroqService;
use App\Services\PredictionService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Team3PlusController extends Controller
{
    use ResolvesDateNav;

    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(Request $request): View
    {
        $tz       = config('app.timezone');
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, 'team3plus-picks.index');
        $today    = $date->copy()->startOfDay();
        $cutoff   = $date->copy()->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_team3plus_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('team3plus_rank')
            ->get();

        if ($picks->isEmpty() && $dateMeta['is_today']) {
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
            return $this->pickWon($p);
        })->count();
        $total   = $recentPicks->count();
        $accuracy = [
            'total'   => $total,
            'correct' => $correct,
            'pct'     => $total > 0 ? round($correct / $total * 100, 1) : null,
        ];

        $offWindow = $this->offWindowState($date, $tz);

        return view('team3plus.index', compact('formatted', 'accuracy', 'dateMeta', 'offWindow'));
    }

    private function autoResolve(EloquentCollection $picks): void
    {
        foreach ($picks as $p) {
            if (! in_array($p->match?->status, ['FT', 'AET', 'PEN'], true)) continue;
            if ($p->match?->home_score === null) continue;
            if ($p->was_correct === null) {
                $won = $this->pickWon($p);
                $p->update(['was_correct' => $won]);
                $p->was_correct = $won;
            }
        }
    }

    /**
     * Determine if a team3plus NO pick won.
     * Labels: 'Home 3+', 'Away 3+', 'Home 2+', 'Away 2+' (new format)
     *         'Home', 'Away' (legacy format — NO on 3+ market)
     * NO pick wins when the named team scores FEWER than the market threshold.
     */
    private function pickWon(Prediction $p): bool
    {
        $label  = $p->team3plus_label ?? 'Home 3+';
        $isHome = str_starts_with($label, 'Home');
        $goals  = $isHome ? (int) $p->match->home_score : (int) $p->match->away_score;

        if (str_ends_with($label, '2+')) return $goals < 2;   // NO on 2+: wins when team scores 0 or 1
        return $goals < 3;                                     // NO on 3+ (new & legacy): wins when team scores < 3
    }

    private function formatPick(Prediction $p): array
    {
        $isAi = ! blank($p->analysis)
            && $p->analysis !== GroqService::FALLBACK_ANALYSIS
            && $p->analysis !== 'Prediction pending';

        $label    = $p->team3plus_label ?? 'Home 3+';
        $isHome   = str_starts_with($label, 'Home');
        $market   = str_ends_with($label, '2+') ? '2+' : '3+';
        $home3    = (float)($p->home_3plus_prob ?? 0);
        $away3    = (float)($p->away_3plus_prob ?? 0);
        $home2    = (float)($p->home_2plus_prob ?? 0);
        $away2    = (float)($p->away_2plus_prob ?? 0);
        $prob     = $market === '2+'
            ? ($isHome ? $home2 : $away2)
            : ($isHome ? $home3 : $away3);
        $teamName = $isHome ? ($p->match?->home_team ?? 'Home') : ($p->match?->away_team ?? 'Away');
        $isFt     = in_array($p->match?->status, ['FT', 'AET', 'PEN'], true);
        $isLive   = in_array($p->match?->status, ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'], true);
        $won      = $isFt ? $this->pickWon($p) : null;

        $liveScore = null;
        if (($isLive || $isFt) && $p->match && $p->match->home_score !== null) {
            $liveScore = $p->match->home_score . '–' . ($p->match->away_score ?? 0);
        }

        return [
            'id'          => $p->id,
            'rank'        => $p->team3plus_rank,
            'label'       => $label,
            'market'      => $market,
            'team_name'   => $teamName,
            'prob'        => round($prob, 1),
            'analysis'    => $p->analysis,
            'is_ai'       => $isAi,
            'was_correct' => $won,
            'live_score'  => $liveScore,
            'home_3plus'  => round($home3, 1),
            'away_3plus'  => round($away3, 1),
            'home_2plus'  => round($home2, 1),
            'away_2plus'  => round($away2, 1),
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
