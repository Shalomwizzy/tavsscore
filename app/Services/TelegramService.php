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
            $rank        = $i === 0 ? '👑' : '⭐';
            $match       = $pick['match'] ?? '';
            $tip         = $pick['tip'] ?? '';
            $conf        = $pick['confidence'] ?? '';
            $league      = $pick['league'] ?? '';
            $leaguePart  = $league ? "\n   🏆 {$league}" : '';
            $lines[] = "{$rank} <b>{$match}</b>{$leaguePart}\n   Tip: <b>{$tip}</b> ({$conf}% confidence)";
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

    public function sendCorrectScoreOutcome(
        string $match,
        string $predictedScores,
        string $actualScore,
        bool   $won,
        string $siteUrl,
        string $league = ''
    ): void {
        $leagueLine = $league ? "🏆 {$league}\n" : '';

        if ($won) {
            $msg = "🎯🔥 <b>CORRECT SCORE — NAILED IT!</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$actualScore}</b>\n"
                . "Our predictions included: <b>{$predictedScores}</b> ✅\n\n"
                . "The AI called the exact scoreline! 🤖🎯\n"
                . "🔗 <a href=\"{$siteUrl}/correct-score\">See correct score predictions</a>";
        } else {
            $msg = "😔 <b>Correct Score — Not This Time</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$actualScore}</b>\n"
                . "Our predictions: <b>{$predictedScores}</b> ❌\n\n"
                . "Correct scores are the hardest to predict in football 🙏\n"
                . "We keep analysing to get closer every day 💪\n"
                . "🔗 <a href=\"{$siteUrl}/correct-score\">See correct score predictions</a>";
        }

        $this->send($msg);
    }

    public function sendCorrectScores(array $predictions, string $siteUrl): void
    {
        if (empty($predictions)) return;

        $lines = ["🎯 <b>Today's Correct Score Predictions</b>\n"];

        foreach ($predictions as $pred) {
            $match      = $pred['match'] ?? '';
            $scores     = $pred['scores'] ?? [];
            $league     = $pred['league'] ?? '';
            if (empty($scores)) continue;

            $scoreLine   = collect($scores)
                ->map(fn ($s) => "{$s['score']} ({$s['pct']}%)")
                ->implode(' | ');
            $leaguePart  = $league ? "\n   🏆 {$league}" : '';

            $lines[] = "⚽ <b>{$match}</b>{$leaguePart}\n   🎯 {$scoreLine}";
        }

        $lines[] = "\n🔗 <a href=\"{$siteUrl}/correct-score\">See all predictions</a>";
        $lines[] = "⚠️ Correct scores are hardest to predict. Use for reference only.";

        $this->send(implode("\n", $lines));
    }

    public function sendCorrectPick(string $match, string $outcome, string $score, string $siteUrl, string $league = ''): void
    {
        $leagueLine = $league ? "🏆 {$league}\n" : '';

        $message = "🔥 <b>BANG ON! We nailed it!</b> ✅\n\n"
            . "{$leagueLine}"
            . "⚽ <b>{$match}</b>\n"
            . "Final score: <b>{$score}</b>\n"
            . "Our tip: <b>{$outcome}</b> — correct! 🎯\n\n"
            . "Keep trusting the AI picks 💡\n"
            . "🔗 <a href=\"{$siteUrl}/picks\">See today's picks</a>";

        $this->send($message);
    }

    public function sendWrongPick(string $match, string $outcome, string $score, string $siteUrl, string $league = ''): void
    {
        $leagueLine = $league ? "🏆 {$league}\n" : '';

        $message = "😔 <b>This one didn't go our way</b>\n\n"
            . "{$leagueLine}"
            . "⚽ <b>{$match}</b>\n"
            . "Final score: <b>{$score}</b>\n"
            . "Our tip: <b>{$outcome}</b> ❌\n\n"
            . "Football is unpredictable — that's what makes it beautiful 🙏\n"
            . "We analyse every game carefully and we'll come back stronger tomorrow 💪\n"
            . "🔗 <a href=\"{$siteUrl}/picks\">See today's picks</a>";

        $this->send($message);
    }

    public function sendRolloverPick(
        string $match,
        string $tip,
        int    $day,
        float  $stake,
        float  $potentialReturn,
        string $siteUrl,
        string $league = ''
    ): void {
        $leaguePart = $league ? "\n   🏆 {$league}" : '';

        $msg = "🎯 <b>ROLLOVER DAY {$day} PICK IS LIVE!</b>\n\n"
            . "⚽ <b>{$match}</b>{$leaguePart}\n"
            . "Tip: <b>{$tip}</b>\n\n"
            . "💰 Stake: <b>" . number_format($stake, 0) . " NGN</b>\n"
            . "🎁 Potential Return: <b>" . number_format($potentialReturn, 0) . " NGN</b>\n\n"
            . "🔗 <a href=\"{$siteUrl}/rollover\">Track the challenge</a>\n"
            . "⚠️ No prediction is guaranteed. Bet responsibly.";

        $this->send($msg);
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
            $msg = "🎉🔥 <b>ROLLOVER DAY {$day} — WON!</b> 💰\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final: <b>{$score}</b>\n"
                . "Our tip: <b>{$tip}</b> ✅\n\n"
                . "💸 Stake: <b>" . number_format($stake, 0) . "</b>\n"
                . "💰 Return: <b>" . number_format($returns, 0) . "</b>\n\n"
                . "The pot keeps growing! Stay locked in 🔒\n"
                . "🔗 <a href=\"{$siteUrl}/rollover\">Track the rollover challenge</a>";
        } else {
            $msg = "😔 <b>ROLLOVER DAY {$day} — Lost</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final: <b>{$score}</b>\n"
                . "Our tip: <b>{$tip}</b> ❌\n\n"
                . "Football isn't always fair — we gave it our best shot 🙏\n"
                . "A fresh challenge starts soon. Stay with us, we rise again 💪\n"
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
            $msg = "⚡🎯 <b>Lineup Pick — WON!</b> 🔥\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final: <b>{$score}</b>\n"
                . "Tip: <b>{$tip}</b> ✅\n\n"
                . "Lineups don't lie — the AI read this one perfectly 🤖💡\n"
                . "🔗 <a href=\"{$siteUrl}/lineup-picks\">See lineup picks</a>";
        } else {
            $msg = "😔 <b>Lineup Pick — Lost</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final: <b>{$score}</b>\n"
                . "Tip: <b>{$tip}</b> ❌\n\n"
                . "Even with lineup data, football can surprise us 🤷\n"
                . "We keep analysing, keep improving 💪\n"
                . "🔗 <a href=\"{$siteUrl}/lineup-picks\">See lineup picks</a>";
        }

        $this->send($msg);
    }

    public function sendWinnerUploadReminder(string $siteUrl): void
    {
        $this->send(
            "🏆 <b>Won with our pick? Show the world!</b>\n\n" .
            "If you made money from this prediction, come share your winning screenshot on our Winners Wall — it takes 30 seconds, gets you featured, and motivates the whole community! 🙌\n\n" .
            "👉 <a href=\"{$siteUrl}/winners\">Upload your winnings here</a>\n\n" .
            "📸 Screenshot of your bet slip or payout is all we need."
        );
    }

    public function sendDrawOutcome(string $match, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        $leagueLine = $league ? "🏆 {$league}\n" : '';
        if ($won) {
            $msg = "🤝✅ <b>DRAW PICK — WON!</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$score}</b>\n\n"
                . "Both teams shared the points exactly as we predicted! 💰\n\n"
                . "🔗 <a href=\"{$siteUrl}/draw-picks\">See all draw picks</a>";
        } else {
            $msg = "🤝❌ <b>Draw Pick — Didn't Land</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$score}</b>\n\n"
                . "No draw this time. The AI will find the next one 💪\n\n"
                . "🔗 <a href=\"{$siteUrl}/draw-picks\">See draw picks</a>";
        }
        $this->send($msg);
    }

    public function sendGGOutcome(string $match, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        $leagueLine = $league ? "🏆 {$league}\n" : '';
        if ($won) {
            $msg = "⚽✅ <b>GG PICK — WON!</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$score}</b>\n\n"
                . "Both teams found the net! GG confirmed! 🔥💰\n\n"
                . "🔗 <a href=\"{$siteUrl}/gg-picks\">See all GG picks</a>";
        } else {
            $msg = "⚽❌ <b>GG Pick — Didn't Land</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$score}</b>\n\n"
                . "Not both teams scored this time. We'll get the next one 💪\n\n"
                . "🔗 <a href=\"{$siteUrl}/gg-picks\">See GG picks</a>";
        }
        $this->send($msg);
    }

    public function sendDrawPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) return;

        $lines = ["🤝 <b>Today's Draw Picks — Triple AI Agreed</b>\n"];

        foreach ($picks as $i => $pick) {
            $rank       = $i === 0 ? '👑' : '⚖️';
            $match      = $pick['match'] ?? '';
            $conf       = $pick['confidence'] ?? '';
            $league     = $pick['league'] ?? '';
            $leaguePart = $league ? "\n   🏆 {$league}" : '';
            $lines[] = "{$rank} <b>{$match}</b>{$leaguePart}\n   Tip: <b>Draw</b> ({$conf}% confidence)";
        }

        $lines[] = "\n🔗 <a href=\"{$siteUrl}/draw-picks\">See full analysis</a>";
        $lines[] = "\n⚠️ No prediction is guaranteed. Bet responsibly.";

        $this->send(implode("\n", $lines));
    }

    public function sendGGPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) return;

        $lines = ["⚽ <b>Today's GG Picks — Both Teams to Score</b>\n"];

        foreach ($picks as $i => $pick) {
            $rank       = $i === 0 ? '👑' : '⚽';
            $match      = $pick['match'] ?? '';
            $conf       = $pick['confidence'] ?? '';
            $league     = $pick['league'] ?? '';
            $leaguePart = $league ? "\n   🏆 {$league}" : '';
            $lines[] = "{$rank} <b>{$match}</b>{$leaguePart}\n   Tip: <b>Both Teams Score</b> ({$conf}% confidence)";
        }

        $lines[] = "\n🔗 <a href=\"{$siteUrl}/gg-picks\">See full analysis</a>";
        $lines[] = "\n⚠️ No prediction is guaranteed. Bet responsibly.";

        $this->send(implode("\n", $lines));
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

    public function sendOver15Outcome(string $match, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        $leagueLine = $league ? "🏆 {$league}\n" : '';
        if ($won) {
            $msg = "⚽✅ <b>OVER 1.5 — WON!</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$score}</b>\n\n"
                . "Goals delivered! Over 1.5 confirmed! 💰\n\n"
                . "🔗 <a href=\"{$siteUrl}/over-1-5\">See all Over 1.5 picks</a>";
        } else {
            $msg = "⚽❌ <b>Over 1.5 — Didn't Land</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$score}</b>\n\n"
                . "Low-scoring game this time. We move 💪\n\n"
                . "🔗 <a href=\"{$siteUrl}/over-1-5\">See picks</a>";
        }
        $this->send($msg);
    }

    public function sendOver25Outcome(string $match, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        $leagueLine = $league ? "🏆 {$league}\n" : '';
        if ($won) {
            $msg = "🔥✅ <b>OVER 2.5 — WON!</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$score}</b>\n\n"
                . "Goals galore! Over 2.5 nailed! 💰🔥\n\n"
                . "🔗 <a href=\"{$siteUrl}/over-2-5\">See all Over 2.5 picks</a>";
        } else {
            $msg = "🔥❌ <b>Over 2.5 — Didn't Land</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$score}</b>\n\n"
                . "Tight game this time. AI recalibrates 💪\n\n"
                . "🔗 <a href=\"{$siteUrl}/over-2-5\">See picks</a>";
        }
        $this->send($msg);
    }

    public function sendTeam3PlusOutcome(string $match, string $team, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        $leagueLine = $league ? "🏆 {$league}\n" : '';
        if ($won) {
            $msg = "🚫✅ <b>TEAM 3+ NO — WON!</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$score}</b>\n\n"
                . "{$team} did NOT score 3+ — exactly as predicted! 🔥💰\n\n"
                . "🔗 <a href=\"{$siteUrl}/team-3-plus\">See all Team 3+ picks</a>";
        } else {
            $msg = "🚫❌ <b>Team 3+ NO — Missed</b>\n\n"
                . "{$leagueLine}"
                . "⚽ <b>{$match}</b>\n"
                . "Final score: <b>{$score}</b>\n\n"
                . "{$team} managed to score 3+ this time. We recalibrate 💪\n\n"
                . "🔗 <a href=\"{$siteUrl}/team-3-plus\">See picks</a>";
        }
        $this->send($msg);
    }

    public function sendOver15Picks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) return;
        $lines = ["⚽ <b>Today's Over 1.5 Goals Picks</b>\n"];
        foreach ($picks as $i => $pick) {
            $rank       = $i === 0 ? '👑' : '⚽';
            $match      = $pick['match'] ?? '';
            $prob       = $pick['prob'] ?? '';
            $league     = $pick['league'] ?? '';
            $leaguePart = $league ? "\n   🏆 {$league}" : '';
            $lines[] = "{$rank} <b>{$match}</b>{$leaguePart}\n   Tip: <b>Over 1.5 Goals</b> ({$prob}% likely)";
        }
        $lines[] = "\n🔗 <a href=\"{$siteUrl}/over-1-5\">See full analysis</a>";
        $lines[] = "\n⚠️ No prediction is guaranteed. Bet responsibly.";
        $this->send(implode("\n", $lines));
    }

    public function sendOver25Picks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) return;
        $lines = ["🔥 <b>Today's Over 2.5 Goals Picks</b>\n"];
        foreach ($picks as $i => $pick) {
            $rank       = $i === 0 ? '👑' : '🔥';
            $match      = $pick['match'] ?? '';
            $prob       = $pick['prob'] ?? '';
            $league     = $pick['league'] ?? '';
            $leaguePart = $league ? "\n   🏆 {$league}" : '';
            $lines[] = "{$rank} <b>{$match}</b>{$leaguePart}\n   Tip: <b>Over 2.5 Goals</b> ({$prob}% likely)";
        }
        $lines[] = "\n🔗 <a href=\"{$siteUrl}/over-2-5\">See full analysis</a>";
        $lines[] = "\n⚠️ No prediction is guaranteed. Bet responsibly.";
        $this->send(implode("\n", $lines));
    }

    public function sendTeam3PlusPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) return;
        $lines = ["🚫 <b>Today's Team 3+ Goals — NO Picks</b>\n"];
        foreach ($picks as $i => $pick) {
            $rank       = $i === 0 ? '👑' : '🚫';
            $match      = $pick['match'] ?? '';
            $team       = $pick['team'] ?? '';
            $prob       = $pick['prob'] ?? '';
            $league     = $pick['league'] ?? '';
            $leaguePart = $league ? "\n   🏆 {$league}" : '';
            $lines[] = "{$rank} <b>{$match}</b>{$leaguePart}\n   Tip: <b>{$team} — 3+ Goals NO</b> (only {$prob}% chance they score 3+)";
        }
        $lines[] = "\n🔗 <a href=\"{$siteUrl}/team-3-plus\">See full analysis</a>";
        $lines[] = "\n⚠️ No prediction is guaranteed. Bet responsibly.";
        $this->send(implode("\n", $lines));
    }

    public function sendDoubleChancePicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) return;
        $lines = ["🎯 <b>Today's Double Chance Picks — 1X & 2X</b>\n"];
        foreach ($picks as $i => $pick) {
            $rank       = $i === 0 ? '👑' : '🎯';
            $match      = $pick['match'] ?? '';
            $label      = $pick['label'] ?? '1X';
            $prob       = $pick['prob'] ?? '';
            $league     = $pick['league'] ?? '';
            $leaguePart = $league ? "\n   🏆 {$league}" : '';
            $desc       = $label === '1X' ? 'Home Win or Draw' : 'Away Win or Draw';
            $lines[] = "{$rank} <b>{$match}</b>{$leaguePart}\n   Tip: <b>{$label} ({$desc})</b> — {$prob}% confidence";
        }
        $lines[] = "\n🔗 <a href=\"{$siteUrl}/double-chance\">See full analysis</a>";
        $lines[] = "\n⚠️ No prediction is guaranteed. Bet responsibly.";
        $this->send(implode("\n", $lines));
    }

    public function sendDoubleChanceOutcome(string $match, string $label, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        $desc = $label === '1X' ? 'Home Win or Draw' : 'Away Win or Draw';
        $leaguePart = $league ? "🏆 {$league} | " : '';
        if ($won) {
            $msg = "✅ <b>Double Chance {$label} — WON! 🎯</b>\n{$leaguePart}{$match} ended <b>{$score}</b>\nPick: <b>{$label} ({$desc})</b> ✅\n\n🔗 <a href=\"{$siteUrl}/double-chance\">View picks</a>";
        } else {
            $msg = "❌ <b>Double Chance {$label} — Missed</b>\n{$leaguePart}{$match} ended <b>{$score}</b>\nPick: <b>{$label} ({$desc})</b> — didn't land this time. We recalibrate 💪\n\n🔗 <a href=\"{$siteUrl}/double-chance\">View picks</a>";
        }
        $this->send($msg);
    }
}
