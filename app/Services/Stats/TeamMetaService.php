<?php

namespace App\Services\Stats;

use App\Models\Coach;
use App\Models\Transfer;
use App\Services\ApiFootball\Client;
use Carbon\Carbon;

/**
 * Team-level metadata from API-Football: transfers and coaches. Feeds the AI
 * blog writer (transfer news) and adds manager context to predictions.
 */
class TeamMetaService
{
    public function __construct(private readonly Client $api) {}

    /** Fetch and persist a team's transfers. Returns rows written. */
    public function fetchTransfers(int $teamId): int
    {
        $data  = $this->api->get('transfers', ['team' => $teamId]);
        $count = 0;

        foreach ($data['response'] ?? [] as $entry) {
            $player = $entry['player'] ?? [];
            $pid    = (int) ($player['id'] ?? 0);
            if ($pid === 0) {
                continue;
            }

            foreach ($entry['transfers'] ?? [] as $t) {
                $date = $t['date'] ?? null;
                if (blank($date)) {
                    continue;
                }

                Transfer::query()->updateOrCreate(
                    [
                        'player_api_id' => $pid,
                        'transfer_date' => Carbon::parse($date)->toDateString(),
                        'team_in_id'    => $t['teams']['in']['id'] ?? null,
                    ],
                    [
                        'player_name'   => (string) ($player['name'] ?? 'Unknown'),
                        'type'          => $t['type'] ?? null,
                        'team_in_name'  => $t['teams']['in']['name'] ?? null,
                        'team_out_id'   => $t['teams']['out']['id'] ?? null,
                        'team_out_name' => $t['teams']['out']['name'] ?? null,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /** Fetch and persist a team's coach(es). Returns rows written. */
    public function fetchCoach(int $teamId): int
    {
        $data  = $this->api->get('coachs', ['team' => $teamId]);
        $count = 0;

        foreach ($data['response'] ?? [] as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid === 0) {
                continue;
            }

            // Current if a career entry for this team has no end date.
            $isCurrent = collect($c['career'] ?? [])
                ->contains(fn ($career) => (int) ($career['team']['id'] ?? 0) === $teamId && blank($career['end'] ?? null));

            Coach::query()->updateOrCreate(
                ['coach_api_id' => $cid, 'team_api_id' => $teamId],
                [
                    'name'        => (string) ($c['name'] ?? 'Unknown'),
                    'team_name'   => $c['team']['name'] ?? null,
                    'age'         => $c['age'] ?? null,
                    'nationality' => $c['nationality'] ?? null,
                    'photo'       => $c['photo'] ?? null,
                    'is_current'  => $isCurrent,
                ]
            );
            $count++;
        }

        return $count;
    }
}
