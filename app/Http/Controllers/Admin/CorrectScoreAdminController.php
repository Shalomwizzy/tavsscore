<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\FootballPredictionBoardRefresher;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class CorrectScoreAdminController extends Controller
{
    public function __construct(private readonly FootballPredictionBoardRefresher $boardRefresher) {}

    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $todayPredictions = Prediction::query()
            ->with('match')
            ->where('is_correct_score_pick', true)
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', ['CANC', 'PST'])
            )
            ->orderBy('correct_score_rank')
            ->get()
            ->filter(fn ($p) => ! empty($p->likely_scores));

        $history = Prediction::query()
            ->with('match')
            ->where('is_correct_score_pick', true)
            ->whereHas('match', fn ($q) => $q
                ->where('match_time', '<', $today)
                ->whereNotIn('status', ['CANC', 'PST'])
            )
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->filter(fn ($p) => ! empty($p->likely_scores))
            ->groupBy(fn ($p) => $p->match?->match_time?->setTimezone($tz)->format('Y-m-d') ?? 'unknown');

        return view('admin.correct-score.index', compact('todayPredictions', 'history'));
    }

    public function sendNotification(): RedirectResponse
    {
        Artisan::call('picks:notify', ['--type' => 'correctscore']);

        return redirect()->route('admin.correct-score.index')
            ->with('success', 'Correct score notification pushed to Telegram and OneSignal.');
    }

    public function rebuild(): RedirectResponse
    {
        try {
            $this->boardRefresher->refreshFixturesAndBoards();
            Artisan::call('picks:notify', ['--type' => 'correctscore', '--force' => true]);
            return redirect()->route('admin.correct-score.index')->with('success', 'Latest fixtures and model boards rebuilt. Qualifying correct-score signals were sent where available.');
        } catch (\Throwable $exception) {
            return redirect()->route('admin.correct-score.index')->with('error', $exception->getMessage());
        }
    }
}
