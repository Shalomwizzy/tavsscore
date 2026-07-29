<?php

namespace App\Services;

use App\Models\Prediction;
use App\Models\PredictionLog;
use Illuminate\Support\Facades\Cache;

/**
 * The operational publication gate.
 *
 * A high raw model probability is not automatically proof of an edge. This
 * service uses settled, like-for-like prediction logs to detect a confidence
 * band that is demonstrably over-confident. When opening odds were captured,
 * it also requires a modest model edge before a supported market is promoted.
 *
 * New markets are deliberately allowed in "shadow" mode until there is enough
 * settled evidence to judge them. That avoids both false certainty and the
 * opposite failure mode of blocking every new market forever.
 */
class PublicationQualityService
{
    public const MIN_CALIBRATION_SAMPLE = 30;

    public const MIN_LEAGUE_SAMPLE = 15;

    public const MAX_OVERCONFIDENCE = 0.07;

    public const MIN_EDGE = 0.03;

    /**
     * Evaluate a raw probability against historical calibration and any
     * already-captured opening odds. It never fetches odds, so select commands
     * cannot burn API quota merely by applying the gate.
     *
     * @return array{allowed:bool,status:string,market:string,probability:float,calibration_sample:int,calibration_realized:?float,calibration_gap:?float,edge:?float,reasons:array<int,string>}
     */
    public function evaluate(Prediction $prediction, string $market, float $probability, ?string $outcome = null): array
    {
        $probability = max(0.0, min(1.0, $probability));
        $stage = $prediction->has_lineup
            ? PredictionLog::STAGE_POST_LINEUP
            : PredictionLog::STAGE_PRE_LINEUP;
        $version = $this->versionFor($prediction);
        $leagueId = $prediction->match?->league_id;
        $history = $this->calibrationFor($version, $stage, $market, $leagueId, $probability);

        $allowed = true;
        $status = $history['sample'] >= self::MIN_CALIBRATION_SAMPLE ? 'measured' : 'shadow';
        $reasons = [];

        if ($history['sample'] >= self::MIN_CALIBRATION_SAMPLE && $history['gap'] !== null) {
            if ($history['gap'] < -self::MAX_OVERCONFIDENCE) {
                $allowed = false;
                $status = 'held';
                $reasons[] = 'Held: this market confidence band is historically over-confident.';
            } else {
                $reasons[] = 'Historical calibration is within the publication tolerance.';
            }
        } else {
            $reasons[] = 'Shadow market: not enough settled like-for-like results to prove this confidence band yet.';
        }

        $edge = $this->edgeFromCapturedOdds($prediction, $market, $outcome, $probability);
        if ($edge !== null) {
            if ($edge < self::MIN_EDGE) {
                $allowed = false;
                $status = 'held';
                $reasons[] = 'Held: model edge versus captured bookmaker consensus is below 3 percentage points.';
            } else {
                $reasons[] = 'Captured bookmaker consensus leaves a positive model edge.';
            }
        } else {
            $reasons[] = 'No comparable captured odds for this exact market; no odds-edge claim is made.';
        }

        return [
            'allowed' => $allowed,
            'status' => $status,
            'market' => $market,
            'probability' => $probability,
            'calibration_sample' => $history['sample'],
            'calibration_realized' => $history['realized'],
            'calibration_gap' => $history['gap'],
            'edge' => $edge,
            'reasons' => $reasons,
        ];
    }

    /** @return array{market:string,probability:float,outcome:?string} */
    public function contextForHeadline(Prediction $prediction): array
    {
        $outcome = (string) $prediction->predicted_outcome;

        return match ($outcome) {
            'Home Win', 'Away Win' => [
                'market' => PredictionLog::MARKET_1X2,
                'probability' => (float) ($outcome === 'Home Win' ? $prediction->home_win_prob : $prediction->away_win_prob) / 100,
                'outcome' => $outcome,
            ],
            'Draw' => [
                'market' => PredictionLog::MARKET_DRAW,
                'probability' => (float) $prediction->draw_prob / 100,
                'outcome' => 'Draw',
            ],
            'Both Teams Score', 'Both Teams Score (GG)' => [
                'market' => PredictionLog::MARKET_GG,
                'probability' => (float) $prediction->btts_prob / 100,
                'outcome' => 'Both Teams Score',
            ],
            'Over 1.5 Goals' => [
                'market' => PredictionLog::MARKET_OVER15,
                'probability' => (float) $prediction->over_15_prob / 100,
                'outcome' => $outcome,
            ],
            'Over 2.5 Goals' => [
                'market' => PredictionLog::MARKET_OVER25,
                'probability' => (float) $prediction->over_25_prob / 100,
                'outcome' => $outcome,
            ],
            'Over 3.5 Goals' => [
                'market' => PredictionLog::MARKET_OVER35,
                'probability' => (float) $prediction->over_35_prob / 100,
                'outcome' => $outcome,
            ],
            default => [
                'market' => 'untracked',
                'probability' => (float) ($prediction->confidence ?? 0) / 100,
                'outcome' => $outcome ?: null,
            ],
        };
    }

    public function allowsHeadline(Prediction $prediction): bool
    {
        $context = $this->contextForHeadline($prediction);

        return $this->evaluate($prediction, $context['market'], $context['probability'], $context['outcome'])['allowed'];
    }

