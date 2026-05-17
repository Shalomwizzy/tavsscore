<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeamPiRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PiRatingsAdminController extends Controller
{
    public function index(): View
    {
        $total = TeamPiRating::count();

        $topHome = TeamPiRating::orderByDesc('pi_home')->limit(30)->get();
        $topAway = TeamPiRating::orderByDesc('pi_away')->limit(30)->get();
        $topOverall = TeamPiRating::orderByDesc(
            DB::raw('(pi_home + pi_away) / 2')
        )->limit(50)->get();

        return view('admin.pi-ratings.index', compact('total', 'topHome', 'topAway', 'topOverall'));
    }

    public function rebuild(): RedirectResponse
    {
        Artisan::call('piratings:rebuild');

        return redirect()->route('admin.pi-ratings.index')
            ->with('success', 'Pi-ratings rebuilt from full match history.');
    }
}
