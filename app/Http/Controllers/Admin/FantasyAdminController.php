<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FantasySquad;
use App\Services\Fantasy\FantasySquadService;
use Illuminate\View\View;

class FantasyAdminController extends Controller
{
    public function index(): View
    {
        $squads = FantasySquad::query()
            ->where('league_id', 39)
            ->latest('built_at')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.fantasy.index', compact('squads'));
    }

    public function rebuild(FantasySquadService $service)
    {
        $squad = $service->build(39);

        return $squad
            ? back()->with('success', "Rebuilt {$squad->gameweek}: {$squad->formation}, captain {$squad->captain}.")
            : back()->with('error', 'Not enough player-stat data to build a squad yet. Run stats:fetch-players first.');
    }
}
