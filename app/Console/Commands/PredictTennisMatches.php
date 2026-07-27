<?php

namespace App\Console\Commands;

use App\Models\TennisMatch;
use App\Services\Tennis\TennisPredictionService;
use Illuminate\Console\Command;

class PredictTennisMatches extends Command
{
    protected $signature = 'tennis:predict {--hours-ahead=48 : Prediction window for scheduled fixtures}';
    protected $description = 'Generate tennis winner probabilities for scheduled ATP/WTA fixtures.';

    public function handle(TennisPredictionService $predictor): int
    {
        $matches = TennisMatch::where('status', 'scheduled')
            ->whereBetween('match_date', [now()->toDateString(), now()->addHours((int) $this->option('hours-ahead'))->toDateString()])
            ->orderBy('match_date')->get();
        foreach ($matches as $match) {
            $prediction = $predictor->predict($match);
            $this->line("{$match->player_one} vs {$match->player_two}: {$prediction->predicted_winner} ({$prediction->confidence}%)");
        }
        $this->info("Predicted {$matches->count()} tennis fixtures.");
        return self::SUCCESS;
    }
}
