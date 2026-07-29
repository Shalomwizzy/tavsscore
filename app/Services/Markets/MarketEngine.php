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
    /** Empirical share of goals scored in the first half (~45/55 split). */
    private const FIRST_HALF_SHARE = 0.45;

    /** Build the matrix from expected goals, then derive all markets (FT + HT). */
    public static function fromExpectedGoals(float $lambdaHome, float $lambdaAway, float $rho = 0.0): array
    {
        $ft = self::fromMatrix(Model::matrix($lambdaHome, $lambdaAway, $rho));

        return array_merge($ft, self::halfTimeMarkets($lambdaHome, $lambdaAway, $rho));
    }

    /**
     * Half-time and HT/FT markets. Splits each team's expected goals into a
     * first-half and second-half Poisson process (~45/55) and derives HT
     * result, HT over/under, HT BTTS, both-halves, highest-scoring-half and all
     * nine HT/FT combinations.
     *
     * @return array<string, float>
     */
    public static function halfTimeMarkets(float $lambdaHome, float $lambdaAway, float $rho = 0.0): array
    {
        $s        = self::FIRST_HALF_SHARE;
        $htMatrix = Model::matrix($lambdaHome * $s, $lambdaAway * $s, $rho);
        $shMatrix = Model::matrix($lambdaHome * (1 - $s), $lambdaAway * (1 - $s), $rho);
        $pct      = fn (float $x): float => round($x * 100, 1);
        $sign     = fn (int $x, int $y): string => $x > $y ? 'H' : ($x === $y ? 'D' : 'A');

        $htHome = $htDraw = $htAway = $htOver05 = $htOver15 = $htBtts = 0.0;
        $htTotalDist = [];
        foreach ($htMatrix as $h => $row) {
            foreach ($row as $a => $p) {
                if ($p <= 0) continue;
                if ($h > $a) $htHome += $p; elseif ($h === $a) $htDraw += $p; else $htAway += $p;
                $t = $h + $a;
                if ($t > 0.5) $htOver05 += $p;
                if ($t > 1.5) $htOver15 += $p;
                if ($h >= 1 && $a >= 1) $htBtts += $p;
                $htTotalDist[$t] = ($htTotalDist[$t] ?? 0) + $p;
            }
        }

        $shTotalDist = [];
        foreach ($shMatrix as $h => $row) {
            foreach ($row as $a => $p) {
                if ($p <= 0) continue;
                $t = $h + $a;
                $shTotalDist[$t] = ($shTotalDist[$t] ?? 0) + $p;
            }
        }

        $bothHalves = (1 - ($htTotalDist[0] ?? 0)) * (1 - ($shTotalDist[0] ?? 0));

        $firstMore = $secondMore = $equalHalves = 0.0;
        foreach ($htTotalDist as $i => $pi) {
            foreach ($shTotalDist as $j => $pj) {
                if ($i > $j) $firstMore += $pi * $pj;
                elseif ($j > $i) $secondMore += $pi * $pj;
                else $equalHalves += $pi * $pj;
            }
        }

        // HT/FT — joint over both half matrices (FT = HT + 2H per team)
        $htft = [];
        foreach ($htMatrix as $h1 => $r1) {
            foreach ($r1 as $a1 => $p1) {
                if ($p1 <= 0) continue;
                $htRes = $sign($h1, $a1);
                foreach ($shMatrix as $h2 => $r2) {
                    foreach ($r2 as $a2 => $p2) {
                        if ($p2 <= 0) continue;
                        $k = $htRes.'/'.$sign($h1 + $h2, $a1 + $a2);
                        $htft[$k] = ($htft[$k] ?? 0) + $p1 * $p2;
                    }
                }
            }
        }

        $m = [];
        $m['HT Home Win'] = $pct($htHome);
        $m['HT Draw']     = $pct($htDraw);
        $m['HT Away Win'] = $pct($htAway);
        $m['HT Over 0.5'] = $pct($htOver05);
        $m['HT Under 0.5'] = $pct(1 - $htOver05);
        $m['HT Over 1.5'] = $pct($htOver15);
        $m['HT Under 1.5'] = $pct(1 - $htOver15);
        $m['HT Both Teams Score'] = $pct($htBtts);
        $m['Both Halves Over 0.5'] = $pct($bothHalves);
        $m['Goal in Both Halves']  = $pct($bothHalves);
        $m['1st Half More Goals']  = $pct($firstMore);
        $m['2nd Half More Goals']  = $pct($secondMore);
        $m['Equal Goals Each Half'] = $pct($equalHalves);

        $names = ['H' => 'Home', 'D' => 'Draw', 'A' => 'Away'];
        foreach ($htft as $k => $p) {
            [$ht, $ft] = explode('/', $k);
            $m["HT/FT {$names[$ht]}/{$names[$ft]}"] = $pct($p);
        }

        return $m;
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

        // ── Exact team goals ──
        $m['Home Exactly 0'] = $pct($homeGoalsDist[0] ?? 0);
        $m['Home Exactly 1'] = $pct($homeGoalsDist[1] ?? 0);
        $m['Home Exactly 2'] = $pct($homeGoalsDist[2] ?? 0);
        $m['Home 3+ Goals']  = $pct($bucketAtLeast($homeGoalsDist, 3));
        $m['Away Exactly 0'] = $pct($awayGoalsDist[0] ?? 0);
        $m['Away Exactly 1'] = $pct($awayGoalsDist[1] ?? 0);
        $m['Away Exactly 2'] = $pct($awayGoalsDist[2] ?? 0);
        $m['Away 3+ Goals']  = $pct($bucketAtLeast($awayGoalsDist, 3));

        // ── Handicaps, winning margin & combo markets (joint pass) ──
        // Half-goal Asian lines deliberately avoid a push/void. Keep all the
        // common 0.5–5.5 ranges available for the specialist handicap picks.
        $handicapLines = [0.5, 1.5, 2.5, 3.5, 4.5, 5.5];
        $handicaps = [];
        foreach ($handicapLines as $line) {
            foreach (['Home', 'Away'] as $side) {
                $handicaps["{$side} +{$line} (Handicap)"] = 0.0;
                $handicaps["{$side} -{$line} (Handicap)"] = 0.0;
            }
        }
        $mH1 = $mH2 = $mH3 = $mA1 = $mA2 = $mA3 = 0.0;
        $hwO = $hwU = $dO = $dU = $awO = $awU = 0.0;               // result × O/U 2.5
        $hwBt = $hwNb = $awBt = $awNb = $drawBt = 0.0;              // result × BTTS
        $btO = $btU = $nbO = $nbU = 0.0;                           // BTTS × O/U 2.5
        foreach ($matrix as $h => $row) {
            foreach ($row as $a => $p) {
                if ($p <= 0) continue;
                $d = $h - $a; $over = ($h + $a) > 2.5; $bt = ($h >= 1 && $a >= 1);

                foreach ($handicapLines as $line) {
                    if ($d + $line > 0)  $handicaps["Home +{$line} (Handicap)"] += $p;
                    if ($d - $line > 0)  $handicaps["Home -{$line} (Handicap)"] += $p;
                    if (-$d + $line > 0) $handicaps["Away +{$line} (Handicap)"] += $p;
                    if (-$d - $line > 0) $handicaps["Away -{$line} (Handicap)"] += $p;
                }

                if ($d === 1) $mH1 += $p; elseif ($d === 2) $mH2 += $p; elseif ($d >= 3) $mH3 += $p;
                elseif ($d === -1) $mA1 += $p; elseif ($d === -2) $mA2 += $p; elseif ($d <= -3) $mA3 += $p;

                if ($h > $a)       { $over ? $hwO += $p : $hwU += $p; $bt ? $hwBt += $p : $hwNb += $p; }
                elseif ($h === $a) { $over ? $dO += $p : $dU += $p;  if ($bt) $drawBt += $p; }
                else               { $over ? $awO += $p : $awU += $p; $bt ? $awBt += $p : $awNb += $p; }

                if ($bt) { $over ? $btO += $p : $btU += $p; } else { $over ? $nbO += $p : $nbU += $p; }
            }
        }

        foreach ($handicaps as $label => $probability) {
            $m[$label] = $pct($probability);
        }

        $m['Home to win by 1']  = $pct($mH1);
        $m['Home to win by 2']  = $pct($mH2);
        $m['Home to win by 3+'] = $pct($mH3);
        $m['Away to win by 1']  = $pct($mA1);
        $m['Away to win by 2']  = $pct($mA2);
        $m['Away to win by 3+'] = $pct($mA3);

        $m['Home & Over 2.5'] = $pct($hwO);
        $m['Home & Under 2.5'] = $pct($hwU);
        $m['Draw & Over 2.5'] = $pct($dO);
        $m['Draw & Under 2.5'] = $pct($dU);
        $m['Away & Over 2.5'] = $pct($awO);
        $m['Away & Under 2.5'] = $pct($awU);

        $m['Home & BTTS'] = $pct($hwBt);
        $m['Home & No BTTS'] = $pct($hwNb);
        $m['Away & BTTS'] = $pct($awBt);
        $m['Away & No BTTS'] = $pct($awNb);
        $m['Draw & BTTS'] = $pct($drawBt);

        $m['BTTS & Over 2.5'] = $pct($btO);
        $m['BTTS & Under 2.5'] = $pct($btU);
        $m['No BTTS & Over 2.5'] = $pct($nbO);
        $m['No BTTS & Under 2.5'] = $pct($nbU);

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
