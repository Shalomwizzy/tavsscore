<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\PredictionLog;

/**
 * Writes market-closing rows to prediction_logs — the real benchmark. The
 * bookmaker consensus is the most calibrated football predictor that exists;
 * every internal model_version is measured relative to it.
 *
 * Idempotent per (match, market, model_version, stage): re-running overwrites
 * the same row rather than duplicating. Stage is always pre_lineup — bookmaker
 * closing lines are set before kickoff, so there is no post_lineup analogue.
 */
class MarketClosingLogger
{
    public const MODEL_VERSION = 'market-closing';

    public function __construct(private readonly OddsService $odds) {}

    /**
     * Fetch normalised implied probabilities and log 1X2 (argmax), Over 2.5,
     * and BTTS market rows. Returns the number of rows written.
     */
    public function logForMatch(FootballMatch $match): int
    {
        if (! $match->match_time || ! $match->api_id) return 0;

        $probs = $this->odds->normalisedImpliedProbabilities($match);
        if (! $probs) return 0;

        $rows = 0;
        foreach ($this->deriveMarkets($probs) as [$market, $outcome, $pOutcome]) {
            if ($pOutcome === null) continue;

            PredictionLog::updateOrCreate(
                [
                    'match_id'         => $match->id,
                    'market'           => $market,
                    'model_version'    => self::MODEL_VERSION,
                    'prediction_stage' => PredictionLog::STAGE_PRE_LINEUP,
                ],
                [
                    'prediction_id'     => null,
                    'league_id'         => $match->league_id,
                    'predicted_outcome' => $outcome,
                    'p_outcome'         => $this->clamp($pOutcome),
                    'p_home'            => $this->clamp($probs['home_win'] ?? null),
                    'p_draw'            => $this->clamp($probs['draw']     ?? null),
                    'p_away'            => $this->clamp($probs['away_win'] ?? null),
                    'is_backfill'       => false,
                    'kickoff_at'        => $match->match_time,
                    'created_at'        => now(),
                ],
            );
            $rows++;
        }
        return $rows;
    }

    /**
     * @return array<int, array{0:string,1:string,2:?float}>
     */
    private function deriveMarkets(array $probs): array
    {
        $markets = [];

        $race = [
            'Home Win' => (float) ($probs['home_win'] ?? 0),
            'Draw'     => (float) ($probs['draw']     ?? 0),
            'Away Win' => (float) ($probs['away_win'] ?? 0),
        ];
        if (max($race) > 0) {
            $argmax = array_search(max($race), $race, true);
            $markets[] = [PredictionLog::MARKET_1X2, $argmax, $race[$argmax]];
        }

        if (($probs['over_25'] ?? null) !== null) {
            $markets[] = [PredictionLog::MARKET_OVER25, 'Over 2.5 Goals', (float) $probs['over_25']];
        }
        if (($probs['btts'] ?? null) !== null) {
            $markets[] = [PredictionLog::MARKET_GG, 'Both Teams Score', (float) $probs['btts']];
        }

        return $markets;
    }

    private function clamp(float|int|null $v): ?float
    {
        if ($v === null) return null;
        return max(0.0, min(1.0, (float) $v));
    }
}
