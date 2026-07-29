<?php

namespace App\Services;

use App\Models\FootballMatch;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FootballService
{
    private const LIVE_CACHE_KEY = 'football_api.live_matches';
    private const TODAY_CACHE_KEY = 'football_api.today_fixtures';

    /**
     * API-Football status codes that represent an active match.
     *
     * @var array<int, string>
     */
    private const LIVE_STATUSES = ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT', 'LIVE'];
    private const UPCOMING_STATUSES = ['NS', 'TBD'];
    private const FINISHED_STATUSES = ['FT', 'AET', 'PEN'];

    public function fetchLiveMatches(): array
    {
        return Cache::remember(self::LIVE_CACHE_KEY, now()->addSeconds(60), function (): array {
            return $this->fetchFixtures(['live' => 'all']);
        });
    }

    public function fetchTodayFixtures(): array
    {
        $date = CarbonImmutable::now(config('app.timezone'))->toDateString();

        return Cache::remember(self::TODAY_CACHE_KEY.'.'.$date, now()->addMinutes(3), function () use ($date): array {
            return $this->fetchFixtures(['date' => $date]);
        });
    }

    public function fetchFixturesByDate(string $date): array
    {
        Cache::forget(self::TODAY_CACHE_KEY.'.'.$date);
        return $this->fetchFixtures(['date' => $date]);
    }

    /**
     * Fetch every fixture in a league × season. API-Football encodes seasons
     * by the starting calendar year — season=2025 means the 2025-26 season.
     * Used by `matches:backfill` to build the historical training set
     * Dixon-Coles needs. One request returns the full ~380-match season.
     */
    public function fetchFixturesByLeagueSeason(int $leagueId, int $season): array
    {
        return $this->fetchFixtures(['league' => $leagueId, 'season' => $season]);
    }

    public function liveMatchesFromDatabase(): Collection
    {
        return FootballMatch::query()
            ->whereIn('status', self::LIVE_STATUSES)
            ->orderBy('match_time')
            ->get()
            ->map(fn (FootballMatch $match): array => $this->formatMatch($match));
    }

    public function todayMatchesFromDatabase(): Collection
    {
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();

        return FootballMatch::query()
            ->whereDate('match_time', $today)
            ->whereIn('status', self::UPCOMING_STATUSES)
            ->orderBy('match_time')
            ->get()
            ->map(fn (FootballMatch $match): array => $this->formatMatch($match));
    }

    public function finishedMatchesFromDatabase(): Collection
    {
        // Show finished matches from yesterday and today by date.
        // This avoids edge cases at the day boundary where matches from yesterday
        // morning would be filtered out by a simple 24-hour window.
        $tz = config('app.timezone');
        $today = CarbonImmutable::now($tz)->toDateString();
        $yesterday = CarbonImmutable::now($tz)->subDay()->toDateString();

        return FootballMatch::query()
            ->whereIn('status', self::FINISHED_STATUSES)
            ->where(function ($query) use ($today, $yesterday) {
                $query->whereDate('match_time', $today)
                      ->orWhereDate('match_time', $yesterday);
            })
            ->orderByDesc('match_time')
            ->get()
            ->map(fn (FootballMatch $match): array => $this->formatMatch($match));
    }

    private function fetchFixtures(array $query): array
    {
        // Short-circuit if we already know we're rate-limited today.
        // Cache flag is set when we receive the "request limit reached" error.
        if (Cache::get('api_football_quota_exhausted')) {
            return [];
        }

        $apiKey = config('services.football.key');
        $baseUrl = rtrim((string) config('services.football.url'), '/');

        if (blank($apiKey)) {
            throw new RuntimeException('API-Football key is not configured.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders(['x-apisports-key' => $apiKey])
                ->acceptJson()
                ->timeout(15)
                ->retry(2, 250)
                ->get('/fixtures', $query);
        } catch (ConnectionException $exception) {
            Log::error('API-Football connection failed.', [
                'query' => $query,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Unable to connect to API-Football.', previous: $exception);
        }

        if ($response->failed()) {
            Log::error('API-Football request failed.', [
                'status' => $response->status(),
                'query' => $query,
                'body' => $response->body(),
            ]);

            throw new RuntimeException('API-Football returned an unsuccessful response.');
        }

        $payload = $response->json();

        if (! empty($payload['errors'])) {
            $errors = $payload['errors'];

            // Daily-quota errors are common on the free tier — return empty
            // rather than throwing. Lets the caller carry on with cached/DB data.
            $isQuotaError = is_array($errors) && (
                isset($errors['requests']) ||
                isset($errors['rateLimit']) ||
                (is_string($errors[0] ?? null) && stripos($errors[0], 'limit') !== false)
            );

            if ($isQuotaError) {
                Log::info('API-Football rate-limited — backing off.', ['errors' => $errors]);
                // Set a back-off flag so callers stop hammering the API for the day
                \Illuminate\Support\Facades\Cache::put('api_football_quota_exhausted', true, now()->addHours(2));
                return [];
            }

            Log::warning('API-Football returned validation or account errors.', [
                'query' => $query,
                'errors' => $errors,
            ]);

            throw new RuntimeException('API-Football returned errors for the request.');
        }

        return collect($payload['response'] ?? [])
            ->map(fn (array $fixture): ?array => $this->normalizeFixture($fixture))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeFixture(array $fixture): ?array
    {
        $fixtureData = $fixture['fixture'] ?? [];
        $leagueData = $fixture['league'] ?? [];
        $teamsData = $fixture['teams'] ?? [];
        $goalsData = $fixture['goals'] ?? [];
        $scoreData = $fixture['score'] ?? [];

        if (
            empty($fixtureData['id'])
            || empty($fixtureData['date'])
            || empty($teamsData['home']['name'])
            || empty($teamsData['away']['name'])
        ) {
            Log::warning('Skipped malformed API-Football fixture.', ['fixture' => $fixture]);

            return null;
        }

        return [
            'api_id' => (int) $fixtureData['id'],
            'league_id' => $this->nullableInteger($leagueData['id'] ?? null),
            'league' => (string) ($leagueData['name'] ?? 'Unknown League'),
            'league_country' => (string) ($leagueData['country'] ?? 'Unknown'),
            'home_team' => (string) $teamsData['home']['name'],
            'home_team_logo' => $teamsData['home']['logo'] ?? null,
            'away_team' => (string) $teamsData['away']['name'],
            'away_team_logo' => $teamsData['away']['logo'] ?? null,
            'home_score' => $this->nullableInteger($goalsData['home'] ?? null),
            'away_score' => $this->nullableInteger($goalsData['away'] ?? null),
            'home_score_ht' => $this->nullableInteger($scoreData['halftime']['home'] ?? null),
            'away_score_ht' => $this->nullableInteger($scoreData['halftime']['away'] ?? null),
            'status' => (string) ($fixtureData['status']['short'] ?? 'NS'),
            'elapsed' => $this->nullableInteger($fixtureData['status']['elapsed'] ?? null),
            'match_time' => CarbonImmutable::parse($fixtureData['date'])->setTimezone(config('app.timezone')),
        ];
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function formatMatch(FootballMatch $match): array
    {
        return [
            'id' => $match->id,
            'api_id' => $match->api_id,
            'league_id' => $match->league_id,
            'league' => $match->league,
            'league_country' => $match->league_country,
            'home_team' => $match->home_team,
            'home_team_logo' => $match->home_team_logo,
            'away_team' => $match->away_team,
            'away_team_logo' => $match->away_team_logo,
            'home_score' => $match->home_score,
            'away_score' => $match->away_score,
            'home_score_ht' => $match->home_score_ht,
            'away_score_ht' => $match->away_score_ht,
            'status' => $match->status,
            'elapsed' => $match->elapsed,
            'display_status' => $this->displayStatus($match),
            'match_time' => $match->match_time?->toIso8601String(),
        ];
    }

    private function displayStatus(FootballMatch $match): string
    {
        if (in_array($match->status, ['1H', '2H', 'ET', 'BT', 'P', 'LIVE'], true) && $match->elapsed !== null) {
            return $match->elapsed."'";
        }

        return $match->status;
    }
}
