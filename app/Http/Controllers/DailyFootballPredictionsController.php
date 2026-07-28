<?php

namespace App\Http\Controllers;

use App\Services\DailyFootballPredictionTableService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyFootballPredictionsController extends Controller
{
    public function index(Request $request, DailyFootballPredictionTableService $dailyPredictions): View
    {
        return view('daily-football-predictions.index', $dailyPredictions->forDate($request->query('date')));
    }
}
