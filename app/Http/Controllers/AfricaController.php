<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Support\LeagueCoverage;
use Illuminate\View\View;

class AfricaController extends Controller
{
    public function index(): View
    {
        $tz = config('app.timezone');

        $base = FootballMatch::query()
            ->with('prediction')
            ->where(function ($q) {
                $q->whereIn('league_country', config('leagues.africa_countries', []))
                  ->orWhereIn('league_id', config('leagues.africa_continental', []));
            });

        $live = (clone $base)
            ->whereIn('status', ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'])
            ->orderBy('match_time')
            ->get();

        $upcoming = (clone $base)
            ->where('match_time', '>=', now($tz))
            ->where('match_time', '<=', now($tz)->addHours(72))
            ->whereNotIn('status', ['FT', 'AET', 'PEN', 'CANC', 'PST', 'ABD', 'AWD', 'WO'])
            ->orderBy('match_time')
            ->limit(40)
            ->get()
            ->groupBy(fn ($m) => $m->league . ' (' . ($m->league_country ?: 'CAF') . ')');

        $finished = (clone $base)
            ->whereIn('status', ['FT', 'AET', 'PEN'])
            ->orderByDesc('match_time')
            ->limit(20)
            ->get();

        // Continental competitions get their own pinned section
        $continentalIds = LeagueCoverage::africaContinental();
        $continental = (clone $base)
            ->whereIn('league_id', $continentalIds)
            ->orderByDesc('match_time')
            ->limit(15)
            ->get();

        return view('africa.index', compact('live', 'upcoming', 'finished', 'continental'));
    }
}
