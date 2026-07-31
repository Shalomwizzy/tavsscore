<?php

namespace Tests\Feature;

use App\Models\TennisMatch;
use App\Models\TennisPrediction;
use App\Services\Tennis\LiveTennisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveTennisSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tennis_live.key' => 'test-tennis-key',
            'services.tennis_live.url' => 'https://tennis.test/v1',
        ]);
    }

    public function test_live_fixture_sync_persists_the_scheduled_time(): void
    {
        Http::fake([
            'https://tennis.test/v1/matches*' => Http::response(['data' => [[
                'id' => 99,
                'is_doubles' => false,
                'tournament' => 'Test Open',
                'surface' => 'hard',
                'scheduled_time' => '2026-08-01T10:00:00Z',
                'format' => 'BO3',
                'players' => ['p1' => ['name' => 'Player One'], 'p2' => ['name' => 'Player Two']],
            ]]], 200),
        ]);

        app(LiveTennisService::class)->syncFixtures();

        $this->assertNotNull(TennisMatch::where('source_key', '99')->firstOrFail()->scheduled_at);
    }

    public function test_it_settles_an_older_pending_tennis_prediction_with_a_saved_live_id(): void
    {
        $match = TennisMatch::create([
            'source' => 'live_tennis',
            'source_key' => '123',
            'tour' => 'ATP',
            'tournament' => 'Test Open',
            'surface' => 'Hard',
            'match_date' => now('Africa/Lagos')->subDays(2)->toDateString(),
            // Intentionally null: old rows created before the fillable fix must
            // still be recoverable by their date and stored source ID.
            'scheduled_at' => null,
            'player_one' => 'Player One',
            'player_two' => 'Player Two',
            'status' => 'scheduled',
        ]);
        $prediction = TennisPrediction::create([
            'tennis_match_id' => $match->id,
            'player_one_win_prob' => 72,
            'player_two_win_prob' => 28,
            'predicted_winner' => 'Player One',
            'confidence' => 72,
            'features' => [],
        ]);

        Http::fake([
            'https://tennis.test/v1/matches/123' => Http::response([
                'id' => 123,
                'status' => 'completed',
                'winner' => 1,
                'score' => ['games' => [[6, 6], [4, 3]]],
            ], 200),
        ]);

        $result = app(LiveTennisService::class)->settleTracked();

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['settled']);
        $this->assertSame('completed', $match->fresh()->status);
        $this->assertSame('Player One', $match->fresh()->winner);
        $this->assertTrue($prediction->fresh()->was_correct);
    }

    public function test_it_uses_imported_tennis_history_when_an_old_live_match_is_no_longer_available(): void
    {
        $date = now('Africa/Lagos')->subDays(4)->toDateString();
        $match = TennisMatch::create([
            'source' => 'live_tennis', 'source_key' => '404', 'tour' => 'WTA',
            'match_date' => $date, 'player_one' => 'Player One', 'player_two' => 'Player Two', 'status' => 'scheduled',
        ]);
        $prediction = TennisPrediction::create([
            'tennis_match_id' => $match->id, 'player_one_win_prob' => 45, 'player_two_win_prob' => 55,
            'predicted_winner' => 'Player Two', 'confidence' => 55, 'features' => [],
        ]);
        TennisMatch::create([
            'source' => 'tennisdata', 'source_key' => 'history-404', 'tour' => 'WTA',
            'match_date' => $date, 'player_one' => 'Player Two', 'player_two' => 'Player One',
            'winner' => 'Player Two', 'score' => '6-4, 6-3', 'status' => 'completed',
        ]);

        Http::fake(['https://tennis.test/v1/matches/404' => Http::response(['error' => 'not_found'], 404)]);

        $result = app(LiveTennisService::class)->settleTracked();

        $this->assertSame(1, $result['settled']);
        $this->assertSame('completed', $match->fresh()->status);
        $this->assertSame('Player Two', $match->fresh()->winner);
        $this->assertSame('tennisdata', $match->fresh()->stats['result_source']);
        $this->assertTrue($prediction->fresh()->was_correct);
    }
}
