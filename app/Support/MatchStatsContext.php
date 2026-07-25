<?php

namespace App\Support;

use App\Models\ApiPrediction;
use App\Models\FootballMatch;
use App\Models\MatchInjury;
use App\Models\Standing;
use App\Models\TeamStatistic;

/**
 * Builds a human-readable API-Football stats block for a fixture — league
 * position, points, form, and season goal record for both teams — to feed as
 * EXTRA CONTEXT into the Groq / Gemini / Mistral prompts. This never touches
 * the Poisson / Dixon-Coles numbers; it only gives the LLMs more signal.
 *
 * Teams are matched by name within the match's league + latest season, since
 * matches store team names as free-form strings (same provider as the stats).
 */
class MatchStatsContext
{
    public static function build(FootballMatch $match): string
    {
        $blocks = array_filter([
            self::seasonStatsBlock($match),
            self::injuriesBlock($match),
            self::apiPredictionBlock($match),
        ]);

        return empty($blocks) ? '' : "\n\n".implode("\n\n", $blocks);
    }

    private static function seasonStatsBlock(FootballMatch $match): string
    {
        $leagueId = (int) ($match->league_id ?? 0);
        if ($leagueId === 0) {
            return '';
        }

        $season = (int) (Standing::query()->where('league_id', $leagueId)->max('season')
            ?: TeamStatistic::query()->where('league_id', $leagueId)->max('season')
            ?: 0);
        if ($season === 0) {
            return '';
        }

        $home = self::teamLine($leagueId, $season, (string) $match->home_team);
        $away = self::teamLine($leagueId, $season, (string) $match->away_team);

        if ($home === null && $away === null) {
            return '';
        }

        $lines = ["═══ SEASON STATS (API-Football, {$season}/".($season + 1).") ═══"];
        if ($home !== null) {
            $lines[] = 'HOME — '.$home;
        }
        if ($away !== null) {
            $lines[] = 'AWAY — '.$away;
        }

        return implode("\n", $lines);
    }

    private static function injuriesBlock(FootballMatch $match): string
    {
        $injuries = MatchInjury::query()->where('match_id', $match->id)->get();
        if ($injuries->isEmpty()) {
            return '';
        }

        $lines = ['═══ INJURIES & SUSPENSIONS (API-Football) ═══'];
        foreach ($injuries->groupBy('team_name') as $team => $rows) {
            $players = $rows->map(function (MatchInjury $i): string {
                $reason = $i->reason ? " ({$i->reason})" : '';
                return $i->player_name.$reason;
            })->implode(', ');
            $lines[] = "{$team}: {$players}";
        }

        return implode("\n", $lines);
    }

    private static function apiPredictionBlock(FootballMatch $match): string
    {
        $p = ApiPrediction::query()->where('match_id', $match->id)->first();
        if ($p === null) {
            return '';
        }

        $lines = ['═══ API-FOOTBALL MODEL PREDICTION ═══'];
        if ($p->percent_home || $p->percent_draw || $p->percent_away) {
            $lines[] = "Win probability — Home {$p->percent_home} / Draw {$p->percent_draw} / Away {$p->percent_away}";
        }
        if ($p->goals_home !== null && $p->goals_away !== null) {
            $lines[] = "Expected goals — Home {$p->goals_home} / Away {$p->goals_away}";
        }
        if (! blank($p->advice)) {
            $lines[] = "Their advice: {$p->advice}";
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    private static function teamLine(int $leagueId, int $season, string $teamName): ?string
    {
        if ($teamName === '') {
            return null;
        }

        $standing = Standing::query()
            ->where('league_id', $leagueId)->where('season', $season)
            ->where('team_name', $teamName)->first();

        $stat = TeamStatistic::query()
            ->where('league_id', $leagueId)->where('season', $season)
            ->where('team_name', $teamName)->first();

        if ($standing === null && $stat === null) {
            return null;
        }

        $parts = [$teamName.':'];

        if ($standing !== null) {
            $parts[] = "{$standing->rank}th, {$standing->points} pts";
            if ($standing->form) {
                $parts[] = 'form '.substr($standing->form, -5);
            }
            $parts[] = "{$standing->win}W-{$standing->draw}D-{$standing->lose}L in {$standing->played}";
        }

        if ($stat !== null) {
            $gfAvg = $stat->goals_for_avg !== null ? number_format((float) $stat->goals_for_avg, 2) : '?';
            $gaAvg = $stat->goals_against_avg !== null ? number_format((float) $stat->goals_against_avg, 2) : '?';
            $parts[] = "scored {$stat->goals_for_total} (avg {$gfAvg}/g), conceded {$stat->goals_against_total} (avg {$gaAvg}/g)";
            $parts[] = "{$stat->clean_sheets_total} clean sheets, failed to score {$stat->failed_to_score_total}x";
        }

        return implode('; ', $parts).'.';
    }
}
