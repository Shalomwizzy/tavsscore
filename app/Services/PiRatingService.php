<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\TeamPiRating;
use Illuminate\Support\Facades\Log;

/**
 * Pi-ratings: dynamic team strength ratings with separate home/away components.
 *
 * Unlike Elo (which only considers win/draw/loss), pi-ratings update based on
 * the actual goal difference vs the expected goal difference derived from the
 * current ratings. Separate home/away ratings capture venue-specific performance.
 *
 * After every resolved match the ratings for both teams update using:
 *   new_rating = old_rating + K × (actual_goal_diff - expected_goal_diff)
 *
 * Based on: Constantinou & Fenton (2012) — demonstrated profitability vs Elo
 * over 5 EPL seasons. CatBoost + pi-ratings achieved the best-ever Soccer
 * Prediction Challenge result (RPS 0.1925, accuracy 55.82%).
 */
class PiRatingService
{
    // Learning rate — how aggressively ratings update per match.
    // Higher K = faster adaptation but more noise. 0.075 is empirically stable.
    private const K = 0.075;

    // Starting rating for new teams with no history.
    private const DEFAULT_RATING = 0.0;

    // Maximum credible single-match goal difference to prevent wild swings
    // from thrashings (e.g. 8-0 would otherwise dominate recent ratings).
    private const MAX_GOAL_DIFF = 4;

    /**
     * Update pi-ratings for both teams after a resolved match.
     * Called from fetch:matches whenever a match transitions to FT/AET/PEN.
     */
    public function updateForMatch(FootballMatch $match): void
    {
        if (! in_array($match->status, ['FT', 'AET', 'PEN'], true)) return;
        if ($match->home_score === null || $match->away_score === null) return;

        $home = $match->home_team;
        $away = $match->away_team;

        $homeRating = TeamPiRating::firstOrCreate(['team' => $home], [
            'pi_home' => self::DEFAULT_RATING, 'pi_away' => self::DEFAULT_RATING,
        ]);
        $awayRating = TeamPiRating::firstOrCreate(['team' => $away], [
            'pi_home' => self::DEFAULT_RATING, 'pi_away' => self::DEFAULT_RATING,
        ]);

        // Expected goal difference: positive means home team expected to outscore away
        $expectedDiff = $homeRating->pi_home - $awayRating->pi_away;

        $actualGoalDiff = (int) $match->home_score - (int) $match->away_score;
        // Clamp to prevent 7-0 thrashings from dominating the rating
        $clampedDiff = max(-self::MAX_GOAL_DIFF, min(self::MAX_GOAL_DIFF, $actualGoalDiff));

        $error = $clampedDiff - $expectedDiff;

        // Home team: update pi_home (they were playing at home)
        $homeRating->update([
            'pi_home'       => round($homeRating->pi_home + self::K * $error, 4),
            'matches_rated' => $homeRating->matches_rated + 1,
            'last_match_at' => $match->match_time,
        ]);

        // Away team: update pi_away (they were playing away)
        $awayRating->update([
            'pi_away'       => round($awayRating->pi_away - self::K * $error, 4),
            'matches_rated' => $awayRating->matches_rated + 1,
            'last_match_at' => $match->match_time,
        ]);
    }

    /**
     * Pi-rating differential for an upcoming match.
     * Positive = home team stronger, negative = away team stronger.
     * Used as an additional feature in the Groq prompt and prediction scoring.
     */
    public function differential(string $homeTeam, string $awayTeam): float
    {
        $home = TeamPiRating::where('team', $homeTeam)->first();
        $away = TeamPiRating::where('team', $awayTeam)->first();

        $homePi = $home?->pi_home ?? self::DEFAULT_RATING;
        $awayPi = $away?->pi_away ?? self::DEFAULT_RATING;

        return round($homePi - $awayPi, 3);
    }

    /**
     * Get both teams' ratings for display/prompt context.
     */
    public function ratingsFor(string $homeTeam, string $awayTeam): array
    {
        $home = TeamPiRating::where('team', $homeTeam)->first();
        $away = TeamPiRating::where('team', $awayTeam)->first();

        return [
            'home_pi'   => round($home?->pi_home ?? self::DEFAULT_RATING, 3),
            'away_pi'   => round($away?->pi_away ?? self::DEFAULT_RATING, 3),
            'diff'      => $this->differential($homeTeam, $awayTeam),
            'home_games'=> $home?->matches_rated ?? 0,
            'away_games'=> $away?->matches_rated ?? 0,
        ];
    }

    /**
     * Rebuild all ratings from scratch using historical match data.
     * Run once on deployment, then incremental updates keep it current.
     */
    public function rebuildFromHistory(): int
    {
        TeamPiRating::truncate();

        $matches = FootballMatch::query()
            ->whereIn('status', ['FT', 'AET', 'PEN'])
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->orderBy('match_time')
            ->get();

        $count = 0;
        foreach ($matches as $match) {
            $this->updateForMatch($match);
            $count++;
        }

        Log::info("PiRatingService: rebuilt ratings from {$count} matches.");
        return $count;
    }
}
