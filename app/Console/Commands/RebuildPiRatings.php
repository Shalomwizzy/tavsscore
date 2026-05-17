<?php

namespace App\Console\Commands;

use App\Services\PiRatingService;
use Illuminate\Console\Command;

class RebuildPiRatings extends Command
{
    protected $signature   = 'piratings:rebuild';
    protected $description = 'Rebuild all team pi-ratings from historical match data.';

    public function handle(PiRatingService $piRating): int
    {
        $this->info('Rebuilding pi-ratings from full match history...');
        $count = $piRating->rebuildFromHistory();
        $this->info("Done — rated {$count} matches.");
        return self::SUCCESS;
    }
}
