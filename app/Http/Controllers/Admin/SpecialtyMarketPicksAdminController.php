<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\MatchInsightService;
use App\Services\PredictionService;
use App\Support\PickHelpers;
use App\Support\SpecialtyPickCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class SpecialtyMarketPicksAdminController extends Controller
{
    public function __construct(
        private readonly PredictionService $predictionService,
        private readonly MatchInsightService $matchInsights,
    ) {}

    public function under35(): View { return $this->show('under35'); }
    public function under45(): View { return $this->show('under45'); }
    public function handicap(): View { return $this->show('handicap'); }
    public function europeanHandicap(): View { return $this->show('europeanhandicap'); }
    public function refreshUnder35(): RedirectResponse { return $this->refresh('under35'); }
    public function refreshUnder45(): RedirectResponse { return $this->refresh('under45'); }
    public function refreshHandicap(): RedirectResponse { return $this->refresh('handicap'); }
    public function refreshEuropeanHandicap(): RedirectResponse { return $this->refresh('europeanhandicap'); }
    public function rebuildUnder35(): RedirectResponse { return $this->rebuild('under35'); }
    public function rebuildUnder45(): RedirectResponse { return $this->rebuild('under45'); }
    public function rebuildHandicap(): RedirectResponse { return $this->rebuild('handicap'); }
    public function rebuildEuropeanHandicap(): RedirectResponse { return $this->rebuild('europeanhandicap'); }

    private function show(string $type): View
    {
        $config = SpecialtyPickCatalog::get($type);
        $today = CarbonImmutable::now('Africa/Lagos')->startOfDay();
        $picks = Prediction::query()->with('match')->where($config['flag'], true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $today->endOfDay()]))
            ->orderBy($config['rank'])->get();
        $history = Prediction::query()->with('match')->where($config['flag'], true)
            ->whereHas('match', fn ($q) => $q->where('match_time', '<', $today))
            ->orderByDesc('created_at')->limit(80)->get();
        $resolved = $history->filter(fn (Prediction $pick) => in_array($pick->match?->status, ['FT', 'AET', 'PEN'], true) && $pick->match?->home_score !== null)
            ->map(fn (Prediction $pick) => PickHelpers::resolveForMatch($pick->match, $config['market'] ?? $pick->{$config['label_field']}))
            ->filter(fn ($result) => $result !== null);
        $total = $resolved->count(); $correct = $resolved->filter()->count();
        $todayCards = $picks->map(fn (Prediction $pick) => $this->card($pick, $config));
        return view('admin.specialty-market-picks.index', compact('config', 'picks', 'todayCards', 'history', 'total', 'correct'));
    }

    private function refresh(string $type): RedirectResponse
    {
        $config = SpecialtyPickCatalog::get($type);
        $picks = $this->predictionService->selectSpecialtyMarketPicks($type);
        if ($picks->isNotEmpty()) Artisan::call('picks:notify', ['--type' => $type, '--force' => true]);
        return redirect()->route('admin.' . $config['admin_route'] . '.index')->with('success', "Selected {$picks->count()} {$config['title']} picks and sent them to Telegram and OneSignal.");
    }

    /** Pull fixtures and rebuild prediction boards before selecting this market. */
    private function rebuild(string $type): RedirectResponse
    {
        $config = SpecialtyPickCatalog::get($type);
        Artisan::call('fetch:matches');
        Artisan::call('predict:matches');
        $picks = $this->predictionService->selectSpecialtyMarketPicks($type);
        if ($picks->isNotEmpty()) Artisan::call('picks:notify', ['--type' => $type, '--force' => true]);

        return redirect()->route('admin.' . $config['admin_route'] . '.index')
            ->with('success', "Latest fixtures and model boards rebuilt. {$picks->count()} {$config['title']} pick(s) cleared the 90% gate.");
    }

    private function card(Prediction $pick, array $config): array
    {
        $label = $config['market'] ?? $pick->{$config['label_field']};
        $board = is_array($pick->market_board) ? $pick->market_board : [];
        preg_match('/^European Handicap ([0-5]:[0-5]) - (Home|Draw|Away)$/', (string) $label, $european);
        $likely = is_array($pick->likely_scores) ? ($pick->likely_scores[0] ?? null) : null;

        return [
            'pick' => $pick,
            'label' => $label,
            'probability' => round((float) ($board[$label] ?? 0)),
            'reasons' => PickHelpers::reasonBullets($pick->analysis, 3),
            'likely_score' => is_array($likely) ? ($likely['score'] ?? null) : null,
            'european_start' => $european[1] ?? null,
            'european_selection' => $european[2] ?? null,
            'insight' => $this->matchInsights->for($pick),
        ];
    }
}
