<?php

namespace App\Console\Commands;

use App\Services\Football\ResultsFallbackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FetchResultsFallback extends Command
{
    protected $signature = 'results:fallback {--days=3 : How many past days of results to reconcile}';

    protected $description = 'Fill pending match results from football-data.org when API-Football is unavailable, then settle outcomes.';

    public function handle(ResultsFallbackService $service): int
    {
        $result = $service->settlePending((int) $this->option('days'));

        if (! ($result['configured'] ?? false)) {
            $this->warn('FOOTBALL_DATA_KEY not configured — fallback unavailable.');
            return self::SUCCESS;
        }

        if (($result['pending'] ?? 0) === 0) {
            $this->info('No pending results — nothing to fall back on.');
            return self::SUCCESS;
        }

        $fd   = $result['fd_rows']   === null ? 'not configured' : $result['fd_rows'] . ' rows';
        $tsdb = $result['tsdb_rows'] === null ? 'not checked (nothing left / key unset)' : $result['tsdb_rows'] . ' rows';

        $this->info("Sources checked → football-data.org: {$fd} | TheSportsDB: {$tsdb}");
        $this->info("Filled {$result['updated']} result(s) ({$result['predicted_updated']} predicted) "
            . "of {$result['pending']} pending ({$result['predicted']} predicted).");

        // Grade the freshly-filled results immediately.
        if (($result['updated'] ?? 0) > 0) {
            Artisan::call('predictions:check-outcomes', ['--days' => (int) $this->option('days')], $this->getOutput());
        }

        return self::SUCCESS;
    }
}
