<?php

namespace App\Console\Commands;

use App\Services\ApiFootball\Client;
use App\Services\Stats\StandingsService;
use App\Support\LeagueCoverage;
use Illuminate\Console\Command;

class FetchStandings extends Command
{
    protected $signature = 'stats:fetch-standings
                            {--leagues= : Comma-separated league IDs (default: all covered leagues)}
                            {--season= : Season start-year (default: current year, Lagos)}
                            {--sleep=250 : Milliseconds to wait between API calls}';

    protected $description = 'Pull league standings tables from API-Football for covered leagues.';

    public function handle(StandingsService $service, Client $api): int
    {
        $leagues = $this->leagues();
        $season  = (int) ($this->option('season') ?: now('Africa/Lagos')->year);
        $sleepUs = max(0, (int) $this->option('sleep')) * 1000;

        if (empty($leagues)) {
            $this->warn('No covered leagues found. Ingest fixtures first (fetch:matches / matches:backfill).');
            return self::SUCCESS;
        }

        $this->info(sprintf('Fetching standings for %d league(s), season %d.', count($leagues), $season));
        $rows = 0;

        foreach ($leagues as $leagueId) {
            if ($api->quotaExhausted()) {
                $this->warn('API quota exhausted — stopping early. Re-run after the daily reset.');
                break;
            }

            $n = $service->fetchLeague($leagueId, $season);
            $rows += $n;
            $this->line("  league {$leagueId}: {$n} rows");

            if ($sleepUs > 0) {
                usleep($sleepUs);
            }
        }

        $this->info("Done. {$rows} standing rows upserted.");

        return self::SUCCESS;
    }

    /** @return array<int, int> */
    private function leagues(): array
    {
        $raw = (string) $this->option('leagues');
        if ($raw !== '') {
            return collect(explode(',', $raw))
                ->map(fn ($x) => (int) trim($x))
                ->filter()
                ->values()
                ->all();
        }

        return LeagueCoverage::coveredLeagueIds();
    }
}
