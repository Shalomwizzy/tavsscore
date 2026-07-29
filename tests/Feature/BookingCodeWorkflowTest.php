<?php

namespace Tests\Feature;

use App\Models\BookingCode;
use App\Models\FootballMatch;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCodeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(OneSignalService::class)->shouldIgnoreMissing();
        $this->mock(TelegramService::class)->shouldIgnoreMissing();
    }

    private function finishedMatch(int $home, int $away): FootballMatch
    {
        return FootballMatch::create([
            'api_id' => random_int(10000, 99999),
            'home_team' => 'Home Test',
            'away_team' => 'Away Test',
            'league' => 'Test League',
            'league_country' => 'Nigeria',
            'status' => 'FT',
            'match_time' => now()->subHour(),
            'home_score' => $home,
            'away_score' => $away,
        ]);
    }

    private function code(array $fixtures): BookingCode
    {
        return BookingCode::create([
            'platform' => 'sportybet',
            'code' => 'TESTCODE',
            'slip_ref' => 'safe-builder',
            'fixtures' => $fixtures,
            'total_odds' => 2.10,
            'source' => 'auto',
            'status' => 'published',
            'pick_date' => now('Africa/Lagos')->toDateString(),
        ]);
    }

    public function test_all_winning_legs_settle_a_booking_code_as_won(): void
    {
        $first = $this->finishedMatch(2, 0);
        $second = $this->finishedMatch(1, 1);
        $code = $this->code([
            ['match_id' => $first->id, 'market' => 'Home Win'],
            ['match_id' => $second->id, 'market' => 'Under 3.5 Goals'],
        ]);

        $this->artisan('booking:grade')->assertSuccessful();

        $this->assertSame('won', $code->fresh()->status);
        $this->assertNotNull($code->fresh()->settled_at);
    }

    public function test_one_losing_leg_settles_an_accumulator_as_lost(): void
    {
        $match = $this->finishedMatch(0, 2);
        $code = $this->code([['match_id' => $match->id, 'market' => 'Home Win']]);

        $this->artisan('booking:grade')->assertSuccessful();

        $this->assertSame('lost', $code->fresh()->status);
        $this->assertNotNull($code->fresh()->settled_at);
    }

    public function test_unknown_saved_leg_keeps_code_waiting_for_a_result(): void
    {
        $match = $this->finishedMatch(2, 0);
        $code = $this->code([
            ['match_id' => $match->id, 'market' => 'Home Win'],
            ['match_id' => 99999999, 'market' => 'Over 1.5 Goals'],
        ]);

        $this->artisan('booking:grade')->assertSuccessful();

        $this->assertSame('published', $code->fresh()->status);
        $this->assertNull($code->fresh()->settled_at);
    }

    public function test_worker_rejects_a_booking_code_below_two_odds(): void
    {
        config(['services.booking_worker.token' => 'test-worker-token']);

        $this->withHeader('X-Worker-Token', 'test-worker-token')
            ->postJson('/api/worker/booking-codes', [
                'platform' => 'sportybet',
                'code' => 'LOWODDS',
                'total_odds' => 1.99,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('total_odds');
    }

    public function test_worker_can_log_a_failed_build_without_publishing_an_invalid_ticket(): void
    {
        config(['services.booking_worker.token' => 'test-worker-token']);

        $this->withHeader('X-Worker-Token', 'test-worker-token')
            ->postJson('/api/worker/booking-codes', [
                'platform' => 'sportybet',
                'code' => 'FAILED-SAFE',
                'slip_ref' => 'safe-builder',
                'status' => 'failed',
                'note' => 'Fixture moved before a code could be created.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('booking_codes', ['code' => 'FAILED-SAFE', 'status' => 'failed']);
    }

    public function test_public_booking_page_shows_today_code_and_outcome_history(): void
    {
        BookingCode::create([
            'platform' => 'SportyBet', 'code' => 'TODAY22', 'total_odds' => 2.22,
            'status' => 'published', 'pick_date' => now('Africa/Lagos')->toDateString(),
        ]);
        BookingCode::create([
            'platform' => 'SportyBet', 'code' => 'WON22', 'total_odds' => 2.33,
            'status' => 'won', 'pick_date' => now('Africa/Lagos')->subDay()->toDateString(), 'settled_at' => now(),
        ]);

        $this->get('/booking-codes')->assertOk()->assertSee('TODAY22')->assertSee('WON22')->assertSee('Booking Codes');
    }
}
