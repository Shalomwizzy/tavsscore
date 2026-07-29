<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\FootballPredictionBoardRefresher;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class LineupPicksAdminController extends Controller
{
    public function __construct(private readonly FootballPredictionBoardRefresher $boardRefresher) {}

    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $todayPicks = Prediction::query()
            ->with('match')
            ->where('has_lineup', true)
            ->whereNotNull('confidence')
            ->whereNotNull('predicted_outcome')
            ->where('predicted_outcome', '!=', 'Competitive Match')
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderByDesc('confidence')
            ->limit(10)
            ->get();

        $history = Prediction::query()
            ->with('match')
            ->where('has_lineup', true)
            ->whereNotNull('confidence')
            ->whereNotNull('predicted_outcome')
            ->where('predicted_outcome', '!=', 'Competitive Match')
            ->whereHas('match', fn ($q) => $q->where('match_time', '<', $today))
            ->orderByDesc('created_at')
            ->limit(40)
            ->get()
            ->groupBy(fn ($p) => $p->match?->match_time?->setTimezone($tz)->format('Y-m-d') ?? 'unknown');

        return view('admin.lineup-picks.index', compact('todayPicks', 'history'));
    }

    public function sendNotification(): RedirectResponse
    {
        Artisan::call('picks:notify', ['--type' => 'lineup']);

        return redirect()->route('admin.lineup-picks.index')
            ->with('success', 'Lineup picks notification pushed to Telegram and OneSignal.');
    }

    public function rebuild(): RedirectResponse
    {
        try {
            $this->boardRefresher->refreshFixturesAndBoards();
            Artisan::call('picks:update-lineups');
            Artisan::call('picks:notify', ['--type' => 'lineup', '--force' => true]);
            return redirect()->route('admin.lineup-picks.index')->with('success', 'Latest fixtures and boards rebuilt. Confirmed lineups were checked and qualifying lineup picks were sent where available.');
        } catch (\Throwable $exception) {
            return redirect()->route('admin.lineup-picks.index')->with('error', $exception->getMessage());
        }
    }
}
