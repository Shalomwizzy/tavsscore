<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\OneSignalService;
use App\Services\RolloverService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CheckPredictionOutcomesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Silence external service calls for all tests in this class
        $this->mock(OneSignalService::class)->shouldIgnoreMissing();
        $this->mock(TelegramService::class)->shouldIgnoreMissing();
        $this->mock(RolloverService::class)->shouldIgnoreMissing();
    }

    private function finishedMatch(int $home, int $away, array $extra = []): FootballMatch
    {
        return FootballMatch::create(array_merge([
            'api_id'         => rand(10000, 99999),
            'home_team'      => 'Team A',
            'away_team'      => 'Team B',
            'league'         => 'Test League',
            'league_country' => 'Nigeria',
            'status'         => 'FT',
            'match_time'     => now()->subHour(),
            'home_score'     => $home,
            'away_score'     => $away,
        ], $extra));
    }

    private function prediction(int $matchId, string $outcome, array $extra = []): Prediction
    {
        return Prediction::create(array_merge([
            'match_id'          => $matchId,
            'home_win_prob'     => 50.0,
            'draw_prob'         => 25.0,
            'away_win_prob'     => 25.0,
            'predicted_outcome' => $outcome,
            'confidence'        => 70,
            'analysis'          => 'Test match analysis.',
        ], $extra));
    }

    // ── Outcome resolution ───────────────────────────────────────────

    public function test_correct_home_win_prediction_is_marked_won(): void
    {
        $match = $this->finishedMatch(2, 0);
        $pred  = $this->prediction($match->id, 'Home Win', ['is_daily_pick' => true]);

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertTrue($pred->fresh()->was_correct);
    }

    public function test_wrong_home_win_prediction_is_marked_lost(): void
    {
        $match = $this->finishedMatch(0, 2);
        $pred  = $this->prediction($match->id, 'Home Win', ['is_daily_pick' => true]);

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertFalse($pred->fresh()->was_correct);
    }

    public function test_draw_prediction_correct_on_draw(): void
    {
        $match = $this->finishedMatch(1, 1);
        $pred  = $this->prediction($match->id, 'Draw');

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertTrue($pred->fresh()->was_correct);
    }

    public function test_over_25_prediction_correct_on_three_goals(): void
    {
        $match = $this->finishedMatch(2, 1);
        $pred  = $this->prediction($match->id, 'Over 2.5 Goals');

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertTrue($pred->fresh()->was_correct);
    }

    public function test_over_25_prediction_wrong_on_two_goals(): void
    {
        $match = $this->finishedMatch(1, 1);
        $pred  = $this->prediction($match->id, 'Over 2.5 Goals');

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertFalse($pred->fresh()->was_correct);
    }

    public function test_btts_prediction_correct(): void
    {
        $match = $this->finishedMatch(1, 1);
        $pred  = $this->prediction($match->id, 'Both Teams Score');

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertTrue($pred->fresh()->was_correct);
    }

    // ── Already resolved — should not be reprocessed ─────────────────

    public function test_already_resolved_prediction_is_not_changed(): void
    {
        $match = $this->finishedMatch(2, 0);
        $pred  = $this->prediction($match->id, 'Away Win', ['was_correct' => false]);

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        // was_correct was explicitly set to false and shouldn't flip
        $this->assertFalse($pred->fresh()->was_correct);
    }

    // ── Cache prevents double-notification ───────────────────────────

    public function test_command_is_idempotent_with_cache_key_set(): void
    {
        $match = $this->finishedMatch(2, 0);
        $pred  = $this->prediction($match->id, 'Home Win', ['is_daily_pick' => true]);

        // Pre-seed the notification cache key as if the command already ran once
        Cache::put("outcome_notified_{$pred->id}", true, now()->addDay());

        // Running again must succeed and leave was_correct resolved correctly
        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertTrue($pred->fresh()->was_correct);
        // Cache key must still be present (not evicted or duplicated)
        $this->assertTrue(Cache::has("outcome_notified_{$pred->id}"));
    }

    // ── Draw picks outcome ────────────────────────────────────────────

    public function test_draw_pick_win_is_marked_correctly(): void
    {
        $match = $this->finishedMatch(0, 0);
        $pred  = $this->prediction($match->id, 'Draw', [
            'is_draw_pick' => true,
            'draw_rank'    => 1,
        ]);

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertTrue($pred->fresh()->was_correct);
    }

    public function test_gg_pick_win_is_marked_correctly(): void
    {
        $match = $this->finishedMatch(2, 1);
        $pred  = $this->prediction($match->id, 'Both Teams Score', [
            'is_gg_pick' => true,
            'gg_rank'    => 1,
        ]);

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertTrue($pred->fresh()->was_correct);
    }

    // ── Correct score notification uses DB flag ───────────────────────

    public function test_correct_score_notification_sets_db_flag(): void
    {
        $match = $this->finishedMatch(2, 1);
        $pred  = Prediction::create([
            'match_id'               => $match->id,
            'home_win_prob'          => 55.0,
            'draw_prob'              => 25.0,
            'away_win_prob'          => 20.0,
            'predicted_outcome'      => 'Home Win',
            'confidence'             => 72,
            'analysis'               => 'Test analysis.',
            'likely_scores'          => [['score' => '2-1', 'probability' => 18.5]],
            'correct_score_notified' => false,
            'is_correct_score_pick'  => true,
            'correct_score_rank'     => 1,
        ]);

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertTrue($pred->fresh()->correct_score_notified);
    }

    public function test_correct_score_not_re_notified_when_db_flag_set(): void
    {
        $match = $this->finishedMatch(2, 1);
        $pred  = Prediction::create([
            'match_id'               => $match->id,
            'home_win_prob'          => 55.0,
            'draw_prob'              => 25.0,
            'away_win_prob'          => 20.0,
            'predicted_outcome'      => 'Home Win',
            'confidence'             => 72,
            'analysis'               => 'Test analysis.',
            'likely_scores'          => [['score' => '2-1', 'probability' => 18.5]],
            'correct_score_notified' => true, // already sent
            'is_correct_score_pick'  => true,
            'correct_score_rank'     => 1,
        ]);

        // Pre-seed outcome cache key so outcome notifications are also skipped
        Cache::put("outcome_notified_{$pred->id}", true, now()->addDay());

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        // DB flag must remain true — command must not reset or re-fire it
        $this->assertTrue($pred->fresh()->correct_score_notified);
    }

    // ── Skips matches not yet finished ───────────────────────────────

    public function test_pending_match_is_not_resolved(): void
    {
        $match = FootballMatch::create([
            'api_id'         => 99001,
            'home_team'      => 'Team X',
            'away_team'      => 'Team Y',
            'league'         => 'Test League',
            'league_country' => 'Ghana',
            'status'         => '1H',
            'match_time'     => now()->subMinutes(30),
        ]);

        $pred = $this->prediction($match->id, 'Home Win');

        $this->artisan('predictions:check-outcomes')->assertSuccessful();

        $this->assertNull($pred->fresh()->was_correct);
    }
}
