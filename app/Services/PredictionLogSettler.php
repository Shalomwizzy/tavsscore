<?php

namespace App\Services;

use App\Models\PredictionLog;
use App\Support\PickHelpers;

/**
 * Settles append-only prediction_logs rows against final match outcomes.
 *
 * Idempotent by construction: only rows with settled_at IS NULL are touched.
 * Re-running the same call is a no-op (verified by feature test).
 */
class PredictionLogSettler
{
    public const FINISHED_STATUSES = ['FT', 'AET', 'PEN'];

    /**
     * Statuses that permanently prevent settlement. VOID rows are excluded
     * from Brier / hit-rate metrics — grading them as losses would distort
     * the baseline against no fault of the prediction.
     */
    public const VOID_STATUSES = ['PST', 'CANC', 'ABD', 'AWD', 'WO'];

    /**
     * @param  int  $sinceDays  Look-back window in days for both settle passes.
     * @return array{settled:int,voided:int}
     */
    public function settleSince(int $sinceDays = 3): array
    {
        $since   = now()->subDays($sinceDays)->startOfDay();
        $settled = 0;
        $voided  = 0;

        // ── WIN / LOSS ──────────────────────────────────────────────────
        // held_for_review excludes blowout scorelines (8+ per side) from
        // settlement until a human confirms — corrupt data would poison
        // Brier / hit-rate metrics for the model_version it lands under.
        PredictionLog::query()
            ->with('match')
            ->whereNull('settled_at')
            ->whereHas('match', fn ($q) => $q
                ->whereIn('status', self::FINISHED_STATUSES)
                ->whereNotNull('home_score')
                ->whereNotNull('away_score')
                ->where('match_time', '>=', $since)
                ->where('held_for_review', false),
            )
            ->chunkById(500, function ($chunk) use (&$settled) {
                foreach ($chunk as $log) {
                    $result = $this->resolve($log);
                    if ($result === null) {
                        continue;
                    }
                    $log->update([
                        'actual_result' => $result,
                        'settled_at'    => now(),
                    ]);
                    $settled++;
                }
            });

        // ── VOID ────────────────────────────────────────────────────────
        PredictionLog::query()
            ->whereNull('settled_at')
            ->whereHas('match', fn ($q) => $q
                ->whereIn('status', self::VOID_STATUSES)
                ->where('match_time', '>=', $since),
            )
            ->chunkById(500, function ($chunk) use (&$voided) {
                foreach ($chunk as $log) {
                    $log->update([
                        'actual_result' => PredictionLog::RESULT_VOID,
                        'settled_at'    => now(),
                    ]);
                    $voided++;
                }
            });

        return ['settled' => $settled, 'voided' => $voided];
    }

    /**
     * Resolve a single log to WIN, LOSS, or null (cannot determine yet).
     * PickHelpers handles the 1X2, draw, over/under, BTTS, double-chance markets.
     * Team-goals markets (Home 3+ style) resolve inline here — PickHelpers doesn't
     * know their outcome strings.
     */
    private function resolve(PredictionLog $log): ?string
    {
        if ($log->market === PredictionLog::MARKET_TEAM3PLUS) {
            return $this->resolveTeamGoals($log);
        }

        if ($log->market === PredictionLog::MARKET_CORRECT_SCORE) {
            return $this->resolveCorrectScore($log);
        }

        $outcome = PickHelpers::resolveForMatch($log->match, $log->predicted_outcome);
        if ($outcome === null) {
            return null;
        }
        return $outcome ? PredictionLog::RESULT_WIN : PredictionLog::RESULT_LOSS;
    }

    /**
     * Correct-score is a single-scoreline forecast — WIN only if the actual
     * FT score matches the string exactly ("2-1" == "2-1").
     */
    private function resolveCorrectScore(PredictionLog $log): ?string
    {
        $match = $log->match;
        if (! $match || $match->home_score === null || $match->away_score === null) return null;

        $actual = ((int) $match->home_score) . '-' . ((int) $match->away_score);
        return $actual === $log->predicted_outcome
            ? PredictionLog::RESULT_WIN
            : PredictionLog::RESULT_LOSS;
    }

    private function resolveTeamGoals(PredictionLog $log): ?string
    {
        $match = $log->match;
        if (! $match || $match->home_score === null || $match->away_score === null) {
            return null;
        }

        $label     = $log->predicted_outcome;
        $isHome    = str_starts_with($label, 'Home');
        $threshold = str_ends_with($label, '2+') ? 2 : 3;
        $goals     = $isHome ? (int) $match->home_score : (int) $match->away_score;

        return $goals >= $threshold ? PredictionLog::RESULT_WIN : PredictionLog::RESULT_LOSS;
    }
}
