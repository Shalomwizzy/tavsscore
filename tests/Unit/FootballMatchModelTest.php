<?php

namespace Tests\Unit;

use App\Models\FootballMatch;
use App\Models\Prediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FootballMatchModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeMatch(array $overrides = []): FootballMatch
    {
        return FootballMatch::create(array_merge([
            'api_id'         => rand(1, 99999),
            'league'         => 'Premier League',
            'league_country' => 'England',
            'home_team'      => 'Arsenal',
            'away_team'      => 'Chelsea',
            'status'         => 'NS',
            'match_time'     => now()->addHours(3),
        ], $overrides));
    }

    // ── Slug ─────────────────────────────────────────────────────

    public function test_slug_is_kebab_case_with_api_id(): void
    {
        $match = $this->makeMatch(['api_id' => 42, 'home_team' => 'Man United', 'away_team' => 'Liverpool']);
        $this->assertEquals('man-united-vs-liverpool-42', $match->slug);
    }

    public function test_slug_handles_special_characters(): void
    {
        $match = $this->makeMatch(['api_id' => 1, 'home_team' => 'Atlético Madrid', 'away_team' => 'Real Sociedad']);
        $this->assertStringStartsWith('atl', $match->slug);
        $this->assertStringEndsWith('-1', $match->slug);
    }

    // ── Relationships ────────────────────────────────────────────

    public function test_match_has_prediction_relationship(): void
    {
        $match = $this->makeMatch(['api_id' => 99]);

        Prediction::create([
            'match_id'          => $match->id,
            'home_win_prob'     => 55.00,
            'draw_prob'         => 25.00,
            'away_win_prob'     => 20.00,
            'predicted_outcome' => 'Home Win',
            'confidence'        => 75,
            'analysis'          => 'Test analysis.',
        ]);

        $this->assertNotNull($match->prediction);
        $this->assertEquals('Home Win', $match->prediction->predicted_outcome);
    }

    public function test_match_with_no_prediction_returns_null(): void
    {
        $match = $this->makeMatch(['api_id' => 88]);
        $this->assertNull($match->prediction);
    }

    // ── Casts ────────────────────────────────────────────────────

    public function test_match_time_is_cast_to_datetime(): void
    {
        $match = $this->makeMatch();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $match->match_time);
    }

    public function test_scores_cast_to_integer(): void
    {
        $match = $this->makeMatch([
            'status'     => 'FT',
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $this->assertIsInt($match->home_score);
        $this->assertIsInt($match->away_score);
    }
}
