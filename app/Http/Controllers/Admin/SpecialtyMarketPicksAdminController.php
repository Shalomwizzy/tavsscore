<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\PredictionService;
use App\Support\PickHelpers;
use App\Support\SpecialtyPickCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class SpecialtyMarketPicksAdminController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService) {}

    public function under35(): View { return $this->show('under35'); }
    public function under45(): View { return $this->show('under45'); }
    public function handicap(): View { return $this->show('handicap'); }
    public function refreshUnder35(): RedirectResponse { return $this->refresh('under35'); }
    public function refreshUnder45(): RedirectResponse { return $this->refresh('under45'); }
    public function refreshHandicap(): RedirectResponse { return $this->refresh('handicap'); }

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
        return view('admin.specialty-market-picks.index', compact('config', 'picks', 'history', 'total', 'correct'));
    }

    private function refresh(string $type): RedirectResponse
    {
        $config = SpecialtyPickCatalog::get($type);
        $picks = $this->predictionService->selectSpecialtyMarketPicks($type);
        if ($picks->isNotEmpty()) Artisan::call('picks:notify', ['--type' => $type, '--force' => true]);
        return redirect()->route('admin.' . $config['admin_route'] . '.index')->with('success', "Selected {$picks->count()} {$config['title']} picks and sent them to Telegram and OneSignal.");
    }
}
