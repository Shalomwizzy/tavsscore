<?php

namespace Tests\Unit;

use App\Services\Markets\PlayerScorerModel;
use PHPUnit\Framework\TestCase;

class PlayerScorerModelTest extends TestCase
{
    public function test_unqualified_players_return_null(): void
    {
        // Too few appearances.
        $this->assertNull(PlayerScorerModel::anytimeScore(3, 2, 180, 1.3));
        // Too few goals.
        $this->assertNull(PlayerScorerModel::anytimeScore(1, 20, 1800, 1.3));
    }

    public function test_established_scorer_returns_reasonable_probability(): void
    {
        $p = PlayerScorerModel::anytimeScore(12, 20, 1700, 1.6);
        $this->assertNotNull($p);
        // Even a hot striker vs a leaky defence is well under certainty.
        $this->assertGreaterThan(30, $p);
        $this->assertLessThan(80, $p);
    }

    public function test_two_plus_is_less_likely_than_anytime(): void
    {
        $anytime = PlayerScorerModel::anytimeScore(15, 20, 1800, 1.4);
        $twoPlus = PlayerScorerModel::toScoreTwoPlus(15, 20, 1800, 1.4);
        $this->assertNotNull($twoPlus);
        $this->assertLessThan($anytime, $twoPlus);
    }

    public function test_weaker_opponent_defence_raises_probability(): void
    {
        $vsStrong = PlayerScorerModel::anytimeScore(10, 20, 1800, 0.8);
        $vsWeak   = PlayerScorerModel::anytimeScore(10, 20, 1800, 1.8);
        $this->assertGreaterThan($vsStrong, $vsWeak);
    }
}
