<?php

namespace App\Services\Stats;

use App\Models\Standing;
use App\Services\ApiFootball\Client;

class StandingsService
{
    public function __construct(private readonly Client $api) {}

    /**
     * Fetch and persist the full standings table for a league × season.
     * Returns the number of team rows upserted (0 if the league has no table,
     * e.g. cups, or when quota is exhausted).
     */
    public function fetchLeague(int $leagueId, int $season): int
    {
        $data   = $this->api->get('standings', ['league' => $leagueId, 'season' => $season]);
        $league = $data['response'][0]['league'] ?? null;

        if (! $league || empty($league['standings'])) {
            return 0;
        }

        $count = 0;

        // `standings` is an array of groups (single group for most leagues).
        foreach ($league['standings'] as $group) {
            foreach ($group as $row) {
                $teamId = (int) ($row['team']['id'] ?? 0);
                if ($teamId === 0) {
                    continue;
                }

                Standing::query()->updateOrCreate(
                    ['league_id' => $leagueId, 'season' => $season, 'team_api_id' => $teamId],
                    [
                        'team_name'     => (string) ($row['team']['name'] ?? 'Unknown'),
                        'team_logo'     => $row['team']['logo'] ?? null,
                        'rank'          => $row['rank'] ?? null,
                        'group_label'   => $row['group'] ?? null,
                        'points'        => (int) ($row['points'] ?? 0),
                        'goals_diff'    => (int) ($row['goalsDiff'] ?? 0),
                        'form'          => $row['form'] ?? null,
                        'status_desc'   => $row['description'] ?? null,
                        'played'        => (int) ($row['all']['played'] ?? 0),
                        'win'           => (int) ($row['all']['win'] ?? 0),
                        'draw'          => (int) ($row['all']['draw'] ?? 0),
                        'lose'          => (int) ($row['all']['lose'] ?? 0),
                        'goals_for'     => (int) ($row['all']['goals']['for'] ?? 0),
                        'goals_against' => (int) ($row['all']['goals']['against'] ?? 0),
                    ]
                );

                $count++;
            }
        }

        return $count;
    }
}
