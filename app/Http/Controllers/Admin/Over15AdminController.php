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

class Over15AdminController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService, private readonly FootballPredictionBoardRefresher $boardRefresher) {}

    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $todayPicks = Prediction::query()
            ->with('match')
            ->where('is_over15_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('over15_rank')
            ->get();

        $history = Prediction::query()
            ->with('match')
            ->where('is_over15_pick', true)
            ->whereHas('match', fn ($q) => $q->where('match_time', '<', $today))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->groupBy(fn ($p) => $p->match?->match_time?->setTimezone($tz)->format('Y-m-d') ?? 'unknown');

        $resolved = Prediction::where('is_over15_pick', true)
            ->whereHas('match', fn ($q) => $q->whereIn('status', ['FT','AET','PEN'])->whereNotNull('home_score'))
            ->with('match')->get();
        $total    = $resolved->count();
        $correct  = $resolved->filter(fn ($p) => $p->match && ((int)$p->match->home_score + (int)$p->match->away_score) >= 2)->count();
        $accuracy = $total > 0 ? round($correct / $total * 100, 1) : null;

        return view('admin.over15.index', compact('todayPicks', 'history', 'total', 'correct', 'accuracy'));
    }

    public function refresh(): RedirectResponse
    {
        $picks = $this->predictionService->selectOver15Picks();

        if ($picks->isNotEmpty()) {
            Artisan::call('picks:notify', ['--type' => 'over15']);
        }

        return redirect()->route('admin.over15.index')
            ->with('success', "Re-selected {$picks->count()} Over 1.5 picks.");
    }

    public function rebuild(): RedirectResponse
    {
        try {
            $this->boardRefresher->refreshFixturesAndBoards();
            $picks = $this->predictionService->selectOver15Picks();
            if ($picks->isNotEmpty()) Artisan::call('picks:notify', ['--type' => 'over15', '--force' => true]);
            return redirect()->route('admin.over15.index')->with('success', "Latest fixtures and model boards rebuilt. {$picks->count()} Over 1.5 pick(s) qualified and were sent where available.");
        } catch (\Throwable $exception) {
            return redirect()->route('admin.over15.index')->with('error', $exception->getMessage());
        }
    }
}
