<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\FixtureIntegrityService;
use App\Services\FootballService;
use App\Services\TeamCanonicalizer;
use App\Support\LeagueCoverage;
use Illuminate\Console\Command;

class FetchMatchesByDate extends Command
{
    protected $signature = 'fetch:date {date? : Date in YYYY-MM-DD format (default: yesterday Lagos)}';

    protected $description = 'Re-fetch and update all fixtures for a specific date. Use to recover missed results when API was exhausted.';

    public function handle(
        FootballService $footballService,
        TeamCanonicalizer $canon,
        FixtureIntegrityService $integrity,
    ): int {
        $date = $this->argument('date')
            ?? now('Africa/Lagos')->subDay()->toDateString();

        $this->info("Fetching fixtures for {$date}…");

        $allFixtures = collect($footballService->fetchFixturesByDate($date));

        if ($allFixtures->isEmpty()) {
            $this->warn("No fixtures returned for {$date}. API may still be exhausted or date has no matches.");
            return self::FAILURE;
        }

        // Pre-load all api_ids already in our DB so we know which matches we track
        $trackedApiIds = FootballMatch::whereIn('api_id', $allFixtures->pluck('api_id'))
            ->pluck('api_id')
            ->flip();

        $updated = 0;
        foreach ($allFixtures as $match) {
            $alreadyTracked = isset($trackedApiIds[$match['api_id']]);

            // Always update matches already in our DB (even non-covered leagues) so
            // final scores reach picks that were created before a league was de-listed.
            // Only insert NEW rows for matches passing the coverage filter.
            if ($alreadyTracked || LeagueCoverage::shouldIngest($match)) {
                $canon->resolve($match['home_team']);
                $canon->resolve($match['away_team']);

                $upserted = FootballMatch::query()->updateOrCreate(
                    ['api_id' => $match['api_id']],
                    [
                        'league'         => $match['league'],
                        'league_id'      => $match['league_id'],
                        'league_country' => $match['league_country'],
                        'home_team'      => $match['home_team'],
                        'home_team_logo' => $match['home_team_logo'] ?? null,
                        'away_team'      => $match['away_team'],
                        'away_team_logo' => $match['away_team_logo'] ?? null,
                        'home_score'     => $match['home_score'],
                        'away_score'     => $match['away_score'],
                        'home_score_ht'  => $match['home_score_ht'] ?? null,
                        'away_score_ht'  => $match['away_score_ht'] ?? null,
                        'status'         => $match['status'],
                        'elapsed'        => $match['elapsed'],
                        'match_time'     => $match['match_time'],
                    ]
                );
                $integrity->evaluate($upserted);
                $updated++;
            }
        }

        // Force any matches for this date still stuck in a live status to FT
        $forced = FootballMatch::query()
            ->whereIn('status', ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE'])
            ->whereDate('match_time', $date)
            ->where('match_time', '<=', now()->subHours(3))
            ->update(['status' => 'FT']);

        $this->info("Updated {$updated} fixtures. Force-expired {$forced} stale live matches to FT.");
        $this->info("Run 'php artisan predictions:check-outcomes' next to resolve pending picks.");

        return self::SUCCESS;
    }
}
