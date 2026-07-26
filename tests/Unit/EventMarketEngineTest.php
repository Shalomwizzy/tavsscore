<?php

namespace Tests\Unit;

use App\Services\Markets\EventMarketEngine;
use PHPUnit\Framework\TestCase;

class EventMarketEngineTest extends TestCase
{
    public function test_corner_over_under_pairs_are_complementary(): void
    {
        $c = EventMarketEngine::corners(6, 4, 5, 5);
        foreach (['8.5', '9.5', '10.5', '11.5'] as $line) {
            $this->assertEqualsWithDelta(100.0, $c["Over {$line} Corners"] + $c["Under {$line} Corners"], 0.5);
        }
    }

    public function test_corner_lines_decrease_monotonically(): void
    {
        $c = EventMarketEngine::corners(6, 4, 5, 5);
        $this->assertGreaterThan($c['Over 9.5 Corners'], $c['Over 8.5 Corners']);
        $this->assertGreaterThan($c['Over 10.5 Corners'], $c['Over 9.5 Corners']);
    }

    public function test_most_corners_favours_higher_rate_side(): void
    {
        // Home creates far more corners than away.
        $c = EventMarketEngine::corners(8, 3, 3, 8);
        $this->assertGreaterThan($c['Away Most Corners'], $c['Home Most Corners']);
    }

    public function test_card_over_under_pairs_are_complementary(): void
    {
        $k = EventMarketEngine::cards(2.1, 1.8, 2.4, 2.0);
        foreach (['2.5', '3.5', '4.5', '5.5'] as $line) {
            $this->assertEqualsWithDelta(100.0, $k["Over {$line} Cards"] + $k["Under {$line} Cards"], 0.5);
        }
    }
}
