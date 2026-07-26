<?php

namespace App\Support;

/**
 * Groups a flat market board (label => probability %) into display categories,
 * each sorted by probability. Powers the grouped market-board UI so the 100+
 * markets read as organised sections instead of one long list.
 */
class MarketCategories
{
    /**
     * @param  array<string, float>  $board
     * @return array<string, array<string, float>>  category => [label => %], sorted desc, empties dropped
     */
    public static function group(array $board): array
    {
        // Fixed display order.
        $cats = [
            'Match Result'      => [],
            'Goals Over/Under'  => [],
            'BTTS & Defence'    => [],
            'Team Goals'        => [],
            'Handicap & Margin' => [],
            'Combos'            => [],
            'Half-Time & HT/FT' => [],
            'Corners'           => [],
            'Cards'             => [],
        ];

        foreach ($board as $label => $prob) {
            $cats[self::categoryFor($label)][$label] = $prob;
        }

        foreach ($cats as $name => $markets) {
            if (empty($markets)) {
                unset($cats[$name]);
                continue;
            }
            arsort($cats[$name]);
        }

        return $cats;
    }

    private static function categoryFor(string $l): string
    {
        return match (true) {
            str_contains($l, 'Corners')                                                                   => 'Corners',
            str_contains($l, 'Cards')                                                                     => 'Cards',
            str_starts_with($l, 'HT') || str_contains($l, 'HT/FT') || str_contains($l, 'Half')            => 'Half-Time & HT/FT',
            str_contains($l, '&')                                                                         => 'Combos',
            str_contains($l, 'Handicap') || str_contains($l, 'to win by')                                 => 'Handicap & Margin',
            (str_starts_with($l, 'Home ') || str_starts_with($l, 'Away '))
                && (str_contains($l, 'Over') || str_contains($l, 'Exactly') || str_contains($l, '3+'))    => 'Team Goals',
            str_contains($l, 'Clean Sheet') || str_contains($l, 'Win to Nil')
                || str_contains($l, 'to Score') || str_contains($l, 'Both Teams Score') || str_contains($l, 'BTTS') => 'BTTS & Defence',
            str_contains($l, 'Goals') || str_contains($l, 'Odd') || str_contains($l, 'Even')              => 'Goals Over/Under',
            default                                                                                       => 'Match Result',
        };
    }
}
