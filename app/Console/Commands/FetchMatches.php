<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\FootballService;
use App\Support\LeagueCoverage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchMatches extends Command
{
    protected $signature = 'fetch:matches';

    protected $description = 'Fetch live, today and finished football matches from API-Football.';

    public function handle(FootballService $footballService): int
    {
        try {
            // ── Pass 1: Today's fixtures (all statuses: NS, 1H, HT, FT, etc.) ──
            // This is the source of truth for finished matches.
            $written = 0;

            $todayMatches = collect($footballService->fetchTodayFixtures())
                ->filter(fn (array $m): bool => LeagueCoverage::shouldIngest($m));

            foreach ($todayMatches as $match) {
                FootballMatch::query()->updateOrCreate(
                    ['api_id' => $match['api_id']],
                    $this->matchData($match)
                );
                $written++;
            }

            // ── Pass 2: Live matches (most accurate for in-progress status + elapsed) ──
            // Overwrites the status written in pass 1 for currently live matches.
            $liveMatches = collect($footballService->fetchLiveMatches())
                ->filter(fn (array $m): bool => LeagueCoverage::shouldIngest($m));

            foreach ($liveMatches as $match) {
                FootballMatch::query()->updateOrCreate(
                    ['api_id' => $match['api_id']],
                    $this->matchData($match)
                );
            }

            // ── Pass 3: Auto-expire stale live statuses ──
            // If a match has been "live" for more than 3 hours something went wrong.
            // Mark as FT so it doesn't sit in the live tab forever.
            $staleCount = FootballMatch::query()
                ->whereIn('status', ['1H', '2H', 'ET', 'BT', 'P', 'LIVE', 'HT'])
                ->where('match_time', '<=', now()->subHours(3))
                ->update(['status' => 'FT']);

            if ($staleCount > 0) {
                Log::info("Auto-expired {$staleCount} stale live matches to FT.");
            }

            $this->info("Updated {$written} today's fixtures + {$liveMatches->count()} live. Stale-expired: {$staleCount}.");

            return self::SUCCESS;

        } catch (Throwable $exception) {
            Log::error('Failed to fetch matches.', [
                'message'   => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $this->error('Failed to fetch matches: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function matchData(array $match): array
    {
        return [
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
        ];
    }
}
