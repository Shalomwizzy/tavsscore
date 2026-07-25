<?php

namespace App\Services\Markets;

use App\Services\DixonColes\Model;

/**
 * Derives the full board of goals/result betting markets from a joint score
 * matrix (matrix[homeGoals][awayGoals] = probability). Pure math — no AI, no
 * API. This is what lets us predict "the most likely outcome across ALL
 * markets" instead of just the 9 curated pick types.
 *
 * Every probability is a 0-100 percentage rounded to 1dp.
 */
class MarketEngine
{
    /** Build the matrix from expected goals, then derive all markets. */
    public static function fromExpectedGoals(float $lambdaHome, float $lambdaAway, float $rho = 0.0): array
    {
        return self::fromMatrix(Model::matrix($lambdaHome, $lambdaAway, $rho));
    }

    /**
     * @param  array<int, array<int, float>>  $matrix
     * @return array<string, float>  market label => probability %
     */
    public static function fromMatrix(array $matrix): array
    {
        $homeWin = $draw = $awayWin = 0.0;
        $bttsYes = 0.0;
        $homeCS = $awayCS = 0.0;              // clean sheets (opponent scores 0)
        $homeScores = $awayScores = 0.0;
        $oddTotal = $evenTotal = 0.0;
        $totalBuckets = [];                    // exact total goals
        $homeGoalsDist = $awayGoalsDist = [];  // per-team exact goals

        foreach ($matrix as $h => $row) {
            foreach ($row as $a => $p) {
                if ($p <= 0) continue;

                // 1X2
                if ($h > $a)      $homeWin += $p;
                elseif ($h === $a) $draw    += $p;
                else              $awayWin += $p;

                // BTTS
                if ($h >= 1 && $a >= 1) $bttsYes += $p;

                // Clean sheets & to-score
                if ($a === 0) $homeCS += $p;
                if ($h === 0) $awayCS += $p;
                if ($h >= 1)  $homeScores += $p;
                if ($a >= 1)  $awayScores += $p;

                // Totals
                $total = $h + $a;
                $totalBuckets[$total] = ($totalBuckets[$total] ?? 0) + $p;
                if ($total % 2 === 0) $evenTotal += $p; else $oddTotal += $p;

                // Per-team goal distributions
                $homeGoalsDist[$h] = ($homeGoalsDist[$h] ?? 0) + $p;
                $awayGoalsDist[$a] = ($awayGoalsDist[$a] ?? 0) + $p;
            }
        }

        $pct = fn (float $x): float => round($x * 100, 1);
        $bucketAtLeast = fn (array $dist, int $n): float => array_sum(array_filter($dist, fn ($v, $k) => $k >= $n, ARRAY_FILTER_USE_BOTH));
        // Over(line) = P(total > line), computed from the exact-total distribution.
        $overProb = function (float $line) use ($totalBuckets): float {
            $s = 0.0;
            foreach ($totalBuckets as $t => $p) {
                if ($t > $line) $s += $p;
            }
            return $s;
        };

        $m = [];

        // ── 1X2 ──
        $m['Home Win'] = $pct($homeWin);
        $m['Draw']     = $pct($draw);
        $m['Away Win'] = $pct($awayWin);

        // ── Double chance ──
        $m['Home or Draw (1X)'] = $pct($homeWin + $draw);
        $m['Home or Away (12)'] = $pct($homeWin + $awayWin);
        $m['Draw or Away (X2)'] = $pct($draw + $awayWin);

        // ── Draw No Bet (win prob given a result) ──
        $decisive = $homeWin + $awayWin;
        if ($decisive > 0) {
            $m['Draw No Bet - Home'] = $pct($homeWin / $decisive);
            $m['Draw No Bet - Away'] = $pct($awayWin / $decisive);
        }

        // ── Over / Under ──
        foreach ([0.5, 1.5, 2.5, 3.5, 4.5, 5.5] as $line) {
            $over = $overProb($line);
            $m["Over {$line} Goals"]  = $pct($over);
            $m["Under {$line} Goals"] = $pct(1 - $over);
        }

        // ── BTTS ──
        $m['Both Teams Score (GG)']    = $pct($bttsYes);
        $m['No Both Teams Score (NG)'] = $pct(1 - $bttsYes);

        // ── Clean sheets / win to nil / to score ──
        $m['Home Clean Sheet'] = $pct($homeCS);
        $m['Away Clean Sheet'] = $pct($awayCS);
        $m['Home Team to Score'] = $pct($homeScores);
        $m['Away Team to Score'] = $pct($awayScores);

        // Win to nil = win AND opponent scores 0
        $homeWTN = $awayWTN = 0.0;
        foreach ($matrix as $h => $row) {
            foreach ($row as $a => $p) {
                if ($h > $a && $a === 0) $homeWTN += $p;
                if ($a > $h && $h === 0) $awayWTN += $p;
            }
        }
        $m['Home Win to Nil'] = $pct($homeWTN);
        $m['Away Win to Nil'] = $pct($awayWTN);

        // ── Odd / Even total goals ──
        $m['Total Goals Odd']  = $pct($oddTotal);
        $m['Total Goals Even'] = $pct($evenTotal);

        // ── Exact total goals ──
        $m['Exactly 0 Goals'] = $pct($totalBuckets[0] ?? 0);
        $m['Exactly 1 Goal']  = $pct($totalBuckets[1] ?? 0);
        $m['Exactly 2 Goals'] = $pct($totalBuckets[2] ?? 0);
        $m['Exactly 3 Goals'] = $pct($totalBuckets[3] ?? 0);
        $m['4+ Goals']        = $pct(array_sum(array_filter($totalBuckets, fn ($v, $k) => $k >= 4, ARRAY_FILTER_USE_BOTH)));

        // ── Multigoals (inclusive ranges) ──
        $rangeSum = fn (int $lo, int $hi): float => array_sum(array_filter($totalBuckets, fn ($v, $k) => $k >= $lo && $k <= $hi, ARRAY_FILTER_USE_BOTH));
        $m['1-2 Goals'] = $pct($rangeSum(1, 2));
        $m['1-3 Goals'] = $pct($rangeSum(1, 3));
        $m['2-3 Goals'] = $pct($rangeSum(2, 3));
        $m['2-4 Goals'] = $pct($rangeSum(2, 4));
        $m['3-4 Goals'] = $pct($rangeSum(3, 4));

        // ── Team totals ──
        $m['Home Over 0.5'] = $pct($bucketAtLeast($homeGoalsDist, 1));
        $m['Home Over 1.5'] = $pct($bucketAtLeast($homeGoalsDist, 2));
        $m['Home Over 2.5'] = $pct($bucketAtLeast($homeGoalsDist, 3));
        $m['Away Over 0.5'] = $pct($bucketAtLeast($awayGoalsDist, 1));
        $m['Away Over 1.5'] = $pct($bucketAtLeast($awayGoalsDist, 2));
        $m['Away Over 2.5'] = $pct($bucketAtLeast($awayGoalsDist, 3));

        return $m;
    }

    /**
     * All markets sorted by probability desc. Optionally floor by min %.
     * @return array<int, array{market:string, probability:float}>
     */
    public static function ranked(array $matrix, float $minPct = 0.0): array
    {
        $markets = self::fromMatrix($matrix);
        arsort($markets);

        $out = [];
        foreach ($markets as $label => $prob) {
            if ($prob < $minPct) continue;
            $out[] = ['market' => $label, 'probability' => $prob];
        }

        return $out;
    }

    /**
     * The single most-likely markets, excluding trivially-high ones (e.g. Over
     * 0.5, both teams "or" chances) so the headline pick is genuinely useful.
     * @return array<int, array{market:string, probability:float}>
     */
    public static function bestPicks(array $matrix, int $limit = 5, float $maxPct = 92.0): array
    {
        return array_slice(
            array_filter(self::ranked($matrix), fn ($m) => $m['probability'] <= $maxPct),
            0,
            $limit,
        );
    }

    /** Top exact scorelines with probabilities. */
    public static function topScores(array $matrix, int $n = 5): array
    {
        return Model::topScores($matrix, $n);
    }
}
