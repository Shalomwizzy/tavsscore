<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\FootballPredictionBoardRefresher;
use App\Services\PredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class DrawPicksAdminController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService, private readonly FootballPredictionBoardRefresher $boardRefresher) {}

    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $todayPicks = Prediction::query()
            ->with('match')
            ->where('is_draw_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('draw_rank')
            ->get();

        $history = Prediction::query()
            ->with('match')
            ->where('is_draw_pick', true)
            ->whereHas('match', fn ($q) => $q->where('match_time', '<', $today))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->groupBy(fn ($p) => $p->match?->match_time?->setTimezone($tz)->format('Y-m-d') ?? 'unknown');

        $resolved = Prediction::where('is_draw_pick', true)->whereNotNull('was_correct')->get(['was_correct']);
        $total    = $resolved->count();
        $correct  = $resolved->where('was_correct', true)->count();
        $accuracy = $total > 0 ? round($correct / $total * 100, 1) : null;

        return view('admin.draw-picks.index', compact('todayPicks', 'history', 'total', 'correct', 'accuracy'));
    }

    public function refresh(): RedirectResponse
    {
        $picks = $this->predictionService->selectDrawPicks();

        return redirect()->route('admin.draw-picks.index')
            ->with('success', "Re-selected {$picks->count()} draw picks.");
    }

    public function rebuild(): RedirectResponse
    {
        try {
            $this->boardRefresher->refreshFixturesAndBoards();
            $picks = $this->predictionService->selectDrawPicks();
            if ($picks->isNotEmpty()) Artisan::call('picks:notify', ['--type' => 'draw', '--force' => true]);
            return redirect()->route('admin.draw-picks.index')->with('success', "Latest fixtures and model boards rebuilt. {$picks->count()} draw pick(s) qualified and were sent where available.");
        } catch (\Throwable $exception) {
            return redirect()->route('admin.draw-picks.index')->with('error', $exception->getMessage());
        }
    }
}
