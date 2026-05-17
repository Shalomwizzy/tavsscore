<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\PredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class Over25AdminController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $todayPicks = Prediction::query()
            ->with('match')
            ->where('is_over25_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('over25_rank')
            ->get();

        $history = Prediction::query()
            ->with('match')
            ->where('is_over25_pick', true)
            ->whereHas('match', fn ($q) => $q->where('match_time', '<', $today))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->groupBy(fn ($p) => $p->match?->match_time?->setTimezone($tz)->format('Y-m-d') ?? 'unknown');

        $resolved = Prediction::where('is_over25_pick', true)
            ->whereHas('match', fn ($q) => $q->whereIn('status', ['FT','AET','PEN'])->whereNotNull('home_score'))
            ->with('match')->get();
        $total    = $resolved->count();
        $correct  = $resolved->filter(fn ($p) => $p->match && ((int)$p->match->home_score + (int)$p->match->away_score) >= 3)->count();
        $accuracy = $total > 0 ? round($correct / $total * 100, 1) : null;

        return view('admin.over25.index', compact('todayPicks', 'history', 'total', 'correct', 'accuracy'));
    }

    public function refresh(): RedirectResponse
    {
        $picks = $this->predictionService->selectOver25Picks();

        if ($picks->isNotEmpty()) {
            Artisan::call('picks:notify', ['--type' => 'over25']);
        }

        return redirect()->route('admin.over25.index')
            ->with('success', "Re-selected {$picks->count()} Over 2.5 picks.");
    }
}
