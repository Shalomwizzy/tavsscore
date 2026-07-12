<?php

namespace Tests\Unit;

use App\Services\DixonColes\Model;
use PHPUnit\Framework\TestCase;

class DixonColesModelTest extends TestCase
{
    public function test_poisson_pmf_matches_hand_computed_values(): void
    {
        // P(0 | λ=1.5) = e^-1.5 ≈ 0.2231
        $this->assertEqualsWithDelta(0.2231, Model::poissonPmf(0, 1.5), 0.0002);
        // P(2 | λ=1.5) = 1.5^2 · e^-1.5 / 2 ≈ 0.2510
        $this->assertEqualsWithDelta(0.2510, Model::poissonPmf(2, 1.5), 0.0002);
    }

    public function test_probability_matrix_sums_to_approximately_one(): void
    {
        $matrix = Model::matrix(1.4, 1.1, rho: -0.08);
        $total = 0.0;
        foreach ($matrix as $row) $total += array_sum($row);
        // Cap at 8 goals per side truncates ~0.001 of mass. Both sums to ≈ 1.
        $this->assertEqualsWithDelta(1.0, $total, 0.005);
    }

    public function test_dc_correction_reduces_to_plain_poisson_when_rho_is_zero(): void
    {
        $lh = 1.4; $la = 1.1;
        $matrix = Model::matrix($lh, $la, rho: 0.0);

        $this->assertEqualsWithDelta(
            Model::poissonPmf(0, $lh) * Model::poissonPmf(0, $la),
            $matrix[0][0],
            1e-9,
        );
        $this->assertEqualsWithDelta(
            Model::poissonPmf(2, $lh) * Model::poissonPmf(1, $la),
            $matrix[2][1],
            1e-9,
        );
    }

    public function test_negative_rho_shifts_mass_toward_0_0_and_1_1(): void
    {
        // Empirical DC finding: negative ρ boosts P(0,0) and P(1,1),
        // reduces P(1,0) and P(0,1). Verify direction.
        $lh = 1.2; $la = 1.0;

        $plain = Model::matrix($lh, $la, rho: 0.0);
        $dc    = Model::matrix($lh, $la, rho: -0.10);

        $this->assertGreaterThan($plain[0][0], $dc[0][0]);
        $this->assertGreaterThan($plain[1][1], $dc[1][1]);
        $this->assertLessThan($plain[1][0], $dc[1][0]);
        $this->assertLessThan($plain[0][1], $dc[0][1]);
    }

    public function test_1x2_derivation_matches_matrix_sum(): void
    {
        $matrix = Model::matrix(1.4, 1.0, rho: -0.05);
        $race   = Model::oneXTwo($matrix);

        $this->assertEqualsWithDelta(1.0, $race['home_win'] + $race['draw'] + $race['away_win'], 0.005);
        // Home lambda > away lambda so home should be favoured
        $this->assertGreaterThan($race['away_win'], $race['home_win']);
    }

    public function test_over_25_is_larger_than_over_35(): void
    {
        $matrix = Model::matrix(1.5, 1.2);
        $this->assertGreaterThan(
            Model::overGoals($matrix, 3.5),
            Model::overGoals($matrix, 2.5),
        );
    }

    public function test_btts_lower_bound_when_one_side_is_near_zero(): void
    {
        // If home lambda is 2.0 but away is 0.1, BTTS should be low
        $matrix = Model::matrix(2.0, 0.1);
        $this->assertLessThan(0.15, Model::btts($matrix));
    }

    public function test_top_scores_are_sorted_descending(): void
    {
        $matrix = Model::matrix(1.4, 1.0);
        $tops   = Model::topScores($matrix, 5);

        $probs = array_column($tops, 'probability');
        $sorted = $probs;
        rsort($sorted);
        $this->assertSame($sorted, $probs);
        $this->assertCount(5, $tops);
    }
}
