<?php

namespace App\Services\Markets;

/**
 * Estimates a player's anytime-goalscorer probability for a fixture from their
 * season scoring rate, adjusted by the opponent's defensive record. Pure math
 * off player_statistics + the opponent's goals-conceded rate.
 */
class PlayerScorerModel
{
    private const LEAGUE_AVG_CONCEDED = 1.3;   // typical goals conceded per game
    private const MIN_APPEARANCES     = 5;      // established players only
    private const MIN_GOALS           = 2;

    /**
     * Anytime-score probability (%) or null if the player doesn't qualify.
     */
    public static function anytimeScore(int $goals, int $appearances, int $minutes, float $oppConcededPerGame): ?float
    {
        if ($appearances < self::MIN_APPEARANCES || $goals < self::MIN_GOALS) {
            return null;
        }

        // Per-90 scoring rate (fall back to per-appearance when minutes missing).
        $per90 = $minutes > 0 ? $goals / ($minutes / 90) : $goals / max($appearances, 1);
        $per90 = min($per90, 1.5);   // clamp unrealistic rates from tiny samples

        $oppFactor = $oppConcededPerGame > 0 ? $oppConcededPerGame / self::LEAGUE_AVG_CONCEDED : 1.0;
        $oppFactor = max(0.5, min(1.8, $oppFactor));

        $lambda = $per90 * $oppFactor;

        return round((1 - exp(-$lambda)) * 100, 1);
    }

    /** Player to score 2+ goals in the match. */
    public static function toScoreTwoPlus(int $goals, int $appearances, int $minutes, float $oppConcededPerGame): ?float
    {
        $anytime = self::anytimeScore($goals, $appearances, $minutes, $oppConcededPerGame);
        if ($anytime === null) {
            return null;
        }

        $per90 = $minutes > 0 ? $goals / ($minutes / 90) : $goals / max($appearances, 1);
        $per90 = min($per90, 1.5);
        $oppFactor = max(0.5, min(1.8, ($oppConcededPerGame > 0 ? $oppConcededPerGame / self::LEAGUE_AVG_CONCEDED : 1.0)));
        $lambda = $per90 * $oppFactor;

        // P(X>=2) = 1 - P(0) - P(1)
        $p2 = 1 - exp(-$lambda) - $lambda * exp(-$lambda);

        return round(max(0, $p2) * 100, 1);
    }
}
