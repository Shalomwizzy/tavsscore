<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Keeps every admin pick channel on the same current-data rebuild path.
 * Selection remains the responsibility of each channel, because every market
 * has its own qualification and ranking rules.
 */
class FootballPredictionBoardRefresher
{
    public function refreshFixturesAndBoards(): void
    {
        if (Artisan::call('fetch:matches') !== 0) {
            throw new RuntimeException('The fixture refresh failed. No picks were re-selected.');
        }

        if (Artisan::call('predict:matches') !== 0) {
            throw new RuntimeException('Prediction boards could not be rebuilt. No picks were re-selected.');
        }
    }

    public function refreshFixturesOnly(): void
    {
        if (Artisan::call('fetch:matches') !== 0) {
            throw new RuntimeException('The fixture refresh failed.');
        }
    }
}
