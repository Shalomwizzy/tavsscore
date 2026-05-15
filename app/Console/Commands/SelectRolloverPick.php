<?php

namespace App\Console\Commands;

use App\Services\RolloverService;
use Illuminate\Console\Command;

class SelectRolloverPick extends Command
{
    protected $signature = 'rollover:select';

    protected $description = 'Select today\'s rollover pick (runs daily at 09:30 Lagos).';

    public function handle(RolloverService $rollover): int
    {
        $pick = $rollover->selectTodaysPick();

        if (! $pick) {
            $this->warn('No rollover pick selected today (no suitable match or already set).');
            return self::SUCCESS;
        }

        $match     = $pick->match;
        $agreed    = $pick->both_agree ? '✓ Both AIs agree' : '⚠ Single AI only';
        $this->info("Day {$pick->day_number}: {$match?->home_team} vs {$match?->away_team}");
        $this->info("  Tip: {$pick->groq_verdict} @ {$pick->implied_odds} odds | Stake: " . number_format($pick->stake_amount, 0) . " → Return: " . number_format($pick->potential_return, 0));
        $this->info("  {$agreed}");

        return self::SUCCESS;
    }
}
