<?php

namespace App\Observers;

use App\Models\Prediction;
use App\Services\PredictionLogger;
use Illuminate\Support\Facades\Log;

/**
 * Bridges the operational Prediction table to the measurement log.
 *
 * `created`: log the prediction as pre_lineup or post_lineup depending on
 *   has_lineup at creation time.
 * `updated`: only log again if has_lineup flipped false → true — that's the
 *   lineup-rerun transition and it materializes a distinct post_lineup row
 *   alongside the original pre_lineup one, so calibration can compare like
 *   for like.
 *
 * All other updates (was_correct, pick flag flips, notification bookkeeping)
 * do not re-log: the pick-flag flips are captured because deriveMarkets()
 * enumerates all active markets at the time of the original create.
 * If a pick flag flips true *after* creation, it is not currently captured —
 * that's a Phase 1 known limitation; today's pipeline sets all pick flags in
 * the same save as prediction creation, so the miss is rare in practice.
 */
class PredictionObserver
{
    public function __construct(private readonly PredictionLogger $logger) {}

    public function created(Prediction $prediction): void
    {
        $this->safeLog($prediction);
    }

    public function updated(Prediction $prediction): void
    {
        if ($prediction->wasChanged('has_lineup') && $prediction->has_lineup) {
            $this->safeLog($prediction);
            return;
        }

        // Capture pick-flag transitions that happen after initial creation
        // (rare but possible). Only re-log when a market's pick flag just
        // flipped true — deriveMarkets() will now include it.
        $pickFlags = [
            'is_draw_pick', 'is_gg_pick', 'is_over15_pick', 'is_over25_pick',
            'is_double_chance_pick', 'is_team3plus_pick', 'is_under35_pick',
            'is_under45_pick', 'is_handicap_pick', 'is_european_handicap_pick',
        ];
        foreach ($pickFlags as $flag) {
            if ($prediction->wasChanged($flag) && $prediction->{$flag}) {
                $this->safeLog($prediction);
                return;
            }
        }
    }

    private function safeLog(Prediction $prediction): void
    {
        try {
            $this->logger->logLive($prediction);
        } catch (\Throwable $e) {
            Log::error('PredictionObserver: logLive failed', [
                'prediction_id' => $prediction->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
