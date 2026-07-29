<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\MatchInjury;
use App\Models\Prediction;
use App\Models\TeamStatistic;
use Illuminate\Support\Facades\Cache;

class MatchInsightService
{
    private const FINISHED = ['FT', 'AET', 'PEN'];

    /**
     * Build the explainable, local-data breakdown shown below a public pick.
     * It never calls an external API: every number is from data TavsScore has
     * already stored for the teams and fixture.
     */
    public function for(Prediction $prediction): array
    {
        $match = $prediction->relationLoaded('match') ? $prediction->match : $prediction->match()->first();

        if (! $match) {
            return $this->empty();
        }

        return Cache::remember(
            "match-insight:{$match->id}:{$prediction->id}",
            now()->addMinutes(10),
            fn (): array => $this->build($prediction, $match),
        );
    }

    private function build(Prediction $prediction, FootballMatch $match): array
    {
        $home = $this->teamSnapshot($match, $match->home_team, $match->home_team_logo);
        $away = $this->teamSnapshot($match, $match->away_team, $match->away_team_logo);
        $h2h  = $this->headToHead($match);

        $injuries = MatchInjury::query()
            ->where('match_id', $match->id)
            ->get()
            ->groupBy('team_name');

        return [
            'available' => true,
            'home'      => $home,
            'away'      => $away,
            'h2h'       => $h2h,
            'reasons'   => $this->reasons($prediction, $match, $home, $away, $h2h),
            'injuries'  => [
                'home' => $this->injuryRows($injuries->get($match->home_team)),
                'away' => $this->injuryRows($injuries->get($match->away_team)),
            ],
        ];
    }

    private function teamSnapshot(FootballMatch $fixture, string $team, ?string $logo): array
    {
        $matches = FootballMatch::query()
            ->where('id', '!=', $fixture->id)
            ->whereIn('status', self::FINISHED)
            ->where('match_time', '<', $fixture->match_time)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->where(fn ($query) => $query->where('home_team', $team)->orWhere('away_team', $team))
            ->latest('match_time')
            ->limit(6)
            ->get();

        $wins = $draws = $losses = $goalsFor = $goalsAgainst = $cleanSheets = $over25 = $btts = 0;
        $form = [];

        foreach ($matches as $match) {
            $isHome = $match->home_team === $team;
            $gf = (int) ($isHome ? $match->home_score : $match->away_score);
            $ga = (int) ($isHome ? $match->away_score : $match->home_score);
            $result = $gf > $ga ? 'W' : ($gf === $ga ? 'D' : 'L');

            $wins += $result === 'W' ? 1 : 0;
            $draws += $result === 'D' ? 1 : 0;
            $losses += $result === 'L' ? 1 : 0;
            $goalsFor += $gf;
            $goalsAgainst += $ga;
            $cleanSheets += $ga === 0 ? 1 : 0;
            $over25 += $gf + $ga >= 3 ? 1 : 0;
            $btts += $gf >= 1 && $ga >= 1 ? 1 : 0;

            $form[] = [
                'result'   => $result,
                'opponent' => $isHome ? $match->away_team : $match->home_team,
                'score'    => "{$gf}-{$ga}",
                'venue'    => $isHome ? 'H' : 'A',
                'date'     => $match->match_time?->format('M j'),
            ];
        }

        $played = $matches->count();
        $season = TeamStatistic::query()
            ->where('league_id', $fixture->league_id)
            ->where('team_name', $team)
            ->latest('season')
            ->first();

        return [
            'name'  => $team,
            'logo'  => $logo ?: $season?->team_logo,
            'form'  => $form,
            'recent' => [
                'played'       => $played,
                'wins'         => $wins,
                'draws'        => $draws,
                'losses'       => $losses,
                'goals_for'    => $goalsFor,
                'goals_against'=> $goalsAgainst,
                'gpg'          => $played ? round($goalsFor / $played, 2) : null,
                'cpg'          => $played ? round($goalsAgainst / $played, 2) : null,
                'clean_sheets' => $cleanSheets,
                'over_25'      => $over25,
                'btts'         => $btts,
            ],
            'season' => $season ? [
                'played' => $season->played_total,
                'wins'   => $season->wins_total,
                'draws'  => $season->draws_total,
                'losses' => $season->loses_total,
                'gpg'    => $season->goals_for_avg,
                'cpg'    => $season->goals_against_avg,
                'form'   => $season->form,
            ] : null,
        ];
    }

