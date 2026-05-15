<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use App\Models\Prediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    // ── /api/matches/live ─────────────────────────────────────────

    public function test_live_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/matches/live');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_live_endpoint_returns_only_live_matches(): void
    {
        FootballMatch::create([
            'api_id' => 10, 'league' => 'PL', 'league_country' => 'England',
            'home_team' => 'A', 'away_team' => 'B',
            'status' => '1H', 'home_score' => 1, 'away_score' => 0,
            'match_time' => now(),
        ]);
        FootballMatch::create([
            'api_id' => 11, 'league' => 'PL', 'league_country' => 'England',
            'home_team' => 'C', 'away_team' => 'D',
            'status' => 'FT', 'home_score' => 2, 'away_score' => 1,
            'match_time' => now()->subHours(2),
        ]);

        $response = $this->getJson('/api/matches/live');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $match) {
            $this->assertContains($match['status'], ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE']);
        }
    }

    // ── /api/matches/today ────────────────────────────────────────

    public function test_today_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/matches/today');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_today_endpoint_includes_todays_match(): void
    {
        FootballMatch::create([
            'api_id' => 20, 'league' => 'La Liga', 'league_country' => 'Spain',
            'home_team' => 'Real Madrid', 'away_team' => 'Atletico',
            'status' => 'NS',
            'match_time' => now('Africa/Lagos')->addHours(2),
        ]);

        $response = $this->getJson('/api/matches/today');
        $response->assertStatus(200);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    // ── /api/matches/finished ────────────────────────────────────

    public function test_finished_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/matches/finished');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_finished_endpoint_returns_only_finished_matches(): void
    {
        FootballMatch::create([
            'api_id' => 30, 'league' => 'Serie A', 'league_country' => 'Italy',
            'home_team' => 'Juventus', 'away_team' => 'Inter',
            'status' => 'FT', 'home_score' => 2, 'away_score' => 2,
            'match_time' => now()->subHours(3),
        ]);

        $response = $this->getJson('/api/matches/finished');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $match) {
            $this->assertContains($match['status'], ['FT', 'AET', 'PEN']);
        }
    }

    // ── /api/predictions ─────────────────────────────────────────

    public function test_predictions_api_returns_json(): void
    {
        $response = $this->getJson('/api/predictions');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_predictions_api_returns_todays_predictions(): void
    {
        // league_id 39 = Premier League, which is in LeagueCoverage::scopeCovered()
        $match = FootballMatch::create([
            'api_id' => 40, 'league' => 'Premier League', 'league_country' => 'England',
            'league_id' => 39,
            'home_team' => 'Bayern', 'away_team' => 'Leipzig',
            'status' => 'NS',
            'match_time' => now()->addHours(2),
        ]);

        Prediction::create([
            'match_id'          => $match->id,
            'home_win_prob'     => 60.00,
            'draw_prob'         => 20.00,
            'away_win_prob'     => 20.00,
            'predicted_outcome' => 'Home Win',
            'confidence'        => 80,
            'analysis'          => 'Strong home form.',
        ]);

        $response = $this->getJson('/api/predictions');
        $response->assertStatus(200);

        $this->assertGreaterThan(0, count($response->json('data')));
    }
}
