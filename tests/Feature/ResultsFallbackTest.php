<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use App\Services\Football\ResultsFallbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResultsFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.football_data.key', 'test-key');
        Config::set('services.football_data.url', 'https://api.football-data.org/v4');
    }

    private function pendingMatch(string $home, string $away): FootballMatch
    {
        return FootballMatch::create([
            'api_id'         => rand(10000, 99999),
            'home_team'      => $home,
            'away_team'      => $away,
            'league'         => 'Premier League',
            'league_country' => 'England',
            'league_id'      => 39,
            'status'         => 'NS',
            'match_time'     => now()->subHours(3),
        ]);
    }

    private function fakeResults(array $matches): void
    {
        Http::fake([
            'api.football-data.org/*' => Http::response(['matches' => $matches], 200),
        ]);
    }

    public function test_fills_result_matching_by_name(): void
    {
        $match = $this->pendingMatch('Manchester United', 'Arsenal');

        $this->fakeResults([[
            'utcDate'   => now()->toIso8601String(),
            'status'    => 'FINISHED',
            'homeTeam'  => ['name' => 'Manchester United FC', 'shortName' => 'Man United', 'tla' => 'MUN'],
            'awayTeam'  => ['name' => 'Arsenal FC', 'shortName' => 'Arsenal', 'tla' => 'ARS'],
            'score'     => ['fullTime' => ['home' => 2, 'away' => 1], 'halfTime' => ['home' => 1, 'away' => 0]],
        ]]);

        $result = app(ResultsFallbackService::class)->settlePending(2);

        $this->assertSame(1, $result['updated']);
        $match->refresh();
        $this->assertSame('FT', $match->status);
        $this->assertSame(2, (int) $match->home_score);
        $this->assertSame(1, (int) $match->away_score);
        $this->assertSame(1, (int) $match->home_score_ht);
    }

    public function test_does_not_touch_matches_with_no_result(): void
    {
        $match = $this->pendingMatch('Some Team', 'Other Team');
        $this->fakeResults([]); // football-data returned nothing for these teams

        $result = app(ResultsFallbackService::class)->settlePending(2);

        $this->assertSame(0, $result['updated']);
        $this->assertSame('NS', $match->fresh()->status);
    }

    public function test_no_op_when_key_missing(): void
    {
        Config::set('services.football_data.key', null);
        $this->pendingMatch('A', 'B');

        $result = app(ResultsFallbackService::class)->settlePending(2);

        $this->assertFalse($result['configured']);
    }

    public function test_predicted_match_is_settled_even_outside_covered_leagues(): void
    {
        // A match in an unusual league (not in the covered set) but that we
        // predicted must still be checked + settled — predicted matches are the
        // priority.
        $match = FootballMatch::create([
            'api_id'     => rand(10000, 99999),
            'home_team'  => 'Real Madrid',
            'away_team'  => 'Barcelona',
            'league'     => 'Friendly',
            'league_id'  => 999999, // deliberately not a covered league
            'status'     => 'NS',
            'match_time' => now()->subHours(3),
        ]);
        \App\Models\Prediction::create([
            'match_id'          => $match->id,
            'home_win_prob'     => 50.0,
            'draw_prob'         => 25.0,
            'away_win_prob'     => 25.0,
            'predicted_outcome' => 'Home Win',
            'confidence'        => 70,
            'analysis'          => 'Test analysis.',
        ]);

        $this->fakeResults([[
            'utcDate'  => now()->toIso8601String(),
            'status'   => 'FINISHED',
            'homeTeam' => ['name' => 'Real Madrid CF'],
            'awayTeam' => ['name' => 'FC Barcelona'],
            'score'    => ['fullTime' => ['home' => 3, 'away' => 1], 'halfTime' => ['home' => 2, 'away' => 0]],
        ]]);

        $result = app(ResultsFallbackService::class)->settlePending(2);

        $this->assertSame(1, $result['predicted']);
        $this->assertSame(1, $result['predicted_updated']);
        $this->assertSame('FT', $match->fresh()->status);
        $this->assertSame(3, (int) $match->fresh()->home_score);
    }

    public function test_ignores_already_finished_matches(): void
    {
        $match = $this->pendingMatch('Chelsea', 'Everton');
        $match->update(['status' => 'FT', 'home_score' => 0, 'away_score' => 0]);

        $this->fakeResults([[
            'utcDate'  => now()->toIso8601String(),
            'status'   => 'FINISHED',
            'homeTeam' => ['name' => 'Chelsea FC'],
            'awayTeam' => ['name' => 'Everton FC'],
            'score'    => ['fullTime' => ['home' => 3, 'away' => 2], 'halfTime' => ['home' => 1, 'away' => 1]],
        ]]);

        $result = app(ResultsFallbackService::class)->settlePending(2);

        // Already-final match is not in the pending set, so nothing is updated.
        $this->assertSame(0, $result['pending']);
        $this->assertSame(0, (int) $match->fresh()->home_score);
    }
}
