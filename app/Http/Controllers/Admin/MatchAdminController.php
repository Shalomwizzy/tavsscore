<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MatchAdminController extends Controller
{
    public function index(): View
    {
        $matches = FootballMatch::orderByDesc('match_time')->paginate(30);

        return view('admin.matches.index', compact('matches'));
    }

    public function fetch(): RedirectResponse
    {
        \Illuminate\Support\Facades\Artisan::call('fetch:matches');

        return redirect()->route('admin.matches')
            ->with('success', 'Matches fetched successfully from API-Football.');
    }
}
