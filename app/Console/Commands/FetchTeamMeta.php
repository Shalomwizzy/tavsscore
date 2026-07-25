<?php

namespace App\Console\Commands;

use App\Models\Standing;
use App\Services\ApiFootball\Client;
use App\Services\Stats\TeamMetaService;
use Illuminate\Console\Command;

class FetchTeamMeta extends Command
{
    protected $signature = 'stats:fetch-team-meta
                            {--leagues= : Comma-separated league IDs (default: all with standings)}
                            {--season= : Season start-year (default: current year, Lagos)}
                            {--sleep=350 : Milliseconds to wait between API calls}
                            {--skip-transfers : Only fetch coaches}
                            {--skip-coaches : Only fetch transfers}';

    protected $description = 'Pull transfers + coaches per team from API-Football (for blog news + manager context).';

    public function handle(TeamMetaService $service, Client $api): int
    {
        $season  = (int) ($this->option('season') ?: now('Africa/Lagos')->year);
        $sleepUs = max(0, (int) $this->option('sleep')) * 1000;

        $leaguesOpt = (string) $this->option('leagues');
        $query = Standing::query()->where('season', $season);
        if ($leaguesOpt !== '') {
            $ids = collect(explode(',', $leaguesOpt))->map(fn ($x) => (int) trim($x))->filter()->all();
            $query->whereIn('league_id', $ids);
        }

        $teamIds = $query->distinct()->pluck('team_api_id')->filter()->all();

        if (empty($teamIds)) {
            $this->warn('No teams found in standings for that season — run stats:fetch-standings first.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Fetching team meta for %d team(s).', count($teamIds)));
        $tr = 0;
        $co = 0;

        foreach ($teamIds as $teamId) {
            if ($api->quotaExhausted()) {
                $this->warn('API quota exhausted — stopping early.');
                break;
            }

            if (! $this->option('skip-transfers')) {
                $tr += $service->fetchTransfers((int) $teamId);
                if ($sleepUs > 0) usleep($sleepUs);
            }
            if (! $this->option('skip-coaches') && ! $api->quotaExhausted()) {
                $co += $service->fetchCoach((int) $teamId);
                if ($sleepUs > 0) usleep($sleepUs);
            }
        }

        $this->info("Done. {$tr} transfer rows, {$co} coach rows upserted.");

        return self::SUCCESS;
    }
}
