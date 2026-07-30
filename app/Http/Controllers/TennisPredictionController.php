<?php

namespace App\Http\Controllers;

use App\Models\TennisPrediction;
use App\Models\Setting;
use Illuminate\View\View;

class TennisPredictionController extends Controller
{
    public function index(): View
    {
        $heroImage = Setting::get('tennis_page_hero_image');
        // Predictions are hidden until the historical data is current enough to
        // trust — show a "coming soon" teaser instead of unreliable picks.
        if (! config('services.tennis.public')) {
            return view('tennis.coming-soon', compact('heroImage'));
        }

        // Only today's matches (Lagos) — never carry yesterday's fixtures over.
        $today = now('Africa/Lagos')->toDateString();
        $predictions = TennisPrediction::query()->with('match')
            ->whereHas('match', fn ($q) => $q->whereDate('match_date', $today))
            ->orderByDesc('confidence')->paginate(30);
        return view('tennis.index', compact('predictions', 'heroImage'));
    }

    public function show(TennisPrediction $tennisPrediction)
    {
        if (! config('services.tennis.public')) {
            return redirect()->route('tennis.index');
        }

        $tennisPrediction->load('match');
        return view('tennis.show', ['prediction' => $tennisPrediction, 'match' => $tennisPrediction->match, 'heroImage' => Setting::get('tennis_page_hero_image')]);
    }
}
