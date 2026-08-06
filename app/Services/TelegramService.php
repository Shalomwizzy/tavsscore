<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TelegramService
{
    private string $token;

    private string $channelId;

    // Only SportyBet booking codes + tennis picks post to Telegram now. Every
    // other send() is suppressed (content still shows on the website). The
    // whitelisted methods flip this flag around their own send() call.
    private bool $essential = false;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token', '');
        $this->channelId = config('services.telegram.channel_id', '');
    }

    /** Run $fn with Telegram sending temporarily allowed (booking + tennis only). */
    private function asEssential(callable $fn): void
    {
        $previous = $this->essential;
        $this->essential = true;
        try {
            $fn();
        } finally {
            $this->essential = $previous;
        }
    }

    public function isConfigured(): bool
    {
        return ! blank($this->token) && ! blank($this->channelId);
    }

    /** @param array<int, array<int, array{text: string, url: string}>>|null $inlineKeyboard */
    public function send(string $message, ?array $inlineKeyboard = null): void
    {
        if (! $this->essential) {
            return; // Telegram is limited to booking codes + tennis picks.
        }

        if (! $this->isConfigured()) {
            Log::info('Telegram not configured - skipping message.');

            return;
        }

        $payload = [
            'chat_id' => $this->channelId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = ['inline_keyboard' => $inlineKeyboard];
        }

        $response = Http::timeout(10)->post(
            "https://api.telegram.org/bot{$this->token}/sendMessage",
            $payload
        );

        if (! $response->successful()) {
            Log::error('Telegram send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Pick notifications
    // ─────────────────────────────────────────────────────────────

    public function sendDailyPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        if ($this->sendPredictionCard("Today's Football Signals", $picks, $siteUrl, '/predictions')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '⚽ <b>TODAY\'S FOOTBALL SIGNALS</b>',
            "<i>{$date} • TavsScore data-led analysis</i>",
            '',
            '<b>THE SHORTLIST</b>',
        ];

        foreach ($picks as $i => $pick) {
            $match = $this->escape((string) ($pick['match'] ?? ''));
            $tip = $this->escape((string) ($pick['tip'] ?? ''));
            $conf = $pick['confidence'] ?? '';
            $league = $this->escape((string) ($pick['league'] ?? ''));
            $signal = $i === 0 ? '🟢' : ($i === 1 ? '🟡' : '🔵');
            $confidence = $conf !== '' ? " <b>• {$conf}%</b>" : '';

            $lines[] = "\n{$signal} <b>{$match}</b>";
            $lines[] = "{$tip}{$confidence}";
            if ($league !== '') {
                $lines[] = "<i>{$league}</i>";
            }
        }

        $lines[] = "\n<i>Data checked: form • team news • match trends</i>";
        $lines[] = '<i>Predictions are analysis, not guarantees. Play responsibly.</i>';

        $this->send(implode("\n", $lines), [[[
            'text' => 'VIEW ALL PREDICTIONS →',
            'url' => rtrim($siteUrl, '/').'/predictions',
        ]]]);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Sends one complete data card: all matches and model calls live inside the
     * JPEG, with only an action button below it. Falls back to the established
     * text formatter if the server cannot render/send a photo.
     *
     * @param array<int, array<string, mixed>> $picks
     */
    private function sendPredictionCard(string $title, array $picks, string $siteUrl, string $path): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $imagePath = app(TelegramPickCardService::class)->render($title, $picks);
        if (! $imagePath) {
            Log::warning('Telegram prediction card was not sent as an image; falling back to text.', ['title' => $title]);

            return false;
        }

        try {
            return $this->sendPhoto($imagePath, $this->predictionCardCaption($title, $picks), [[[
                'text' => '📊 VIEW FULL ANALYSIS',
                'url' => rtrim($siteUrl, '/').$path,
            ]]], fallbackToText: false);
        } finally {
            Storage::disk('public')->delete($imagePath);
        }
    }

    /**
     * Telegram displays a photo caption directly below its image. Keep the
     * useful match list there as well, rather than making users choose between
     * a visual card and the readable text details they already know.
     *
     * @param array<int, array<string, mixed>> $picks
     */
    private function predictionCardCaption(string $title, array $picks): string
    {
        $lines = [
            '⚽ <b>TAVSSCORE — '.$this->escape($title).'</b>',
            '<i>'.now('Africa/Lagos')->format('l, d M Y · H:i').' Lagos</i>',
            '━━━━━━━━━━━━━━━━━━━━',
        ];

        foreach (array_values($picks) as $index => $pick) {
            $match = trim((string) ($pick['match'] ?? ''));
            if ($match === '') {
                continue;
            }

            $league = trim((string) ($pick['league'] ?? $pick['tournament'] ?? ''));
            $tip = $this->captionTip($pick);
            $probability = $pick['confidence'] ?? $pick['prob'] ?? null;
            $parts = ["\n<b>".($index + 1).'. '.$this->escape($this->captionShorten($match, 48)).'</b>'];

            if ($league !== '') {
                $parts[] = '🏟️ <i>'.$this->escape($this->captionShorten($league, 38)).'</i>';
            }
            $parts[] = '📌 '.$this->escape($this->captionShorten($tip, 50));
            if ($probability !== null && $probability !== '') {
                $parts[] = '📊 <b>'.$this->escape((string) $probability).'%</b>';
            }

            $candidate = implode("\n", $parts);
            // Telegram photo captions have a 1,024-character limit. Preserve
            // complete rows and a proper closing disclaimer instead of cutting
            // a match or an HTML tag in half.
            if (mb_strlen(implode("\n", $lines).$candidate) > 810) {
                break;
            }
            $lines[] = $candidate;
        }

        $lines[] = '━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '<i>Model analysis, not a guarantee. Play responsibly.</i>';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $pick */
    private function captionTip(array $pick): string
    {
        foreach (['tip', 'line', 'label', 'winner'] as $field) {
            if (filled($pick[$field] ?? null)) {
                return (string) $pick[$field];
            }
        }

        if (filled($pick['player'] ?? null)) {
            return (string) $pick['player'].' — Anytime goalscorer';
        }
        if (filled($pick['team'] ?? null) && filled($pick['market'] ?? null)) {
            return (string) $pick['team'].' — '.(string) $pick['market'];
        }
        if (isset($pick['scores']) && is_array($pick['scores'])) {
            $scores = collect($pick['scores'])->take(2)->map(function ($score): string {
                $value = (string) ($score['score'] ?? '');
                $pct = $score['pct'] ?? null;

                return $pct !== null ? $value.' ('.$pct.'%)' : $value;
            })->filter()->implode(' · ');
            if ($scores !== '') {
                return 'Top score: '.$scores;
            }
        }

        return 'Model selection';
    }

    private function captionShorten(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1).'…' : $value;
    }

    public function sendLineupPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        if ($this->sendPredictionCard('Lineups Confirmed', $picks, $siteUrl, '/lineup-picks')) {
            return;
        }

        $lines = [
            '⚡ <b>LINEUPS CONFIRMED — PICKS UPDATED</b>',
            '<i>Squads are in. AI has re-analysed.</i>',
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];

        foreach ($picks as $pick) {
            $league = $pick['league'] ?? '';
            $lines[] = '';
            if ($league) {
                $lines[] = "🏟️ <i>{$league}</i>";
            }
            $lines[] = "⚽ <b>{$pick['match']}</b>";
            $lines[] = "📌 Tip: <b>{$pick['tip']}</b>";
            $lines[] = "📊 Confidence: <b>{$pick['confidence']}%</b>";
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/lineup-picks\">See lineup analysis →</a>";

        $this->send(implode("\n", $lines));
    }

    /** @param array<int,array{match:string,market:string}> $withdrawn @param array<int,array{match:string,market:string}> $replacements */
    public function sendPickQualityUpdate(array $withdrawn, array $replacements, string $siteUrl): void
    {
        $lines = [
            '🛡️ <b>TAVSSCORE — QUALITY UPDATE</b>',
            '<i>Late lineup, injury, odds or data-freshness checks changed the board.</i>',
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];
        foreach ($withdrawn as $pick) {
            $lines[] = "\n❌ <b>Withdrawn</b>: ".$this->escape($pick['match'])."\n<i>".$this->escape($pick['market']).'</i>';
        }
        if (! empty($replacements)) {
            $lines[] = "\n✅ <b>Current qualifying picks</b>";
            foreach ($replacements as $pick) {
                $lines[] = '• <b>'.$this->escape($pick['match']).'</b> — '.$this->escape($pick['market']);
            }
        }
        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = '🔗 <a href="'.rtrim($siteUrl, '/').'/picks">Review the latest board →</a>';
        $lines[] = '<i>Predictions are analysis, not guarantees. Play responsibly.</i>';

        $this->send(implode("\n", $lines));
    }

    public function sendDrawPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        $cardPicks = array_map(fn (array $pick) => $pick + ['tip' => 'Draw (X)'], $picks);
        if ($this->sendPredictionCard('Draw Picks', $cardPicks, $siteUrl, '/draw-picks')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '🤝 <b>TAVSSCORE — DRAW PICKS</b>',
            "<i>📅 {$date}</i>",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
            "\n<i>Statistically strong draw signals — Poisson model + AI consensus</i>",
        ];

        foreach ($picks as $i => $pick) {
            $match = $pick['match'] ?? '';
            $conf = $pick['confidence'] ?? '';
            $league = $pick['league'] ?? '';
            $label = $i === 0 ? '👑 <b>TOP DRAW</b>' : '⚖️ <b>DRAW '.($i + 1).'</b>';

            $lines[] = "\n{$label}";
            if ($league) {
                $lines[] = "🏟️ <i>{$league}</i>";
            }
            $lines[] = "⚽ <b>{$match}</b>";
            $lines[] = '📌 Tip: <b>Draw (X)</b>';
            $lines[] = "📊 AI Confidence: <b>{$conf}%</b>";
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/draw-picks\">Full draw analysis →</a>";
        $lines[] = "\n<i>⚠️ AI predictions — not financial advice. Gamble responsibly.</i>";

        $this->send(implode("\n", $lines));
    }

    public function sendGGPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        $cardPicks = array_map(fn (array $pick) => $pick + ['tip' => 'Both Teams To Score — GG'], $picks);
        if ($this->sendPredictionCard('Both Teams To Score', $cardPicks, $siteUrl, '/gg-picks')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '⚽ <b>TAVSSCORE — BOTH TEAMS TO SCORE</b>',
            "<i>📅 {$date}</i>",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
            "\n<i>High BTTS probability — Poisson simulation + AI agreement</i>",
        ];

        foreach ($picks as $i => $pick) {
            $match = $pick['match'] ?? '';
            $conf = $pick['confidence'] ?? '';
            $league = $pick['league'] ?? '';
            $label = $i === 0 ? '👑 <b>TOP GG PICK</b>' : '⚽ <b>GG PICK '.($i + 1).'</b>';

            $lines[] = "\n{$label}";
            if ($league) {
                $lines[] = "🏟️ <i>{$league}</i>";
            }
            $lines[] = "⚽ <b>{$match}</b>";
            $lines[] = '📌 Tip: <b>Both Teams to Score — GG</b>';
            $lines[] = "📊 AI Confidence: <b>{$conf}%</b>";
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/gg-picks\">Full GG analysis →</a>";
        $lines[] = "\n<i>⚠️ AI predictions — not financial advice. Gamble responsibly.</i>";

        $this->send(implode("\n", $lines));
    }

    public function sendOver15Picks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        $cardPicks = array_map(fn (array $pick) => $pick + ['tip' => 'Over 1.5 Goals'], $picks);
        if ($this->sendPredictionCard('Over 1.5 Goals', $cardPicks, $siteUrl, '/over-1-5')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '🎯 <b>TAVSSCORE — OVER 1.5 GOALS</b>',
            "<i>📅 {$date}</i>",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];

        foreach ($picks as $i => $pick) {
            $match = $pick['match'] ?? '';
            $prob = $pick['prob'] ?? '';
            $league = $pick['league'] ?? '';
            $label = $i === 0 ? '👑 <b>TOP PICK</b>' : '⚽ <b>PICK '.($i + 1).'</b>';

            $lines[] = "\n{$label}";
            if ($league) {
                $lines[] = "🏟️ <i>{$league}</i>";
            }
            $lines[] = "⚽ <b>{$match}</b>";
            $lines[] = '📌 Tip: <b>Over 1.5 Goals</b>';
            $lines[] = "📊 Poisson Probability: <b>{$prob}%</b>";
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/over-1-5\">Full analysis →</a>";
        $lines[] = "\n<i>⚠️ AI predictions — not financial advice. Gamble responsibly.</i>";

        $this->send(implode("\n", $lines));
    }

    public function sendOver25Picks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        $cardPicks = array_map(fn (array $pick) => $pick + ['tip' => 'Over 2.5 Goals'], $picks);
        if ($this->sendPredictionCard('Over 2.5 Goals', $cardPicks, $siteUrl, '/over-2-5')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '🔥 <b>TAVSSCORE — OVER 2.5 GOALS</b>',
            "<i>📅 {$date}</i>",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];

        foreach ($picks as $i => $pick) {
            $match = $pick['match'] ?? '';
            $prob = $pick['prob'] ?? '';
            $league = $pick['league'] ?? '';
            $label = $i === 0 ? '👑 <b>TOP PICK</b>' : '🔥 <b>PICK '.($i + 1).'</b>';

            $lines[] = "\n{$label}";
            if ($league) {
                $lines[] = "🏟️ <i>{$league}</i>";
            }
            $lines[] = "⚽ <b>{$match}</b>";
            $lines[] = '📌 Tip: <b>Over 2.5 Goals</b>';
            $lines[] = "📊 Poisson Probability: <b>{$prob}%</b>";
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/over-2-5\">Full analysis →</a>";
        $lines[] = "\n<i>⚠️ AI predictions — not financial advice. Gamble responsibly.</i>";

        $this->send(implode("\n", $lines));
    }

    /** A readable shared Telegram card for Under and Asian Handicap slates. */
    public function sendSpecialtyPicks(array $picks, string $title, string $path, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        if ($this->sendPredictionCard($title, $picks, $siteUrl, $path)) {
            return;
        }
        $lines = ['<b>TAVSSCORE — '.$this->escape($title).'</b>', '<i>'.now('Africa/Lagos')->format('l, d M Y').'</i>', '━━━━━━━━━━━━━━━━━━━━━━━━━━'];
        foreach ($picks as $i => $pick) {
            $lines[] = "\n".($i === 0 ? '👑 <b>TOP PICK</b>' : '⚽ <b>PICK '.($i + 1).'</b>');
            if (! empty($pick['league'])) {
                $lines[] = '🏟️ <i>'.$this->escape((string) $pick['league']).'</i>';
            }
            $lines[] = '⚽ <b>'.$this->escape((string) ($pick['match'] ?? '')).'</b>';
            $lines[] = '📌 Tip: <b>'.$this->escape((string) ($pick['tip'] ?? '')).'</b>';
            $lines[] = '📊 Model probability: <b>'.(int) ($pick['prob'] ?? 0).'%</b>';
        }
        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = '🔗 <a href="'.rtrim($siteUrl, '/').$path.'">Full analysis →</a>';
        $lines[] = '<i>Predictions are analysis, not guarantees. Play responsibly.</i>';
        $this->send(implode("\n", $lines));
    }

    public function sendSpecialtyOutcome(string $match, string $market, string $score, bool $won, string $path, string $siteUrl, string $league = ''): void
    {
        $status = $won ? '✅ WON' : '❌ MISSED';
        $message = "<b>{$status} — ".$this->escape($market)."</b>\n"
            .'⚽ <b>'.$this->escape($match)."</b>\n"
            .($league ? '🏟️ <i>'.$this->escape($league)."</i>\n" : '')
            ."📊 Final score: <b>{$this->escape($score)}</b>\n\n"
            .'🔗 <a href="'.rtrim($siteUrl, '/').$path.'">View the picks →</a>';
        $this->send($message);
    }

    public function sendCornersPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        if ($this->sendPredictionCard('Corner Picks', $picks, $siteUrl, '/corners-picks')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '🚩 <b>TAVSSCORE — CORNER PICKS</b>',
            "<i>📅 {$date}</i>",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];

        foreach ($picks as $i => $pick) {
            $match = $pick['match'] ?? '';
            $line = $pick['line'] ?? '';
            $prob = $pick['prob'] ?? '';
            $league = $pick['league'] ?? '';
            $label = $i === 0 ? '👑 <b>TOP PICK</b>' : '🚩 <b>PICK '.($i + 1).'</b>';

            $lines[] = "\n{$label}";
            if ($league) {
                $lines[] = "🏟️ <i>{$league}</i>";
            }
            $lines[] = "⚽ <b>{$match}</b>";
            $lines[] = "📌 Tip: <b>{$line}</b>";
            if ($prob !== '') {
                $lines[] = "📊 Probability: <b>{$prob}%</b>";
            }
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/corners-picks\">Full analysis →</a>";
        $lines[] = "\n<i>⚠️ AI predictions — not financial advice. Gamble responsibly.</i>";

        $this->send(implode("\n", $lines));
    }

    public function sendGoalscorerPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        if ($this->sendPredictionCard('Anytime Goalscorer', $picks, $siteUrl, '/goalscorer-picks')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '⚽ <b>TAVSSCORE — ANYTIME GOALSCORER</b>',
            "<i>📅 {$date}</i>",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];

        foreach ($picks as $i => $pick) {
            $player = $pick['player'] ?? '';
            $match = $pick['match'] ?? '';
            $prob = $pick['prob'] ?? '';
            $label = $i === 0 ? '👑 <b>TOP PICK</b>' : '⚽ <b>PICK '.($i + 1).'</b>';

            $lines[] = "\n{$label}";
            $lines[] = "🎯 <b>{$player}</b> to score";
            if ($match) {
                $lines[] = "⚽ <i>{$match}</i>";
            }
            if ($prob !== '') {
                $lines[] = "📊 Probability: <b>{$prob}%</b>";
            }
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/goalscorer-picks\">Full list →</a>";
        $lines[] = "\n<i>⚠️ AI predictions — not financial advice. Gamble responsibly.</i>";

        $this->send(implode("\n", $lines));
    }

    public function sendTeam3PlusPicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        if ($this->sendPredictionCard('Team Goals Safety', $picks, $siteUrl, '/team-3-plus')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '📊 <b>TAVSSCORE — TEAM GOALS PICKS</b>',
            "<i>📅 {$date}</i>",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];

        foreach ($picks as $i => $pick) {
            $match = $pick['match'] ?? '';
            $team = $pick['team'] ?? '';
            $prob = $pick['prob'] ?? '';
            $market = $pick['market'] ?? '3+';
            $league = $pick['league'] ?? '';
            $label = $i === 0 ? '👑 <b>TOP PICK</b>' : '📊 <b>PICK '.($i + 1).'</b>';

            $lines[] = "\n{$label}";
            if ($league) {
                $lines[] = "🏟️ <i>{$league}</i>";
            }
            $lines[] = "⚽ <b>{$match}</b>";
            $lines[] = "📌 Tip: <b>{$team} — Under {$market} Goals</b>";
            $lines[] = "📊 Chance of scoring {$market}+: only <b>{$prob}%</b>";
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/team-3-plus\">Full analysis →</a>";
        $lines[] = "\n<i>⚠️ AI predictions — not financial advice. Gamble responsibly.</i>";

        $this->send(implode("\n", $lines));
    }

    public function sendDoubleChancePicks(array $picks, string $siteUrl): void
    {
        if (empty($picks)) {
            return;
        }
        if ($this->sendPredictionCard('Double Chance Picks', $picks, $siteUrl, '/double-chance')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '🛡️ <b>TAVSSCORE — DOUBLE CHANCE PICKS</b>',
            "<i>📅 {$date}</i>",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
            "\n<i>Two results cover you — highest safety margin picks</i>",
        ];

        foreach ($picks as $i => $pick) {
            $match = $pick['match'] ?? '';
            $label = $pick['label'] ?? '1X';
            $prob = $pick['prob'] ?? '';
            $league = $pick['league'] ?? '';
            $desc = $label === '1X' ? 'Home Win or Draw' : 'Away Win or Draw';
            $rank = $i === 0 ? '👑 <b>TOP PICK</b>' : '🛡️ <b>PICK '.($i + 1).'</b>';

            $lines[] = "\n{$rank}";
            if ($league) {
                $lines[] = "🏟️ <i>{$league}</i>";
            }
            $lines[] = "⚽ <b>{$match}</b>";
            $lines[] = "📌 Tip: <b>{$label} — {$desc}</b>";
            $lines[] = "📊 AI Confidence: <b>{$prob}%</b>";
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/double-chance\">Full analysis →</a>";
        $lines[] = "\n<i>⚠️ AI predictions — not financial advice. Gamble responsibly.</i>";

        $this->send(implode("\n", $lines));
    }

    // ─────────────────────────────────────────────────────────────
    //  Rollover pick & outcome
    // ─────────────────────────────────────────────────────────────

    public function sendRolloverPick(
        string $match,
        string $tip,
        int $day,
        float $stake,
        float $potentialReturn,
        string $siteUrl,
        string $league = '',
        array $cardPicks = [],
    ): void {
        $stakeF = number_format($stake, 0);
        $returnF = number_format($potentialReturn, 0);

        $msg = "🎯 <b>ROLLOVER CHALLENGE — DAY {$day}</b>\n"
            ."<i>Pick is live. Triple-AI verified.</i>\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        if ($league) {
            $msg .= "🏟️ <i>{$league}</i>\n";
        }
        $msg .= "⚽ <b>{$match}</b>\n"
            ."📌 Tip: <b>{$tip}</b>\n\n"
            ."💰 Stake: <b>₦{$stakeF}</b>\n"
            ."🎁 Potential Return: <b>₦{$returnF}</b>\n\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            ."🔗 <a href=\"{$siteUrl}/rollover\">Track the challenge →</a>\n"
            ."\n<i>⚠️ AI picks only. Not financial advice. Bet responsibly.</i>";

        // The rollover is a headline product, so its Telegram post gets the
        // same clear, watermarked image treatment as the daily pick boards.
        // The full readable ticket remains directly below the photo as its
        // caption, not as a separate follow-up message.
        if ($cardPicks !== []) {
            $imagePath = app(TelegramPickCardService::class)->render("Rollover Challenge — Day {$day}", $cardPicks);
            if ($imagePath) {
                try {
                    if ($this->sendPhoto($imagePath, $msg, [[[
                        'text' => '🎯 TRACK THE ROLLOVER',
                        'url' => rtrim($siteUrl, '/').'/rollover',
                    ]]], fallbackToText: false)) {
                        return;
                    }
                } finally {
                    Storage::disk('public')->delete($imagePath);
                }
            }
        }

        $this->send($msg, [[[
            'text' => '🎯 TRACK THE ROLLOVER',
            'url' => rtrim($siteUrl, '/').'/rollover',
        ]]]);
    }

    public function sendRolloverOutcome(
        string $match,
        string $tip,
        string $score,
        string $status,
        int $day,
        float $stake,
        float $returns,
        string $siteUrl,
        string $league = ''
    ): void {
        $won = $status === 'won';
        $stakeF = number_format($stake, 0);
        $returnF = number_format($returns, 0);

        if ($won) {
            $msg = "🎉 <b>ROLLOVER DAY {$day} — WON! 🔥</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n"
                ."📌 Our Tip: <b>{$tip}</b> ✅\n\n"
                ."💸 Stake: <b>₦{$stakeF}</b>\n"
                ."💰 Return: <b>₦{$returnF}</b>\n\n"
                ."The pot keeps growing! Stay locked in 🔒\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/rollover\">Track the rollover →</a>";
        } else {
            $msg = "😔 <b>ROLLOVER DAY {$day} — LOST</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n"
                ."📌 Our Tip: <b>{$tip}</b> ❌\n\n"
                ."Football can surprise — we gave it our best shot 🙏\n"
                ."A new challenge starts soon. We rise again 💪\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/rollover\">See rollover history →</a>";
        }

        $this->send($msg);
    }

    // ─────────────────────────────────────────────────────────────
    //  Outcome notifications
    // ─────────────────────────────────────────────────────────────

    public function sendCorrectPick(string $match, string $outcome, string $score, string $siteUrl, string $league = ''): void
    {
        $msg = "✅ <b>PICK WON! 🔥</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        if ($league) {
            $msg .= "🏟️ <i>{$league}</i>\n";
        }
        $msg .= "⚽ <b>{$match}</b>\n"
            ."🏁 Final Score: <b>{$score}</b>\n"
            ."📌 Our Tip: <b>{$outcome}</b> ✅\n\n"
            ."Keep trusting the AI 💡\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            ."🔗 <a href=\"{$siteUrl}/picks\">See today's picks →</a>";

        $this->send($msg);
    }

    public function sendWrongPick(string $match, string $outcome, string $score, string $siteUrl, string $league = ''): void
    {
        $msg = "❌ <b>Pick Didn't Land</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        if ($league) {
            $msg .= "🏟️ <i>{$league}</i>\n";
        }
        $msg .= "⚽ <b>{$match}</b>\n"
            ."🏁 Final Score: <b>{$score}</b>\n"
            ."📌 Our Tip: <b>{$outcome}</b> ❌\n\n"
            ."Football is unpredictable — that's what makes it beautiful 🙏\n"
            ."We analyse every game and come back stronger 💪\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            ."🔗 <a href=\"{$siteUrl}/picks\">See today's picks →</a>";

        $this->send($msg);
    }

    public function sendDrawOutcome(string $match, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        if ($won) {
            $msg = "🤝 <b>DRAW PICK — WON! ✅</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n\n"
                ."Both teams shared the points — exactly as predicted! 💰\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/draw-picks\">See draw picks →</a>";
        } else {
            $msg = "🤝 <b>Draw Pick — Didn't Land ❌</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n\n"
                ."No draw this time. The AI will find the next one 💪\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/draw-picks\">See draw picks →</a>";
        }
        $this->send($msg);
    }

    public function sendGGOutcome(string $match, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        if ($won) {
            $msg = "⚽ <b>GG PICK — WON! ✅</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n\n"
                ."Both teams found the net — GG confirmed! 🔥💰\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/gg-picks\">See GG picks →</a>";
        } else {
            $msg = "⚽ <b>GG Pick — Didn't Land ❌</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n\n"
                ."Not both teams scored this time. We'll get the next one 💪\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/gg-picks\">See GG picks →</a>";
        }
        $this->send($msg);
    }

    public function sendOver15Outcome(string $match, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        if ($won) {
            $msg = "🎯 <b>OVER 1.5 — WON! ✅</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n\n"
                ."Goals delivered — Over 1.5 confirmed! 💰\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/over-1-5\">See Over 1.5 picks →</a>";
        } else {
            $msg = "🎯 <b>Over 1.5 — Didn't Land ❌</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n\n"
                ."Low-scoring game this time. We move 💪\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/over-1-5\">See picks →</a>";
        }
        $this->send($msg);
    }

    public function sendOver25Outcome(string $match, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        if ($won) {
            $msg = "🔥 <b>OVER 2.5 — WON! ✅</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n\n"
                ."Goals galore — Over 2.5 nailed! 🔥💰\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/over-2-5\">See Over 2.5 picks →</a>";
        } else {
            $msg = "🔥 <b>Over 2.5 — Didn't Land ❌</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n\n"
                ."Tight game this time. AI recalibrates 💪\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/over-2-5\">See picks →</a>";
        }
        $this->send($msg);
    }

    public function sendTeam3PlusOutcome(string $match, string $team, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        if ($won) {
            $msg = "📊 <b>TEAM GOALS PICK — WON! ✅</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n\n"
                ."<b>{$team}</b> did NOT score 3+ — exactly as predicted! 🔥💰\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/team-3-plus\">See Team Goals picks →</a>";
        } else {
            $msg = "📊 <b>Team Goals Pick — Missed ❌</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n\n"
                ."<b>{$team}</b> managed 3+ this time. We recalibrate 💪\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/team-3-plus\">See picks →</a>";
        }
        $this->send($msg);
    }

    public function sendDoubleChanceOutcome(string $match, string $label, string $score, bool $won, string $siteUrl, string $league = ''): void
    {
        $desc = $label === '1X' ? 'Home Win or Draw' : 'Away Win or Draw';
        if ($won) {
            $msg = "🛡️ <b>DOUBLE CHANCE {$label} — WON! ✅</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n"
                ."📌 Pick: <b>{$label} — {$desc}</b> ✅\n\n"
                ."Safety margin paid off! 💰\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/double-chance\">View picks →</a>";
        } else {
            $msg = "🛡️ <b>Double Chance {$label} — Missed ❌</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n"
                ."📌 Pick: <b>{$label} — {$desc}</b> ❌\n\n"
                ."Didn't land this time. We recalibrate 💪\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/double-chance\">View picks →</a>";
        }
        $this->send($msg);
    }

    public function sendLineupOutcome(
        string $match,
        string $tip,
        string $score,
        bool $won,
        string $siteUrl,
        string $league = ''
    ): void {
        if ($won) {
            $msg = "⚡ <b>LINEUP PICK — WON! ✅</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n"
                ."📌 Tip: <b>{$tip}</b> ✅\n\n"
                ."Lineups don't lie — the AI read this one perfectly 🤖💡\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/lineup-picks\">See lineup picks →</a>";
        } else {
            $msg = "⚡ <b>Lineup Pick — Lost ❌</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$score}</b>\n"
                ."📌 Tip: <b>{$tip}</b> ❌\n\n"
                ."Even with lineup data, football can surprise us 🤷\n"
                ."We keep analysing, keep improving 💪\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/lineup-picks\">See lineup picks →</a>";
        }
        $this->send($msg);
    }

    // ─────────────────────────────────────────────────────────────
    //  Daily results summary
    // ─────────────────────────────────────────────────────────────

    public function sendDailyResults(array $results, string $siteUrl): void
    {
        if (empty($results)) {
            return;
        }

        $correct = collect($results)->where('correct', true)->count();
        $total = count($results);

        $header = $correct === $total
            ? "🔥 <b>PERFECT DAY — {$correct}/{$total} PICKS WON!</b>"
            : ($correct > 0
                ? "📊 <b>TODAY'S RESULTS — {$correct}/{$total} WON</b>"
                : "📊 <b>TODAY'S RESULTS — {$correct}/{$total} WON</b>");

        $lines = [
            $header,
            '<i>📅 '.now('Africa/Lagos')->format('l, d M Y').'</i>',
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];

        foreach ($results as $r) {
            $tick = $r['correct'] ? '✅' : '❌';
            $score = $r['score'] ? " — <b>{$r['score']}</b>" : '';
            $conf = $r['confidence'] ? " ({$r['confidence']}%)" : '';
            $league = isset($r['league']) && $r['league'] ? "\n   🏟️ <i>{$r['league']}</i>" : '';

            $lines[] = "\n{$tick}{$league}\n   ⚽ <b>{$r['match']}</b>{$score}\n   📌 {$r['tip']}{$conf}";
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";

        if ($correct === $total) {
            $lines[] = '🔥 Flawless! All picks correct today!';
        } elseif ($correct === 0) {
            $lines[] = "Football humbles everyone. We'll come back stronger 💪";
        } else {
            $lines[] = 'Good day — keep following the AI picks 📈';
        }

        $lines[] = "\n🔗 <a href=\"{$siteUrl}/picks\">See full analysis →</a>";
        $lines[] = "\n<i>⚠️ AI predictions — not financial advice.</i>";

        $this->send(implode("\n", $lines));
    }

    // ─────────────────────────────────────────────────────────────
    //  Misc
    // ─────────────────────────────────────────────────────────────

    public function sendCorrectScoreOutcome(
        string $match,
        string $predictedScores,
        string $actualScore,
        bool $won,
        string $siteUrl,
        string $league = ''
    ): void {
        if ($won) {
            $msg = "🎯 <b>CORRECT SCORE — NAILED IT! 🔥</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$actualScore}</b>\n"
                ."📌 Our prediction included: <b>{$predictedScores}</b> ✅\n\n"
                ."The AI called the exact scoreline! 🤖🎯\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/correct-score\">See correct score predictions →</a>";
        } else {
            $msg = "🎯 <b>Correct Score — Not This Time ❌</b>\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            if ($league) {
                $msg .= "🏟️ <i>{$league}</i>\n";
            }
            $msg .= "⚽ <b>{$match}</b>\n"
                ."🏁 Final Score: <b>{$actualScore}</b>\n"
                ."📌 Our predictions: <b>{$predictedScores}</b> ❌\n\n"
                ."Correct scores are the hardest to predict in football 🙏\n"
                ."We keep analysing to get closer every day 💪\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                ."🔗 <a href=\"{$siteUrl}/correct-score\">See correct score predictions →</a>";
        }
        $this->send($msg);
    }

    public function sendCorrectScores(array $predictions, string $siteUrl): void
    {
        if (empty($predictions)) {
            return;
        }
        if ($this->sendPredictionCard('Correct Score Predictions', $predictions, $siteUrl, '/correct-score')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '🎯 <b>TAVSSCORE — CORRECT SCORE PREDICTIONS</b>',
            "<i>📅 {$date}</i>",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];

        foreach ($predictions as $pred) {
            $match = $pred['match'] ?? '';
            $scores = $pred['scores'] ?? [];
            $league = $pred['league'] ?? '';
            if (empty($scores)) {
                continue;
            }

            $scoreLine = collect($scores)
                ->map(fn ($s) => "<b>{$s['score']}</b> ({$s['pct']}%)")
                ->implode('  |  ');

            $lines[] = '';
            if ($league) {
                $lines[] = "🏟️ <i>{$league}</i>";
            }
            $lines[] = "⚽ <b>{$match}</b>";
            $lines[] = "🎯 {$scoreLine}";
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/correct-score\">See all predictions →</a>";
        $lines[] = "\n<i>⚠️ Correct scores are hardest to predict. Reference only.</i>";

        $this->send(implode("\n", $lines));
    }

    /** Daily top tennis picks. Each: match, winner, confidence, tournament, country. */
    public function sendTennisPicks(array $predictions, string $siteUrl): void
    {
        if (empty($predictions)) {
            return;
        }

        $this->asEssential(function () use ($predictions, $siteUrl) {
        $cardPicks = array_map(fn (array $pick) => [
            'match' => $pick['match'] ?? '',
            'tip' => $pick['winner'] ?? 'Model selection',
            'confidence' => $pick['confidence'] ?? '',
            'league' => $pick['tournament'] ?? 'Tennis intelligence',
        ], $predictions);
        if ($this->sendPredictionCard('Tennis Picks', $cardPicks, $siteUrl, '/tennis')) {
            return;
        }

        $date = now('Africa/Lagos')->format('l, d M Y');
        $lines = [
            '🎾 <b>TAVSSCORE — TENNIS PICKS</b>',
            "<i>📅 {$date}</i>",
            '━━━━━━━━━━━━━━━━━━━━━━━━━━',
        ];

        foreach ($predictions as $p) {
            $lines[] = '';
            if (! empty($p['tournament'])) {
                $lines[] = "🏆 <i>{$p['tournament']}</i>";
            }
            $lines[] = "🎾 <b>{$p['match']}</b>";
            $lines[] = "✅ Pick: <b>{$p['winner']}</b> ({$p['confidence']}%)";
        }

        $lines[] = "\n━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🔗 <a href=\"{$siteUrl}/tennis\">See all tennis picks →</a>";
        $lines[] = "\n<i>⚠️ Tennis is high-variance. Bet responsibly.</i>";

        $this->send(implode("\n", $lines));
        });
    }

    public function sendWinnerUploadReminder(string $siteUrl): void
    {
        $this->send(
            "🏆 <b>WON WITH OUR PICK? SHOW THE WORLD!</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Share your winning screenshot on our <b>Winners Wall</b> — it takes 30 seconds, gets you featured, and motivates the whole community! 🙌\n\n"
            ."📸 Bet slip or payout screenshot is all we need.\n\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            ."👉 <a href=\"{$siteUrl}/winners\">Upload your winnings →</a>"
        );
    }

    public function sendBookingCode(string $platform, string $code, string $note, string $siteUrl, ?string $affiliateUrl = null, ?string $ticketUrl = null, ?string $ticketImagePath = null, ?float $totalOdds = null): void
    {
        $this->asEssential(function () use ($platform, $code, $note, $siteUrl, $affiliateUrl, $ticketUrl, $ticketImagePath, $totalOdds) {
        $notePart = $note ? "\n📋 Picks: <b>{$this->escape($note)}</b>" : '';
        $safeCode = $this->escape($code);
        $howTo = match (strtolower($platform)) {
            'bet9ja' => "Open Bet9ja app → Booking Code → Enter <b>{$safeCode}</b>",
            '1xbet' => "Open 1xBet app → Coupon Code → Enter <b>{$safeCode}</b>",
            '1win' => "Open 1Win app → Betting Slip → Coupon Code → Enter <b>{$safeCode}</b>",
            'sportybet' => "Open SportyBet app → Booking Code → Enter <b>{$safeCode}</b>",
            'betway' => "Open Betway app → Booking Code → Enter <b>{$safeCode}</b>",
            'parimatch' => "Open Parimatch app → Booking Code → Enter <b>{$safeCode}</b>",
            default => 'Open '.$this->escape($platform)." app → Booking Code → Enter <b>{$safeCode}</b>",
        };

        $msg = '🎟️ <b>BOOKING CODE — '.strtoupper($platform)."</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
            ."🔐 <b>YOUR BOOKING CODE</b>\n"
            ."<b>🔥 {$safeCode} 🔥</b>\n"
            ."<code>{$safeCode}</code>\n"
            .($totalOdds ? '📈 Total odds: <b>'.number_format($totalOdds, 2).'</b>\n' : '')
            ."☝️ Tap <b>Copy code</b> below, then load it in {$platform}."
            .$notePart."\n\n"
            ."📲 <b>How to use:</b>\n{$howTo}\n\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            ."⚠️ Verify odds before placing. Bet responsibly.\n"
            ."🔗 <a href=\"{$siteUrl}/booking-codes\">See ticket analysis →</a>";

        if ($affiliateUrl) {
            $msg .= "\n📝 No {$platform} account? <a href=\"{$affiliateUrl}\">Register free →</a>";
        }

        $keyboard = [[[
            'text' => '📋 COPY CODE',
            'copy_text' => ['text' => $code],
        ]]];
        if ($ticketUrl) {
            $keyboard[] = [[
                'text' => '🎟️ OPEN TICKET IN '.strtoupper($platform),
                'url' => $ticketUrl,
            ]];
        }
        $keyboard[] = [[
            'text' => '📊 VIEW TICKET ANALYSIS',
            'url' => rtrim($siteUrl, '/').'/booking-codes',
        ]];

        if ($ticketImagePath && Storage::disk('public')->exists($ticketImagePath)) {
            $this->sendPhoto($ticketImagePath, $msg, $keyboard);
            return;
        }

        $this->send($msg, $keyboard);
        });
    }

    /** Send an authenticated worker screenshot as a Telegram photo + caption. */
    private function sendPhoto(string $path, string $caption, array $inlineKeyboard, bool $fallbackToText = true): bool
    {
        if (! $this->essential) {
            return false; // Telegram is limited to booking codes + tennis picks.
        }

        if (! $this->isConfigured()) {
            return false;
        }

        $fullPath = Storage::disk('public')->path($path);
        $stream = @fopen($fullPath, 'rb');
        if ($stream === false) {
            if ($fallbackToText) {
                $this->send($caption, $inlineKeyboard);
            }
            return false;
        }

        try {
            $response = Http::timeout(20)
                ->attach('photo', $stream, basename($path))
                ->post("https://api.telegram.org/bot{$this->token}/sendPhoto", [
                    'chat_id' => $this->channelId,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
                ]);

            if (! $response->successful()) {
                Log::error('Telegram photo send failed', ['status' => $response->status(), 'body' => $response->body()]);
                if ($fallbackToText) {
                    $this->send($caption, $inlineKeyboard);
                }
                return false;
            }
            return true;
        } finally {
            fclose($stream);
        }
    }

    /** Result of a settled booking code (all legs won, or one leg lost). */
    public function sendBookingOutcome(string $platform, string $code, string $note, bool $won, string $siteUrl, ?string $ticketImagePath = null, ?float $totalOdds = null): void
    {
        $this->asEssential(function () use ($platform, $code, $note, $won, $siteUrl, $ticketImagePath, $totalOdds) {
        $head     = $won ? '✅ <b>BOOKING CODE WON</b>' : '❌ <b>BOOKING CODE LOST</b>';
        $safeCode = $this->escape($code);
        $notePart = $note ? "\n📋 ".$this->escape($note) : '';

        $msg = $head."\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
            ."🔐 <b>BOOKING CODE</b>\n"
            ."<b>🔥 {$safeCode} 🔥</b>\n"
            ."<code>{$safeCode}</code>\n"
            .($totalOdds ? '📈 Total odds: <b>'.number_format($totalOdds, 2).'</b>' : '')
            .$notePart."\n\n"
            ."🔗 <a href=\"{$siteUrl}/booking-codes\">Booking code history →</a>";

        $keyboard = [[[
                'text' => '📊 VIEW BOOKING HISTORY',
                'url' => rtrim($siteUrl, '/').'/booking-codes',
        ]]];

        $outcomeCard = app(BookingOutcomeCardService::class)->render($platform, $code, $note, $won, $totalOdds);
        if ($outcomeCard) {
            try {
                if ($this->sendPhoto($outcomeCard, $msg, $keyboard, fallbackToText: false)) {
                    return;
                }
            } finally {
                Storage::disk('public')->delete($outcomeCard);
            }
        }

        if ($ticketImagePath && Storage::disk('public')->exists($ticketImagePath)) {
            $this->sendPhoto($ticketImagePath, $msg, $keyboard);
            return;
        }

        $this->send($msg, $keyboard);
        });
    }
}
