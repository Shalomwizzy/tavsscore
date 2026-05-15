<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\OneSignalService;
use App\Services\RolloverService;
use App\Services\TelegramService;
use App\Support\PickHelpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckPredictionOutcomes extends Command
{
    protected $signature = 'predictions:check-outcomes {--days=3 : How many past days to check}';

    protected $description = 'Compare finished match results against predictions and mark was_correct.';

    private const FINISHED_STATUSES = ['FT', 'AET', 'PEN'];

    public function handle(OneSignalService $oneSignal, TelegramService $telegram, RolloverService $rollover): int
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

            $score = (int) $match->home_score . '-' . (int) $match->away_score;
            $matchLabel = "{$match->home_team} vs {$match->away_team}";
            $league     = $match->league ?? '';

            $siteUrl  = config('app.url');
            $cacheKey = "outcome_notified_{$prediction->id}";

            if ($wasCorrect) {
                $this->line("  ✅  {$matchLabel} → {$prediction->predicted_outcome} ({$score})");

                if (! Cache::has($cacheKey)) {
                    if ($prediction->is_daily_pick) {
                        $oneSignal->sendPickOutcome(
                            title: '🔥 We Nailed It! Pick Won!',
                            body:  ($league ? "{$league} | " : '') . "{$matchLabel} {$score} — {$prediction->predicted_outcome} ✅ Check your returns!",
                            path:  '/picks',
                        );
                        $telegram->sendCorrectPick($matchLabel, $prediction->predicted_outcome, $score, $siteUrl, $league);
                    }

                    if ($prediction->is_lineup_pick ?? false) {
                        $oneSignal->sendPickOutcome(
                            title: '⚡ Lineup Pick WON! 🎯',
                            body:  ($league ? "{$league} | " : '') . "{$matchLabel} {$score} — {$prediction->predicted_outcome} ✅",
                            path:  '/lineup-picks',
                        );
                        $telegram->sendLineupOutcome($matchLabel, $prediction->predicted_outcome, $score, true, $siteUrl, $league);
                    }

                    Cache::put($cacheKey, true, now()->addDays(2));
                }
            } else {
                $this->line("  ❌  {$matchLabel} → predicted {$prediction->predicted_outcome}, actual {$score}");

                if (! Cache::has($cacheKey)) {
                    if ($prediction->is_daily_pick) {
                        $oneSignal->sendPickOutcome(
                            title: '😔 Pick Lost — We Go Again',
                            body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score}. Football can surprise anyone — back tomorrow 💪",
                            path:  '/picks',
                        );
                        $telegram->sendWrongPick($matchLabel, $prediction->predicted_outcome, $score, $siteUrl, $league);
                    }

                    if ($prediction->is_lineup_pick ?? false) {
                        $oneSignal->sendPickOutcome(
                            title: '😔 Lineup Pick Lost',
                            body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score}. Football isn't always fair — we rise again 💪",
                            path:  '/lineup-picks',
                        );
                        $telegram->sendLineupOutcome($matchLabel, $prediction->predicted_outcome, $score, false, $siteUrl, $league);
                    }

                    Cache::put($cacheKey, true, now()->addDays(2));
                }
            }
        }

        $this->info("Resolved {$resolved} predictions.");
        Log::info("CheckPredictionOutcomes: resolved {$resolved} predictions.");

        // Also settle any pending rollover picks whose matches have finished
        $rollover->checkPendingPicks();

        return self::SUCCESS;
    }
}
