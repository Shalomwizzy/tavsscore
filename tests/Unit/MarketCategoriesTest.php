<?php

namespace Tests\Unit;

use App\Services\Markets\MarketEngine;
use App\Support\MarketCategories;
use PHPUnit\Framework\TestCase;

class MarketCategoriesTest extends TestCase
{
    public function test_grouping_preserves_every_market(): void
    {
        $board   = MarketEngine::fromExpectedGoals(1.6, 1.1, -0.03);
        $grouped = MarketCategories::group($board);

        $total = array_sum(array_map('count', $grouped));
        $this->assertSame(count($board), $total, 'No market should be dropped or duplicated');
    }

    public function test_markets_land_in_expected_categories(): void
    {
        $grouped = MarketCategories::group(MarketEngine::fromExpectedGoals(1.6, 1.1, -0.03));

        $this->assertArrayHasKey('Home Win', $grouped['Match Result']);
        $this->assertArrayHasKey('Over 2.5 Goals', $grouped['Goals Over/Under']);
        $this->assertArrayHasKey('Both Teams Score (GG)', $grouped['BTTS & Defence']);
        $this->assertArrayHasKey('Home -1.5 (Handicap)', $grouped['Handicap & Margin']);
        $this->assertArrayHasKey('BTTS & Over 2.5', $grouped['Combos']);
        $this->assertArrayHasKey('HT Home Win', $grouped['Half-Time & HT/FT']);
    }

    public function test_each_category_is_sorted_descending(): void
    {
        $grouped = MarketCategories::group(MarketEngine::fromExpectedGoals(1.6, 1.1, -0.03));

        foreach ($grouped as $markets) {
            $vals = array_values($markets);
            $sorted = $vals;
            rsort($sorted);
            $this->assertSame($sorted, $vals);
        }
    }

    public function test_empty_board_yields_no_groups(): void
    {
        $this->assertSame([], MarketCategories::group([]));
    }
}
