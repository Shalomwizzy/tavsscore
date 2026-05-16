<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\PredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GGPicksAdminController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $todayPicks = Prediction::query()
            ->with('match')
            ->where('is_gg_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('gg_rank')
            ->get();

        $history = Prediction::query()
            ->with('match')
            ->where('is_gg_pick', true)
            ->whereHas('match', fn ($q) => $q->where('match_time', '<', $today))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->groupBy(fn ($p) => $p->match?->match_time?->setTimezone($tz)->format('Y-m-d') ?? 'unknown');

        $resolved = Prediction::where('is_gg_pick', true)->whereNotNull('was_correct')->get(['was_correct']);
        $total    = $resolved->count();
        $correct  = $resolved->where('was_correct', true)->count();
        $accuracy = $total > 0 ? round($correct / $total * 100, 1) : null;

        return view('admin.gg-picks.index', compact('todayPicks', 'history', 'total', 'correct', 'accuracy'));
    }

    public function refresh(): RedirectResponse
    {
        $picks = $this->predictionService->selectGGPicks();

        return redirect()->route('admin.gg-picks.index')
            ->with('success', "Re-selected {$picks->count()} GG picks.");
    }
}
