<?php

namespace App\Services;

use App\Models\FootballMatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Third AI for triple cross-validation. Only fires if MISTRAL_API_KEY is set.
 *
 * Mistral is API-compatible and free-tier friendly (console.mistral.ai).
 * Like Gemini, it receives ONLY raw stats and H2H — never Groq's or Gemini's
 * output. All three AIs must independently reach the same conclusion before
 * a prediction is published or a rollover pick is placed.
 */
class MistralService
{
    public function isConfigured(): bool
    {
        return ! blank(config('services.mistral.key'));
    }

    /**
     * Completely independent prediction — Mistral receives only raw match
     * stats and H2H, with NO knowledge of what Groq or Gemini concluded.
     * Returns ['outcome' => string, 'confidence' => int] or null on failure.
     */
    public function independentVerdict(
        FootballMatch $match,
        array         $homeStats = [],
        array         $awayStats = [],
        array         $h2h = [],
        string        $statsContext = '',
    ): ?array {
        if (! $this->isConfigured()) return null;

        $homeBlock = $this->buildStatsBlock($match->home_team, $homeStats);
        $awayBlock = $this->buildStatsBlock($match->away_team, $awayStats);
        $h2hBlock  = $this->buildH2HBlock($h2h);

        $prompt = <<<PROMPT
You are a professional football analyst and betting expert. Study the data below and predict the single most probable betting market outcome for this match.

FIXTURE: {$match->home_team} vs {$match->away_team}
LEAGUE:  {$match->league} ({$match->league_country})
KICKOFF: {$match->match_time?->format('Y-m-d H:i')} UTC

{$homeBlock}

{$awayBlock}

{$h2hBlock}{$statsContext}

Instructions:
- Study recent form, goals scored/conceded, home/away performance, and H2H trends carefully.
- Select the SINGLE outcome you are most confident about from the list below.
- Do NOT guess — only pick what the data genuinely supports.
- Rate your confidence from 0 to 100. If you are not at least 60% confident, reflect that in your score.
- Never use em dashes (—) in any text. Use commas, colons, or full stops instead.

Respond with ONLY valid JSON (no markdown, no code fences):
{"outcome":"Home Win","confidence":76}

Allowed outcomes (use EXACT label):
Home Win, Draw, Away Win, Home or Draw (1X), Draw or Away (X2), Home or Away (12), Over 1.5 Goals, Over 2.5 Goals, Under 2.5 Goals, Both Teams Score (GG), No Both Teams Score (NG), Draw No Bet - Home, Draw No Bet - Away
PROMPT;

        try {
            $response = Http::timeout(25)
                ->withToken(config('services.mistral.key'))
                ->post(config('services.mistral.url', 'https://api.mistral.ai/v1/chat/completions'), [
                    'model'       => config('services.mistral.model', 'mistral-small-latest'),
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.10,
                    'max_tokens'  => 80,
                ]);
        } catch (ConnectionException | Throwable $e) {
            Log::info('MistralService independentVerdict failed', ['match' => $match->id, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) {
            Log::info('MistralService HTTP error', ['match' => $match->id, 'status' => $response->status()]);
            return null;
        }

        $raw  = trim((string) data_get($response->json(), 'choices.0.message.content'));
        $raw  = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
        $data = json_decode(trim($raw), true);

        if (! is_array($data) || empty($data['outcome'])) return null;

        return [
            'outcome'    => trim((string) $data['outcome']),
            'confidence' => (int) ($data['confidence'] ?? 70),
        ];
    }

    private function buildStatsBlock(string $team, array $s): string
    {
        if (empty($s) || ($s['matches_played'] ?? 0) === 0) {
            return "{$team} STATS: No recent data available.";
        }

        $n       = max(1, $s['matches_played']);
        $csRate  = round(($s['clean_sheets']    ?? 0) / $n * 100);
        $bttRate = round(($s['btts_count']      ?? 0) / $n * 100);
        $o25Rate = round(($s['over25_count']    ?? 0) / $n * 100);
        $ftsRate = round(($s['failed_to_score'] ?? 0) / $n * 100);

        $lines = [
            "{$team} STATS (last {$n} games):",
            "  Record: {$s['wins']}W / {$s['draws']}D / {$s['losses']}L",
            "  Goals: scored {$s['gpg']}/game · conceded {$s['cpg']}/game",
            "  Clean sheets {$csRate}% · BTTS {$bttRate}% · Over 2.5 {$o25Rate}% · Failed to score {$ftsRate}%",
        ];

        if (($s['home_matches'] ?? 0) > 0) {
            $lines[] = "  At home (last {$s['home_matches']}): scored {$s['home_scored']} · conceded {$s['home_conceded']}";
        }
        if (($s['away_matches'] ?? 0) > 0) {
            $lines[] = "  Away (last {$s['away_matches']}): scored {$s['away_scored']} · conceded {$s['away_conceded']}";
        }
        if (! empty($s['form_detailed'])) {
            $lines[] = '  Form: ' . implode(', ', array_slice($s['form_detailed'], 0, 6));
        }

        return implode("\n", $lines);
    }

    private function buildH2HBlock(array $h2h): string
    {
        if (empty($h2h['results'])) {
            return 'HEAD-TO-HEAD: No recent meetings in database.';
        }

        $summary = "H2H summary: {$h2h['home_wins']}W / {$h2h['draws']}D / {$h2h['away_wins']}L for home side";
        $lines   = ['HEAD-TO-HEAD (last ' . count($h2h['results']) . ' meetings):', "  {$summary}"];
        foreach ($h2h['results'] as $r) {
            $lines[] = "  · {$r}";
        }

        return implode("\n", $lines);
    }
}
