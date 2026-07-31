<?php

namespace App\Console\Commands;

use App\Services\Tennis\LiveTennisService;
use App\Services\Tennis\TennisEspnResultsFallbackService;
use Illuminate\Console\Command;

class SettleTennisResults extends Command
{
    protected $signature = 'tennis:settle-results';
    protected $description = 'Check tracked Live Tennis fixtures and mark tennis predictions won or lost.';
    public function handle(LiveTennisService $live, TennisEspnResultsFallbackService $espn): int
    {
        try {
            $liveResult = $live->settleTracked();
            // ESPN is a keyless verification fallback for fixtures which a
            // limited Live Tennis plan no longer exposes after completion.
            $espnResult = $espn->settlePending();
            $this->info("Live Tennis checked {$liveResult['checked']}; settled {$liveResult['settled']}. ESPN checked {$espnResult['checked']}; settled {$espnResult['settled']}.");

            return self::SUCCESS;
        }
        catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
