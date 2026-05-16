<?php

namespace App\Services;

use App\Models\Prediction;
use Illuminate\Support\Facades\Cache;

/**
 * Learns from the app's resolved prediction history and automatically adjusts
 * the quality thresholds used to select daily picks.
 *
 * Key outputs:
 *   - minimumConfidenceThreshold(): the lowest AI confidence band that has been
 *     historically profitable (win rate ≥ 52%). Replaces the hardcoded 65.
 *   - coldMarkets(): markets in a losing streak over the last 14 days —
 *     these receive an extra scoring penalty in pick selection.
 *   - learnedState(): full summary for the admin AI-Learning page.
 *
 * All results are cached for CACHE_HOURS so the DB is not queried on every
 * page load. The admin can force a refresh via the AI-Learning page.
 */
class AdaptiveThresholdService
{
    public const  CACHE_HOURS       = 6;
    private const FLOOR_CONF        = 60;   // never go below 60%
    private const CEILING_CONF      = 82;   // never go above 82%
    private const PROFITABLE_RATE   = 0.52; // minimum win rate for a band to count
    private const MIN_BAND_PICKS    = 10;   // minimum picks needed for a band to be trusted
    private const COLD_DAYS         = 14;
    private const COLD_WIN_RATE     = 0.40; // <40% in last 14 days = cold
    private const COLD_MIN_PICKS    = 3;    // minimum picks in the window to flag cold

    // ── Public API ────────────────────────────────────────────────────

    /**
     * Minimum AI confidence for daily pick selection — learned from history.
     * Defaults to 65 when there is not enough resolved data.
     */
    public function minimumConfidenceThreshold(): int
    {
        return $this->learnedState()['threshold'];
    }

    /**
     * Markets currently in a cold streak (win rate < 40% in last 14 days).
     * Used to apply a stronger penalty in PredictionService scoring.
     */
    public function coldMarkets(): array
    {
        return $this->learnedState()['cold_markets'];
    }

    /**
     * Full summary of what the system has learned — consumed by the admin page.
     */
    public function learnedState(): array
    {
        return Cache::remember('adaptive_learned_state_v1', now()->addHours(self::CACHE_HOURS), fn () => $this->compute());
    }

    /** Force-clear the learned state cache (used by admin recalibrate action). */
    public function forceRecalibrate(): void
    {
        Cache::forget('adaptive_learned_state_v1');
        Cache::forget('calibration_bands_v1');
        Cache::forget('calibration_league_weights_v1');
    }

    // ── Core computation ─────────────────────────────────────────────

    private function compute(): array
    {
        $tz = config('app.timezone', 'Africa/Lagos');

        // ── All resolved daily picks (90 days) ───────────────────
        $all90 = Prediction::query()
            ->with('match:id,league,league_country')
            ->where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->whereNotNull('confidence')
            ->whereNotNull('predicted_outcome')
            ->where('created_at', '>=', now($tz)->subDays(90))
            ->get(['id', 'match_id', 'confidence', 'was_correct', 'predicted_outcome', 'created_at']);

        $all30 = $all90->filter(fn ($p) => $p->created_at >= now($tz)->subDays(30));
        $all14 = $all90->filter(fn ($p) => $p->created_at >= now($tz)->subDays(self::COLD_DAYS));

        // ── Overall accuracy ──────────────────────────────────────
        $acc90 = $this->pct($all90);
        $acc30 = $this->pct($all30);
        $acc7  = $this->pct($all90->filter(fn ($p) => $p->created_at >= now($tz)->subDays(7)));

        $trend = 'stable';
        if ($acc30 !== null && $acc90 !== null) {
            if ($acc30 > $acc90 + 3) $trend = 'improving';
            elseif ($acc30 < $acc90 - 3) $trend = 'declining';
        }

        // ── Confidence band calibration ───────────────────────────
        // Use 5-point bands for a more granular threshold search
        $bands = $this->buildConfidenceBands($all90);

        // Learned threshold = lowest band where win rate ≥ PROFITABLE_RATE
        // with enough picks. Scan from low to high and take the lowest qualifying.
        $threshold = 65; // default fallback
        foreach ($bands as $band) {
            if ($band['total'] >= self::MIN_BAND_PICKS && ($band['pct'] ?? 0) / 100 >= self::PROFITABLE_RATE) {
                $threshold = $band['min'];
                break;
            }
        }
        $threshold = max(self::FLOOR_CONF, min(self::CEILING_CONF, $threshold));

        // ── Market performance ────────────────────────────────────
        $marketPerf = $this->buildMarketPerformance($all90, $all14);

        // ── Cold markets ──────────────────────────────────────────
        $coldMarkets = collect($marketPerf)
            ->filter(fn ($m) => $m['is_cold'])
            ->pluck('market')
            ->values()
            ->all();

        // ── League reliability ────────────────────────────────────
        $leaguePerf = $this->buildLeaguePerformance($all90);

        return [
            'threshold'      => $threshold,
            'threshold_why'  => $this->thresholdReason($bands, $threshold),
            'cold_markets'   => $coldMarkets,
            'acc_90d'        => $acc90,
            'acc_30d'        => $acc30,
            'acc_7d'         => $acc7,
            'trend'          => $trend,
            'total_90d'      => $all90->count(),
            'total_30d'      => $all30->count(),
            'confidence_bands' => $bands,
            'market_perf'    => $marketPerf,
            'league_perf'    => $leaguePerf,
            'computed_at'    => now()->toIso8601String(),
        ];
    }

