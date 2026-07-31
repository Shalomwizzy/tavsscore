<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchMatchesByDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoreless_stale_live_matches_remain_recoverable(): void
    {
        $date = now('Africa/Lagos')->toDateString();
        $match = FootballMatch::create([
            'api_id' => 700001,
            'league_id' => 39,
            'league' => 'Premier League',
            'league_country' => 'England',
            'home_team' => 'Still Awaiting Score',
            'away_team' => 'Result Source',
            'status' => 'LIVE',
            'match_time' => now('Africa/Lagos')->subHours(4),
        ]);

        config(['services.football.key' => 'test-key', 'services.football.url' => 'https://football.test/v3']);
        Cache::forget('api_football_quota_exhausted');
        Http::fake([
            'https://football.test/v3/fixtures*' => Http::response(['response' => [[
                'fixture' => ['id' => 700002, 'date' => now()->toIso8601String(), 'status' => ['short' => 'NS', 'elapsed' => null]],
                'league' => ['id' => 39, 'name' => 'Premier League', 'country' => 'England'],
                'teams' => ['home' => ['name' => 'Other Home'], 'away' => ['name' => 'Other Away']],
                'goals' => ['home' => null, 'away' => null],
                'score' => ['halftime' => ['home' => null, 'away' => null]],
            ]]], 200),
        ]);

        $this->artisan('fetch:date', ['date' => $date])->assertSuccessful();

        $this->assertSame('LIVE', $match->fresh()->status);
        $this->assertNull($match->fresh()->home_score);
        $this->assertNull($match->fresh()->away_score);
    }
}
