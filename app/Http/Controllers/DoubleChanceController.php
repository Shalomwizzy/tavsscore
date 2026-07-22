<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Models\Prediction;
use App\Services\PredictionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoubleChanceController extends Controller
{
    use ResolvesDateNav;

    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(Request $request): View
    {
        $tz       = config('app.timezone');
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, 'double-chance.index');
        $today    = $date->copy()->startOfDay();
        $cutoff   = $date->copy()->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_double_chance_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('double_chance_rank')
            ->get();

        if ($picks->isEmpty() && $dateMeta['is_today']) {
            $picks = $this->predictionService->selectDoubleChancePicks();
        }

        $this->autoResolve($picks);
        $formatted = $picks->map(fn ($p) => $this->formatPick($p));

        $recentPicks = Prediction::query()
            ->where('is_double_chance_pick', true)
            ->where('double_chance_notified', true)
            ->whereHas('match', fn ($q) => $q
                ->where('match_time', '>=', now($tz)->subDays(7)->startOfDay())
                ->where('match_time', '<=', now($tz)->endOfDay())
            )
            ->with('match')
            ->get();

        $correct  = $recentPicks->filter(fn ($p) => $p->match && $this->pickWon($p))->count();
        $total    = $recentPicks->count();
        $accuracy = [
            'total'   => $total,
            'correct' => $correct,
            'pct'     => $total > 0 ? round($correct / $total * 100, 1) : null,
        ];

        $offWindow = $this->offWindowState($date, $tz);

        return view('double-chance.index', compact('formatted', 'accuracy', 'dateMeta', 'offWindow'));
    }

    private function autoResolve(\Illuminate\Database\Eloquent\Collection $picks): void
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

    private function pickWon(Prediction $p): bool
    {
        $home = (int) $p->match->home_score;
        $away = (int) $p->match->away_score;

        if ($p->double_chance_label === '1X') {
            return $home >= $away; // home win or draw
        }
        return $away >= $home; // away win or draw (2X)
    }

    private function formatPick(Prediction $p): array
    {
        $label  = $p->double_chance_label ?? '1X';
        $dc1x   = round((float) $p->home_win_prob + (float) $p->draw_prob, 1);
        $dc2x   = round((float) $p->away_win_prob + (float) $p->draw_prob, 1);
        $prob   = $label === '1X' ? $dc1x : $dc2x;
        $isFt   = in_array($p->match?->status, ['FT', 'AET', 'PEN'], true);
        $isLive = in_array($p->match?->status, ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'], true);
        $won    = $isFt ? $this->pickWon($p) : null;

        $liveScore = null;
        if (($isLive || $isFt) && $p->match && $p->match->home_score !== null) {
            $liveScore = $p->match->home_score . '–' . ($p->match->away_score ?? 0);
        }

        return [
            'id'          => $p->id,
            'rank'        => $p->double_chance_rank,
            'label'       => $label,
            'prob'        => $prob,
            'dc1x'        => $dc1x,
            'dc2x'        => $dc2x,
            'was_correct' => $won,
            'live_score'  => $liveScore,
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
