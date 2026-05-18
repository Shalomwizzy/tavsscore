<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\FootballService;
use App\Support\LeagueCoverage;
use Illuminate\Console\Command;

class FetchMatchesByDate extends Command
{
    protected $signature = 'fetch:date {date? : Date in YYYY-MM-DD format (default: yesterday Lagos)}';

    protected $description = 'Re-fetch and update all fixtures for a specific date. Use to recover missed results when API was exhausted.';

    public function handle(FootballService $footballService): int
    {
        $date = $this->argument('date')
            ?? now('Africa/Lagos')->subDay()->toDateString();

        $this->info("Fetching fixtures for {$date}…");

        $fixtures = collect($footballService->fetchFixturesByDate($date))
            ->filter(fn (array $m): bool => LeagueCoverage::shouldIngest($m));

        if ($fixtures->isEmpty()) {
            $this->warn("No fixtures returned for {$date}. API may still be exhausted or date has no matches.");
            return self::FAILURE;
        }

        $updated = 0;
        foreach ($fixtures as $match) {
            FootballMatch::query()->updateOrCreate(
                ['api_id' => $match['api_id']],
                [
                    'league'         => $match['league'],
                    'league_id'      => $match['league_id'],
                    'league_country' => $match['league_country'],
                    'home_team'      => $match['home_team'],
                    'away_team'      => $match['away_team'],
                    'home_score'     => $match['home_score'],
                    'away_score'     => $match['away_score'],
                    'home_score_ht'  => $match['home_score_ht'] ?? null,
                    'away_score_ht'  => $match['away_score_ht'] ?? null,
                    'status'         => $match['status'],
                    'elapsed'        => $match['elapsed'],
                    'match_time'     => $match['match_time'],
                ]
            );
            $updated++;
        }

        // Force stale live matches for this date to FT so outcome checker resolves them
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