    /**
     * Compact scorecard consumed by the admin metrics page.
     *
     * @return array<int,array<string,mixed>>
     */
    public function scorecard(): array
    {
        return Cache::remember('publication_quality_scorecard_v1', now()->addHours(6), function (): array {
            return PredictionLog::query()
                ->selectRaw("\n                    model_version, market, prediction_stage,\n                    COUNT(*) AS logged_n,\n                    SUM(CASE WHEN actual_result IN ('WIN','LOSS') THEN 1 ELSE 0 END) AS settled_n,\n                    SUM(CASE WHEN actual_result = 'WIN' THEN 1 ELSE 0 END) AS wins,\n                    AVG(CASE WHEN actual_result = 'WIN' THEN 1.0 WHEN actual_result = 'LOSS' THEN 0.0 END) AS realized,\n                    AVG(CASE WHEN actual_result IN ('WIN','LOSS') THEN p_outcome END) AS stated\n                ")
                ->where('model_version', '!=', MarketClosingLogger::MODEL_VERSION)
                ->groupBy('model_version', 'market', 'prediction_stage')
                ->orderBy('market')
                ->get()
                ->map(function ($row): array {
                    $settled = (int) $row->settled_n;
                    $gap = $settled > 0 ? (float) $row->realized - (float) $row->stated : null;
                    $state = $settled >= 100 && ($gap === null || $gap >= -self::MAX_OVERCONFIDENCE)
                        ? 'proven'
                        : ($settled >= self::MIN_CALIBRATION_SAMPLE ? 'measured' : 'shadow');

                    return [
                        'model_version' => $row->model_version,
                        'market' => $row->market,
                        'stage' => $row->prediction_stage,
                        'logged_n' => (int) $row->logged_n,
                        'settled_n' => $settled,
                        'wins' => (int) $row->wins,
                        'stated' => $settled > 0 ? (float) $row->stated : null,
                        'realized' => $settled > 0 ? (float) $row->realized : null,
                        'gap' => $gap,
                        'state' => $state,
                    ];
                })
                ->all();
        });
    }

    public function forget(): void
    {
        Cache::forget('publication_quality_scorecard_v1');
        Cache::forget('publication_quality_calibration_v1');
    }

    /** @return array{sample:int,realized:?float,gap:?float} */
    private function calibrationFor(string $version, string $stage, string $market, mixed $leagueId, float $probability): array
    {
        $band = min(9, max(0, (int) floor($probability * 10)));
        $key = implode(':', [$version, $stage, $market, $leagueId ?: 'all', $band]);

        return Cache::remember('publication_quality_calibration_v1:'.$key, now()->addHours(6), function () use ($version, $stage, $market, $leagueId, $band): array {
            $base = PredictionLog::query()
                ->where('model_version', $version)
                ->where('prediction_stage', $stage)
                ->where('market', $market)
                ->whereIn('actual_result', [PredictionLog::RESULT_WIN, PredictionLog::RESULT_LOSS])
                ->where('p_outcome', '>=', $band / 10)
                ->where('p_outcome', '<', min(1, ($band + 1) / 10));

            // Prefer a league-specific estimate once it is large enough; the
            // all-league estimate is the honest fallback for a small league.
            $league = $leagueId ? (clone $base)->where('league_id', $leagueId)->get(['actual_result', 'p_outcome']) : collect();
            $rows = $league->count() >= self::MIN_LEAGUE_SAMPLE ? $league : $base->get(['actual_result', 'p_outcome']);
            $sample = $rows->count();
            $realized = $sample ? $rows->where('actual_result', PredictionLog::RESULT_WIN)->count() / $sample : null;
            $stated = $sample ? (float) $rows->avg('p_outcome') : null;

            return [
                'sample' => $sample,
                'realized' => $realized,
                'gap' => $sample ? $realized - $stated : null,
            ];
        });
    }

    private function versionFor(Prediction $prediction): string
    {
        if (! config('prediction.dc_enabled')) {
            return PredictionLogger::VERSION_BASELINE;
        }

        $leagueId = (int) ($prediction->match?->league_id ?? 0);

        return in_array($leagueId, (array) config('prediction.dc_1x2_leagues'), true)
            ? PredictionLogger::VERSION_DC_HYBRID
            : PredictionLogger::VERSION_BASELINE;
    }

    private function edgeFromCapturedOdds(Prediction $prediction, string $market, ?string $outcome, float $probability): ?float
    {
        $odds = is_array($prediction->closing_odds) ? $prediction->closing_odds : $prediction->opening_odds;
        if (! is_array($odds)) {
            return null;
        }

        $key = match ($market) {
            PredictionLog::MARKET_1X2 => match ($outcome) {
                'Home Win' => 'home_win', 'Away Win' => 'away_win', 'Draw' => 'draw', default => null,
            },
            PredictionLog::MARKET_DRAW => 'draw',
            PredictionLog::MARKET_GG => 'btts',
            PredictionLog::MARKET_OVER25 => 'over_25',
            default => null,
        };

        if (! $key || ! isset($odds[$key])) {
            return null;
        }

        return $probability - ((float) $odds[$key] / 100);
    }
}
