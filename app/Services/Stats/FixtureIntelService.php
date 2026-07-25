<?php

namespace App\Services\Stats;

use App\Models\ApiPrediction;
use App\Models\FootballMatch;
use App\Models\MatchInjury;
use App\Services\ApiFootball\Client;

/**
 * Per-fixture intelligence from API-Football that sharpens predictions:
 *  - injuries / suspensions declared for the fixture
 *  - API-Football's own model prediction (advice, percentages, expected goals)
 *
 * Both are keyed to a fixture and fed into MatchStatsContext so every LLM and
 * the Claude arbiter sees them. Requires the match to have an api_id.
 */
class FixtureIntelService
{
    public function __construct(private readonly Client $api) {}

    /** Fetch and persist injuries/suspensions for a fixture. Returns rows written. */
    public function fetchInjuries(FootballMatch $match): int
    {
        if (blank($match->api_id)) {
            return 0;
        }

        $data = $this->api->get('injuries', ['fixture' => $match->api_id]);
        $rows = $data['response'] ?? [];

        // Replace this fixture's injuries with the fresh set (players recover / get added).
        $seen = [];
        foreach ($rows as $row) {
            $playerId = (int) ($row['player']['id'] ?? 0);
            if ($playerId === 0) {
                continue;
            }

            MatchInjury::query()->updateOrCreate(
                ['match_id' => $match->id, 'player_api_id' => $playerId],
                [
                    'team_api_id'  => $row['team']['id'] ?? null,
                    'team_name'    => (string) ($row['team']['name'] ?? 'Unknown'),
                    'player_name'  => (string) ($row['player']['name'] ?? 'Unknown'),
                    'player_photo' => $row['player']['photo'] ?? null,
                    'type'         => $row['player']['type'] ?? null,
                    'reason'       => $row['player']['reason'] ?? null,
                ]
            );
            $seen[] = $playerId;
        }

        // Prune anyone no longer listed for this fixture.
        MatchInjury::query()
            ->where('match_id', $match->id)
            ->when(! empty($seen), fn ($q) => $q->whereNotIn('player_api_id', $seen))
            ->when(empty($seen), fn ($q) => $q)
            ->delete();

        return count($seen);
    }

    /** Fetch and persist API-Football's own prediction for a fixture. Returns true if written. */
    public function fetchApiPrediction(FootballMatch $match): bool
    {
        if (blank($match->api_id)) {
            return false;
        }

        $data = $this->api->get('predictions', ['fixture' => $match->api_id]);
        $p    = $data['response'][0]['predictions'] ?? null;

        if (empty($p)) {
            return false;
        }

        ApiPrediction::query()->updateOrCreate(
            ['match_id' => $match->id],
            [
                'winner_name'    => $p['winner']['name'] ?? null,
                'winner_comment' => $p['winner']['comment'] ?? null,
                'advice'         => $p['advice'] ?? null,
                'percent_home'   => $p['percent']['home'] ?? null,
                'percent_draw'   => $p['percent']['draw'] ?? null,
                'percent_away'   => $p['percent']['away'] ?? null,
                'under_over'     => $p['under_over'] ?? null,
                'goals_home'     => $this->toFloat($p['goals']['home'] ?? null),
                'goals_away'     => $this->toFloat($p['goals']['away'] ?? null),
                'raw'            => $p,
            ]
        );

        return true;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        // API-Football sends expected goals as strings like "-1.5" or "2.3".
        return (float) str_replace(['−', '+'], ['-', ''], (string) $value);
    }
}
