<?php

namespace App\Http\Controllers;

use App\Models\FantasySquad;
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

        return view('fantasy.index', compact('squad'));
    }
}
