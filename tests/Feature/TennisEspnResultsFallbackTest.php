<?php

namespace Tests\Feature;

use App\Models\TennisMatch;
use App\Models\TennisPrediction;
use App\Services\Tennis\TennisEspnResultsFallbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TennisEspnResultsFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_espn_settles_a_verified_pending_tennis_result_when_the_live_source_is_unavailable(): void
    {
        config(['services.espn.tennis_url' => 'https://tennis-espn.test/sports']);
        $match = TennisMatch::create([
            'source' => 'live_tennis', 'source_key' => 'unavailable-now', 'tour' => 'ATP',
            'match_date' => now('Africa/Lagos')->subDay()->toDateString(),
            'player_one' => 'Sinner J.', 'player_two' => 'Alcaraz C.', 'status' => 'scheduled',
        ]);
        $prediction = TennisPrediction::create([
            'tennis_match_id' => $match->id, 'player_one_win_prob' => 61, 'player_two_win_prob' => 39,
            'predicted_winner' => 'Sinner J.', 'confidence' => 61, 'features' => [],
        ]);

        Http::fake([
            'https://tennis-espn.test/sports/tennis/all/scoreboard*' => Http::response(['events' => [[
                'id' => 'espn-44',
                'status' => ['type' => ['completed' => true, 'state' => 'post']],
                'competitions' => [['competitors' => [
                    ['athlete' => ['displayName' => 'Jannik Sinner'], 'winner' => true, 'linescores' => [['displayValue' => '6'], ['displayValue' => '6']]],
                    ['athlete' => ['displayName' => 'Carlos Alcaraz'], 'winner' => false, 'linescores' => [['displayValue' => '4'], ['displayValue' => '3']]],
                ]]],
            ]]], 200),
        ]);

        $result = app(TennisEspnResultsFallbackService::class)->settlePending();

        $this->assertSame(1, $result['settled']);
        $this->assertSame('completed', $match->fresh()->status);
        $this->assertSame('Sinner J.', $match->fresh()->winner);
        $this->assertSame('6-4, 6-3', $match->fresh()->score);
        $this->assertSame('espn_tennis', $match->fresh()->stats['result_source']);
        $this->assertTrue($prediction->fresh()->was_correct);
    }

    public function test_tennis_results_page_shows_the_separate_record(): void
    {
        $this->get(route('tennis.results'))
            ->assertOk()
            ->assertSee('The tennis track record.');
    }
}
