<?php

namespace App\Services\Tennis;

use App\Models\TennisMatch;
use App\Models\TennisPlayerRating;

class TennisRatingService
{
    /** Replays completed matches chronologically to create overall + surface Elo ratings. */
    public function rebuild(): int
    {
        $ratings = [];
        $matches = TennisMatch::query()->where('status', 'completed')->whereNotNull('winner')
            ->orderBy('match_date')->orderBy('id')->cursor();

        foreach ($matches as $match) {
            $tour = $match->tour;
            foreach (['all', strtolower($match->surface ?: 'hard')] as $surface) {
                $oneKey = "$tour|$surface|{$match->player_one}";
                $twoKey = "$tour|$surface|{$match->player_two}";
                $one = $ratings[$oneKey] ?? ['rating' => 1500.0, 'matches' => 0];
                $two = $ratings[$twoKey] ?? ['rating' => 1500.0, 'matches' => 0];
                $expected = 1 / (1 + 10 ** (($two['rating'] - $one['rating']) / 400));
                $k = ($one['matches'] < 30 ? 40 : 24);
                $one['rating'] += $k * (1 - $expected);
                $two['rating'] -= $k * (1 - $expected);
                $one['matches']++;
                $two['matches']++;
                $ratings[$oneKey] = $one;
                $ratings[$twoKey] = $two;
            }
        }

        foreach ($ratings as $key => $value) {
            [$tour, $surface, $player] = explode('|', $key, 3);
            TennisPlayerRating::updateOrCreate(
                ['tour' => $tour, 'surface' => $surface, 'player_name' => $player],
                ['rating' => round($value['rating'], 2), 'matches_played' => $value['matches'], 'as_of_date' => now()->toDateString()],
            );
        }

        return count($ratings);
    }
}
