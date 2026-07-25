<?php

namespace App\Console\Commands;

use App\Models\Standing;
use App\Services\ApiFootball\Client;
use App\Services\Stats\TeamStatisticsService;
use App\Support\LeagueCoverage;
use Illuminate\Console\Command;

class FetchTeamStatistics extends Command
{
    protected $signature = 'stats:fetch-teams
                            {--leagues= : Comma-separated league IDs (default: all covered leagues)}
                            {--season= : Season start-year (default: current year, Lagos)}
                            {--sleep=300 : Milliseconds to wait between API calls}';

    protected $description = 'Pull per-team season statistics from API-Football for covered leagues.';

    public function handle(TeamStatisticsService $service, Client $api): int
    {
        $leagues = $this->leagues();
        $season  = (int) ($this->option('season') ?: now('Africa/Lagos')->year);
        $sleepUs = max(0, (int) $this->option('sleep')) * 1000;

        if (empty($leagues)) {
            $this->warn('No covered leagues found. Ingest fixtures first.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Fetching team statistics for %d league(s), season %d.', count($leagues), $season));
        $teamsWritten = 0;

        foreach ($leagues as $leagueId) {
            // Team IDs come from the standings table — run stats:fetch-standings first.
            $teamIds = Standing::query()
                ->where('league_id', $leagueId)
                ->where('season', $season)
                ->pluck('team_api_id')
                ->all();

            if (empty($teamIds)) {
                $this->line("  league {$leagueId}: no standings yet — skipped (run stats:fetch-standings)");
                continue;
            }

            foreach ($teamIds as $teamId) {
                if ($api->quotaExhausted()) {
                    $this->warn('API quota exhausted — stopping early. Re-run after the daily reset.');
                    $this->info("Done. {$teamsWritten} team-stat rows upserted.");
                    return self::SUCCESS;
                }

                if ($service->fetchTeam($leagueId, $season, (int) $teamId)) {
                    $teamsWritten++;
                }

                if ($sleepUs > 0) {
                    usleep($sleepUs);
                }
            }

            $this->line("  league {$leagueId}: ".count($teamIds).' teams processed');
        }

        $this->info("Done. {$teamsWritten} team-stat rows upserted.");

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
