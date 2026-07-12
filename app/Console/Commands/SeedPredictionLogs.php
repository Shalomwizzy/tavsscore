<?php

namespace App\Console\Commands;

use App\Models\ModelRun;
use App\Models\Prediction;
use App\Services\PredictionLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill: walk every existing Prediction and materialize
 * corresponding prediction_logs rows under groq-poisson-v0 with is_backfill=true.
 * Idempotent — upserts by (match_id, market, model_version, prediction_stage).
 *
 * Rows that had a lineup rerun overwrite their pre-lineup probabilities are
 * logged as post_lineup only — we cannot recover the pre-lineup numbers from
 * the operational table. Ongoing observer logging captures both stages cleanly.
 */
class SeedPredictionLogs extends Command
{
    protected $signature   = 'predictions:seed-logs
                              {--chunk=500 : How many predictions to process per batch}
                              {--fresh     : Truncate prediction_logs before seeding}';
    protected $description = 'Backfill prediction_logs from existing Prediction rows as groq-poisson-v0 (is_backfill=true).';

    public function handle(PredictionLogger $logger): int
    {
        if ($this->option('fresh') && $this->confirm('Truncate prediction_logs before seeding?', false)) {
            DB::table('prediction_logs')->truncate();
            $this->warn('Truncated prediction_logs.');
        }

        ModelRun::firstOrCreate(
            ['model_version' => PredictionLogger::VERSION_BASELINE],
            [
                'notes' => "Baseline hybrid: 1X2 probabilities from Groq (LLM); Over 2.5 and BTTS are 50/50 blends of Groq and internal Poisson; Over 1.5 / Over 3.5 pure Poisson; predicted_outcome derived from Groq's tips. Retroactively logged via predictions:seed-logs.",
            ],
        );

        $total   = Prediction::count();
        $written = 0;
        $bar     = $this->output->createProgressBar($total);
        $bar->start();

        Prediction::query()
            ->with('match')
            ->orderBy('id')
            ->chunkById((int) $this->option('chunk'), function ($chunk) use ($logger, &$written, $bar) {
                foreach ($chunk as $prediction) {
                    $written += $logger->logBackfill($prediction);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Seeded {$written} prediction_log rows from {$total} predictions.");
        return self::SUCCESS;
    }
}
