<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Models\TennisPrediction;
use App\Models\Setting;
use App\Services\Tennis\TennisPerformanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TennisPredictionController extends Controller
{
    use ResolvesDateNav;

    public function index(Request $request): View
    {
        $heroImage = Setting::get('tennis_page_hero_image');
        // Predictions are hidden until the historical data is current enough to
        // trust — show a "coming soon" teaser instead of unreliable picks.
        if (! config('services.tennis.public')) {
            return view('tennis.coming-soon', compact('heroImage'));
        }

        // Date picker: default today, browse up to a year of history.
        $tz       = 'Africa/Lagos';
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, 'tennis.index');

        $predictions = TennisPrediction::query()->with('match')
            ->whereHas('match', fn ($q) => $q->whereDate('match_date', $date->toDateString()))
            ->orderByDesc('confidence')->paginate(30)->withQueryString();

        // Win record over the last 30 settled days, shown to build trust.
        $settled = TennisPrediction::query()
            ->whereNotNull('was_correct')
            ->whereHas('match', fn ($q) => $q->whereDate('match_date', '>=', now($tz)->subDays(30)->toDateString()))
            ->get(['was_correct']);
        $winStats = [
            'total' => $settled->count(),
            'won'   => $settled->where('was_correct', true)->count(),
        ];

        return view('tennis.index', compact('predictions', 'heroImage', 'dateMeta', 'winStats'));
    }

    public function show(TennisPrediction $tennisPrediction)
    {
        if (! config('services.tennis.public')) {
            return redirect()->route('tennis.index');
        }

        $tennisPrediction->load('match');
        return view('tennis.show', ['prediction' => $tennisPrediction, 'match' => $tennisPrediction->match, 'heroImage' => Setting::get('tennis_page_hero_image')]);
    }

    public function results(TennisPerformanceService $performance): View
    {
        $report = $performance->report();

        return view('tennis.results', $report);
    }
}