    // ── Builders ─────────────────────────────────────────────────────

    private function buildConfidenceBands($picks): array
    {
        $defs = [
            ['label' => '50–54%',  'min' => 50,  'max' => 54],
            ['label' => '55–59%',  'min' => 55,  'max' => 59],
            ['label' => '60–64%',  'min' => 60,  'max' => 64],
            ['label' => '65–69%',  'min' => 65,  'max' => 69],
            ['label' => '70–74%',  'min' => 70,  'max' => 74],
            ['label' => '75–79%',  'min' => 75,  'max' => 79],
            ['label' => '80–84%',  'min' => 80,  'max' => 84],
            ['label' => '85–100%', 'min' => 85,  'max' => 100],
        ];

        return array_map(function (array $d) use ($picks) {
            $g       = $picks->filter(fn ($p) => $p->confidence >= $d['min'] && $p->confidence <= $d['max']);
            $total   = $g->count();
            $correct = $g->where('was_correct', true)->count();
            $pct     = $total >= 5 ? round($correct / $total * 100, 1) : null;
            $gap     = ($pct !== null) ? round($pct - (($d['min'] + $d['max']) / 2), 1) : null;
            return array_merge($d, [
                'total'   => $total,
                'correct' => $correct,
                'pct'     => $pct,
                'gap'     => $gap,     // negative = AI overconfident; positive = AI underconfident
                'trusted' => $total >= self::MIN_BAND_PICKS,
            ]);
        }, $defs);
    }

    private function buildMarketPerformance($all90, $all14): array
    {
        $perf = [];

        foreach ($all90->groupBy('predicted_outcome') as $market => $preds) {
            $total   = $preds->count();
            $correct = $preds->where('was_correct', true)->count();
            $pct     = $total > 0 ? round($correct / $total * 100, 1) : null;
            $winRate = $total > 0 ? $correct / $total : null;

            // Last-14-day window
            $recent  = $all14->where('predicted_outcome', $market);
            $rTotal  = $recent->count();
            $rPct    = $rTotal >= self::COLD_MIN_PICKS
                ? round($recent->where('was_correct', true)->count() / $rTotal * 100, 1)
                : null;
            $isCold  = $rTotal >= self::COLD_MIN_PICKS
                && ($recent->where('was_correct', true)->count() / $rTotal) < self::COLD_WIN_RATE;
            $isHot   = $rTotal >= self::COLD_MIN_PICKS
                && ($recent->where('was_correct', true)->count() / $rTotal) >= 0.65;

            $weight = match (true) {
                $winRate === null      => 1.0,
                $winRate >= 0.70       => 1.25,
                $winRate >= 0.55       => 1.10,
                $winRate >= 0.45       => 1.00,
                $winRate >= 0.35       => 0.85,
                default                => 0.70,
            };

            $perf[] = [
                'market'      => $market,
                'total'       => $total,
                'correct'     => $correct,
                'pct'         => $pct,
                'recent_pct'  => $rPct,
                'recent_total'=> $rTotal,
                'weight'      => $weight,
                'is_cold'     => $isCold,
                'is_hot'      => $isHot,
            ];
        }

        usort($perf, fn ($a, $b) => $b['total'] <=> $a['total']);
        return $perf;
    }

    private function buildLeaguePerformance($all90): array
    {
        $perf = [];

        foreach ($all90->groupBy(fn ($p) => ($p->match?->league ?? 'Unknown') . '||' . ($p->match?->league_country ?? '')) as $key => $preds) {
            if ($preds->count() < 5) continue;

            [$league, $country] = array_pad(explode('||', $key, 2), 2, '');
            $total   = $preds->count();
            $correct = $preds->where('was_correct', true)->count();
            $pct     = round($correct / $total * 100, 1);
            $winRate = $correct / $total;

            $weight = match (true) {
                $winRate >= 0.70 => 1.20,
                $winRate >= 0.55 => 1.10,
                $winRate >= 0.45 => 1.00,
                $winRate >= 0.35 => 0.90,
                default          => 0.75,
            };

            $perf[] = compact('league', 'country', 'total', 'correct', 'pct', 'weight');
        }

        usort($perf, fn ($a, $b) => $b['total'] <=> $a['total']);
        return $perf;
    }

    private function thresholdReason(array $bands, int $threshold): string
    {
        foreach ($bands as $band) {
            if ($band['min'] === $threshold && $band['trusted']) {
                return "Lowest profitable band: {$band['label']} wins {$band['pct']}% ({$band['total']} picks)";
            }
        }
        return 'Default — insufficient historical data to learn threshold';
    }

    private function pct($picks): ?float
    {
        $t = $picks->count();
        if ($t === 0) return null;
        return round($picks->where('was_correct', true)->count() / $t * 100, 1);
    }
}
