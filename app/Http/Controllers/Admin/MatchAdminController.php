<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Services\PendingPredictionRecoveryService;
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

    public function checkPastResults(PendingPredictionRecoveryService $recovery): RedirectResponse
    {
        $result = $recovery->recoverFootball();
        $message = "Football result check finished: {$result['settled']} prediction(s) settled";
        $message .= $result['pending'] > 0
            ? "; {$result['pending']} remain pending because no verified final score is available yet."
            : '; no past pending football predictions remain.';

        if ($result['warnings'] !== []) {
            return back()->with('error', $message.' '.implode(' ', $result['warnings']));
        }

        return back()->with('success', $message);
    }
}
