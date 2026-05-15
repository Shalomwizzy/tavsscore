<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\FootballService;
use App\Services\OneSignalService;
use App\Support\LeagueCoverage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchMatches extends Command
{
    protected $signature = 'fetch:matches';

    protected $description = 'Fetch live, today and finished football matches from API-Football.';

    public function handle(FootballService $footballService, OneSignalService $oneSignal): int
    {
        try {
            $written = 0;

            // ── Pass 1: Today's fixtures ──
            $todayMatches = collect($footballService->fetchTodayFixtures())
                ->filter(fn (array $m): bool => LeagueCoverage::shouldIngest($m));

            foreach ($todayMatches as $match) {
                FootballMatch::query()->updateOrCreate(
                    ['api_id' => $match['api_id']],
                    $this->matchData($match)
                );
                $written++;
            }

            // ── Pass 2: Live matches + goal detection ──
            $liveMatches = collect($footballService->fetchLiveMatches())
                ->filter(fn (array $m): bool => LeagueCoverage::shouldIngest($m));

            foreach ($liveMatches as $match) {
                $existing = FootballMatch::where('api_id', $match['api_id'])->first();

                $this->detectAndNotifyGoal($existing, $match, $oneSignal);
                $this->detectAndNotifyFullTime($existing, $match, $oneSignal);

                FootballMatch::query()->updateOrCreate(
                    ['api_id' => $match['api_id']],
                    $this->matchData($match)
                );
            }

            // ── Pass 3: Auto-expire stale live statuses ──
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

    private function detectAndNotifyFullTime(?FootballMatch $existing, array $newData, OneSignalService $oneSignal): void
    {
        if (! $existing) {
            return;
        }

        $liveStatuses = ['1H', '2H', 'ET', 'BT', 'P', 'LIVE', 'HT'];
        $wasLive      = in_array($existing->status, $liveStatuses, true);
        $isNowFT      = in_array($newData['status'], ['FT', 'AET', 'PEN'], true);

        if (! $wasLive || ! $isNowFT) {
            return;
        }

        $cacheKey = "ft_notified_{$existing->api_id}";

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addHours(6));

        $home = $newData['home_score'] ?? $existing->home_score;
        $away = $newData['away_score'] ?? $existing->away_score;

        $result = match(true) {
            $home > $away => "{$existing->home_team} Win",
            $away > $home => "{$existing->away_team} Win",
            default       => 'Draw',
        };

        $oneSignal->sendMatchAlert(
            title:   "🏁 FT: {$existing->home_team} {$home} - {$away} {$existing->away_team}",
            message: "{$result} · {$existing->league} - Tap for more results",
        );

        $this->info("FT alert sent: {$existing->home_team} {$home} - {$away} {$existing->away_team}");
    }

    private function detectAndNotifyGoal(?FootballMatch $existing, array $newData, OneSignalService $oneSignal): void
    {
        if (! $existing) {
            return;
        }

        $oldTotal = (int) $existing->home_score + (int) $existing->away_score;
        $newTotal = (int) $newData['home_score'] + (int) $newData['away_score'];

        if ($newTotal <= $oldTotal) {
            return;
        }

        // Deduplicate - only notify once per exact scoreline per match
        $cacheKey = "goal_notified_{$existing->api_id}_{$newData['home_score']}_{$newData['away_score']}";

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addMinutes(30));

        $oneSignal->sendGoalAlert(
            homeTeam:  $existing->home_team,
            awayTeam:  $existing->away_team,
            homeScore: (int) $newData['home_score'],
            awayScore: (int) $newData['away_score'],
            league:    $existing->league,
            elapsed:   $newData['elapsed'] ?? null,
        );

        $this->info("Goal alert sent: {$existing->home_team} {$newData['home_score']} - {$newData['away_score']} {$existing->away_team}");
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
