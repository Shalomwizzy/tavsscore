<?php

namespace Tests\Unit;

use App\Services\Markets\MarketEngine;
use App\Services\DixonColes\Model;
use PHPUnit\Framework\TestCase;

class MarketEngineTest extends TestCase
{
    private function board(): array
    {
        return MarketEngine::fromExpectedGoals(1.6, 1.1, -0.03);
    }

    public function test_it_derives_the_full_board(): void
    {
        $this->assertGreaterThanOrEqual(100, count($this->board()));
    }

    public function test_1x2_sums_to_100(): void
    {
        $b = $this->board();
        $this->assertEqualsWithDelta(100.0, $b['Home Win'] + $b['Draw'] + $b['Away Win'], 0.5);
    }

    public function test_over_under_pairs_are_complementary(): void
    {
        $b = $this->board();
        foreach (['0.5', '1.5', '2.5', '3.5', '4.5'] as $line) {
            $this->assertEqualsWithDelta(
                100.0,
                $b["Over {$line} Goals"] + $b["Under {$line} Goals"],
                0.5,
                "Over/Under {$line} should sum to 100"
            );
        }
    }

    public function test_btts_pair_is_complementary(): void
    {
        $b = $this->board();
        $this->assertEqualsWithDelta(100.0, $b['Both Teams Score (GG)'] + $b['No Both Teams Score (NG)'], 0.5);
    }

    public function test_htft_nine_combos_sum_to_100(): void
    {
        $sum = 0.0;
        foreach ($this->board() as $label => $prob) {
            if (str_starts_with($label, 'HT/FT ')) {
                $sum += $prob;
            }
        }
        $this->assertEqualsWithDelta(100.0, $sum, 0.6);
    }

    public function test_winning_margins_plus_draw_sum_to_100(): void
    {
        $b = $this->board();
        $sum = $b['Home to win by 1'] + $b['Home to win by 2'] + $b['Home to win by 3+']
            + $b['Away to win by 1'] + $b['Away to win by 2'] + $b['Away to win by 3+']
            + $b['Draw'];
        $this->assertEqualsWithDelta(100.0, $sum, 0.6);
    }

    public function test_handicap_is_harder_than_straight_win(): void
    {
        $b = $this->board();
        // Winning by 2+ must be less likely than simply winning.
        $this->assertLessThan($b['Home Win'], $b['Home -1.5 (Handicap)']);
    }

    public function test_large_positive_handicaps_are_available_and_safer_than_smaller_positive_lines(): void
    {
        $b = $this->board();

        $this->assertArrayHasKey('Home +4.5 (Handicap)', $b);
        $this->assertArrayHasKey('Away +4.5 (Handicap)', $b);
        $this->assertGreaterThan($b['Home +3.5 (Handicap)'], $b['Home +4.5 (Handicap)']);
    }

    public function test_over_lines_decrease_monotonically(): void
    {
        $b = $this->board();
        $this->assertGreaterThan($b['Over 1.5 Goals'], $b['Over 0.5 Goals']);
        $this->assertGreaterThan($b['Over 2.5 Goals'], $b['Over 1.5 Goals']);
        $this->assertGreaterThan($b['Over 3.5 Goals'], $b['Over 2.5 Goals']);
    }

    public function test_favourite_has_higher_win_probability(): void
    {
        // Strong home side vs weak away side.
        $b = MarketEngine::fromMatrix(Model::matrix(2.2, 0.7));
        $this->assertGreaterThan($b['Away Win'], $b['Home Win']);
    }
}
