<?php

namespace Tests\Feature;

use App\Models\DcLeagueParams;
use App\Models\DcTeamParams;
use App\Models\FootballMatch;
use App\Models\ShalomPrediction;
use App\Services\DixonColes\TeamNameNormalizer;
use App\Services\ShalomAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShalomAIWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function seedModel(int $leagueId): void
    {
        DcLeagueParams::create(['league_id' => $leagueId, 'model_version' => ShalomAIService::VERSION, 'gamma' => .20, 'rho' => -.05, 'half_life_days' => 240, 'fit_at' => now(), 'training_start' => now()->subYear(), 'training_end' => now(), 'training_matches' => 120, 'final_log_likelihood' => -1.2, 'iterations' => 100, 'converged' => true]);
        foreach (['Home FC', 'Away FC'] as $team) DcTeamParams::create(['league_id' => $leagueId, 'model_version' => ShalomAIService::VERSION, 'team_name' => TeamNameNormalizer::key($team), 'attack' => 0, 'defense' => 0, 'matches_used' => 20, 'is_shrunk' => false]);
    }

    public function test_shalom_ai_creates_and_settles_an_isolated_shadow_prediction(): void
    {
        $this->seedModel(999);
        $match = FootballMatch::create(['api_id' => 998899, 'league_id' => 999, 'league' => 'Shalom Test League', 'league_country' => 'Nigeria', 'home_team' => 'Home FC', 'away_team' => 'Away FC', 'status' => 'NS', 'match_time' => now()->addHour(), 'held_for_review' => false]);

        app(ShalomAIService::class)->predictUpcoming();
        $prediction = ShalomPrediction::where('match_id', $match->id)->firstOrFail();
        $this->assertTrue($prediction->is_shadow);
        $this->assertSame(ShalomAIService::VERSION, $prediction->model_version);

        $match->update(['status' => 'FT', 'home_score' => 1, 'away_score' => 0]);
        app(ShalomAIService::class)->settle();

        $this->assertNotNull($prediction->fresh()->settled_at);
        $this->assertNotNull($prediction->fresh()->was_correct);
    }

    public function test_shalom_editorial_draft_is_private_and_data_grounded(): void
    {
        $this->seedModel(998);
        FootballMatch::create(['api_id' => 998898, 'league_id' => 998, 'league' => 'Shalom Test League', 'league_country' => 'Nigeria', 'home_team' => 'Home FC', 'away_team' => 'Away FC', 'status' => 'NS', 'match_time' => now()->addHour(), 'held_for_review' => false]);
        $ai = app(ShalomAIService::class);
        $ai->predictUpcoming();
        $draft = $ai->makeBlogDraft();

        $this->assertNotNull($draft);
        $this->assertSame('draft', $draft->status);
        $this->assertStringContainsString('admin-only', $draft->content);
    }
}
