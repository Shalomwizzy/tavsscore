<?php

namespace App\Support;

use App\Models\Prediction;
use Illuminate\Support\Collection;

class PickHelpers
{
    /**
     * Convert a numeric confidence (%) to a label band with emoji.
     * Returns ['emoji', 'label', 'color'].
     */
    public static function confidenceBand(?int $pct): array
    {
        if ($pct === null) {
            return ['emoji' => '⚪', 'label' => 'Unknown', 'color' => '#6b7280'];
        }
        return match (true) {
            $pct >= 75 => ['emoji' => '🟢', 'label' => 'High',   'color' => '#10b981'],
            $pct >= 60 => ['emoji' => '🟡', 'label' => 'Medium', 'color' => '#f59e0b'],
            default    => ['emoji' => '🔴', 'label' => 'Risky',  'color' => '#ef4444'],
        };
    }

    /**
     * Split a long analysis paragraph into 2-4 short sentences, suitable for a
     * bullet list. Strips the 💡 Tip sentence and any meta phrases.
     */
    public static function reasonBullets(?string $analysis, int $max = 3): array
    {
        if (blank($analysis)) return [];

        // Drop the 💡 Tip sentence — it's already shown elsewhere
        $stripped = preg_replace('/\s*💡\s*Tip:[^.!?]*[.!?]?/iu', '', $analysis);

        // Split on sentence boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/', trim((string) $stripped));
        $sentences = array_filter(array_map('trim', $sentences ?: []));

        // Drop very short or filler-only sentences
        $cleaned = [];
        foreach ($sentences as $s) {
            $s = rtrim($s, '.!?');
            if (mb_strlen($s) < 18) continue;       // too short
            if (mb_strlen($s) > 180) continue;      // too long for a bullet
            $cleaned[] = $s;
            if (count($cleaned) >= $max) break;
        }
        return $cleaned;
    }

    /**
     * Markets we CAN'T currently verify against API-Football's basic fixture
     * payload. These are shown as informational tips with an "unverified" flag
     * so users know the outcome won't appear on the accuracy log.
     *
     * To verify these we'd need to call /fixtures/statistics per match.
     */
    private const UNVERIFIABLE_KEYWORDS = ['Corners', 'Cards', 'Score First', 'Team to Score First'];

    /**
     * Is this market verifiable from the basic fixture endpoint (final + HT score)?
     */
    public static function isVerifiable(?string $market): bool
    {
        if (! $market) return false;
        foreach (self::UNVERIFIABLE_KEYWORDS as $kw) {
            if (str_contains($market, $kw)) return false;
        }
        return true;
    }

    /**
     * Walk resolved daily picks newest-first counting consecutive wins (or losses).
     * Returns ['type' => 'win'|'loss'|'none', 'count' => int, 'best' => int].
     * "best" is the longest historical winning streak across the dataset.
     */
    public static function streakFromResolved(Collection $resolved): array
    {
        if ($resolved->isEmpty()) {
            return ['type' => 'none', 'count' => 0, 'best' => 0];
        }

        // Already passed in newest-first
        $first   = (bool) $resolved->first()->was_correct;
        $type    = $first ? 'win' : 'loss';
        $current = 0;
        foreach ($resolved as $p) {
            if ((bool) $p->was_correct === $first) {
                $current++;
            } else {
                break;
            }
        }

        // Longest historical winning streak (not necessarily current)
        $best = 0;
        $run  = 0;
        foreach ($resolved->reverse() as $p) {
            if ($p->was_correct) {
                $run++;
                $best = max($best, $run);
            } else {
                $run = 0;
            }
        }

        return ['type' => $type, 'count' => $current, 'best' => $best];
    }

