<?php

namespace App\Console\Commands;

use App\Services\Fantasy\FantasySquadService;
use Illuminate\Console\Command;

class BuildFantasySquad extends Command
{
    protected $signature = 'fantasy:build {--league=39 : League id (default Premier League)}';

    protected $description = 'Build this week\'s Fantasy best XI from stored player stats.';

    public function handle(FantasySquadService $service): int
    {
        $leagueId = (int) $this->option('league');

        $squad = $service->build($leagueId);

        if (! $squad) {
            $this->warn('Not enough player-stat data to build a Fantasy squad yet.');
            return self::SUCCESS;
        }

        $this->info("Built {$squad->gameweek}: {$squad->formation}, £{$squad->budget_used}m, "
            . "{$squad->total_points} pts, captain {$squad->captain}.");

        return self::SUCCESS;
    }
}
