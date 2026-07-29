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
            'market'    => $this->marketEvidence($prediction, $match, $home, $away),
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
        $reasons = $this->marketEvidence($prediction, $match, $home, $away)['reasons'];
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

    /** Market-specific evidence prevents a GG or draw pick being explained like a 1X2 pick. */
    private function marketEvidence(Prediction $prediction, FootballMatch $match, array $home, array $away): array
    {
        $outcome = (string) ($prediction->predicted_outcome ?? 'Model pick');
        $metrics = [];
        $reasons = [];
        $homeWin = round((float) $prediction->home_win_prob, 1);
        $draw = round((float) $prediction->draw_prob, 1);
        $awayWin = round((float) $prediction->away_win_prob, 1);
        $over15 = round((float) ($prediction->over_15_prob ?? 0), 1);
        $over25 = round((float) ($prediction->over_25_prob ?? 0), 1);
        $btts = round((float) ($prediction->btts_prob ?? 0), 1);

        $add = function (string $label, float|int|string|null $value, string $hint = '') use (&$metrics): void {
            if ($value === null || $value === '') return;
            $metrics[] = ['label' => $label, 'value' => is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.') . '%' : $value, 'hint' => $hint];
        };

        $isDraw = $prediction->is_draw_pick || $outcome === 'Draw';
        $isGg = $prediction->is_gg_pick || str_contains($outcome, 'Both Teams Score');
        $isOver15 = $prediction->is_over15_pick || str_contains($outcome, 'Over 1.5');
        $isOver25 = $prediction->is_over25_pick || str_contains($outcome, 'Over 2.5');

        if ($isDraw) {
            $add('Draw probability', $draw, 'Model chance of a level finish');
            $add('Win-probability gap', abs($homeWin - $awayWin), 'Smaller gap means a more even matchup');
            $reasons[] = "The model gives the draw a {$draw}% chance and rates the two teams only ".number_format(abs($homeWin - $awayWin), 1)." percentage points apart for an outright win.";
        } elseif ($isGg) {
            $add('BTTS probability', $btts, 'Both teams to score');
            $add("{$match->home_team} BTTS", $home['recent']['played'] ? round($home['recent']['btts'] / $home['recent']['played'] * 100, 1) : null, 'Across recent matches');
            $add("{$match->away_team} BTTS", $away['recent']['played'] ? round($away['recent']['btts'] / $away['recent']['played'] * 100, 1) : null, 'Across recent matches');
            $reasons[] = "Both Teams to Score is rated at {$btts}% by the model, supported by each side's recent scoring profile.";
        } elseif ($isOver25 || $isOver15) {
            $line = $isOver25 ? 'Over 2.5 Goals' : 'Over 1.5 Goals';
            $probability = $isOver25 ? $over25 : $over15;
            $threshold = $isOver25 ? 3 : 2;
            $add("{$line} probability", $probability, "Chance of {$threshold}+ total goals");
            $add("{$match->home_team} high-scoring games", $home['recent']['played'] ? round($home['recent']['over_25'] / $home['recent']['played'] * 100, 1) : null, 'Recent matches with 3+ goals');
            $add("{$match->away_team} high-scoring games", $away['recent']['played'] ? round($away['recent']['over_25'] / $away['recent']['played'] * 100, 1) : null, 'Recent matches with 3+ goals');
            $reasons[] = "{$line} is rated at {$probability}% after combining both teams' recent goal output and defensive records.";
        } elseif ($prediction->is_double_chance_pick || str_contains($outcome, 'Home or Draw') || str_contains($outcome, 'Draw or Away')) {
            $homeSide = str_contains($outcome, 'Home') || $prediction->double_chance_label === '1X';
            $probability = $homeSide ? $homeWin + $draw : $draw + $awayWin;
            $label = $homeSide ? '1X · Home or Draw' : 'X2 · Draw or Away';
            $add($label, $probability, 'Two outcomes covered by the model');
            $add('Loss probability', 100 - $probability, 'Only the uncovered result loses');
            $reasons[] = "{$label} covers {$probability}% of the model's 1X2 probability, leaving ".number_format(100 - $probability, 1)."% on the only losing outcome.";
        } elseif ($prediction->is_team3plus_pick) {
            $homeSide = $prediction->team3plus_label === 'Home';
            $team = $homeSide ? $match->home_team : $match->away_team;
            $threePlus = $homeSide ? (float) $prediction->home_3plus_prob : (float) $prediction->away_3plus_prob;
            $add("{$team} 3+ goals", $threePlus, 'Model chance of scoring at least three');
            $add("{$team} under 3 goals", 100 - $threePlus, 'Probability behind the selected NO market');
            $reasons[] = "{$team}'s chance of scoring three or more is only ".number_format($threePlus, 1)."%, which supports the selected team-goals market.";
        } elseif ($prediction->is_corners_pick) {
            $label = (string) ($prediction->corners_label ?: $outcome);
            $probability = is_array($prediction->market_board) ? ($prediction->market_board[$label] ?? null) : null;
            $add($label, $probability, 'Model probability from stored market board');
            $reasons[] = $probability !== null
                ? "{$label} is the strongest stored corners line for this fixture at ".number_format((float) $probability, 1).'%.'
                : 'This corners line was selected from the available team corner history and market model.';
        } elseif (! empty($prediction->likely_scores)) {
            $topScore = $prediction->likely_scores[0] ?? null;
            if (is_array($topScore)) {
                $add('Most likely score', $topScore['score'] ?? null, 'Top Poisson scoreline');
                $add('Scoreline probability', $topScore['pct'] ?? null, 'Exact-score model probability');
                $reasons[] = 'The score forecast is led by '.$topScore['score'].' at '.($topScore['pct'] ?? '—').'%, based on the goal model.';
            }
        } else {
            $winner = max(['home' => $homeWin, 'draw' => $draw, 'away' => $awayWin]);
            $label = $winner === $homeWin ? $match->home_team.' win' : ($winner === $awayWin ? $match->away_team.' win' : 'Draw');
            $add($match->home_team.' win', $homeWin, '1X2 model probability');
            $add('Draw', $draw, '1X2 model probability');
            $add($match->away_team.' win', $awayWin, '1X2 model probability');
            $reasons[] = "The 1X2 model's strongest outcome is {$label} at ".number_format($winner, 1).'%.';
        }

        $topTip = is_array($prediction->tips) ? ($prediction->tips[0] ?? []) : [];
        if (! empty($topTip['market_implied'])) {
            $add('Bookmaker implied chance', $topTip['market_implied'], ($topTip['market_agrees'] ?? false) ? 'Market agrees with this pick' : 'Market does not fully agree');
        }

        return ['outcome' => $outcome, 'confidence' => $prediction->confidence, 'metrics' => $metrics, 'reasons' => $reasons];
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
        return ['available' => false, 'home' => [], 'away' => [], 'h2h' => [], 'market' => [], 'reasons' => [], 'injuries' => ['home' => [], 'away' => []]];
    }
}
