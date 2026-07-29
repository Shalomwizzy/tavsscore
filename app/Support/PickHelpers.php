<?php

namespace App\Support;

use App\Models\FixtureStatistic;
use App\Models\FootballMatch;
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
     * Markets excluded from automatic "safest board pick" selection: they can
     * void on a tie (no clean win/loss for a single selection) or carry too
     * much variance to be treated as the platform's headline safe pick.
     */
    private const SAFE_PICK_BLOCK = [
        'Home Most Corners', 'Away Most Corners',
        'Draw No Bet - Home', 'Draw No Bet - Away',   // valid legs, but push-prone; excluded from headline auto-pick
    ];

    /**
     * On top of the tie/void blocklist, the headline pick also skips markets
     * that are near-certain but carry no betting value ("Over 0.5", "Under 5.5",
     * blanket double-chance on a mismatch) — otherwise every fixture's headline
     * would collapse onto the same trivial market. Rollover keeps these (survival
     * beats value there); the user-facing headline wants a real, playable edge.
     */
    private const HEADLINE_TRIVIAL = [
        'Over 0.5 Goals', 'Under 5.5 Goals', 'Over 4.5 Goals',
        'Home or Away (12)', 'Total Goals Odd', 'Total Goals Even',
    ];

    public static function headlineBlock(): array
    {
        return array_merge(self::SAFE_PICK_BLOCK, self::HEADLINE_TRIVIAL);
    }

    /**
     * The single source of truth for "which market does the platform play on
     * this fixture". Scans the ENTIRE stored market board and returns the
     * highest-probability market that (a) clears the floor and (b) isn't in the
     * tie/void blocklist. Returns ['market' => label, 'prob' => float] or null.
     *
     * Used by rollover, daily-pick selection, and any surface that needs the
     * safest option — so the whole site plays off the same 103-market board.
     */
    public static function safestBoardMarket(?array $board, float $minProb = 90.0, array $block = self::SAFE_PICK_BLOCK): ?array
    {
        if (empty($board) || ! is_array($board)) {
            return null;
        }

        $best = null;
        foreach ($board as $market => $prob) {
            if (! is_string($market) || in_array($market, $block, true)) {
                continue;
            }
            $prob = (float) $prob;
            if ($prob < $minProb) {
                continue;
            }
            if ($best === null || $prob > $best['prob']) {
                $best = ['market' => $market, 'prob' => $prob];
            }
        }

        return $best;
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
        return self::resolveForMatch($match, $p->predicted_outcome);
    }

    /**
     * Resolve an arbitrary outcome string against a finished match.
     * Used by rollover picks which may have a different tip than the
     * linked prediction's predicted_outcome (e.g. pick was "Over 2.5 Goals"
     * but the prediction was later updated to "Away Win" by lineup regeneration).
     */
    public static function resolveForMatch(?FootballMatch $match, ?string $outcome): ?bool
    {
        if (! $match || $match->home_score === null || $match->away_score === null) {
            return null;
        }

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

        // Corners & cards are graded from post-match statistics, not the score.
        if (str_contains($outcome, 'Corners') || str_contains($outcome, 'Cards')) {
            return self::resolveEventMarket($match, $outcome);
        }

        $diff    = $home - $away;
        $shTotal = $sh1;                                   // second-half total goals
        $ftSign  = $home > $away ? 'H' : ($home === $away ? 'D' : 'A');
        $htSign  = $hasHt ? ($htHome > $htAway ? 'H' : ($htHome === $htAway ? 'D' : 'A')) : null;

        // Asian half-goal handicaps never push. This accepts every published
        // Home/Away +/- 0.5 through 5.5 line without maintaining fragile cases.
        if (preg_match('/^(Home|Away) ([+-])(0\.5|1\.5|2\.5|3\.5|4\.5|5\.5) \(Handicap\)$/', $outcome, $parts)) {
            $teamMargin = $parts[1] === 'Home' ? $diff : -$diff;
            $line = (float) $parts[3];
            return $parts[2] === '+' ? $teamMargin + $line > 0 : $teamMargin - $line > 0;
        }
        if (preg_match('/^European Handicap ([0-5]):([0-5]) - (Home|Draw|Away)$/', $outcome, $parts)) {
            $adjustedMargin = $diff + (int) $parts[1] - (int) $parts[2];
            return match ($parts[3]) {
                'Home' => $adjustedMargin > 0,
                'Draw' => $adjustedMargin === 0,
                'Away' => $adjustedMargin < 0,
            };
        }

        return match ($outcome) {
            // ── 1X2 / double chance / DNB ──
            'Home Win'                     => $home > $away,
            'Away Win'                     => $away > $home,
            'Draw'                         => $home === $away,
            'Home or Draw (1X)'            => $home >= $away,
            'Draw or Away (X2)'            => $away >= $home,
            'Home or Away (12)'            => $home !== $away,
            'Draw No Bet - Home'           => $home === $away ? null : $home > $away,  // null = void on a draw
            'Draw No Bet - Away'           => $home === $away ? null : $away > $home,

            // ── Total goals over/under ──
            'Over 0.5 Goals'               => $total > 0,
            'Under 0.5 Goals'              => $total === 0,
            'Over 1.5 Goals'               => $total > 1,
            'Under 1.5 Goals'              => $total <= 1,
            'Over 2.5 Goals'               => $total > 2,
            'Under 2.5 Goals'              => $total <= 2,
            'Over 3.5 Goals'               => $total > 3,
            'Under 3.5 Goals'              => $total <= 3,
            'Over 4.5 Goals'               => $total > 4,
            'Under 4.5 Goals'              => $total <= 4,
            'Over 5.5 Goals'               => $total > 5,
            'Under 5.5 Goals'              => $total <= 5,

            // ── Odd / even ──
            'Total Goals Odd'              => $total % 2 === 1,
            'Total Goals Even'             => $total % 2 === 0,

            // ── Exact total goals & multigoals ──
            'Exactly 0 Goals'              => $total === 0,
            'Exactly 1 Goal'               => $total === 1,
            'Exactly 2 Goals'              => $total === 2,
            'Exactly 3 Goals'              => $total === 3,
            '4+ Goals'                     => $total >= 4,
            '1-2 Goals'                    => $total >= 1 && $total <= 2,
            '1-3 Goals'                    => $total >= 1 && $total <= 3,
            '2-3 Goals'                    => $total >= 2 && $total <= 3,
            '2-4 Goals'                    => $total >= 2 && $total <= 4,
            '3-4 Goals'                    => $total >= 3 && $total <= 4,

            // ── BTTS / defence ──
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

            // ── Team totals & exact team goals ──
            'Home Over 0.5'                => $home >= 1,
            'Home Over 1.5'                => $home >= 2,
            'Home Over 2.5'                => $home >= 3,
            'Away Over 0.5'                => $away >= 1,
            'Away Over 1.5'                => $away >= 2,
            'Away Over 2.5'                => $away >= 3,
            'Home Exactly 0'               => $home === 0,
            'Home Exactly 1'               => $home === 1,
            'Home Exactly 2'               => $home === 2,
            'Home 3+ Goals'                => $home >= 3,
            'Away Exactly 0'               => $away === 0,
            'Away Exactly 1'               => $away === 1,
            'Away Exactly 2'               => $away === 2,
            'Away 3+ Goals'                => $away >= 3,

            // ── Handicaps (.5 lines — no push) ──
            'Home -1.5 (Handicap)'         => $diff >= 2,
            'Home +1.5 (Handicap)'         => $diff >= -1,
            'Away -1.5 (Handicap)'         => -$diff >= 2,
            'Away +1.5 (Handicap)'         => -$diff >= -1,
            'Home -2.5 (Handicap)'         => $diff >= 3,
            'Away -2.5 (Handicap)'         => -$diff >= 3,
            'Home +3.5 (Handicap)'         => $diff >= -3,
            'Away +3.5 (Handicap)'         => -$diff >= -3,
            'Home +4.5 (Handicap)'         => $diff >= -4,
            'Away +4.5 (Handicap)'         => -$diff >= -4,
            // Legacy whole-goal handicap labels (void on exact line handled as loss upstream)
            'Home -1 Handicap'             => $diff >= 2,
            'Home -2 Handicap'             => $diff >= 3,
            'Away +1 Handicap'             => $away + 1 > $home,
            'Away +2 Handicap'             => $away + 2 > $home,

            // ── Winning margin ──
            'Home to win by 1'             => $diff === 1,
            'Home to win by 2'             => $diff === 2,
            'Home to win by 3+'            => $diff >= 3,
            'Away to win by 1'             => -$diff === 1,
            'Away to win by 2'             => -$diff === 2,
            'Away to win by 3+'            => -$diff >= 3,

            // ── Result + over/under 2.5 combos ──
            'Home & Over 2.5'              => $home > $away && $total > 2,
            'Home & Under 2.5'             => $home > $away && $total <= 2,
            'Draw & Over 2.5'              => $home === $away && $total > 2,
            'Draw & Under 2.5'             => $home === $away && $total <= 2,
            'Away & Over 2.5'              => $away > $home && $total > 2,
            'Away & Under 2.5'             => $away > $home && $total <= 2,

            // ── Result + BTTS combos ──
            'Home & BTTS'                  => $home > $away && $btts,
            'Home & No BTTS'               => $home > $away && ! $btts,
            'Away & BTTS'                  => $away > $home && $btts,
            'Away & No BTTS'               => $away > $home && ! $btts,
            'Draw & BTTS'                  => $home === $away && $btts,

            // ── BTTS + over/under combos ──
            'BTTS & Over 2.5'              => $btts && $total > 2,
            'BTTS & Under 2.5'             => $btts && $total <= 2,
            'No BTTS & Over 2.5'           => ! $btts && $total > 2,
            'No BTTS & Under 2.5'          => ! $btts && $total <= 2,

            // ── Half-time ──
            'HT Home Win'                  => $hasHt ? $htHome > $htAway : null,
            'HT Draw'                      => $hasHt ? $htHome === $htAway : null,
            'HT Away Win'                  => $hasHt ? $htAway > $htHome : null,
            'HT Over 0.5'                  => $hasHt ? $htTotal > 0 : null,
            'HT Under 0.5'                 => $hasHt ? $htTotal === 0 : null,
            'HT Over 1.5'                  => $hasHt ? $htTotal > 1 : null,
            'HT Under 1.5'                 => $hasHt ? $htTotal <= 1 : null,
            'HT Both Teams Score'          => $hasHt ? ($htHome >= 1 && $htAway >= 1) : null,
            'Home Lead at HT'              => $hasHt ? $htHome > $htAway : null,
            'Away Lead at HT'              => $hasHt ? $htAway > $htHome : null,
            'Draw at HT'                   => $hasHt ? $htHome === $htAway : null,
            'Over 0.5 First Half'          => $hasHt ? $htTotal >= 1 : null,
            'Over 1.5 First Half'          => $hasHt ? $htTotal >= 2 : null,

            // ── Halves ──
            'Both Halves Over 0.5',
            'Goal in Both Halves'          => $hasHt ? ($htTotal >= 1 && $shTotal >= 1) : null,
            'Both Halves Over 1.5'         => $hasHt ? ($htTotal >= 2 && $shTotal >= 2) : null,
            'Both Halves BTTS'             => $hasHt ? ($htHome >= 1 && $htAway >= 1 && $sh1Btts) : null,
            '1st Half More Goals'          => $hasHt ? $htTotal > $shTotal : null,
            '2nd Half More Goals'          => $hasHt ? $shTotal > $htTotal : null,
            'Equal Goals Each Half'        => $hasHt ? $htTotal === $shTotal : null,

            // ── HT/FT (nine combinations) ──
            'HT/FT Home/Home'              => $hasHt ? ($htSign === 'H' && $ftSign === 'H') : null,
            'HT/FT Home/Draw'             => $hasHt ? ($htSign === 'H' && $ftSign === 'D') : null,
            'HT/FT Home/Away'             => $hasHt ? ($htSign === 'H' && $ftSign === 'A') : null,
            'HT/FT Draw/Home'             => $hasHt ? ($htSign === 'D' && $ftSign === 'H') : null,
            'HT/FT Draw/Draw'             => $hasHt ? ($htSign === 'D' && $ftSign === 'D') : null,
            'HT/FT Draw/Away'             => $hasHt ? ($htSign === 'D' && $ftSign === 'A') : null,
            'HT/FT Away/Home'             => $hasHt ? ($htSign === 'A' && $ftSign === 'H') : null,
            'HT/FT Away/Draw'             => $hasHt ? ($htSign === 'A' && $ftSign === 'D') : null,
            'HT/FT Away/Away'             => $hasHt ? ($htSign === 'A' && $ftSign === 'A') : null,

            default                        => null,
        };
    }

    /**
     * Grade a corners / cards market from post-match fixture statistics.
     * Returns null when we don't have the stats yet (never a false loss).
     */
    private static function resolveEventMarket(FootballMatch $match, string $outcome): ?bool
    {
        $stats = FixtureStatistic::query()->where('match_id', $match->id)->get();
        if ($stats->count() < 2) {
            return null;   // stats not fetched yet — ungradeable, leave pending
        }

        $homeStat = $stats->firstWhere('team_name', $match->home_team) ?? $stats->first();
        $awayStat = $stats->firstWhere('team_name', $match->away_team) ?? $stats->last();

        $homeCorners = (int) ($homeStat->corners ?? 0);
        $awayCorners = (int) ($awayStat->corners ?? 0);
        $totalCorners = $homeCorners + $awayCorners;
        $totalCards = (int) ($homeStat->yellow_cards ?? 0) + (int) ($homeStat->red_cards ?? 0)
            + (int) ($awayStat->yellow_cards ?? 0) + (int) ($awayStat->red_cards ?? 0);
        $totalReds = (int) ($homeStat->red_cards ?? 0) + (int) ($awayStat->red_cards ?? 0);

        return match ($outcome) {
            'Over 8.5 Corners'   => $totalCorners > 8,   'Under 8.5 Corners'  => $totalCorners <= 8,
            'Over 9.5 Corners'   => $totalCorners > 9,   'Under 9.5 Corners'  => $totalCorners <= 9,
            'Over 10.5 Corners'  => $totalCorners > 10,  'Under 10.5 Corners' => $totalCorners <= 10,
            'Over 11.5 Corners'  => $totalCorners > 11,  'Under 11.5 Corners' => $totalCorners <= 11,
            'Home Over 4.5 Corners' => $homeCorners > 4,
            'Away Over 4.5 Corners' => $awayCorners > 4,
            'Home Most Corners'  => $homeCorners === $awayCorners ? null : $homeCorners > $awayCorners,
            'Away Most Corners'  => $homeCorners === $awayCorners ? null : $awayCorners > $homeCorners,
            'Over 2.5 Cards'     => $totalCards > 2,  'Under 2.5 Cards' => $totalCards <= 2,
            'Over 3.5 Cards'     => $totalCards > 3,  'Under 3.5 Cards' => $totalCards <= 3,
            'Over 4.5 Cards'     => $totalCards > 4,  'Under 4.5 Cards' => $totalCards <= 4,
            'Over 5.5 Cards'     => $totalCards > 5,  'Under 5.5 Cards' => $totalCards <= 5,
            'Over 0.5 Red Cards' => $totalReds > 0,
            default              => null,
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
