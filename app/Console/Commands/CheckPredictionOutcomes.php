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
        $siteUrl = config('app.url');

        // ── 1. Daily picks + lineup picks ────────────────────────────────────
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

            $score      = (int) $match->home_score . '-' . (int) $match->away_score;
            $matchLabel = "{$match->home_team} vs {$match->away_team}";
            $league     = $match->league ?? '';
            $cacheKey   = "outcome_notified_{$prediction->id}";

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

                    if ($prediction->has_lineup) {
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

                    if ($prediction->has_lineup) {
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

        // ── 2. Correct score outcomes ─────────────────────────────────────────
        $scorePredictions = Prediction::query()
            ->with('match')
            ->whereNotNull('likely_scores')
            ->whereHas('match', fn ($q) => $q
                ->whereIn('status', self::FINISHED_STATUSES)
                ->whereNotNull('home_score')
                ->whereNotNull('away_score')
                ->where('match_time', '>=', $since)
            )
            ->get()
            ->filter(fn ($p) => ! empty($p->likely_scores));

        foreach ($scorePredictions as $prediction) {
            $match = $prediction->match;
            if (! $match) continue;

            $cacheKey = "correct_score_notified_{$prediction->id}";
            if (Cache::has($cacheKey)) continue;

            $actualScore   = (int) $match->home_score . '-' . (int) $match->away_score;
            $league        = $match->league ?? '';
            $matchLabel    = "{$match->home_team} vs {$match->away_team}";
            $likelyScores  = is_array($prediction->likely_scores) ? $prediction->likely_scores : [];
            $predictedList = collect($likelyScores)->pluck('score')->filter()->implode(', ');
            $won           = collect($likelyScores)->pluck('score')->contains($actualScore);

            $this->line($won
                ? "  🎯  {$matchLabel} correct score {$actualScore} — HIT!"
                : "  ❌  {$matchLabel} correct score {$actualScore} — missed (predicted: {$predictedList})"
            );

            if ($won) {
                $oneSignal->sendPickOutcome(
                    title: '🎯 Correct Score — NAILED IT! 🔥',
                    body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$actualScore} — we called the exact score! 🤖",
                    path:  '/correct-score',
                );
            } else {
                $oneSignal->sendPickOutcome(
                    title: '😔 Correct Score — Not This Time',
                    body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$actualScore}. Our picks: {$predictedList}",
                    path:  '/correct-score',
                );
            }

            $telegram->sendCorrectScoreOutcome($matchLabel, $predictedList, $actualScore, $won, $siteUrl, $league);

            Cache::put($cacheKey, true, now()->addDays(2));
        }

        // ── 3. Rollover picks ─────────────────────────────────────────────────
        $rollover->checkPendingPicks();

        return self::SUCCESS;
    }
}
