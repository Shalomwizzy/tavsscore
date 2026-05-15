<?php

namespace Tests\Unit;

use App\Models\FootballMatch;
use App\Models\RolloverChallenge;
use App\Models\RolloverPick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolloverChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function makeChallenge(float $stake = 10000): RolloverChallenge
    {
        return RolloverChallenge::create([
            'started_at'    => now()->toDateString(),
            'initial_stake' => $stake,
            'status'        => 'active',
        ]);
    }

    private function makeMatch(): FootballMatch
    {
        static $id = 1000;
        return FootballMatch::create([
            'api_id'       => $id++,
            'league'       => 'Test League',
            'league_country' => 'Test',
            'home_team'    => 'Home FC',
            'away_team'    => 'Away FC',
            'status'       => 'FT',
            'home_score'   => 1,
            'away_score'   => 0,
            'match_time'   => now()->subHours(2),
        ]);
    }

    // ── currentBalance() ──────────────────────────────────────────

    public function test_current_balance_returns_initial_stake_with_no_picks(): void
    {
        $challenge = $this->makeChallenge(10000);
        $this->assertEquals(10000.0, $challenge->currentBalance());
    }

    public function test_current_balance_returns_last_won_potential_return(): void
    {
        $challenge = $this->makeChallenge(10000);
        $match     = $this->makeMatch();

        RolloverPick::create([
            'challenge_id'     => $challenge->id,
            'match_id'         => $match->id,
            'day_number'       => 1,
            'pick_date'        => now()->toDateString(),
            'implied_odds'     => 1.25,
            'stake_amount'     => 10000,
            'potential_return' => 12500,
            'groq_verdict'     => 'Home Win',
            'both_agree'       => false,
            'status'           => 'won',
        ]);

        $this->assertEquals(12500.0, $challenge->currentBalance());
    }

    public function test_current_balance_uses_latest_won_pick(): void
    {
        $challenge = $this->makeChallenge(10000);
        $match1    = $this->makeMatch();
        $match2    = $this->makeMatch();

        RolloverPick::create([
            'challenge_id'     => $challenge->id,
            'match_id'         => $match1->id,
            'day_number'       => 1,
            'pick_date'        => now()->subDay()->toDateString(),
            'implied_odds'     => 1.25,
            'stake_amount'     => 10000,
            'potential_return' => 12500,
            'groq_verdict'     => 'Home Win',
            'both_agree'       => false,
            'status'           => 'won',
        ]);

        RolloverPick::create([
            'challenge_id'     => $challenge->id,
            'match_id'         => $match2->id,
            'day_number'       => 2,
            'pick_date'        => now()->toDateString(),
            'implied_odds'     => 1.20,
            'stake_amount'     => 12500,
            'potential_return' => 15000,
            'groq_verdict'     => 'Over 1.5',
            'both_agree'       => true,
            'status'           => 'won',
        ]);

        $this->assertEquals(15000.0, $challenge->currentBalance());
    }

    // ── currentDay() ─────────────────────────────────────────────

    public function test_current_day_is_1_with_no_picks(): void
    {
        $challenge = $this->makeChallenge();
        $this->assertEquals(1, $challenge->currentDay());
    }

    public function test_current_day_increments_with_picks(): void
    {
        $challenge = $this->makeChallenge();
        $match     = $this->makeMatch();

        RolloverPick::create([
            'challenge_id'     => $challenge->id,
            'match_id'         => $match->id,
            'day_number'       => 1,
            'pick_date'        => now()->toDateString(),
            'implied_odds'     => 1.25,
            'stake_amount'     => 10000,
            'potential_return' => 12500,
            'groq_verdict'     => 'Home Win',
            'both_agree'       => false,
            'status'           => 'won',
        ]);

        $this->assertEquals(2, $challenge->currentDay());
    }

    public function test_current_day_caps_at_10(): void
    {
        $challenge = $this->makeChallenge();

        for ($day = 1; $day <= 12; $day++) {
            $match = $this->makeMatch();
            RolloverPick::create([
                'challenge_id'     => $challenge->id,
                'match_id'         => $match->id,
                'day_number'       => $day,
                'pick_date'        => now()->subDays(12 - $day)->toDateString(),
                'implied_odds'     => 1.25,
                'stake_amount'     => 10000,
                'potential_return' => 12500,
                'groq_verdict'     => 'Home Win',
                'both_agree'       => false,
                'status'           => 'won',
            ]);
        }

        $this->assertEquals(10, $challenge->currentDay());
    }

    // ── projectedFinal() ─────────────────────────────────────────

    public function test_projected_final_compounds_remaining_days(): void
    {
        $challenge = $this->makeChallenge(10000);
        // No picks yet, balance = 10000, 10 days remain at 1.25 odds
        $projected = $challenge->projectedFinal(1.25);
        $expected  = round(10000 * pow(1.25, 10), 2);
        $this->assertEquals($expected, $projected);
    }

    public function test_projected_final_reduces_as_days_won(): void
    {
        $challenge = $this->makeChallenge(10000);
        $match     = $this->makeMatch();

        RolloverPick::create([
            'challenge_id'     => $challenge->id,
            'match_id'         => $match->id,
            'day_number'       => 1,
            'pick_date'        => now()->toDateString(),
            'implied_odds'     => 1.20,
            'stake_amount'     => 10000,
            'potential_return' => 12000,
            'groq_verdict'     => 'Home Win',
            'both_agree'       => true,
            'status'           => 'won',
        ]);

        // 9 remaining days, balance = 12000
        $projected = $challenge->projectedFinal(1.20);
        $expected  = round(12000 * pow(1.20, 9), 2);
        $this->assertEquals($expected, $projected);
    }
}
