<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DailyFootballPredictionTableService;
use App\Services\PendingPredictionRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyFootballPredictionsAdminController extends Controller
{
    public function index(Request $request, DailyFootballPredictionTableService $dailyPredictions): View
    {
        return view('admin.daily-football-predictions.index', $dailyPredictions->forDate($request->query('date')));
    }

    public function settlePast(PendingPredictionRecoveryService $recovery): RedirectResponse
    {
        // Recovery re-fetches missed scores across a wide window; give it the time
        // the CLI has so LiteSpeed's web timeout can't kill it mid-run.
        @set_time_limit(0);
        ignore_user_abort(true);

        $result = $recovery->recover();
        $settled = $result['football_settled'] + $result['tennis_settled'];
        $remaining = $result['football_pending'] + $result['tennis_pending'];

        $message = "Past-result recovery finished: {$settled} prediction(s) settled";
        if ($remaining > 0) {
            $message .= "; {$remaining} remain pending because no verified final score is available yet.";
        } else {
            $message .= '; no past pending predictions remain in the recovery window.';
        }

        if ($result['warnings'] !== []) {
            return back()->with('error', $message.' '.implode(' ', $result['warnings']));
        }

        return back()->with('success', $message);
    }
}