    /**
     * Compute whether a prediction was correct from the final match score.
     * Returns true/false, or null if the outcome cannot be determined
     * (match not finished, score missing, unverifiable market, etc.).
     */
    public static function resolveOutcome(Prediction $p): ?bool
    {
        $match = $p->match;
        if (! $match || $match->home_score === null || $match->away_score === null) {
            return null;
        }

        $outcome = $p->predicted_outcome;
        if (! $outcome || $outcome === 'Competitive Match') {
            return null;
        }

        $home    = (int) $match->home_score;
        $away    = (int) $match->away_score;
        $total   = $home + $away;
        $btts    = $home >= 1 && $away >= 1;

        $htHome  = $match->home_score_ht;
        $htAway  = $match->away_score_ht;
        $hasHt   = $htHome !== null && $htAway !== null;
        $htHome  = (int) ($htHome ?? 0);
        $htAway  = (int) ($htAway ?? 0);
        $htTotal = $htHome + $htAway;
        $sh1     = max(0, $home - $htHome) + max(0, $away - $htAway);
        $sh1Btts = ($home - $htHome) >= 1 && ($away - $htAway) >= 1;

        return match ($outcome) {
            'Home Win'                     => $home > $away,
            'Away Win'                     => $away > $home,
            'Draw'                         => $home === $away,
            'Home or Draw (1X)'            => $home >= $away,
            'Draw or Away (X2)'            => $away >= $home,
            'Home or Away (12)'            => $home !== $away,
            'Over 1.5 Goals'               => $total > 1,
            'Under 1.5 Goals'              => $total <= 1,
            'Over 2.5 Goals'               => $total > 2,
            'Under 2.5 Goals'              => $total <= 2,
            'Over 3.5 Goals'               => $total > 3,
            'Under 3.5 Goals'              => $total <= 3,
            'Both Teams Score',
            'Both Teams Score (GG)'        => $btts,
            'No Both Teams Score',
            'No Both Teams Score (NG)'     => ! $btts,
            'Home Win to Nil'              => $home > $away && $away === 0,
            'Away Win to Nil'              => $away > $home && $home === 0,
            'Home Clean Sheet'             => $away === 0,
            'Away Clean Sheet'             => $home === 0,
            'Home Team to Score'           => $home >= 1,
            'Away Team to Score'           => $away >= 1,
            'Home -1 Handicap'             => $home - $away >= 2,
            'Home -2 Handicap'             => $home - $away >= 3,
            'Away +1 Handicap'             => $away + 1 > $home,
            'Away +2 Handicap'             => $away + 2 > $home,
            'Home Lead at HT'              => $hasHt ? $htHome > $htAway : null,
            'Away Lead at HT'              => $hasHt ? $htAway > $htHome : null,
            'Draw at HT'                   => $hasHt ? $htHome === $htAway : null,
            'Over 0.5 First Half'          => $hasHt ? $htTotal >= 1 : null,
            'Over 1.5 First Half'          => $hasHt ? $htTotal >= 2 : null,
            'Both Halves Over 0.5'         => $hasHt ? ($htTotal >= 1 && $sh1 >= 1) : null,
            'Both Halves Over 1.5'         => $hasHt ? ($htTotal >= 2 && $sh1 >= 2) : null,
            'Both Halves BTTS'             => $hasHt ? ($htHome >= 1 && $htAway >= 1 && $sh1Btts) : null,
            default                        => null,
        };
    }

    /**
     * The probability of the predicted outcome itself, expressed as a percentage.
     * For 1X2 outcomes that's the matching probability; for goal markets it's the
     * goal-line probability that triggered the tip.
     */
    public static function confidencePct(Prediction $p): float
    {
        return match ($p->predicted_outcome) {
            'Home Win'         => (float) $p->home_win_prob,
            'Away Win'         => (float) $p->away_win_prob,
            'Draw'             => (float) $p->draw_prob,
            'Over 2.5 Goals'   => (float) ($p->over_25_prob ?? 0),
            'Under 2.5 Goals'  => 100 - (float) ($p->over_25_prob ?? 0),
            'Both Teams Score' => (float) ($p->btts_prob ?? 0),
            default            => max(
                (float) $p->home_win_prob,
                (float) $p->draw_prob,
                (float) $p->away_win_prob,
            ),
        };
    }
}
