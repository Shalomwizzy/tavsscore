<?php

namespace App\Http\Controllers;

use App\Models\TennisPrediction;
use Illuminate\View\View;

class TennisPredictionController extends Controller
{
    public function index(): View
    {
        // Predictions are hidden until the historical data is current enough to
        // trust — show a "coming soon" teaser instead of unreliable picks.
        if (! config('services.tennis.public')) {
            return view('tennis.coming-soon');
        }

        $predictions = TennisPrediction::query()->with('match')
            ->whereHas('match')
            ->orderByDesc('created_at')->paginate(30);
        return view('tennis.index', compact('predictions'));
    }

    public function show(TennisPrediction $tennisPrediction)
    {
        if (! config('services.tennis.public')) {
            return redirect()->route('tennis.index');
        }

        $tennisPrediction->load('match');
        return view('tennis.show', ['prediction' => $tennisPrediction, 'match' => $tennisPrediction->match]);
    }
}
