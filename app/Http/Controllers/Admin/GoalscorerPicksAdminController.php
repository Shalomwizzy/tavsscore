<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\GoalscorerPicksController;
use App\Services\FootballPredictionBoardRefresher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class GoalscorerPicksAdminController extends Controller
{
    public function index(GoalscorerPicksController $public): View
    {
        // Reuse the public pick-building logic, render inside the admin layout.
        return view('admin.goalscorer-picks.index', $public->buildPicks(40));
    }

    public function rebuild(FootballPredictionBoardRefresher $boardRefresher): RedirectResponse
    {
        try {
            $boardRefresher->refreshFixturesOnly();
            if (Artisan::call('stats:fetch-standings', ['--sleep' => 0]) !== 0) {
                throw new \RuntimeException('Standings refresh failed, so goalscorer picks were not sent.');
            }
            if (Artisan::call('stats:fetch-players', ['--max-pages' => 5]) !== 0) {
                throw new \RuntimeException('Player-stat refresh failed, so goalscorer picks were not sent.');
            }
            Artisan::call('picks:notify', ['--type' => 'goalscorer', '--force' => true]);
            return redirect()->route('admin.goalscorer-picks.index')->with('success', 'Latest fixtures, standings and player data were refreshed. Qualifying goalscorer picks were sent where available.');
        } catch (\Throwable $exception) {
            return redirect()->route('admin.goalscorer-picks.index')->with('error', $exception->getMessage());
        }
    }
}
