<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\PredictionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class PicksAdminController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService)
    {
    }

    public function index(): View
    {
        $tz = config('app.timezone');

        $today = Prediction::with('match')
            ->where('is_daily_pick', true)
            ->whereDate('created_at', now($tz)->toDateString())
            ->orderBy('pick_rank')
            ->get();

        $history = Prediction::with('match')
            ->where('is_daily_pick', true)
            ->where('created_at', '<', now($tz)->startOfDay())
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->groupBy(fn ($p) => $p->created_at->format('Y-m-d'));

        $resolvedAll = Prediction::where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->get(['was_correct']);

        $totalAll   = $resolvedAll->count();
        $correctAll = $resolvedAll->where('was_correct', true)->count();
        $accuracy   = $totalAll > 0 ? round($correctAll / $totalAll * 100, 1) : null;

        return view('admin.picks.index', compact('today', 'history', 'totalAll', 'correctAll', 'accuracy'));
    }

    public function refresh(): RedirectResponse
    {
        $picks = $this->predictionService->selectDailyPicks();

        return redirect()->route('admin.picks')
            ->with('success', "Re-selected {$picks->count()} daily picks.");
    }

    public function recheck(): RedirectResponse
    {
        Artisan::call('predictions:check-outcomes', ['--days' => 7]);

        return redirect()->route('admin.picks')
            ->with('success', 'Re-checked outcomes for the last 7 days.');
    }
}
