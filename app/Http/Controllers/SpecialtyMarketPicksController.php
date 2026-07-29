<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Models\Prediction;
use App\Services\PredictionService;
use App\Services\MatchInsightService;
use App\Support\LeagueCoverage;
use App\Support\PickHelpers;
use App\Support\SpecialtyPickCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpecialtyMarketPicksController extends Controller
{
    use ResolvesDateNav;

    public function __construct(
        private readonly PredictionService $predictionService,
        private readonly MatchInsightService $matchInsights,
    ) {}

    public function under35(Request $request): View { return $this->show($request, 'under35'); }
    public function under45(Request $request): View { return $this->show($request, 'under45'); }
    public function handicap(Request $request): View { return $this->show($request, 'handicap'); }
    public function europeanHandicap(Request $request): View { return $this->show($request, 'europeanhandicap'); }

    private function show(Request $request, string $type): View
    {
        $config   = SpecialtyPickCatalog::get($type);
        $tz       = config('app.timezone');
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, $config['route']);
        $today    = $date->copy()->startOfDay();
        $cutoff   = $date->copy()->endOfDay();

        $picks = Prediction::query()->with('match')->where($config['flag'], true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy($config['rank'])->get();

        if ($picks->isEmpty() && $dateMeta['is_today']) {
            $picks = $this->predictionService->selectSpecialtyMarketPicks($type);
        }

        $formatted = $picks->map(fn (Prediction $pick) => $this->formatPick($pick, $config));
        $resolved = Prediction::query()->with('match')->where($config['flag'], true)
            ->whereHas('match', fn ($q) => $q->whereIn('status', ['FT', 'AET', 'PEN'])->whereNotNull('home_score')->whereNotNull('away_score'))
            ->get()->map(fn (Prediction $pick) => PickHelpers::resolveForMatch($pick->match, $this->label($pick, $config)))
            ->filter(fn ($result) => $result !== null);
        $accuracy = ['total' => $resolved->count(), 'correct' => $resolved->filter()->count()];
        $accuracy['pct'] = $accuracy['total'] ? round($accuracy['correct'] / $accuracy['total'] * 100, 1) : null;

        return view('specialty-market-picks.index', [
            'config' => $config, 'formatted' => $formatted, 'accuracy' => $accuracy,
            'dateMeta' => $dateMeta, 'offWindow' => $this->offWindowState($date, $tz),
        ]);
    }

    private function formatPick(Prediction $pick, array $config): array
    {
        $match = $pick->match;
        $label = $this->label($pick, $config);
        $board = is_array($pick->market_board) ? $pick->market_board : [];
        $finished = in_array($match?->status, ['FT', 'AET', 'PEN'], true) && $match?->home_score !== null;
        $live = in_array($match?->status, ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE'], true);
        preg_match('/^European Handicap ([0-5]:[0-5]) - (Home|Draw|Away)$/', $label, $european);
        $likely = is_array($pick->likely_scores) ? ($pick->likely_scores[0] ?? null) : null;
        $insight = $this->matchInsights->for($pick);
        return [
            'rank' => $pick->{$config['rank']}, 'label' => $label,
            'prob' => round((float) ($board[$label] ?? 0)),
            'result' => $finished ? PickHelpers::resolveForMatch($match, $label) : null,
            'score' => ($finished || $live) ? "{$match?->home_score}–" . ($match?->away_score ?? 0) : null,
            'analysis' => $pick->analysis,
            'reasons' => PickHelpers::reasonBullets($pick->analysis, 3),
            'likely_score' => is_array($likely) ? ($likely['score'] ?? null) : null,
            'european_start' => $european[1] ?? null,
            'european_selection' => $european[2] ?? null,
            'insight' => $insight,
            'match' => [
                'home' => $match?->home_team ?? 'Home', 'away' => $match?->away_team ?? 'Away',
                'league' => LeagueCoverage::formatName($match?->league, $match?->league_country),
                'time' => $match?->match_time?->format('H:i'), 'status' => $match?->status ?? '',
            ],
        ];
    }

    private function label(Prediction $pick, array $config): string
    {
        return $config['market'] ?? ($pick->{$config['label_field']} ?: 'Handicap');
    }
}
