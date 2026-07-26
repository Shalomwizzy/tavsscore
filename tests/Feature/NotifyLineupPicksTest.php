<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifyLineupPicksTest extends TestCase
{
    use RefreshDatabase;

    private OneSignalService $oneSignal;
    private TelegramService $telegram;

    protected function setUp(): void
    {
        parent::setUp();
        // Ignore-missing so incidental push calls (e.g. notifyLineupUpdated) are
        // no-ops; these tests assert on Telegram + explicit expectations only.
        $this->oneSignal = $this->mock(OneSignalService::class)->shouldIgnoreMissing();
        $this->telegram  = $this->mock(TelegramService::class);
    }

    private function todayMatch(array $extra = []): FootballMatch
    {
        return FootballMatch::create(array_merge([
            'api_id'         => rand(10000, 99999),
            'home_team'      => 'Arsenal',
            'away_team'      => 'Chelsea',
            'league'         => 'Premier League',
            'league_country' => 'England',
            'status'         => 'NS',
            'match_time'     => now('Africa/Lagos')->setTime(15, 0),
        ], $extra));
    }

    private function predictionWithLineup(int $matchId, array $extra = []): Prediction
    {
        return Prediction::create(array_merge([
            'match_id'          => $matchId,
            'home_win_prob'     => 55.0,
            'draw_prob'         => 25.0,
            'away_win_prob'     => 20.0,
            'predicted_outcome' => 'Home Win',
            'confidence'        => 78,
            'analysis'          => 'Lineup analysis.',
            'has_lineup'        => true,
        ], $extra));
    }

    // ── Command fires OneSignal + Telegram when lineup pick exists ────

    public function test_lineup_notification_fires_when_has_lineup_true(): void
    {
        $match = $this->todayMatch();
        $this->predictionWithLineup($match->id);

        $this->oneSignal
            ->shouldReceive('notifyLineupUpdated')
            ->once();

        $this->telegram
            ->shouldReceive('sendLineupPicks')
            ->once();

        $this->artisan('picks:notify', ['--type' => 'lineup'])->assertSuccessful();
    }

    // ── Competitive Match predictions are excluded ────────────────────

    public function test_competitive_match_outcome_is_excluded(): void
    {
        $match = $this->todayMatch();
        $this->predictionWithLineup($match->id, ['predicted_outcome' => 'Competitive Match']);

        $this->oneSignal->shouldNotReceive('sendMatchAlert');
        $this->telegram->shouldNotReceive('sendLineupPicks');

        $this->artisan('picks:notify', ['--type' => 'lineup'])->assertSuccessful();
    }

    // ── Predictions without lineup flag are excluded ───────────────────

    public function test_no_notification_when_has_lineup_is_false(): void
    {
        $match = $this->todayMatch();
        Prediction::create([
            'match_id'          => $match->id,
            'home_win_prob'     => 55.0,
            'draw_prob'         => 25.0,
            'away_win_prob'     => 20.0,
            'predicted_outcome' => 'Home Win',
            'confidence'        => 75,
            'analysis'          => 'No lineup yet.',
            'has_lineup'        => false,
        ]);

        $this->oneSignal->shouldNotReceive('sendMatchAlert');
        $this->telegram->shouldNotReceive('sendLineupPicks');

        $this->artisan('picks:notify', ['--type' => 'lineup'])->assertSuccessful();
    }

    // ── Picks ordered by confidence descending ────────────────────────

    public function test_lineup_picks_ordered_by_confidence(): void
    {
        $match1 = $this->todayMatch(['api_id' => 11111, 'home_team' => 'Man City', 'away_team' => 'Liverpool']);
        $match2 = $this->todayMatch(['api_id' => 22222, 'home_team' => 'Real Madrid', 'away_team' => 'Barcelona']);

        $this->predictionWithLineup($match1->id, ['confidence' => 65]);
        $this->predictionWithLineup($match2->id, ['confidence' => 85]);

        $this->oneSignal
            ->shouldReceive('notifyLineupUpdated')
            ->once()
            ->withArgs(fn ($topMatch) => str_contains($topMatch, 'Real Madrid'));

        $this->telegram->shouldReceive('sendLineupPicks')->once();

        $this->artisan('picks:notify', ['--type' => 'lineup'])->assertSuccessful();
    }

    // ── Past matches (yesterday) are not included ─────────────────────

    public function test_past_match_lineup_not_notified(): void
    {
        $match = $this->todayMatch(['match_time' => now('Africa/Lagos')->subDay()->setTime(15, 0)]);
        $this->predictionWithLineup($match->id);

        $this->oneSignal->shouldNotReceive('sendMatchAlert');
        $this->telegram->shouldNotReceive('sendLineupPicks');

        $this->artisan('picks:notify', ['--type' => 'lineup'])->assertSuccessful();
    }
}
