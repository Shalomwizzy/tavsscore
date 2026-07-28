<?php

namespace App\Http\Controllers;

use App\Models\TennisPrediction;
use Illuminate\View\View;

class TennisPredictionController extends Controller
{
    public function index(): View
    {
        abort_unless(config('services.tennis.public'), 404);

        $predictions = TennisPrediction::query()->with('match')
            ->whereHas('match')
            ->orderByDesc('created_at')->paginate(30);
        return view('tennis.index', compact('predictions'));
    }

    public function show(TennisPrediction $tennisPrediction): View
    {
        abort_unless(config('services.tennis.public'), 404);

        $tennisPrediction->load('match');
        return view('tennis.show', ['prediction' => $tennisPrediction, 'match' => $tennisPrediction->match]);
    }
}
