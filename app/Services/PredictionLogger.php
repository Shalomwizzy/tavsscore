<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\PredictionLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Single write entry point for the append-only measurement log.
 *
 * Called from the Prediction observer (real-time, is_backfill=false) and by
 * predictions:seed-logs (retroactive, is_backfill=true). Reads probabilities
 * straight off the passed Prediction instance — never recomputes — so the
 * measurement log stays byte-identical to what the operational store shows.
 *
 * Guardrail: refuses to log a real-time prediction whose kickoff has already
 * passed. A prediction made after kickoff is either a bug or a leak, and it
 * would silently inflate accuracy metrics. Backfill callers must opt in via
 * $isBackfill=true.
 */
class PredictionLogger
{
    /**
     * Baseline model_version. Naming reflects reality: 1X2 numbers come from
     * Groq; O2.5/BTTS are 50/50 blends with the internal Poisson model. If DC
     * ships and beats this, the claim is "DC beats our Groq-Poisson hybrid" —
     * not "stats beat LLMs."
     */
    public const VERSION_BASELINE = 'groq-poisson-v0';

    /**
     * Applied when Dixon-Coles has taken over the 1X2 numbers for a match
     * (see config('prediction.dc_1x2_leagues')). Predictions in DC-eligible
     * leagues are logged under this version so the dashboard tracks live DC
     * performance separately from the pure-hybrid baseline.
     */
    public const VERSION_DC_HYBRID = 'dc-hybrid-v1';

    /**
     * Real-time observer entry point. Determines stage from the current
     * has_lineup flag on the Prediction, and picks the right model_version
     * based on whether DC is enabled for this match's league. Enforces
     * the kickoff guard.
     */
    public function logLive(Prediction $p, ?CarbonInterface $now = null): int
    {
        $stage = $p->has_lineup
            ? PredictionLog::STAGE_POST_LINEUP
            : PredictionLog::STAGE_PRE_LINEUP;

        return $this->log($p, $this->activeVersion($p), $stage, isBackfill: false, now: $now);
    }

    /**
     * dc-hybrid-v1 if this Prediction's league is in the DC-enabled 1X2 set
     * at write time, otherwise the pure Groq+Poisson baseline. Read straight
     * off the prediction's related match to avoid a stale value.
     */
    private function activeVersion(Prediction $p): string
    {
        if (! config('prediction.dc_enabled')) return self::VERSION_BASELINE;

        $match = $p->relationLoaded('match') ? $p->match : \App\Models\FootballMatch::find($p->match_id);
        if (! $match || ! $match->league_id) return self::VERSION_BASELINE;

        return in_array((int) $match->league_id, (array) config('prediction.dc_1x2_leagues'), true)
            ? self::VERSION_DC_HYBRID
            : self::VERSION_BASELINE;
    }

    /**
     * Backfill entry point. Skips the kickoff guard and stamps is_backfill=true.
     */
    public function logBackfill(Prediction $p, ?CarbonInterface $now = null): int
    {
        $stage = $p->has_lineup
            ? PredictionLog::STAGE_POST_LINEUP
            : PredictionLog::STAGE_PRE_LINEUP;

        return $this->log($p, self::VERSION_BASELINE, $stage, isBackfill: true, now: $now);
    }

    public function log(
        Prediction $p,
        string $modelVersion,
        string $stage,
        bool $isBackfill,
        ?CarbonInterface $now = null,
    ): int {
        if (! in_array($stage, [PredictionLog::STAGE_PRE_LINEUP, PredictionLog::STAGE_POST_LINEUP], true)) {
            throw new InvalidArgumentException("Invalid prediction_stage: {$stage}");
        }

        $match = $p->relationLoaded('match') ? $p->match : FootballMatch::find($p->match_id);
        if (! $match || ! $match->match_time) {
            return 0;
        }

        $now     = $now ?? now();
        $kickoff = $match->match_time;

        // Kickoff guard — the exact class of accidentally-cheating metrics
        // (post-kickoff predictions counted as if made pre-kickoff) that pollutes
        // every downstream comparison. Only backfill callers may bypass.
        if (! $isBackfill && $now->gte($kickoff)) {
            Log::warning('PredictionLogger: refusing to log post-kickoff prediction', [
                'prediction_id' => $p->id,
                'match_id'      => $p->match_id,
                'kickoff_at'    => $kickoff->toIso8601String(),
                'now'           => $now->toIso8601String(),
            ]);
            return 0;
        }

        $rows = 0;
        foreach ($this->deriveMarkets($p) as [$market, $outcome, $pOutcome]) {
            if ($pOutcome === null) {
                continue;
            }

            PredictionLog::updateOrCreate(
                [
                    'match_id'         => $p->match_id,
                    'market'           => $market,
                    'model_version'    => $modelVersion,
                    'prediction_stage' => $stage,
                ],
                [
                    'prediction_id'     => $p->id,
                    'league_id'         => $match->league_id,
                    'predicted_outcome' => $outcome,
                    'p_outcome'         => $this->clampProb($pOutcome),
                    'p_home'            => $this->clampProb($p->home_win_prob / 100),
                    'p_draw'            => $this->clampProb($p->draw_prob / 100),
                    'p_away'            => $this->clampProb($p->away_win_prob / 100),
                    'is_backfill'       => $isBackfill,
                    'kickoff_at'        => $kickoff,
                    'created_at'        => $isBackfill ? $p->created_at : $now,
                ],
            );
            $rows++;
        }

        return $rows;
    }

