<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\GroqService;
use App\Services\PredictionService;
use Illuminate\Console\Command;

/**
 * Validate a replacement Groq model before flipping GROQ_MODEL in .env
 * (Aug 2026 llama-3.3-70b-versatile decommission).
 *
 * Picks N recent finished fixtures, runs Groq with the override model
 * using the same prompt PredictionService builds, and reports whether:
 *   - the API call succeeds
 *   - the JSON response has the expected keys (home_win, draw, away_win,
 *     over_25, btts, tips, analysis)
 *   - the probabilities are in a sane range (1X2 sum near 100)
 *
 * Doesn't touch prediction_logs or any operational data — pure validation.
 * If all 3 test fixtures return well-formed schemas, the flip is safe.
 */
class GroqTestModel extends Command
{
    protected $signature   = 'groq:test-model
                              {--model= : Groq model ID to test (required, e.g. gpt-oss-120b)}
                              {--limit=3 : Number of recent finished matches to test}';
    protected $description = 'Dry-run a replacement Groq model against N sample fixtures to confirm schema compatibility.';

    public function handle(GroqService $groq, PredictionService $svc): int
    {
        $model = $this->option('model');
        if (blank($model)) {
            $this->error('--model=<id> is required. See https://console.groq.com/docs/models for current IDs.');
            return self::FAILURE;
        }
        $limit = (int) $this->option('limit');

        $matches = FootballMatch::query()
            ->whereIn('status', ['FT', 'AET', 'PEN'])
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->orderByDesc('match_time')
            ->limit($limit)
            ->get();

        if ($matches->isEmpty()) {
            $this->error('No finished matches to test against.');
            return self::FAILURE;
        }

        $this->info("Testing Groq model '{$model}' on {$matches->count()} recent finished fixtures…");
        $this->line('');

        $passed = 0;
        $failed = 0;

        foreach ($matches as $match) {
            $label = "{$match->home_team} vs {$match->away_team}";
            $this->line("→ {$label}");

            // Build a minimal Poisson fallback (the shadow test doesn't need
            // deep stats — we're validating schema compatibility, not accuracy).
            $poisson = [
                'home_win' => 45.0, 'draw' => 25.0, 'away_win' => 30.0,
                'over_15' => 78.0, 'over_25' => 55.0, 'over_35' => 32.0,
                'btts' => 52.0, 'home_clean_sheet' => 20.0, 'away_clean_sheet' => 20.0,
            ];

            try {
                $result = $groq->getPrediction(
                    $match, $poisson,
                    [], [], [], [], '', '', '', [], '', [], 1.5, 1.2, [], '',
                    modelOverride: $model,
                );
            } catch (\Throwable $e) {
                $this->error("  ✗ Exception: {$e->getMessage()}");
                $failed++;
                continue;
            }

            if ($result === null) {
                $this->error('  ✗ Groq returned null (API error, rate limit, or parse failure — check logs).');
                $failed++;
                usleep(2_100_000); // rate-limit courtesy
                continue;
            }

            $required = ['home_win', 'draw', 'away_win', 'over_25', 'btts', 'tips', 'analysis'];
            $missing  = array_diff($required, array_keys($result));

            if (! empty($missing)) {
                $this->error('  ✗ Missing keys in response: ' . implode(', ', $missing));
                $failed++;
                usleep(2_100_000);
                continue;
            }

            $sum1X2 = ($result['home_win'] ?? 0) + ($result['draw'] ?? 0) + ($result['away_win'] ?? 0);
            if ($sum1X2 < 95 || $sum1X2 > 105) {
                $this->warn("  ⚠ 1X2 sums to {$sum1X2}% (expected ~100). Parser may need adjustment.");
            }

            $this->line(sprintf(
                "  ✓ H:%.0f%% D:%.0f%% A:%.0f%% · O2.5:%.0f%% · BTTS:%.0f%% · %d tip(s)",
                $result['home_win'], $result['draw'], $result['away_win'],
                $result['over_25'], $result['btts'], count($result['tips'] ?? []),
            ));
            $passed++;

            usleep(2_100_000); // 30 RPM Groq rate limit
        }

        $this->line('');
        if ($failed === 0) {
            $this->info("✅ All {$passed} fixtures returned a well-formed schema. Model '{$model}' is safe to promote.");
            $this->line("Next: set GROQ_MODEL={$model} in .env and run `php artisan config:clear`.");
            return self::SUCCESS;
        }

        $this->error("❌ {$failed} of {$matches->count()} calls failed. Do NOT flip GROQ_MODEL — investigate first.");
        return self::FAILURE;
    }
}
