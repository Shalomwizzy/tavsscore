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

        if (isset($result['error'])) {
            $this->warn("football-data.org fetch failed ({$result['error']}).");
            return self::SUCCESS;
        }

        $this->info("Filled {$result['updated']} result(s) from football-data.org "
            . "({$result['predicted_updated']} predicted) — pending {$result['pending']}, "
            . "of which {$result['predicted']} predicted; source rows {$result['results']}.");

        // Grade the freshly-filled results immediately.
        if (($result['updated'] ?? 0) > 0) {
            Artisan::call('predictions:check-outcomes', ['--days' => (int) $this->option('days')], $this->getOutput());
        }

        return self::SUCCESS;
    }
}