    /**
     * Map a Prediction's active pick flags to (market, outcome, probability) tuples.
     * Skips correct_score (non-binary) and over35 (no pick flag) — measured later.
     *
     * @return array<int, array{0:string,1:string,2:?float}>
     */
    private function deriveMarkets(Prediction $p): array
    {
        $markets = [];

        // Always log the argmax 1X2 forecast — even when the operational
        // predicted_outcome is a goal or double-chance market. This avoids
        // selection bias in calibration: we measure the model on every
        // 1X2 forecast it produces, not just the ones promoted to picks.
        $probs = [
            'Home Win' => (float) $p->home_win_prob,
            'Draw'     => (float) $p->draw_prob,
            'Away Win' => (float) $p->away_win_prob,
        ];
        if (max($probs) > 0) {
            $argmax = array_search(max($probs), $probs, true);
            $markets[] = [PredictionLog::MARKET_1X2, $argmax, $probs[$argmax] / 100];
        }

        // Global BTTS / Over-2.5 forecasts: log unconditionally when the model
        // emitted a probability. Same anti-bias reason as 1X2.
        if ($p->btts_prob !== null) {
            $markets[] = [PredictionLog::MARKET_GG, 'Both Teams Score', $p->btts_prob / 100];
        }
        if ($p->over_25_prob !== null) {
            $markets[] = [PredictionLog::MARKET_OVER25, 'Over 2.5 Goals', $p->over_25_prob / 100];
        }
        if ($p->over_15_prob !== null) {
            $markets[] = [PredictionLog::MARKET_OVER15, 'Over 1.5 Goals', $p->over_15_prob / 100];
        }
        if ($p->over_35_prob !== null) {
            $markets[] = [PredictionLog::MARKET_OVER35, 'Over 3.5 Goals', $p->over_35_prob / 100];
        }

        // Pick-specific markets: only log when a pick flag was set — these
        // represent editorialised choices, not raw model output.
        if ($p->is_draw_pick) {
            $markets[] = [PredictionLog::MARKET_DRAW, 'Draw', $p->draw_prob / 100];
        }

        // Correct-score picks: log the model's top-ranked scoreline as a
        // single-outcome forecast. Multiple-score portfolio picks are messy
        // for Brier scoring — the top score is the cleanest single event.
        if ($p->is_correct_score_pick && is_array($p->likely_scores) && ! empty($p->likely_scores)) {
            $top = collect($p->likely_scores)
                ->sortByDesc(fn ($s) => $s['probability'] ?? 0)
                ->first();
            $scoreStr = $top['score'] ?? null;
            $prob     = isset($top['probability']) ? ((float) $top['probability']) / 100 : null;
            if ($scoreStr && $prob !== null) {
                $markets[] = [PredictionLog::MARKET_CORRECT_SCORE, $scoreStr, $prob];
            }
        }

        if ($p->is_double_chance_pick) {
            $label   = $p->double_chance_label ?: '1X';
            $outcome = $label === '1X' ? 'Home or Draw (1X)' : 'Draw or Away (X2)';
            $prob    = $label === '1X'
                ? ($p->home_win_prob + $p->draw_prob)
                : ($p->draw_prob    + $p->away_win_prob);
            $markets[] = [PredictionLog::MARKET_DOUBLE_CHANCE, $outcome, $prob / 100];
        }

        if ($p->is_team3plus_pick) {
            $label     = $p->team3plus_label ?: 'Home 3+';
            $isHome    = str_starts_with($label, 'Home');
            $threshold = str_ends_with($label, '2+') ? '2plus' : '3plus';
            $key       = ($isHome ? 'home_' : 'away_') . $threshold . '_prob';
            $prob      = $p->{$key} ?? null;
            if ($prob !== null) {
                $markets[] = [PredictionLog::MARKET_TEAM3PLUS, $label, $prob / 100];
            }
        }

        return $markets;
    }

    private function clampProb(float|int|null $v): float
    {
        if ($v === null) return 0.0;
        return max(0.0, min(1.0, (float) $v));
    }
}
