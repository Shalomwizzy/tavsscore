<?php

namespace App\Console\Commands;

use App\Models\FixtureStatistic;
use App\Models\FootballMatch;
use App\Services\ApiFootball\Client;
use App\Services\Stats\FixtureStatisticsService;
use App\Support\LeagueCoverage;
use Illuminate\Console\Command;

class FetchFixtureStatistics extends Command
{
    protected $signature = 'stats:fetch-fixture-stats
                            {--days=3 : Look back this many days for finished fixtures}
                            {--sleep=300 : Milliseconds to wait between API calls}';

    protected $description = 'Pull post-match statistics (shots/corners/cards/xG) for recently finished covered fixtures.';

    public function handle(FixtureStatisticsService $service, Client $api): int
    {
        $days    = max(1, (int) $this->option('days'));
        $sleepUs = max(0, (int) $this->option('sleep')) * 1000;

        $matches = FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->whereNotNull('api_id')
            ->whereIn('status', ['FT', 'AET', 'PEN'])
            ->whereBetween('match_time', [now()->subDays($days), now()])
            // skip fixtures we already have stats for
            ->whereNotIn('id', FixtureStatistic::query()->select('match_id'))
            ->orderByDesc('match_time')
            ->get();

        if ($matches->isEmpty()) {
            $this->info('No finished covered fixtures needing statistics.');
            return self::SUCCESS;
        }

        $this->info("Fetching statistics for {$matches->count()} finished fixture(s).");
        $rows = 0;

        foreach ($matches as $match) {
            if ($api->quotaExhausted()) {
                $this->warn('API quota exhausted — stopping early.');
                break;
            }
            $rows += $service->fetchForMatch($match);
            if ($sleepUs > 0) usleep($sleepUs);
        }

        $this->info("Done. {$rows} fixture-statistic rows upserted.");

        return self::SUCCESS;
    }
}
