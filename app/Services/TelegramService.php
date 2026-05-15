<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;
    private string $channelId;

    public function __construct()
    {
        $this->token     = config('services.telegram.bot_token', '');
        $this->channelId = config('services.telegram.channel_id', '');
    }

    public function isConfigured(): bool
    {
        return ! blank($this->token) && ! blank($this->channelId);
    }

    public function send(string $message): void
    {
        if (! $this->isConfigured()) {
            Log::info('Telegram not configured - skipping message.');
            return;
        }

        $response = Http::timeout(10)->post(
            "https://api.telegram.org/bot{$this->token}/sendMessage",
            [
                'chat_id'                  => $this->channelId,
                'text'                     => $message,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => false,
            ]
        );

        if (! $response->successful()) {
            Log::error('Telegram send failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }
    }

    public function sendDailyPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) return;

        $lines = ["⭐ <b>Today's TavsScore Daily Picks</b>\n"];

        foreach ($picks as $i => $pick) {
            $rank   = $i === 0 ? '👑' : '⭐';
            $match  = $pick['match'] ?? '';
            $tip    = $pick['tip'] ?? '';
            $conf   = $pick['confidence'] ?? '';
            $league = $pick['league'] ?? '';
            $lines[] = "{$rank} <b>{$match}</b>\n   {$league}\n   Tip: <b>{$tip}</b> ({$conf}% confidence)";
        }

        $lines[] = "\n🔗 <a href=\"{$siteUrl}/picks\">See full analysis</a>";
        $lines[] = "\n⚠️ No prediction is guaranteed. Bet responsibly.";

        $this->send(implode("\n", $lines));
    }

    public function sendLineupPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) return;

        $lines = ["⚡ <b>Lineup Confirmed - Picks Updated!</b>\n"];

        foreach ($picks as $pick) {
            $league  = $pick['league'] ?? '';
            $prefix  = $league ? "🏆 {$league}\n   " : '';
            $lines[] = "• {$prefix}<b>{$pick['match']}</b>: {$pick['tip']} ({$pick['confidence']}%)";
        }

        $lines[] = "\n🔗 <a href=\"{$siteUrl}/lineup-picks\">See analysis</a>";

        $this->send(implode("\n", $lines));
    }

    public function sendCorrectScores(array $predictions, string $siteUrl): void
    {
        if (empty($predictions)) return;

        $lines = ["🎯 <b>Today's Correct Score Predictions</b>\n"];

        foreach ($predictions as $pred) {
            $match  = $pred['match'] ?? '';
            $scores = $pred['scores'] ?? [];
            if (empty($scores)) continue;

            $scoreLine = collect($scores)
                ->map(fn ($s) => "{$s['score']} ({$s['pct']}%)")
                ->implode(' | ');

            $lines[] = "⚽ <b>{$match}</b>\n   🎯 {$scoreLine}";
        }

        $lines[] = "\n🔗 <a href=\"{$siteUrl}/correct-score\">See all predictions</a>";
        $lines[] = "⚠️ Correct scores are hardest to predict. Use for reference only.";

        $this->send(implode("\n", $lines));
    }

    public function sendCorrectPick(string $match, string $outcome, string $score, string $siteUrl, string $league = ''): void
    {
        $leagueLine = $league ? "🏆 {$league}\n" : '';

        $message = "✅ <b>We Got It Right!</b>\n\n"
            . "{$leagueLine}"
            . "<b>{$match}</b>\n"
            . "Final score: {$score}\n"
            . "Our tip: <b>{$outcome}</b> ✓\n\n"
            . "🔗 <a href=\"{$siteUrl}/picks\">See today's picks</a>";

        $this->send($message);
    }

    public function sendRolloverOutcome(
        string $match,
        string $tip,
        string $score,
        string $status,
        int    $day,
        float  $stake,
        float  $returns,
        string $siteUrl,
        string $league = ''
    ): void {
        $won = $status === 'won';
        $leagueLine = $league ? "🏆 {$league}\n" : '';

        if ($won) {
            $msg = "🎉 <b>ROLLOVER DAY {$day} — WON!</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final: <b>{$score}</b>\n"
                . "Our tip: <b>{$tip}</b> ✅\n\n"
                . "💰 Stake: " . number_format($stake, 0) . " → Return: <b>" . number_format($returns, 0) . "</b>\n\n"
                . "🔗 <a href=\"{$siteUrl}/rollover\">See rollover progress</a>";
        } else {
            $msg = "😔 <b>ROLLOVER DAY {$day} — LOST</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final: <b>{$score}</b>\n"
                . "Our tip: <b>{$tip}</b> ❌\n\n"
                . "We go again 💪 A new challenge starts soon.\n\n"
                . "🔗 <a href=\"{$siteUrl}/rollover\">See rollover</a>";
        }

        $this->send($msg);
    }

    public function sendLineupOutcome(
        string $match,
        string $tip,
        string $score,
        bool   $won,
        string $siteUrl,
        string $league = ''
    ): void {
        $leagueLine = $league ? "🏆 {$league}\n" : '';

        if ($won) {
            $msg = "✅ <b>Lineup Pick — WON!</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final: <b>{$score}</b>\n"
                . "Tip: <b>{$tip}</b> ✅\n\n"
                . "🔗 <a href=\"{$siteUrl}/lineup-picks\">See lineup picks</a>";
        } else {
            $msg = "❌ <b>Lineup Pick — Lost</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final: <b>{$score}</b>\n"
                . "Tip: <b>{$tip}</b> ❌\n\n"
                . "Better luck next time 💪\n"
                . "🔗 <a href=\"{$siteUrl}/lineup-picks\">See lineup picks</a>";
        }

        $this->send($msg);
    }

    public function sendDailyResults(array $results, string $siteUrl): void
    {
        if (empty($results)) return;

        $correct = collect($results)->where('correct', true)->count();
        $total   = count($results);

        $emoji  = $correct === $total ? '🔥' : ($correct > 0 ? '✅' : '❌');
        $lines  = ["{$emoji} <b>Today's Pick Results</b>\n"];

        foreach ($results as $r) {
            $tick    = $r['correct'] ? '✅' : '❌';
            $score   = $r['score'] ? " [{$r['score']}]" : '';
            $conf    = $r['confidence'] ? " ({$r['confidence']}%)" : '';
            $league  = isset($r['league']) && $r['league'] ? "🏆 {$r['league']}\n   " : '';
            $lines[] = "{$tick} {$league}<b>{$r['match']}</b>{$score}\n   Tip: {$r['tip']}{$conf}";
        }

        $lines[] = "\n📊 <b>{$correct}/{$total}</b> picks correct today";

        if ($correct === $total) {
            $lines[] = "🔥 Perfect day - all picks correct!";
        } elseif ($correct === 0) {
            $lines[] = "We'll come back stronger tomorrow 💪";
        }

        $lines[] = "\n🔗 <a href=\"{$siteUrl}/picks\">See full analysis</a>";
        $lines[] = "⚠️ No prediction is guaranteed. Bet responsibly.";

        $this->send(implode("\n", $lines));
    }
}
