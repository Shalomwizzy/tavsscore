<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\OneSignalService;
use App\Services\RolloverService;
use App\Services\TelegramService;
use App\Support\LeagueCoverage;
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
            $league     = LeagueCoverage::formatName($match->league, $match->league_country);
            $cacheKey   = "outcome_notified_{$prediction->id}";

            if ($wasCorrect) {
                $this->line("  ✅  {$matchLabel} → {$prediction->predicted_outcome} ({$score})");

                if (! Cache::has($cacheKey)) {
                    if ($prediction->is_daily_pick) {
                        $oneSignal->notifyPickWon($matchLabel, $prediction->predicted_outcome, $score, $league, '/picks');
                        $telegram->sendCorrectPick($matchLabel, $prediction->predicted_outcome, $score, $siteUrl, $league);
                    }

                    if ($prediction->has_lineup) {
                        $oneSignal->notifyPickWon($matchLabel, $prediction->predicted_outcome, $score, $league, '/lineup-picks');
                        $telegram->sendLineupOutcome($matchLabel, $prediction->predicted_outcome, $score, true, $siteUrl, $league);
                    }

                    if ($prediction->is_draw_pick) {
                        $oneSignal->notifyPickWon($matchLabel, 'Draw', $score, $league, '/draw-picks');
                        $telegram->sendDrawOutcome($matchLabel, $score, true, $siteUrl, $league);
                    }

                    if ($prediction->is_gg_pick) {
                        $oneSignal->notifyPickWon($matchLabel, 'Both Teams Scored', $score, $league, '/gg-picks');
                        $telegram->sendGGOutcome($matchLabel, $score, true, $siteUrl, $league);
                    }

                    // Winner upload reminder — only for actual picks, never for generic predictions
                    $isActualPick = $prediction->is_daily_pick || $prediction->is_draw_pick
                        || $prediction->is_gg_pick || $prediction->has_lineup
                        || $prediction->is_over15_pick || $prediction->is_over25_pick
                        || $prediction->is_team3plus_pick || $prediction->is_correct_score_pick;

                    if ($isActualPick && ! $prediction->winner_reminder_sent) {
                        $oneSignal->notifyWinnerReminder();
                        $telegram->sendWinnerUploadReminder($siteUrl);
                        $prediction->update(['winner_reminder_sent' => true]);
                    }

                    Cache::put($cacheKey, true, now()->addDays(2));
                }
            } else {
                $this->line("  ❌  {$matchLabel} → predicted {$prediction->predicted_outcome}, actual {$score}");

                if (! Cache::has($cacheKey)) {
                    if ($prediction->is_daily_pick) {
                        $oneSignal->notifyPickLost($matchLabel, $prediction->predicted_outcome, $score, $league, '/picks');
                        $telegram->sendWrongPick($matchLabel, $prediction->predicted_outcome, $score, $siteUrl, $league);
                    }

                    if ($prediction->has_lineup) {
                        $oneSignal->notifyPickLost($matchLabel, $prediction->predicted_outcome, $score, $league, '/lineup-picks');
                        $telegram->sendLineupOutcome($matchLabel, $prediction->predicted_outcome, $score, false, $siteUrl, $league);
                    }

                    if ($prediction->is_draw_pick) {
                        $oneSignal->notifyPickLost($matchLabel, 'Draw', $score, $league, '/draw-picks');
                        $telegram->sendDrawOutcome($matchLabel, $score, false, $siteUrl, $league);
                    }

                    if ($prediction->is_gg_pick) {
                        $oneSignal->notifyPickLost($matchLabel, 'Both Teams Score', $score, $league, '/gg-picks');
                        $telegram->sendGGOutcome($matchLabel, $score, false, $siteUrl, $league);
                    }

                    Cache::put($cacheKey, true, now()->addDays(2));
                }
            }
        }

        $this->info("Resolved {$resolved} predictions.");
        Log::info("CheckPredictionOutcomes: resolved {$resolved} predictions.");

        // ── 2. Correct score outcomes ─────────────────────────────────────────
        $scoreSince = now()->subDays($days)->startOfDay();

        $scorePredictions = Prediction::query()
            ->with('match')
            ->where('is_correct_score_pick', true)
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
            $league        = LeagueCoverage::formatName($match->league, $match->league_country);
            $matchLabel    = "{$match->home_team} vs {$match->away_team}";
            $likelyScores  = is_array($prediction->likely_scores) ? $prediction->likely_scores : [];
            $predictedList = collect($likelyScores)->pluck('score')->filter()->implode(', ');
            $won           = collect($likelyScores)->pluck('score')->contains($actualScore);

            $this->line($won
                ? "  🎯  {$matchLabel} correct score {$actualScore} — HIT!"
                : "  ❌  {$matchLabel} correct score {$actualScore} — missed (predicted: {$predictedList})"
            );

            if ($won) {
                $oneSignal->notifyPickWon($matchLabel, 'Correct Score', $actualScore, $league, '/correct-score');

                if (! $prediction->winner_reminder_sent) {
                    $oneSignal->notifyWinnerReminder();
                    $telegram->sendWinnerUploadReminder($siteUrl);
                    $prediction->update(['winner_reminder_sent' => true]);
                }
            } else {
                $oneSignal->notifyPickLost($matchLabel, "Correct Score ({$predictedList})", $actualScore, $league, '/correct-score');
            }

            $telegram->sendCorrectScoreOutcome($matchLabel, $predictedList, $actualScore, $won, $siteUrl, $league);

            // Mark in DB so this never fires again even after cache:clear
            $prediction->update(['correct_score_notified' => true]);
        }

        // ── 3. Over 1.5 / Over 2.5 / Team 3+ outcomes ───────────────────────
        $goalsSince = now()->subDays($days)->startOfDay();

        $goalsFinished = Prediction::query()
            ->with('match')
            ->where(fn ($q) => $q
                ->where('is_over15_pick', true)
                ->orWhere('is_over25_pick', true)
                ->orWhere('is_team3plus_pick', true)
                ->orWhere('is_double_chance_pick', true)
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
            $league     = LeagueCoverage::formatName($match->league, $match->league_country);

            // Over 1.5
            if ($prediction->is_over15_pick && ! $prediction->over15_notified) {
                $won = $total >= 2;
                $this->line($won
                    ? "  ⚽✅  {$matchLabel} {$score} — Over 1.5 HIT"
                    : "  ⚽❌  {$matchLabel} {$score} — Over 1.5 missed");

                $won
                    ? $oneSignal->notifyPickWon($matchLabel, 'Over 1.5 Goals', $score, $league, '/over-1-5')
                    : $oneSignal->notifyPickLost($matchLabel, 'Over 1.5 Goals', $score, $league, '/over-1-5');
                $telegram->sendOver15Outcome($matchLabel, $score, $won, $siteUrl, $league);
                if ($won && ! $prediction->winner_reminder_sent) {
                    $oneSignal->notifyWinnerReminder();
                    $telegram->sendWinnerUploadReminder($siteUrl);
                    $prediction->update(['winner_reminder_sent' => true]);
                }
                $prediction->update(['over15_notified' => true]);
            }

            // Over 2.5
            if ($prediction->is_over25_pick && ! $prediction->over25_notified) {
                $won = $total >= 3;
                $this->line($won
                    ? "  🔥✅  {$matchLabel} {$score} — Over 2.5 HIT"
                    : "  🔥❌  {$matchLabel} {$score} — Over 2.5 missed");

                $won
                    ? $oneSignal->notifyPickWon($matchLabel, 'Over 2.5 Goals', $score, $league, '/over-2-5')
                    : $oneSignal->notifyPickLost($matchLabel, 'Over 2.5 Goals', $score, $league, '/over-2-5');
                $telegram->sendOver25Outcome($matchLabel, $score, $won, $siteUrl, $league);
                if ($won && ! $prediction->winner_reminder_sent) {
                    $oneSignal->notifyWinnerReminder();
                    $telegram->sendWinnerUploadReminder($siteUrl);
                    $prediction->update(['winner_reminder_sent' => true]);
                }
                $prediction->update(['over25_notified' => true]);
            }

            // Team goals YES pick — wins when the named team scores at least the market threshold
            if ($prediction->is_team3plus_pick && ! $prediction->team3plus_notified) {
                $label    = $prediction->team3plus_label ?? 'Home 3+';
                $isHome   = str_starts_with($label, 'Home');
                $teamName = $isHome ? $match->home_team : $match->away_team;
                $goals    = $isHome ? $home : $away;

                // YES picks: wins when team reaches the goal threshold.
                if (str_ends_with($label, '2+')) { $won = $goals >= 2; $marketLabel = '2+ Goals: YES'; }
                else                              { $won = $goals >= 3; $marketLabel = '3+ Goals: YES'; }

                $this->line($won
                    ? "  ⚽✅  {$matchLabel} {$score} — {$teamName} {$marketLabel} hit"
                    : "  ⚽❌  {$matchLabel} {$score} — {$teamName} {$marketLabel} missed");

                $won
                    ? $oneSignal->notifyPickWon($matchLabel, "{$teamName} — {$marketLabel}", $score, $league, '/team-3-plus')
                    : $oneSignal->notifyPickLost($matchLabel, "{$teamName} — {$marketLabel}", $score, $league, '/team-3-plus');
                $telegram->sendTeam3PlusOutcome($matchLabel, $teamName, $score, $won, $siteUrl, $league);
                if ($won && ! $prediction->winner_reminder_sent) {
                    $oneSignal->notifyWinnerReminder();
                    $telegram->sendWinnerUploadReminder($siteUrl);
                    $prediction->update(['winner_reminder_sent' => true]);
                }
                $prediction->update(['team3plus_notified' => true]);
            }

            // Double Chance — wins when the result matches the 1X or 2X label
            if ($prediction->is_double_chance_pick && ! $prediction->double_chance_notified) {
                $label = $prediction->double_chance_label ?? '1X';
                $won   = $label === '1X' ? ($home >= $away) : ($away >= $home);

                $this->line($won
                    ? "  🎯✅  {$matchLabel} {$score} — Double Chance {$label} hit"
                    : "  🎯❌  {$matchLabel} {$score} — Double Chance {$label} missed");

                $won
                    ? $oneSignal->notifyPickWon($matchLabel, "Double Chance {$label}", $score, $league, '/double-chance')
                    : $oneSignal->notifyPickLost($matchLabel, "Double Chance {$label}", $score, $league, '/double-chance');
                $telegram->sendDoubleChanceOutcome($matchLabel, $label, $score, $won, $siteUrl, $league);
                if ($won && ! $prediction->winner_reminder_sent) {
                    $oneSignal->notifyWinnerReminder();
                    $telegram->sendWinnerUploadReminder($siteUrl);
                    $prediction->update(['winner_reminder_sent' => true]);
                }
                $prediction->update(['double_chance_notified' => true]);
            }
        }

        // ── 4. Rollover picks ─────────────────────────────────────────────────
        $rollover->checkPendingPicks();

        return self::SUCCESS;
    }
}
