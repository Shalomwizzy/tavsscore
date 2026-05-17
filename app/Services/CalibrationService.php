<?php

namespace App\Services;

use App\Models\Prediction;
use Illuminate\Support\Facades\Cache;

/**
 * Computes historical accuracy data from resolved predictions.
 * Used by controllers for display-time calibration badges.
 * All results are cached to avoid repeated DB hits.
 */
class CalibrationService
{
    private const CACHE_HOURS = 6;

    /**
     * Historical win rate for picks at the given AI confidence level.
     * Returns null when the data pool is too small to be meaningful (<10 picks).
     */
    public function historicalPct(int $confidence): ?float
    {
        $bands = $this->bands();
        foreach ($bands as $band) {
            if ($confidence >= $band['min'] && $confidence <= $band['max']) {
                return $band['pct'];
            }
        }
        return null;
    }

    /**
     * Full confidence → actual win rate breakdown for all resolved daily picks.
     * Each entry: ['label', 'min', 'max', 'total', 'correct', 'pct' (null = insufficient data)]
     */
    public function bands(): array
    {
        return Cache::remember('calibration_bands_v1', now()->addHours(self::CACHE_HOURS), function () {
            $resolved = Prediction::query()
                ->where('is_daily_pick', true)
                ->whereNotNull('was_correct')
                ->whereNotNull('confidence')
                ->get(['confidence', 'was_correct']);

            $buckets = [
                '50-59'  => ['label' => '50–59%',  'min' => 50,  'max' => 59,  'total' => 0, 'correct' => 0],
                '60-69'  => ['label' => '60–69%',  'min' => 60,  'max' => 69,  'total' => 0, 'correct' => 0],
                '70-79'  => ['label' => '70–79%',  'min' => 70,  'max' => 79,  'total' => 0, 'correct' => 0],
                '80-89'  => ['label' => '80–89%',  'min' => 80,  'max' => 89,  'total' => 0, 'correct' => 0],
                '90-100' => ['label' => '90–100%', 'min' => 90,  'max' => 100, 'total' => 0, 'correct' => 0],
            ];

            foreach ($resolved as $p) {
                $conf = (int) $p->confidence;
                foreach ($buckets as $key => &$b) {
                    if ($conf >= $b['min'] && $conf <= $b['max']) {
                        $b['total']++;
                        if ($p->was_correct) $b['correct']++;
                        break;
                    }
                }
                unset($b);
            }

            foreach ($buckets as &$b) {
                $b['trusted'] = $b['total'] >= 10;
                $b['pct']     = $b['trusted']
                    ? round($b['correct'] / $b['total'] * 100, 1)
                    : null;
                $midpoint     = ($b['min'] + $b['max']) / 2;
                $b['gap']     = $b['pct'] !== null
                    ? round($b['pct'] - $midpoint, 1)
                    : null;
            }
            unset($b);

            return array_values($buckets);
        });
    }

    /**
     * Per-league actual win rate map for daily picks, cached 6 hours.
     * Key format: "{league}||{league_country}". Only leagues with >=5 picks included.
     * Returns multipliers (same scale as PredictionService market weights).
     */
    public function leagueWeightsMap(): array
    {
        return Cache::remember('calibration_league_weights_v1', now()->addHours(self::CACHE_HOURS), function () {
            $resolved = Prediction::query()
                ->with('match:id,league,league_country')
                ->where('is_daily_pick', true)
                ->whereNotNull('was_correct')
                ->where('created_at', '>=', now()->subDays(90))
                ->get(['id', 'match_id', 'was_correct']);

            if ($resolved->count() < 10) return [];

            $weights = [];
            foreach ($resolved->groupBy(fn ($p) => ($p->match?->league ?? '') . '||' . ($p->match?->league_country ?? '')) as $key => $preds) {
                $total = $preds->count();
                if ($total < 5) continue;
                $winRate = $preds->where('was_correct', true)->count() / $total;
                $weights[$key] = match (true) {
                    $winRate >= 0.70 => 1.20,
                    $winRate >= 0.55 => 1.10,
                    $winRate >= 0.45 => 1.00,
                    $winRate >= 0.35 => 0.90,
                    default          => 0.75,
                };
            }
            return $weights;
        });
    }
}
