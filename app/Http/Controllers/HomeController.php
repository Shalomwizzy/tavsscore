<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $tz = config('app.timezone');

        // African fixtures from now → 24h ahead, with predictions if available.
        // Falls back to recent finished African matches if nothing upcoming.
        $africanCountries  = config('leagues.africa_countries', []);
        $africanContinental = config('leagues.africa_continental', []);

        $africanQuery = FootballMatch::query()
            ->with('prediction')
            ->where(function ($q) use ($africanCountries, $africanContinental) {
                $q->whereIn('league_country', $africanCountries)
                  ->orWhereIn('league_id', $africanContinental);
            });

        $africanUpcoming = (clone $africanQuery)
            ->where('match_time', '>=', now($tz))
            ->where('match_time', '<=', now($tz)->addHours(36))
            ->whereNotIn('status', ['CANC','PST','ABD','AWD','WO'])
            ->orderBy('match_time')
            ->limit(6)
            ->get();

        if ($africanUpcoming->isEmpty()) {
            $africanUpcoming = (clone $africanQuery)
                ->whereIn('status', ['FT','AET','PEN','1H','2H','HT','ET','BT','P','LIVE'])
                ->orderByDesc('match_time')
                ->limit(6)
                ->get();
        }

        return view('home.index', [
            'africanMatches' => $africanUpcoming,
        ]);
    }
}
