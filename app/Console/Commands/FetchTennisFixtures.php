<?php

namespace App\Console\Commands;

use App\Services\Tennis\LiveTennisService;
use Illuminate\Console\Command;

class FetchTennisFixtures extends Command
{
    protected $signature = 'tennis:fetch-fixtures';
    protected $description = 'Fetch upcoming ATP and WTA singles fixtures from Live Tennis API.';
    public function handle(LiveTennisService $live): int
    {
        try { $this->info('Upserted ' . $live->syncFixtures() . ' tennis fixtures.'); return self::SUCCESS; }
        catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
