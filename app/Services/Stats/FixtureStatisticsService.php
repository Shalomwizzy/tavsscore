<?php

namespace App\Services\Stats;

use App\Models\FixtureStatistic;
use App\Models\FootballMatch;
use App\Services\ApiFootball\Client;

/**
 * Post-match fixture statistics (shots, possession, corners, cards, xG) per
 * team. Powers richer markets (corners/cards) and better model training.
 * Only meaningful for finished matches.
 */
class FixtureStatisticsService
{
    public function __construct(private readonly Client $api) {}

    /** Fetch and persist both teams' statistics for a fixture. Returns rows written. */
    public function fetchForMatch(FootballMatch $match): int
    {
        if (blank($match->api_id)) {
            return 0;
        }

        $data  = $this->api->get('fixtures/statistics', ['fixture' => $match->api_id]);
        $count = 0;

        foreach ($data['response'] ?? [] as $teamBlock) {
            $teamId = (int) ($teamBlock['team']['id'] ?? 0);
            if ($teamId === 0) {
                continue;
            }

            $stats = $this->index($teamBlock['statistics'] ?? []);

            FixtureStatistic::query()->updateOrCreate(
                ['match_id' => $match->id, 'team_api_id' => $teamId],
                [
                    'team_name'       => (string) ($teamBlock['team']['name'] ?? 'Unknown'),
                    'shots_total'     => $this->int($stats['Total Shots'] ?? null),
                    'shots_on'        => $this->int($stats['Shots on Goal'] ?? null),
                    'shots_off'       => $this->int($stats['Shots off Goal'] ?? null),
                    'possession'      => $this->int(rtrim((string) ($stats['Ball Possession'] ?? ''), '%')),
                    'corners'         => $this->int($stats['Corner Kicks'] ?? null),
                    'offsides'        => $this->int($stats['Offsides'] ?? null),
                    'fouls'           => $this->int($stats['Fouls'] ?? null),
                    'yellow_cards'    => $this->int($stats['Yellow Cards'] ?? null),
                    'red_cards'       => $this->int($stats['Red Cards'] ?? null),
                    'saves'           => $this->int($stats['Goalkeeper Saves'] ?? null),
                    'passes_total'    => $this->int($stats['Total passes'] ?? null),
                    'passes_accurate' => $this->int($stats['Passes accurate'] ?? null),
                    'expected_goals'  => $this->float($stats['expected_goals'] ?? null),
                    'raw'             => $stats,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * API-Football returns statistics as [{type, value}, ...] — index by type.
     * @return array<string, mixed>
     */
    private function index(array $statistics): array
    {
        $out = [];
        foreach ($statistics as $s) {
            if (isset($s['type'])) {
                $out[$s['type']] = $s['value'];
            }
        }
        return $out;
    }

    private function int(mixed $v): ?int
    {
        return ($v === null || $v === '') ? null : (int) $v;
    }

    private function float(mixed $v): ?float
    {
        return ($v === null || $v === '') ? null : (float) $v;
    }
}