    private function headToHead(FootballMatch $fixture): array
    {
        $matches = FootballMatch::query()
            ->where('id', '!=', $fixture->id)
            ->whereIn('status', self::FINISHED)
            ->where('match_time', '<', $fixture->match_time)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('home_team', $fixture->home_team)->where('away_team', $fixture->away_team))
                ->orWhere(fn ($q) => $q->where('home_team', $fixture->away_team)->where('away_team', $fixture->home_team))
            )
            ->latest('match_time')
            ->limit(5)
            ->get();

        $homeWins = $draws = $awayWins = 0;
        $rows = $matches->map(function (FootballMatch $match) use ($fixture, &$homeWins, &$draws, &$awayWins): array {
            $flipped = $match->home_team !== $fixture->home_team;
            $homeScore = (int) ($flipped ? $match->away_score : $match->home_score);
            $awayScore = (int) ($flipped ? $match->home_score : $match->away_score);
            $outcome = $homeScore > $awayScore ? 'home' : ($homeScore === $awayScore ? 'draw' : 'away');

            $homeWins += $outcome === 'home' ? 1 : 0;
            $draws += $outcome === 'draw' ? 1 : 0;
            $awayWins += $outcome === 'away' ? 1 : 0;

            return [
                'date'  => $match->match_time?->format('M j, Y'),
                'score' => "{$fixture->home_team} {$homeScore}-{$awayScore} {$fixture->away_team}",
                'outcome' => $outcome,
            ];
        })->values()->all();

        return ['total' => count($rows), 'home_wins' => $homeWins, 'draws' => $draws, 'away_wins' => $awayWins, 'results' => $rows];
    }

    private function reasons(Prediction $prediction, FootballMatch $match, array $home, array $away, array $h2h): array
    {
        $reasons = [];
        $topTip = is_array($prediction->tips) ? ($prediction->tips[0] ?? []) : [];

        if (! empty($topTip['rationale'])) {
            $reasons[] = (string) $topTip['rationale'];
        }

        $homePoints = $home['recent']['wins'] * 3 + $home['recent']['draws'];
        $awayPoints = $away['recent']['wins'] * 3 + $away['recent']['draws'];
        if ($home['recent']['played'] >= 3 && $away['recent']['played'] >= 3 && $homePoints !== $awayPoints) {
            $leader = $homePoints > $awayPoints ? $match->home_team : $match->away_team;
            $reasons[] = "Recent form leans to {$leader}: {$homePoints} points versus {$awayPoints} across the last {$home['recent']['played']} matches.";
        }

        if ($home['recent']['played'] >= 3 && $away['recent']['played'] >= 3) {
            $combinedGoals = ($home['recent']['gpg'] ?? 0) + ($away['recent']['gpg'] ?? 0);
            if ($combinedGoals >= 2.6) {
                $reasons[] = 'Recent scoring profiles point to an open game: the two sides combine for '.number_format($combinedGoals, 2).' goals scored per match.';
            } elseif ($combinedGoals <= 1.7) {
                $reasons[] = 'Recent scoring profiles suggest a tighter game: the two sides combine for '.number_format($combinedGoals, 2).' goals scored per match.';
            }
        }

        if ($h2h['total'] >= 2) {
            $reasons[] = "Head-to-head record: {$match->home_team} {$h2h['home_wins']} wins, {$h2h['draws']} draws, {$match->away_team} {$h2h['away_wins']} wins in the last {$h2h['total']} meetings.";
        }

        return array_slice(array_values(array_unique($reasons)), 0, 4);
    }

    private function injuryRows($rows): array
    {
        return collect($rows ?? [])->take(4)->map(fn (MatchInjury $injury) => [
            'player' => $injury->player_name,
            'reason' => $injury->reason ?: ($injury->type ?: 'Unavailable'),
        ])->values()->all();
    }

    private function empty(): array
    {
        return ['available' => false, 'home' => [], 'away' => [], 'h2h' => [], 'reasons' => [], 'injuries' => ['home' => [], 'away' => []]];
    }
}
