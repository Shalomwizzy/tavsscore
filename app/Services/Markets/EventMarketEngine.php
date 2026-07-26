<?php

namespace App\Services\Markets;

use App\Services\DixonColes\Model;

/**
 * Corners and cards markets — Poisson models off each team's average
 * corners/cards (for and against), taken from fixture_statistics. Separate from
 * the goals score-matrix; these live alongside the main market board when there
 * is enough post-match statistical history to estimate the rates.
 *
 * Every probability is a 0-100 percentage rounded to 1dp.
 */
class EventMarketEngine
{
    /**
     * Corner markets from each team's corner averages.
     * Expected total = home's created rate blended with away's conceded rate, + vice-versa.
     *
     * @return array<string, float>
     */
    public static function corners(float $homeFor, float $homeAgainst, float $awayFor, float $awayAgainst): array
    {
        $homeExp = ($homeFor + $awayAgainst) / 2;
        $awayExp = ($awayFor + $homeAgainst) / 2;
        $total   = max(0.1, $homeExp + $awayExp);

        $m = self::overUnder($total, [8.5, 9.5, 10.5, 11.5], 'Corners');
        $m['Home Most Corners'] = self::pct(self::poissonWinProb($homeExp, $awayExp));
        $m['Away Most Corners'] = self::pct(self::poissonWinProb($awayExp, $homeExp));
        $m['Home Over 4.5 Corners'] = self::pct(self::overProb($homeExp, 4));
        $m['Away Over 4.5 Corners'] = self::pct(self::overProb($awayExp, 4));

        return $m;
    }

    /**
     * Card markets from each team's card averages.
     *
     * @return array<string, float>
     */
    public static function cards(float $homeFor, float $homeAgainst, float $awayFor, float $awayAgainst): array
    {
        // "For" = cards the team receives; "Against" = cards it draws from opponents.
        $homeExp = ($homeFor + $awayAgainst) / 2;
        $awayExp = ($awayFor + $homeAgainst) / 2;
        $total   = max(0.1, $homeExp + $awayExp);

        $m = self::overUnder($total, [2.5, 3.5, 4.5, 5.5], 'Cards');
        $m['Over 0.5 Red Cards'] = self::pct(min(0.35, $total * 0.04)); // rough: reds are rare, scale with card volume

        return $m;
    }

    /**
     * @param  array<int, float>  $lines
     * @return array<string, float>
     */
    private static function overUnder(float $lambda, array $lines, string $noun): array
    {
        $m = [];
        foreach ($lines as $line) {
            $over = self::overProb($lambda, (int) floor($line));
            $m["Over {$line} {$noun}"]  = self::pct($over);
            $m["Under {$line} {$noun}"] = self::pct(1 - $over);
        }
        return $m;
    }

    /** P(X > threshold) for a Poisson with mean λ. */
    private static function overProb(float $lambda, int $threshold): float
    {
        $cdf = 0.0;
        for ($k = 0; $k <= $threshold; $k++) {
            $cdf += Model::poissonPmf($k, $lambda);
        }
        return max(0.0, 1 - $cdf);
    }

    /** P(A > B) for two independent Poissons (approx, capped grid). */
    private static function poissonWinProb(float $lambdaA, float $lambdaB): float
    {
        $p = 0.0;
        for ($a = 0; $a <= 25; $a++) {
            $pa = Model::poissonPmf($a, $lambdaA);
            for ($b = 0; $b < $a; $b++) {
                $p += $pa * Model::poissonPmf($b, $lambdaB);
            }
        }
        return $p;
    }

    private static function pct(float $x): float
    {
        return round($x * 100, 1);
    }
}
