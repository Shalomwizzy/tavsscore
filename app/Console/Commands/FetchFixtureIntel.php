<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\ApiFootball\Client;
use App\Services\Stats\FixtureIntelService;
use App\Support\LeagueCoverage;
use Illuminate\Console\Command;

class FetchFixtureIntel extends Command
{
    protected $signature = 'stats:fetch-fixture-intel
                            {--hours-ahead=48 : Look this many hours ahead for fixtures}
                            {--sleep=300 : Milliseconds to wait between API calls}
                            {--skip-injuries : Only fetch API-Football predictions}
                            {--skip-predictions : Only fetch injuries}';

    protected $description = 'Pull injuries + API-Football predictions for upcoming covered fixtures.';

    public function handle(FixtureIntelService $service, Client $api): int
    {
        $hours   = max(1, (int) $this->option('hours-ahead'));
        $sleepUs = max(0, (int) $this->option('sleep')) * 1000;

        $matches = FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->whereNotNull('api_id')
            ->whereIn('status', ['NS', 'TBD'])
            ->whereBetween('match_time', [now(), now()->addHours($hours)])
            ->orderBy('match_time')
            ->get();

        if ($matches->isEmpty()) {
            $this->info('No upcoming covered fixtures in window.');
            return self::SUCCESS;
        }

        $this->info("Fetching fixture intel for {$matches->count()} fixture(s).");
        $inj = 0;
        $pred = 0;

        foreach ($matches as $match) {
            if ($api->quotaExhausted()) {
                $this->warn('API quota exhausted — stopping early.');
                break;
            }

            if (! $this->option('skip-injuries')) {
                $inj += $service->fetchInjuries($match);
                if ($sleepUs > 0) usleep($sleepUs);
            }

            if (! $this->option('skip-predictions') && ! $api->quotaExhausted()) {
                if ($service->fetchApiPrediction($match)) $pred++;
                if ($sleepUs > 0) usleep($sleepUs);
            }
        }

        $this->info("Done. {$inj} injury rows, {$pred} API predictions upserted.");

        return self::SUCCESS;
    }
}
