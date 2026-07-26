<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\PlayerStatistic;
use App\Models\Standing;
use App\Models\TeamStatistic;
use App\Services\Markets\PlayerScorerModel;
use App\Support\LeagueCoverage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalscorerPicksController extends Controller
{
    public function index(Request $request): View
    {
        return view('goalscorer-picks.index', $this->buildPicks());
    }

    /**
     * Today's ranked anytime-goalscorer picks. Reused by the admin view.
     * @return array{picks:\Illuminate\Support\Collection, season:int, date:string}
     */
    public function buildPicks(int $limit = 24): array
    {
        $tz     = config('app.timezone');
        $start  = now($tz)->startOfDay();
        $end    = now($tz)->endOfDay();
        $season = (int) (PlayerStatistic::query()->max('season') ?: now($tz)->year);

        $matches = FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->whereBetween('match_time', [$start, $end])
            ->whereNotIn('status', ['CANC', 'PST', 'ABD', 'FT', 'AET', 'PEN'])
            ->orderBy('match_time')
            ->get();

        $picks = collect();

        foreach ($matches as $match) {
            $homeConceded = $this->concededPerGame($match->home_team, $season);
            $awayConceded = $this->concededPerGame($match->away_team, $season);

            // Home players score against the away defence, and vice versa.
            $picks = $picks
                ->merge($this->playersFor($match, $match->home_team, $match->away_team, $awayConceded, $season))
                ->merge($this->playersFor($match, $match->away_team, $match->home_team, $homeConceded, $season));
        }

        return [
            'picks'  => $picks->sortByDesc('probability')->take($limit)->values(),
            'season' => $season,
            'date'   => now($tz)->format('l, F j Y'),
        ];
    }

    private function playersFor(FootballMatch $match, string $team, string $opponent, float $oppConceded, int $season): \Illuminate\Support\Collection
    {
        return PlayerStatistic::query()
            ->where('team_name', $team)
            ->where('season', $season)
            ->where('goals', '>', 0)
            ->orderByDesc('goals')
            ->limit(6)
            ->get()
            ->map(function (PlayerStatistic $p) use ($match, $team, $opponent, $oppConceded): ?array {
                $prob = PlayerScorerModel::anytimeScore($p->goals, $p->appearances, $p->minutes, $oppConceded);
                if ($prob === null) {
                    return null;
                }

                return [
                    'player'      => $p->player_name,
                    'photo'       => $p->player_photo,
                    'team'        => $team,
                    'opponent'    => $opponent,
                    'goals'       => $p->goals,
                    'apps'        => $p->appearances,
                    'probability' => $prob,
                    'two_plus'    => PlayerScorerModel::toScoreTwoPlus($p->goals, $p->appearances, $p->minutes, $oppConceded),
                    'match'       => "{$match->home_team} vs {$match->away_team}",
                    'kickoff'     => $match->match_time?->format('H:i'),
                ];
            })
            ->filter()
            ->values();
    }

    /** Opponent goals conceded per game — from standings, then team stats, else league avg. */
    private function concededPerGame(string $teamName, int $season): float
    {
        $s = Standing::query()->where('team_name', $teamName)->where('season', $season)->first();
        if ($s && $s->played > 0) {
            return $s->goals_against / $s->played;
        }

        $t = TeamStatistic::query()->where('team_name', $teamName)->where('season', $season)->first();
        if ($t && $t->played_total > 0) {
            return $t->goals_against_total / $t->played_total;
        }

        return 1.3;
    }
}
