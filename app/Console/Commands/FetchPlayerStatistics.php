<?php

namespace App\Console\Commands;

use App\Services\ApiFootball\Client;
use App\Services\Stats\PlayerStatisticsService;
use App\Support\LeagueCoverage;
use Illuminate\Console\Command;

class FetchPlayerStatistics extends Command
{
    protected $signature = 'stats:fetch-players
                            {--leagues= : Comma-separated league IDs, or "all" (default: top European leagues)}
                            {--season= : Season start-year (default: current year, Lagos)}
                            {--max-pages=40 : Safety cap on pages per league (protects quota)}
                            {--sleep=350 : Milliseconds to wait between API calls}';

    protected $description = 'Pull per-player season statistics from API-Football (paginated, quota-heavy).';

    public function handle(PlayerStatisticsService $service, Client $api): int
    {
        $leagues  = $this->leagues();
        $season   = (int) ($this->option('season') ?: LeagueCoverage::currentSeason());
        $maxPages = max(1, (int) $this->option('max-pages'));
        $sleepUs  = max(0, (int) $this->option('sleep')) * 1000;

        if (empty($leagues)) {
            $this->warn('No covered leagues found. Ingest fixtures first.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Fetching player statistics for %d league(s), season %d (≤%d pages each).',
            count($leagues), $season, $maxPages
        ));
        $rows = 0;

        foreach ($leagues as $leagueId) {
            if ($api->quotaExhausted()) {
                $this->warn('API quota exhausted — stopping early. Re-run after the daily reset.');
                break;
            }

            $page       = 1;
            $totalPages = 1;
            $leagueRows = 0;

            do {
                $result      = $service->fetchLeaguePage($leagueId, $season, $page);
                $rows       += $result['count'];
                $leagueRows += $result['count'];
                $totalPages  = min($result['total_pages'], $maxPages);
                $page++;

                if ($sleepUs > 0 && $page <= $totalPages) {
                    usleep($sleepUs);
                }
            } while ($page <= $totalPages && ! $api->quotaExhausted());

            $this->line("  league {$leagueId}: {$leagueRows} player rows ({$totalPages} pages)");
        }

        $this->info("Done. {$rows} player-stat rows upserted.");

        return self::SUCCESS;
    }

    /** @return array<int, int> */
    private function leagues(): array
    {
        $raw = (string) $this->option('leagues');

        if (strtolower(trim($raw)) === 'all') {
            return LeagueCoverage::coveredLeagueIds();
        }

        if ($raw !== '') {
            return collect(explode(',', $raw))
                ->map(fn ($x) => (int) trim($x))
                ->filter()
                ->values()
                ->all();
        }

        // Player stats only power Fantasy (Premier League) and Top Scorers /
        // Goalscorer picks (top European leagues). Paging /players for all ~56
        // covered leagues — most of which never show a player anywhere on the
        // site — was the main API-quota drain, so default to the top leagues.
        // Use --leagues=all for a full pull.
        return LeagueCoverage::topEuropean();
    }
}
