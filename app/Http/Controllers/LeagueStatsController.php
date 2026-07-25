<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\PlayerStatistic;
use App\Models\Standing;
use App\Support\LeagueCoverage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeagueStatsController extends Controller
{
    public function standings(Request $request): View
    {
        $season   = (int) (Standing::query()->max('season') ?: now('Africa/Lagos')->year);
        $leagues  = $this->leagueOptions(Standing::query(), $season);
        $leagueId = $this->pickLeague($request, $leagues);

        $rows = $leagueId
            ? Standing::query()
                ->where('league_id', $leagueId)
                ->where('season', $season)
                ->orderBy('group_label')
                ->orderBy('rank')
                ->get()
            : collect();

        $leagueName = $leagues[$leagueId] ?? null;

        return view('standings.index', compact('leagues', 'leagueId', 'leagueName', 'rows', 'season'));
    }

    public function topScorers(Request $request): View
    {
        $season   = (int) (PlayerStatistic::query()->max('season') ?: now('Africa/Lagos')->year);
        $leagues  = $this->leagueOptions(PlayerStatistic::query(), $season);
        $leagueId = $this->pickLeague($request, $leagues);

        $metric = $request->query('metric') === 'assists' ? 'assists' : 'goals';

        $players = $leagueId
            ? PlayerStatistic::query()
                ->where('league_id', $leagueId)
                ->where('season', $season)
                ->where($metric, '>', 0)
                ->orderByDesc($metric)
                ->orderByDesc('goals')
                ->orderByDesc('assists')
                ->limit(50)
                ->get()
            : collect();

        $leagueName = $leagues[$leagueId] ?? null;

        return view('top-scorers.index', compact('leagues', 'leagueId', 'leagueName', 'players', 'season', 'metric'));
    }

    /**
     * Build an ordered [league_id => "Country · League"] map for leagues that
     * have rows in the given table for the season. League display names come
     * from the matches table since the stat tables only store team info.
     *
     * @return array<int, string>
     */
    private function leagueOptions(Builder $base, int $season): array
    {
        $ids = (clone $base)->where('season', $season)->distinct()->pluck('league_id')->all();
        if (empty($ids)) {
            return [];
        }

        $names = FootballMatch::query()
            ->whereIn('league_id', $ids)
            ->get(['league_id', 'league', 'league_country'])
            ->unique('league_id')
            ->mapWithKeys(fn ($m): array => [
                (int) $m->league_id => LeagueCoverage::formatName($m->league, $m->league_country),
            ]);

        $out = [];
        foreach ($ids as $id) {
            $out[(int) $id] = $names[(int) $id] ?? ('League '.$id);
        }
        asort($out);

        return $out;
    }

    /**
     * @param  array<int, string>  $leagues
     */
    private function pickLeague(Request $request, array $leagues): ?int
    {
        if (empty($leagues)) {
            return null;
        }

        $requested = (int) $request->query('league');
        if ($requested && isset($leagues[$requested])) {
            return $requested;
        }

        return (int) array_key_first($leagues);
    }
}
