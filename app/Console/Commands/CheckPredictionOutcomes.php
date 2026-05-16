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

                    if ($prediction->is_draw_pick) {
                        $oneSignal->sendPickOutcome(
                            title: '🤝 Draw Pick WON! 💰',
                            body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score} — Draw confirmed! ✅",
                            path:  '/draw-picks',
                        );
                        $telegram->sendDrawOutcome($matchLabel, $score, true, $siteUrl, $league);
                    }

                    if ($prediction->is_gg_pick) {
                        $oneSignal->sendPickOutcome(
                            title: '⚽ GG Pick WON! Both Teams Scored! 🔥',
                            body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score} — Both teams scored! ✅",
                            path:  '/gg-picks',
                        );
                        $telegram->sendGGOutcome($matchLabel, $score, true, $siteUrl, $league);
                    }

                    // Winner upload reminder on every win
                    $reminderKey = "winner_reminder_pred_{$prediction->id}";
                    if (! Cache::has($reminderKey)) {
                        $oneSignal->sendPickOutcome(
                            title: '🏆 Won? Upload Your Screenshot!',
                            body:  'Share your winning screenshot on our Winners Wall and get featured! Takes 30 seconds 📸',
                            path:  '/winners',
                        );
                        $telegram->sendWinnerUploadReminder($siteUrl);
                        Cache::put($reminderKey, true, now()->addDays(3));
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

                    if ($prediction->is_draw_pick) {
                        $oneSignal->sendPickOutcome(
                            title: '😔 Draw Pick Lost',
                            body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score} — not a draw this time. Back tomorrow 💪",
                            path:  '/draw-picks',
                        );
                        $telegram->sendDrawOutcome($matchLabel, $score, false, $siteUrl, $league);
                    }

                    if ($prediction->is_gg_pick) {
                        $oneSignal->sendPickOutcome(
                            title: '😔 GG Pick Lost',
                            body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score} — not both teams scored. Back tomorrow 💪",
                            path:  '/gg-picks',
                        );
                        $telegram->sendGGOutcome($matchLabel, $score, false, $siteUrl, $league);
                    }

                    Cache::put($cacheKey, true, now()->addDays(2));
                }
            }
        }

        $this->info("Resolved {$resolved} predictions.");
        Log::info("CheckPredictionOutcomes: resolved {$resolved} predictions.");

        // ── 2. Correct score outcomes ─────────────────────────────────────────
        // Two-layer guard:
        //  (a) correct_score_notified DB flag — survives cache:clear and deploys
        //  (b) 26-hour match_time window — structurally prevents re-firing old
        //      records that have correct_score_notified=false after a fresh migration
        $scoreSince = now()->subHours(26);

        $scorePredictions = Prediction::query()
            ->with('match')
            ->whereNotNull('likely_scores')
            ->where('correct_score_notified', false)
            ->whereHas('match', fn ($q) => $q
                ->whereIn('status', self::FINISHED_STATUSES)
                ->whereNotNull('home_score')
                ->whereNotNull('away_score')
                ->where('match_time', '>=', $scoreSince)
            )
            ->get()
            ->filter(fn ($p) => ! empty($p->likely_scores));

        foreach ($scorePredictions as $prediction) {
            $match = $prediction->match;
            if (! $match) continue;

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

                // Winner upload reminder
                $reminderKey = "winner_reminder_score_{$prediction->id}";
                if (! Cache::has($reminderKey)) {
                    $oneSignal->sendPickOutcome(
                        title: '🏆 Won? Upload Your Screenshot!',
                        body:  'Share your winning screenshot on our Winners Wall and get featured! Takes 30 seconds 📸',
                        path:  '/winners',
                    );
                    $telegram->sendWinnerUploadReminder($siteUrl);
                    Cache::put($reminderKey, true, now()->addDays(3));
                }
            } else {
                $oneSignal->sendPickOutcome(
                    title: '😔 Correct Score — Not This Time',
                    body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$actualScore}. Our picks: {$predictedList}",
                    path:  '/correct-score',
                );
            }

            $telegram->sendCorrectScoreOutcome($matchLabel, $predictedList, $actualScore, $won, $siteUrl, $league);

            // Mark in DB so this never fires again even after cache:clear
            $prediction->update(['correct_score_notified' => true]);
        }

        // ── 3. Over 1.5 / Over 2.5 / Team 3+ outcomes ───────────────────────
        // Each pick type resolves independently from was_correct (which tracks the
        // primary AI outcome, not the goals threshold). Uses a 26-hour window and
        // a DB-persisted notified flag so deploys can never re-fire old results.
        $goalsSince = now()->subHours(26);

        $goalsFinished = Prediction::query()
            ->with('match')
            ->where(fn ($q) => $q
                ->where('is_over15_pick', true)
                ->orWhere('is_over25_pick', true)
                ->orWhere('is_team3plus_pick', true)
            )
            ->whereHas('match', fn ($q) => $q
                ->whereIn('status', self::FINISHED_STATUSES)
                ->whereNotNull('home_score')
                ->whereNotNull('away_score')
                ->where('match_time', '>=', $goalsSince)
            )
            ->get();

        foreach ($goalsFinished as $prediction) {
            $match = $prediction->match;
            if (! $match) continue;

            $home  = (int) $match->home_score;
            $away  = (int) $match->away_score;
            $total = $home + $away;
            $score = "{$home}-{$away}";
            $matchLabel = "{$match->home_team} vs {$match->away_team}";
            $league     = $match->league ?? '';

            // Over 1.5
            if ($prediction->is_over15_pick && ! $prediction->over15_notified) {
                $won = $total >= 2;
                $this->line($won
                    ? "  ⚽✅  {$matchLabel} {$score} — Over 1.5 HIT"
                    : "  ⚽❌  {$matchLabel} {$score} — Over 1.5 missed");

                if ($won) {
                    $oneSignal->sendPickOutcome(
                        title: '⚽ Over 1.5 Goals — WON! 💰',
                        body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score} — goals delivered! ✅",
                        path:  '/over-1-5',
                    );
                } else {
                    $oneSignal->sendPickOutcome(
                        title: '😔 Over 1.5 — Low Scoring Game',
                        body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score}. We go again 💪",
                        path:  '/over-1-5',
                    );
                }
                $telegram->sendOver15Outcome($matchLabel, $score, $won, $siteUrl, $league);
                $prediction->update(['over15_notified' => true]);
            }

            // Over 2.5
            if ($prediction->is_over25_pick && ! $prediction->over25_notified) {
                $won = $total >= 3;
                $this->line($won
                    ? "  🔥✅  {$matchLabel} {$score} — Over 2.5 HIT"
                    : "  🔥❌  {$matchLabel} {$score} — Over 2.5 missed");

                if ($won) {
                    $oneSignal->sendPickOutcome(
                        title: '🔥 Over 2.5 Goals — WON! 💰',
                        body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score} — goals galore! ✅",
                        path:  '/over-2-5',
                    );
                } else {
                    $oneSignal->sendPickOutcome(
                        title: '😔 Over 2.5 — Tight Game',
                        body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score}. AI recalibrates 💪",
                        path:  '/over-2-5',
                    );
                }
                $telegram->sendOver25Outcome($matchLabel, $score, $won, $siteUrl, $league);
                $prediction->update(['over25_notified' => true]);
            }

            // Team 3+ NO — wins when the named team scores fewer than 3
            if ($prediction->is_team3plus_pick && ! $prediction->team3plus_notified) {
                $label    = $prediction->team3plus_label ?? 'Home';
                $teamName = $label === 'Home' ? $match->home_team : $match->away_team;
                $won      = $label === 'Home' ? $home < 3 : $away < 3;

                $this->line($won
                    ? "  🚫✅  {$matchLabel} {$score} — {$teamName} did NOT score 3+ (NO hit)"
                    : "  🚫❌  {$matchLabel} {$score} — {$teamName} scored 3+, NO missed");

                if ($won) {
                    $oneSignal->sendPickOutcome(
                        title: '🚫 Team 3+ NO — Won! 🔥',
                        body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score} — {$teamName} stayed under 3 goals! ✅",
                        path:  '/team-3-plus',
                    );
                } else {
                    $oneSignal->sendPickOutcome(
                        title: '😔 Team 3+ NO — Didn\'t Land',
                        body:  ($league ? "{$league} | " : '') . "{$matchLabel} ended {$score}. {$teamName} hit 3+. We recalibrate 💪",
                        path:  '/team-3-plus',
                    );
                }
                $telegram->sendTeam3PlusOutcome($matchLabel, $teamName, $score, $won, $siteUrl, $league);
                $prediction->update(['team3plus_notified' => true]);
            }
        }

        // ── 4. Rollover picks ─────────────────────────────────────────────────
        $rollover->checkPendingPicks();

        return self::SUCCESS;
    }
}
