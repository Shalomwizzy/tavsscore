<?php

namespace App\Console\Commands;

use App\Services\Tennis\TennisRatingService;
use Illuminate\Console\Command;

class RebuildTennisRatings extends Command
{
    protected $signature = 'tennis:ratings:rebuild';
    protected $description = 'Rebuild overall and surface-specific tennis Elo ratings from imported results.';

    public function handle(TennisRatingService $ratings): int
    {
        $this->info('Rebuilt ' . $ratings->rebuild() . ' tennis rating records.');
        return self::SUCCESS;
    }
}
