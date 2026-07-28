<?php

namespace App\Services\Stats;

use App\Models\PlayerStatistic;
use App\Services\ApiFootball\Client;
use Illuminate\Support\Facades\Log;

class PlayerStatisticsService
{
    public function __construct(private readonly Client $api) {}

    /**
     * Fetch and persist one page of players for a league × season.
     * Returns ['count' => rows written, 'total_pages' => int, 'current' => int].
     * The /players endpoint is paginated (~20 players/page), so callers loop
     * pages up to total_pages, throttling between calls to protect quota.
     */
    public function fetchLeaguePage(int $leagueId, int $season, int $page = 1): array
    {
        $data   = $this->api->get('players', ['league' => $leagueId, 'season' => $season, 'page' => $page]);
        $rows   = $data['response'] ?? [];
        $paging = $data['paging'] ?? ['current' => $page, 'total' => 1];
        $count  = 0;

        foreach ($rows as $entry) {
            $player = $entry['player'] ?? [];
            $pid    = (int) ($player['id'] ?? 0);
            if ($pid === 0) {
                continue;
            }

            // A player's statistics array can span multiple teams/leagues —
            // keep the block(s) for the league we're fetching.
            foreach (($entry['statistics'] ?? []) as $stat) {
                if ((int) ($stat['league']['id'] ?? 0) !== $leagueId) {
                    continue;
                }

                $teamId = (int) ($stat['team']['id'] ?? 0);
                if ($teamId === 0) {
                    continue;
                }

                // One malformed record must never abort a multi-league run that
                // has already spent API calls — sanitise + isolate each upsert.
                try {
                    PlayerStatistic::query()->updateOrCreate(
                        [
                            'player_api_id' => $pid,
                            'team_api_id'   => $teamId,
                            'league_id'     => $leagueId,
                            'season'        => $season,
                        ],
                        [
                            'player_name'  => mb_substr((string) ($player['name'] ?? 'Unknown'), 0, 255),
                            'player_photo' => $player['photo'] ?? null,
                            'age'          => $this->saneAge($player['age'] ?? null),
                            'nationality'  => $player['nationality'] ?? null,
                            'team_name'    => (string) ($stat['team']['name'] ?? 'Unknown'),
                            'position'     => $stat['games']['position'] ?? null,
                            'appearances'  => (int) ($stat['games']['appearences'] ?? 0),
                            'lineups'      => (int) ($stat['games']['lineups'] ?? 0),
                            'minutes'      => (int) ($stat['games']['minutes'] ?? 0),
                            'goals'        => (int) ($stat['goals']['total'] ?? 0),
                            'assists'      => (int) ($stat['goals']['assists'] ?? 0),
                            'yellow_cards' => (int) ($stat['cards']['yellow'] ?? 0),
                            'red_cards'    => (int) ($stat['cards']['red'] ?? 0),
                            'rating'       => $this->toFloat($stat['games']['rating'] ?? null),
                            'raw'          => $stat,
                        ]
                    );
                    $count++;
                } catch (\Throwable $e) {
                    Log::warning("PlayerStatistics: skipped player {$pid} ({$leagueId}/{$season}) — {$e->getMessage()}");
                }
            }
        }

        return [
            'count'       => $count,
            'total_pages' => (int) ($paging['total'] ?? 1),
            'current'     => (int) ($paging['current'] ?? $page),
        ];
    }

    private function toFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /** API sometimes returns junk ages (e.g. a year). Keep only plausible ones. */
    private function saneAge(mixed $value): ?int
    {
        $age = (int) $value;
        return ($age >= 10 && $age <= 70) ? $age : null;
    }
}
