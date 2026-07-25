<?php

namespace App\Services\Stats;

use App\Models\TeamStatistic;
use App\Services\ApiFootball\Client;

class TeamStatisticsService
{
    public function __construct(private readonly Client $api) {}

    /**
     * Fetch and persist season statistics for a single team in a league.
     * Returns true if a row was written.
     */
    public function fetchTeam(int $leagueId, int $season, int $teamId): bool
    {
        $data = $this->api->get('teams/statistics', [
            'league' => $leagueId, 'season' => $season, 'team' => $teamId,
        ]);

        $s = $data['response'] ?? null;
        if (empty($s) || empty($s['team']['id'])) {
            return false;
        }

        TeamStatistic::query()->updateOrCreate(
            ['league_id' => $leagueId, 'season' => $season, 'team_api_id' => $teamId],
            [
                'team_name'  => (string) ($s['team']['name'] ?? 'Unknown'),
                'team_logo'  => $s['team']['logo'] ?? null,
                'form'       => $s['form'] ?? null,

                'played_total' => (int) ($s['fixtures']['played']['total'] ?? 0),
                'played_home'  => (int) ($s['fixtures']['played']['home'] ?? 0),
                'played_away'  => (int) ($s['fixtures']['played']['away'] ?? 0),

                'wins_total' => (int) ($s['fixtures']['wins']['total'] ?? 0),
                'wins_home'  => (int) ($s['fixtures']['wins']['home'] ?? 0),
                'wins_away'  => (int) ($s['fixtures']['wins']['away'] ?? 0),
                'draws_total' => (int) ($s['fixtures']['draws']['total'] ?? 0),
                'draws_home'  => (int) ($s['fixtures']['draws']['home'] ?? 0),
                'draws_away'  => (int) ($s['fixtures']['draws']['away'] ?? 0),
                'loses_total' => (int) ($s['fixtures']['loses']['total'] ?? 0),
                'loses_home'  => (int) ($s['fixtures']['loses']['home'] ?? 0),
                'loses_away'  => (int) ($s['fixtures']['loses']['away'] ?? 0),

                'goals_for_total' => (int) ($s['goals']['for']['total']['total'] ?? 0),
                'goals_for_home'  => (int) ($s['goals']['for']['total']['home'] ?? 0),
                'goals_for_away'  => (int) ($s['goals']['for']['total']['away'] ?? 0),
                'goals_against_total' => (int) ($s['goals']['against']['total']['total'] ?? 0),
                'goals_against_home'  => (int) ($s['goals']['against']['total']['home'] ?? 0),
                'goals_against_away'  => (int) ($s['goals']['against']['total']['away'] ?? 0),

                'goals_for_avg'     => $this->toFloat($s['goals']['for']['average']['total'] ?? null),
                'goals_against_avg' => $this->toFloat($s['goals']['against']['average']['total'] ?? null),

                'clean_sheets_total'    => (int) ($s['clean_sheet']['total'] ?? 0),
                'failed_to_score_total' => (int) ($s['failed_to_score']['total'] ?? 0),

                'raw' => $s,
            ]
        );

        return true;
    }

    private function toFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
