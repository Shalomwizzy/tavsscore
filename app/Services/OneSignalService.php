<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    // ─────────────────────────────────────────────────────────────
    //  Core send (kept for admin broadcast controller)
    // ─────────────────────────────────────────────────────────────

    public function sendMatchAlert(string $title, string $message, string $path = '/live'): void
    {
        $appId = config('services.onesignal.app_id');
        $apiKey = config('services.onesignal.rest_api_key');

        if (! $appId || ! $apiKey) {
            return;
        }

        Http::withHeaders([
            'Authorization' => "Basic {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', [
            'app_id' => $appId,
            'included_segments' => ['All'],
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
            'url' => config('app.url').$path,
        ]);
    }

    /** Kept for backward compat with older call sites. */
    public function sendPickOutcome(string $title, string $body, string $path = '/picks'): void
    {
        $this->sendMatchAlert($title, $body, $path);
    }

    // ─────────────────────────────────────────────────────────────
    //  Goal alert
    // ─────────────────────────────────────────────────────────────

    public function sendGoalAlert(
        string $homeTeam,
        string $awayTeam,
        int $homeScore,
        int $awayScore,
        string $league,
        ?int $elapsed
    ): void {
        $appId = config('services.onesignal.app_id');
        $apiKey = config('services.onesignal.rest_api_key');

        if (! $appId || ! $apiKey) {
            Log::warning('OneSignal not configured — skipping goal alert.');

            return;
        }

        $minute = $elapsed ? " {$elapsed}'" : '';
        $score = "{$homeScore}-{$awayScore}";

        $response = Http::withHeaders([
            'Authorization' => "Basic {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', [
            'app_id' => $appId,
            'included_segments' => ['All'],
            'headings' => ['en' => "⚽ GOAL! {$homeTeam} {$score} {$awayTeam}{$minute}"],
            'contents' => ['en' => $league.' — Tap for live score & picks'],
            'url' => config('app.url').'/live',
            'web_push_topic' => 'goal-alert',
        ]);

        if (! $response->successful()) {
            Log::error('OneSignal goal alert failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Pick announcement notifications
    // ─────────────────────────────────────────────────────────────

    public function notifyDailyPicks(string $topMatch, string $topTip, int $total): void
    {
        $extra = $total > 1 ? ' + '.($total - 1).' more' : '';
        $this->sendMatchAlert(
            title: "🎯 {$total} Daily Pick".($total > 1 ? 's' : '').' Are Live!',
            message: "{$topMatch} — {$topTip}{$extra}. Tap for full AI analysis →",
            path: '/picks',
        );
    }

    public function notifyDrawPicks(string $topMatch, int $total): void
    {
        $this->sendMatchAlert(
            title: "🤝 {$total} Draw Pick".($total > 1 ? 's' : '').' — AI Agreed!',
            message: "Top draw: {$topMatch}. Statistically selected — tap to see all picks →",
            path: '/draw-picks',
        );
    }

    public function notifyGGPicks(string $topMatch, int $total): void
    {
        $this->sendMatchAlert(
            title: "⚽ {$total} GG Pick".($total > 1 ? 's' : '').' — Both Teams to Score!',
            message: "Top pick: {$topMatch}. High BTTS probability — tap for full breakdown →",
            path: '/gg-picks',
        );
    }

    public function notifyOver15Picks(string $topMatch, string $topProb, int $total): void
    {
        $this->sendMatchAlert(
            title: "🎯 {$total} Over 1.5 Goals Pick".($total > 1 ? 's' : '').' Live!',
            message: "{$topMatch} — {$topProb}% probability. Tap for all picks →",
            path: '/over-1-5',
        );
    }

    public function notifyOver25Picks(string $topMatch, string $topProb, int $total): void
    {
        $this->sendMatchAlert(
            title: "🔥 {$total} Over 2.5 Goals Pick".($total > 1 ? 's' : '').' Live!',
            message: "{$topMatch} — {$topProb}% probability. Tap for all picks →",
            path: '/over-2-5',
        );
    }

    public function notifySpecialtyPicks(string $title, string $topMatch, string $topTip, string $topProb, int $total, string $path): void
    {
        $this->sendMatchAlert(
            title: "{$total} {$title} Pick".($total > 1 ? 's' : '').' Live!',
            message: "{$topMatch} — {$topTip} ({$topProb}). Tap to see all picks →",
            path: $path,
        );
    }

    public function notifyCornersPicks(string $topMatch, string $topLine, int $total): void
    {
        $this->sendMatchAlert(
            title: "🚩 {$total} Corner Pick".($total > 1 ? 's' : '').' Live!',
            message: "{$topMatch} — {$topLine}. Tap for all corner picks →",
            path: '/corners-picks',
        );
    }

    public function notifyGoalscorerPicks(string $topPlayer, int $total): void
    {
        $this->sendMatchAlert(
            title: "⚽ {$total} Goalscorer Pick".($total > 1 ? 's' : '').' Today!',
            message: "{$topPlayer} leads today's anytime-scorer picks. Tap to see them →",
            path: '/goalscorer-picks',
        );
    }

    public function notifyTennisPicks(string $topMatch, string $topPick, int $total): void
    {
        $this->sendMatchAlert(
            title: "🎾 {$total} Tennis Pick".($total > 1 ? 's' : '').' Today!',
            message: "{$topMatch} → {$topPick}. Tap for today's tennis picks →",
            path: '/tennis',
        );
    }

    public function notifyTeam3Picks(string $topMatch, string $topTeam, int $total): void
    {
        $this->sendMatchAlert(
            title: "📊 {$total} Team Goals Pick".($total > 1 ? 's' : '').' — Low Scorers!',
            message: "{$topMatch}: {$topTeam} unlikely to hit threshold. Tap for breakdown →",
            path: '/team-3-plus',
        );
    }

    public function notifyDoubleChancePicks(string $topMatch, string $label, string $prob, int $total): void
    {
        $desc = $label === '1X' ? 'Home Win or Draw' : 'Away Win or Draw';
        $this->sendMatchAlert(
            title: "🛡️ {$total} Double Chance Pick".($total > 1 ? 's' : '')." — {$prob}% Safe!",
            message: "{$topMatch} — {$label} ({$desc}). Tap for all picks →",
            path: '/double-chance',
        );
    }

    public function notifyLineupUpdated(string $topMatch, string $topTip, int $total): void
    {
        $extra = $total > 1 ? ' + '.($total - 1).' more' : '';
        $this->sendMatchAlert(
            title: "⚡ Lineups In — {$total} Pick".($total > 1 ? 's' : '').' Updated!',
            message: "{$topMatch} → {$topTip}{$extra}. Re-analysed with confirmed squads →",
            path: '/lineup-picks',
        );
    }

    public function notifyPickQualityUpdate(int $withdrawn, int $replacements): void
    {
        $replacementText = $replacements > 0
            ? "{$replacements} replacement".($replacements === 1 ? '' : 's').' are live.'
            : 'No replacement cleared the quality gate.';

        $this->sendMatchAlert(
            title: "🛡️ TavsScore Pick Update — {$withdrawn} withdrawn",
            message: "Late data changed the quality check. {$replacementText} Tap to review the latest board.",
            path: '/picks',
        );
    }

    public function notifyCorrectScorePicks(string $topMatch, string $topScore, int $total): void
    {
        $this->sendMatchAlert(
            title: "🎯 {$total} Correct Score Prediction".($total > 1 ? 's' : '').' Live!',
            message: "{$topMatch} — top score: {$topScore}. Tap for all predictions →",
            path: '/correct-score',
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Outcome notifications
    // ─────────────────────────────────────────────────────────────

    public function notifyPickWon(string $match, string $tip, string $score, string $league, string $path = '/picks'): void
    {
        $leaguePart = $league ? " | {$league}" : '';
        $this->sendMatchAlert(
            title: "✅ PICK WON! {$match} 🔥",
            message: "{$tip} landed — Final: {$score}{$leaguePart}. Tap to see results →",
            path: $path,
        );
    }

    public function notifyPickLost(string $match, string $tip, string $score, string $league, string $path = '/picks'): void
    {
        $leaguePart = $league ? " | {$league}" : '';
        $this->sendMatchAlert(
            title: "❌ Pick Lost — {$match}",
            message: "Final: {$score}{$leaguePart}. Tip: {$tip}. Back stronger tomorrow 💪",
            path: $path,
        );
    }

    public function notifyRolloverPick(int $day, string $match, string $tip, float $stake, float $potReturn): void
    {
        $stakeF = number_format($stake, 0);
        $returnF = number_format($potReturn, 0);
        $this->sendMatchAlert(
            title: "🎯 Rollover Day {$day} — Pick Is Live!",
            message: "{$match} — {$tip}. Stake: ₦{$stakeF} → Win: ₦{$returnF}. Tap to track →",
            path: '/rollover',
        );
    }

    public function notifyRolloverWon(int $day, string $match, string $score, float $returns): void
    {
        $returnF = number_format($returns, 0);
        $this->sendMatchAlert(
            title: "🎉 ROLLOVER DAY {$day} WON! 💰",
            message: "{$match} — Final: {$score}. Return: ₦{$returnF}. Pot keeps growing! 🔒",
            path: '/rollover',
        );
    }

    public function notifyRolloverLost(int $day, string $match, string $score): void
    {
        $this->sendMatchAlert(
            title: "😔 Rollover Day {$day} Lost",
            message: "{$match} ended {$score}. New challenge starts soon. Stay with us 💪",
            path: '/rollover',
        );
    }

    public function notifyWinnerReminder(): void
    {
        $this->sendMatchAlert(
            title: '🏆 Won with our pick? Show the world!',
            message: 'Share your winning screenshot on our Winners Wall — takes 30 seconds 📸',
            path: '/winners',
        );
    }
}
