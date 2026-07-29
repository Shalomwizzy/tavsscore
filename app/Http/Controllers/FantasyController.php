<?php

namespace App\Http\Controllers;

use App\Models\FantasySquad;
use App\Models\Setting;
use Illuminate\View\View;

class FantasyController extends Controller
{
    public function index(): View
    {
        $squad = FantasySquad::query()
            ->where('league_id', 39)
            ->latest('built_at')
            ->latest('id')
            ->first();

        $fantasyHero = Setting::get('fantasy_feature_image');

        return view('fantasy.index', compact('squad', 'fantasyHero'));
    }
}
