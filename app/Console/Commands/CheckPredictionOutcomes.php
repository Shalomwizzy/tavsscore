<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Support\PickHelpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPredictionOutcomes extends Command
{
    protected $signature = 'predictions:check-outcomes {--days=3 : How many past days to check}';

    protected $description = 'Compare finished match results against predictions and mark was_correct.';

    private const FINISHED_STATUSES = ['FT', 'AET', 'PEN'];

    public function handle(): int
    {
        $days  = (int) $this->option('days');
        $since = now()->subDays($days)->startOfDay();

        $pending = Prediction::query()
            ->with('match')
            ->whereNull('was_correct')
            ->whereNotNull('predicted_outcome')
            ->whereHas('match', fn ($q) => $q
                ->whereIn('status', self::FINISHED_STATUSES)
                ->whereNotNull('home_score')
                ->whereNotNull('away_score')
                ->where('match_time', '>=', $since)
            )
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending outcomes to resolve.');
            return self::SUCCESS;
        }

        $resolved = 0;

        foreach ($pending as $prediction) {
            $match = $prediction->match;

            if (! $match || $match->home_score === null || $match->away_score === null) {
                continue;
            }

            if ($prediction->predicted_outcome === 'Competitive Match') {
                $prediction->update(['was_correct' => null]);
                continue;
            }

            $wasCorrect = PickHelpers::resolveOutcome($prediction);

            if ($wasCorrect === null) {
                continue;
            }

            $prediction->update(['was_correct' => $wasCorrect]);
            $resolved++;

            if ($wasCorrect) {
                $this->line(sprintf(
                    '  ✅  %s vs %s  →  %s  (%d–%d)',
                    $match->home_team, $match->away_team,
                    $prediction->predicted_outcome,
                    (int) $match->home_score, (int) $match->away_score
                ));
            } else {
                $this->line(sprintf(
                    '  ❌  %s vs %s  →  predicted %s, actual %d–%d',
                    $match->home_team, $match->away_team,
                    $prediction->predicted_outcome,
                    (int) $match->home_score, (int) $match->away_score
                ));
            }
        }

        $this->info("Resolved {$resolved} predictions.");
        Log::info("CheckPredictionOutcomes: resolved {$resolved} predictions.");

        return self::SUCCESS;
    }
}
