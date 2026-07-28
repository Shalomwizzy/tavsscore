<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DailyFootballPredictionTableService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyFootballPredictionsAdminController extends Controller
{
    public function index(Request $request, DailyFootballPredictionTableService $dailyPredictions): View
    {
        return view('admin.daily-football-predictions.index', $dailyPredictions->forDate($request->query('date')));
    }
}
