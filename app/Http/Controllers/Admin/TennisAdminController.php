<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TennisPrediction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class TennisAdminController extends Controller
{
    public function index(): View
    {
        $predictions = TennisPrediction::with('match')->orderByDesc('created_at')->paginate(30);
        return view('admin.tennis.index', compact('predictions'));
    }

    public function fetchFixtures(): RedirectResponse
    {
        Artisan::call('tennis:fetch-fixtures');
        return back()->with('success', trim(Artisan::output()) ?: 'Tennis fixtures refreshed.');
    }

    public function generatePredictions(): RedirectResponse
    {
        Artisan::call('tennis:predict');
        return back()->with('success', trim(Artisan::output()) ?: 'Tennis predictions generated.');
    }

    public function settleResults(): RedirectResponse
    {
        Artisan::call('tennis:settle-results');
        return back()->with('success', trim(Artisan::output()) ?: 'Tennis results checked.');
    }
}
