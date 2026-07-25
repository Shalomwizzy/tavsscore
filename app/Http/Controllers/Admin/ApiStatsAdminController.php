<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\PlayerStatistic;
use App\Models\Standing;
use App\Models\TeamStatistic;
use App\Support\LeagueCoverage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ApiStatsAdminController extends Controller
{
    public function index(Request $request): View
    {
        $season = (int) (Standing::query()->max('season')
            ?: TeamStatistic::query()->max('season')
            ?: now('Africa/Lagos')->year);

        $leagues  = $this->leagueOptions($season);
        $leagueId = $this->pickLeague($request, $leagues);

        $summary = [
            'standings_rows' => Standing::query()->where('season', $season)->count(),
            'team_rows'      => TeamStatistic::query()->where('season', $season)->count(),
            'player_rows'    => PlayerStatistic::query()->where('season', $season)->count(),
            'leagues'        => count($leagues),
        ];

        $standings = $leagueId
            ? Standing::query()->where('league_id', $leagueId)->where('season', $season)
                ->orderBy('group_label')->orderBy('rank')->get()
            : collect();

        $teamStats = $leagueId
            ? TeamStatistic::query()->where('league_id', $leagueId)->where('season', $season)
                ->orderByDesc('goals_for_total')->get()
            : collect();

        $topScorers = $leagueId
            ? PlayerStatistic::query()->where('league_id', $leagueId)->where('season', $season)
                ->where('goals', '>', 0)->orderByDesc('goals')->orderByDesc('assists')->limit(20)->get()
            : collect();

        $leagueName = $leagues[$leagueId] ?? null;

        return view('admin.api-stats.index', compact(
            'leagues', 'leagueId', 'leagueName', 'season', 'summary',
            'standings', 'teamStats', 'topScorers'
        ));
    }

    public function fetchStandings(Request $request): RedirectResponse
    {
        return $this->runFetch($request, 'stats:fetch-standings', 'Standings');
    }

    public function fetchTeams(Request $request): RedirectResponse
    {
        return $this->runFetch($request, 'stats:fetch-teams', 'Team stats');
    }

    public function fetchPlayers(Request $request): RedirectResponse
    {
        // Cap pages so a synchronous admin request never runs long or burns quota.
        return $this->runFetch($request, 'stats:fetch-players', 'Player stats', ['--max-pages' => 5]);
    }

    private function runFetch(Request $request, string $command, string $label, array $extra = []): RedirectResponse
    {
        $leagueId = (int) $request->input('league');
        $params   = $extra;

        if ($leagueId) {
            $params['--leagues'] = (string) $leagueId;
        }
        if ($season = (int) $request->input('season')) {
            $params['--season'] = (string) $season;
        }

        try {
            Artisan::call($command, $params);
        } catch (\Throwable $e) {
            return back()->with('error', "{$label} fetch failed: ".$e->getMessage());
        }

        $back = ['league' => $leagueId ?: null, 'season' => $season ?: null];

        return redirect()->route('admin.api-stats.index', array_filter($back))
            ->with('success', "{$label} fetch completed".($leagueId ? " for league {$leagueId}." : '.'));
    }

    /** @return array<int, string> */
    private function leagueOptions(int $season): array
    {
        $ids = collect()
            ->merge(Standing::query()->where('season', $season)->distinct()->pluck('league_id'))
            ->merge(TeamStatistic::query()->where('season', $season)->distinct()->pluck('league_id'))
            ->merge(PlayerStatistic::query()->where('season', $season)->distinct()->pluck('league_id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        // Also offer every covered league so admins can fetch a league that
        // has no rows yet (first-time pull).
        $ids = $ids->merge(LeagueCoverage::coveredLeagueIds())->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $names = FootballMatch::query()
            ->whereIn('league_id', $ids->all())
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

    /** @param array<int, string> $leagues */
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
