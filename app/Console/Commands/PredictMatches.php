<?php

namespace App\Console\Commands;

use App\Services\PredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PredictMatches extends Command
{
    protected $signature = 'predict:matches';

    protected $description = 'Generate statistical predictions for upcoming football matches.';

    public function handle(PredictionService $predictionService): int
    {
        try {
            $predictions = $predictionService->generateForUpcomingMatches();

            $this->info("Generated {$predictions->count()} match predictions.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Failed to generate match predictions.', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $this->error('Failed to generate predictions. Check the application logs for details.');

            return self::FAILURE;
        }
    }
}
