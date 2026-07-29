<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\OneSignalService;
use App\Services\PredictionLogSettler;
use App\Services\RolloverService;
use App\Services\TelegramService;
use App\Support\LeagueCoverage;
use App\Support\PickHelpers;
use App\Support\SpecialtyPickCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckPredictionOutcomes extends Command
{
    protected $signature = 'predictions:check-outcomes {--days=3 : How many past days to check}';

    protected $description = 'Compare finished match results against predictions and mark was_correct.';

    private const FINISHED_STATUSES = ['FT', 'AET', 'PEN'];

    public function handle(
        OneSignalService $oneSignal,
        TelegramService $telegram,
        RolloverService $rollover,
        PredictionLogSettler $logSettler,
    ): int {
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

            $home  = (int) $match->home_score;
            $away  = (int) $match->away_score;
            $score = "{$home}-{$away}";
            // Each specialty market is graded from the score itself, NOT the
            // shared $wasCorrect (which is the headline market). A 2-0 is a GG
            // loss even when the headline "Home Win" was correct.
            $bttsWon = $home >= 1 && $away >= 1;
            $drawWon = $home === $away;

            $matchLabel = "{$match->home_team} vs {$match->away_team}";
            $league     = LeagueCoverage::formatName($match->league, $match->league_country);
            $cacheKey   = "outcome_notified_{$prediction->id}";

            $this->line(($wasCorrect ? '  ✅  ' : '  ❌  ') . "{$matchLabel} → {$prediction->predicted_outcome} ({$score})");

            if (! Cache::has($cacheKey)) {
                // Daily + lineup pages track the headline market ($wasCorrect).
                if ($prediction->is_daily_pick) {
                    $wasCorrect
                        ? $oneSignal->notifyPickWon($matchLabel, $prediction->predicted_outcome, $score, $league, '/picks')
                        : $oneSignal->notifyPickLost($matchLabel, $prediction->predicted_outcome, $score, $league, '/picks');
                    $wasCorrect
                        ? $telegram->sendCorrectPick($matchLabel, $prediction->predicted_outcome, $score, $siteUrl, $league)
                        : $telegram->sendWrongPick($matchLabel, $prediction->predicted_outcome, $score, $siteUrl, $league);
                }

                if ($prediction->has_lineup) {
                    $wasCorrect
                        ? $oneSignal->notifyPickWon($matchLabel, $prediction->predicted_outcome, $score, $league, '/lineup-picks')
                        : $oneSignal->notifyPickLost($matchLabel, $prediction->predicted_outcome, $score, $league, '/lineup-picks');
                    $telegram->sendLineupOutcome($matchLabel, $prediction->predicted_outcome, $score, $wasCorrect, $siteUrl, $league);
                }

                // Draw + GG pages track their OWN market, graded from the score.
                if ($prediction->is_draw_pick) {
                    $drawWon
                        ? $oneSignal->notifyPickWon($matchLabel, 'Draw', $score, $league, '/draw-picks')
                        : $oneSignal->notifyPickLost($matchLabel, 'Draw', $score, $league, '/draw-picks');
                    $telegram->sendDrawOutcome($matchLabel, $score, $drawWon, $siteUrl, $league);
                }

                if ($prediction->is_gg_pick) {
                    $bttsWon
                        ? $oneSignal->notifyPickWon($matchLabel, 'Both Teams Scored', $score, $league, '/gg-picks')
                        : $oneSignal->notifyPickLost($matchLabel, 'Both Teams Score', $score, $league, '/gg-picks');
                    $telegram->sendGGOutcome($matchLabel, $score, $bttsWon, $siteUrl, $league);
                }

                // Winner upload reminder — fire when any of this row's markets actually won.
                $anyWon = ($prediction->is_daily_pick && $wasCorrect)
                    || ($prediction->has_lineup && $wasCorrect)
                    || ($prediction->is_draw_pick && $drawWon)
                    || ($prediction->is_gg_pick && $bttsWon);

                if ($anyWon && ! $prediction->winner_reminder_sent) {
                    $oneSignal->notifyWinnerReminder();
                    $telegram->sendWinnerUploadReminder($siteUrl);
                    $prediction->update(['winner_reminder_sent' => true]);
                }

                Cache::put($cacheKey, true, now()->addDays(2));
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
                ->orWhere('is_corners_pick', true)
                ->orWhere('is_under35_pick', true)
                ->orWhere('is_under45_pick', true)
                ->orWhere('is_handicap_pick', true)
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

            // Under-goals and Asian Handicap specialist markets. Half-goal
            // handicap lines are resolved by PickHelpers with no push state.
            foreach (SpecialtyPickCatalog::types() as $specialtyType) {
                $config = SpecialtyPickCatalog::get($specialtyType);
                if (! $prediction->{$config['flag']} || $prediction->{$config['notified']}) continue;
                $label = $config['market'] ?? $prediction->{$config['label_field']};
                $won = PickHelpers::resolveForMatch($match, $label);
                if ($won === null) continue;
                $this->line($won
                    ? "  {$config['icon']}✅  {$matchLabel} {$score} — {$label} HIT"
                    : "  {$config['icon']}❌  {$matchLabel} {$score} — {$label} missed");
                $won
                    ? $oneSignal->notifyPickWon($matchLabel, $label, $score, $league, $config['path'])
                    : $oneSignal->notifyPickLost($matchLabel, $label, $score, $league, $config['path']);
                $telegram->sendSpecialtyOutcome($matchLabel, $label, $score, $won, $config['path'], $siteUrl, $league);
                if ($won && ! $prediction->winner_reminder_sent) {
                    $oneSignal->notifyWinnerReminder();
                    $telegram->sendWinnerUploadReminder($siteUrl);
                    $prediction->update(['winner_reminder_sent' => true]);
                }
                $prediction->update([$config['notified'] => true]);
            }

            // Team goals NO pick — wins when the named team scores fewer than the market threshold
            if ($prediction->is_team3plus_pick && ! $prediction->team3plus_notified) {
                $label    = $prediction->team3plus_label ?? 'Home 3+';
                $isHome   = str_starts_with($label, 'Home');
                $teamName = $isHome ? $match->home_team : $match->away_team;
                $goals    = $isHome ? $home : $away;

                // 2+ NO: wins when team scores 0 or 1. 3+ NO (new & legacy): wins when team scores < 3.
                if (str_ends_with($label, '2+')) { $won = $goals < 2; $marketLabel = '2+ Goals: NO'; }
                else                              { $won = $goals < 3; $marketLabel = '3+ Goals: NO'; }

                $this->line($won
                    ? "  🚫✅  {$matchLabel} {$score} — {$teamName} {$marketLabel} hit"
                    : "  🚫❌  {$matchLabel} {$score} — {$teamName} {$marketLabel} missed");

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

            // Corners — graded from post-match fixture statistics against the
            // stored line. resolveForMatch returns null until stats are fetched,
            // so it stays pending (never a false loss) and retries next run.
            if ($prediction->is_corners_pick && ! $prediction->corners_notified) {
                $won = PickHelpers::resolveForMatch($match, $prediction->corners_label);
                if ($won !== null) {
                    $label = $prediction->corners_label ?? 'Corners';
                    $this->line($won
                        ? "  🚩✅  {$matchLabel} {$score} — {$label} hit"
                        : "  🚩❌  {$matchLabel} {$score} — {$label} missed");

                    $won
                        ? $oneSignal->notifyPickWon($matchLabel, $label, $score, $league, '/corners-picks')
                        : $oneSignal->notifyPickLost($matchLabel, $label, $score, $league, '/corners-picks');
                    if ($won && ! $prediction->winner_reminder_sent) {
                        $oneSignal->notifyWinnerReminder();
                        $telegram->sendWinnerUploadReminder($siteUrl);
                        $prediction->update(['winner_reminder_sent' => true]);
                    }
                    $prediction->update(['was_correct' => $won, 'corners_notified' => true]);
                }
            }
        }

        // ── 4. Rollover picks ─────────────────────────────────────────────────
        $rollover->checkPendingPicks();

        // ── 5. Prediction-log settlement (measurement layer) ─────────────────
        $logCounts = $logSettler->settleSince($days);
        $this->info("Prediction logs: settled {$logCounts['settled']}, voided {$logCounts['voided']}.");
        Log::info('CheckPredictionOutcomes: prediction_logs settled', $logCounts);

        return self::SUCCESS;
    }
}
