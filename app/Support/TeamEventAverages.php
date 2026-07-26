<?php

namespace App\Support;

use App\Models\FixtureStatistic;

/**
 * Rolling corner/card averages (for and against) for a team, from the
 * post-match fixture_statistics we collect. Returns null until there is enough
 * history to estimate rates, so corner/card markets only appear once the data
 * supports them.
 */
class TeamEventAverages
{
    private const MIN_SAMPLES = 3;

    /**
     * @return array{corners_for:float, corners_against:float, cards_for:float, cards_against:float, samples:int}|null
     */
    public static function for(string $teamName, int $lastN = 10): ?array
    {
        $rows = FixtureStatistic::query()
            ->where('team_name', $teamName)
            ->latest('id')
            ->limit($lastN)
            ->get();

        if ($rows->count() < self::MIN_SAMPLES) {
            return null;
        }

        // Opponent rows in the same fixtures give the "against" figures.
        $opp = FixtureStatistic::query()
            ->whereIn('match_id', $rows->pluck('match_id'))
            ->where('team_name', '!=', $teamName)
            ->get()
            ->keyBy('match_id');

        $n = $rows->count();
        $cornersFor = $cardsFor = 0.0;
        $cornersAgainst = $cardsAgainst = 0.0;
        $oppN = 0;

        foreach ($rows as $r) {
            $cornersFor += (int) ($r->corners ?? 0);
            $cardsFor   += (int) ($r->yellow_cards ?? 0) + (int) ($r->red_cards ?? 0);

            $o = $opp[$r->match_id] ?? null;
            if ($o) {
                $cornersAgainst += (int) ($o->corners ?? 0);
                $cardsAgainst   += (int) ($o->yellow_cards ?? 0) + (int) ($o->red_cards ?? 0);
                $oppN++;
            }
        }

        return [
            'corners_for'     => $cornersFor / $n,
            'corners_against' => $oppN ? $cornersAgainst / $oppN : $cornersFor / $n,
            'cards_for'       => $cardsFor / $n,
            'cards_against'   => $oppN ? $cardsAgainst / $oppN : $cardsFor / $n,
            'samples'         => $n,
        ];
    }
}
