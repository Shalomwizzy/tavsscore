<?php

namespace App\Console\Commands;

use App\Models\ApiPrediction;
use App\Models\FootballMatch;
use App\Models\MatchInjury;
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
                            {--skip-predictions : Only fetch injuries}
                            {--force : Re-fetch even data we already have}';

    // Injuries change slowly; re-fetch at most this often per fixture. This still
    // lets the two daily scheduled passes both run, but blocks rapid re-fetching.
    private const INJURY_TTL_HOURS = 6;

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

        $force   = (bool) $this->option('force');
        $skipped = 0;

        foreach ($matches as $match) {
            if ($api->quotaExhausted()) {
                $this->warn('API quota exhausted — stopping early.');
                break;
            }

            // Injuries change slowly — only re-fetch if we don't have a recent pull.
            if (! $this->option('skip-injuries')) {
                $freshInjuries = MatchInjury::query()
                    ->where('match_id', $match->id)
                    ->where('updated_at', '>=', now()->subHours(self::INJURY_TTL_HOURS))
                    ->exists();
                if ($force || ! $freshInjuries) {
                    $inj += $service->fetchInjuries($match);
                    if ($sleepUs > 0) usleep($sleepUs);
                } else {
                    $skipped++;
                }
            }

            // API-Football's own prediction never changes once published — fetch it once.
            if (! $this->option('skip-predictions') && ! $api->quotaExhausted()) {
                $havePrediction = ApiPrediction::query()->where('match_id', $match->id)->exists();
                if ($force || ! $havePrediction) {
                    if ($service->fetchApiPrediction($match)) $pred++;
                    if ($sleepUs > 0) usleep($sleepUs);
                } else {
                    $skipped++;
                }
            }
        }

        $this->info("Done. {$inj} injury rows, {$pred} API predictions upserted. Skipped {$skipped} already-current fetch(es).");

        return self::SUCCESS;
    }
}
