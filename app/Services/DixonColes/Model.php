<?php

namespace App\Services\DixonColes;

/**
 * Pure Dixon-Coles math. No I/O, no persistence — just probability
 * calculations that can be unit-tested in isolation.
 *
 * The model treats home and away goals as independent Poisson variables
 * with rates λ_home and λ_away, then applies Dixon-Coles's low-score
 * correction τ(x, y) to fix the overprediction of 0-0 / 1-0 / 0-1 / 1-1
 * that plain Poisson exhibits.
 *
 * λ_home = exp(α_home + β_away + γ)     home attack × away defense × home adv
 * λ_away = exp(α_away + β_home)
 * P(x, y) = Poisson(x, λ_home) × Poisson(y, λ_away) × τ(x, y)
 */
class Model
{
    /** Score matrix cap: 0-8 goals per side covers >99.99% of Poisson mass. */
    public const MAX_GOALS = 8;

    /**
     * Full joint score-probability matrix as a MAX_GOALS+1 × MAX_GOALS+1 array.
     * Rows = home goals (0-MAX), columns = away goals (0-MAX). Sums to ~1.
     *
     * @return float[][]
     */
    public static function matrix(float $lambdaHome, float $lambdaAway, float $rho = 0.0): array
    {
        $n = self::MAX_GOALS + 1;
        $matrix = [];

        // Precompute Poisson PMF arrays once for speed
        $pHome = self::poissonPmfArray($lambdaHome, $n);
        $pAway = self::poissonPmfArray($lambdaAway, $n);

        for ($x = 0; $x < $n; $x++) {
            $row = [];
            for ($y = 0; $y < $n; $y++) {
                $row[$y] = $pHome[$x] * $pAway[$y] * self::tau($x, $y, $lambdaHome, $lambdaAway, $rho);
            }
            $matrix[$x] = $row;
        }

        return $matrix;
    }

    /**
     * Dixon-Coles low-score correction. Adjusts joint probability for the
     * cells where the independent-Poisson assumption is empirically wrong
     * (the four bottom-left cells of the matrix).
     *
     * ρ is bounded by max(-1/λ_h, -1/λ_a) < ρ < min(1/(λ_h λ_a), 1) to keep
     * probabilities non-negative. The fitter clamps ρ into a safe range.
     */
    public static function tau(int $x, int $y, float $lh, float $la, float $rho): float
    {
        return match (true) {
            $x === 0 && $y === 0 => 1 - $lh * $la * $rho,
            $x === 0 && $y === 1 => 1 + $lh * $rho,
            $x === 1 && $y === 0 => 1 + $la * $rho,
            $x === 1 && $y === 1 => 1 - $rho,
            default              => 1.0,
        };
    }

    /**
     * Poisson PMF: P(X=k) = λ^k · e^-λ / k!
     * Uses log-space computation to avoid overflow at large k, then exp back.
     */
    public static function poissonPmf(int $k, float $lambda): float
    {
        if ($lambda <= 0) return $k === 0 ? 1.0 : 0.0;
        return exp($k * log($lambda) - $lambda - self::logFactorial($k));
    }

    /**
     * @return float[] PMF values for k = 0..n-1
     */
    public static function poissonPmfArray(float $lambda, int $n): array
    {
        $out = [];
        for ($k = 0; $k < $n; $k++) {
            $out[$k] = self::poissonPmf($k, $lambda);
        }
        return $out;
    }

    /** Stirling-approximation-free log(k!) via cached gammaln-equivalent. */
    private static array $logFactCache = [0 => 0.0, 1 => 0.0];
    public static function logFactorial(int $k): float
    {
        if (isset(self::$logFactCache[$k])) return self::$logFactCache[$k];
        $val = self::$logFactCache[count(self::$logFactCache) - 1];
        for ($i = count(self::$logFactCache); $i <= $k; $i++) {
            $val += log($i);
            self::$logFactCache[$i] = $val;
        }
        return self::$logFactCache[$k];
    }

    // ── Market derivations ───────────────────────────────────────────────

    /**
     * @return array{home_win: float, draw: float, away_win: float}
     */
    public static function oneXTwo(array $matrix): array
    {
        $home = $draw = $away = 0.0;
        foreach ($matrix as $x => $row) {
            foreach ($row as $y => $p) {
                if ($x > $y)      $home += $p;
                elseif ($x < $y)  $away += $p;
                else              $draw += $p;
            }
        }
        return ['home_win' => $home, 'draw' => $draw, 'away_win' => $away];
    }

    /** Probability that home + away goals exceed the line (Over side). */
    public static function overGoals(array $matrix, float $line): float
    {
        $over = 0.0;
        foreach ($matrix as $x => $row) {
            foreach ($row as $y => $p) {
                if (($x + $y) > $line) $over += $p;
            }
        }
        return $over;
    }

    /** Probability both teams score (BTTS = Yes). */
    public static function btts(array $matrix): float
    {
        $yes = 0.0;
        foreach ($matrix as $x => $row) {
            if ($x === 0) continue;
            foreach ($row as $y => $p) {
                if ($y >= 1) $yes += $p;
            }
        }
        return $yes;
    }

    /**
     * Top N most likely correct scores as [{score:"X-Y", probability}].
     */
    public static function topScores(array $matrix, int $n = 3): array
    {
        $scores = [];
        foreach ($matrix as $x => $row) {
            foreach ($row as $y => $p) {
                $scores[] = ['score' => "{$x}-{$y}", 'probability' => $p];
            }
        }
        usort($scores, fn ($a, $b) => $b['probability'] <=> $a['probability']);
        return array_slice($scores, 0, $n);
    }
}
