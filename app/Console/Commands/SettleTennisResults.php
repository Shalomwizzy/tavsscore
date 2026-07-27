<?php

namespace App\Console\Commands;

use App\Services\Tennis\LiveTennisService;
use Illuminate\Console\Command;

class SettleTennisResults extends Command
{
    protected $signature = 'tennis:settle-results';
    protected $description = 'Check tracked Live Tennis fixtures and mark tennis predictions won or lost.';
    public function handle(LiveTennisService $live): int
    {
        try { $r = $live->settleTracked(); $this->info("Checked {$r['checked']}; settled {$r['settled']}. "); return self::SUCCESS; }
        catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
