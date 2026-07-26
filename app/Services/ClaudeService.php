<?php

namespace App\Services;

use App\Models\FootballMatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fourth AI for the consensus cross-validation. Only fires if ANTHROPIC_API_KEY
 * is set. Like Gemini and Mistral, Claude receives ONLY raw match stats, H2H,
 * and (optionally) live standings context — never Groq's, Gemini's, or Mistral's
 * output. It votes independently on the outcome; it never generates probabilities.
 *
 * Uses the Anthropic Messages API directly via Laravel's HTTP client to match the
 * pattern of the other three AI services (no extra SDK dependency).
 */
class ClaudeService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function isConfigured(): bool
    {
        return ! blank(config('services.anthropic.key'));
    }

    /**
     * Completely independent verdict — Claude sees only raw match data, with no
     * knowledge of what the other AIs concluded.
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
- Never use em dashes in any text. Use commas, colons, or full stops instead.

Respond with ONLY valid JSON (no markdown, no code fences):
{"outcome":"Home Win","confidence":76}

Allowed outcomes (use EXACT label):
Home Win, Draw, Away Win, Home or Draw (1X), Draw or Away (X2), Home or Away (12), Over 1.5 Goals, Over 2.5 Goals, Under 2.5 Goals, Both Teams Score (GG), No Both Teams Score (NG), Draw No Bet - Home, Draw No Bet - Away
PROMPT;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key'         => config('services.anthropic.key'),
                    'anthropic-version' => self::API_VERSION,
                ])
                ->acceptJson()
                ->post(self::API_URL, [
                    'model'      => config('services.anthropic.model', 'claude-opus-4-8'),
                    'max_tokens' => 100,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ]);
        } catch (ConnectionException | Throwable $e) {
            Log::info('ClaudeService independentVerdict failed', ['match' => $match->id, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) {
            Log::info('ClaudeService HTTP error', ['match' => $match->id, 'status' => $response->status()]);
            return null;
        }

        // Anthropic returns content as an array of blocks; the text is in the first text block.
        $raw = trim((string) data_get($response->json(), 'content.0.text'));
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
        $data = json_decode(trim($raw), true);

        if (! is_array($data) || empty($data['outcome'])) return null;

        return [
            'outcome'    => trim((string) $data['outcome']),
            'confidence' => (int) ($data['confidence'] ?? 70),
        ];
    }

    /**
     * Final arbiter. Claude reviews the match data AND what every other AI
     * concluded, then issues the definitive call — confirm the panel's pick,
     * override it, or adjust confidence. This is the top of the decision chain;
     * callers fall back to the raw consensus if this returns null.
     *
     * @param  array<string, array{outcome:string, confidence:int}|null>  $panel  keyed by ai name (groq/gemini/mistral)
     * @return array{outcome:string, confidence:int, confirmed:bool, rationale:string}|null
     */
    public function finalVerdict(
        FootballMatch $match,
        array         $panel,
        array         $homeStats = [],
        array         $awayStats = [],
        array         $h2h = [],
        string        $statsContext = '',
    ): ?array {
        if (! $this->isConfigured()) return null;

        $homeBlock = $this->buildStatsBlock($match->home_team, $homeStats);
        $awayBlock = $this->buildStatsBlock($match->away_team, $awayStats);
        $h2hBlock  = $this->buildH2HBlock($h2h);

        $panelLines = [];
        foreach ($panel as $ai => $verdict) {
            if ($verdict === null) {
                $panelLines[] = strtoupper($ai) . ': (no verdict — unavailable)';
                continue;
            }
            $panelLines[] = sprintf('%s: %s (%d%% confident)', strtoupper($ai), $verdict['outcome'], $verdict['confidence']);
        }
        $panelBlock = implode("\n", $panelLines);

        $prompt = <<<PROMPT
You are the HEAD betting analyst. Three junior analysts (AI models) have each independently predicted this match. Your job is to make the FINAL decision on the single best betting outcome, using the raw data AND their opinions.

FIXTURE: {$match->home_team} vs {$match->away_team}
LEAGUE:  {$match->league} ({$match->league_country})
KICKOFF: {$match->match_time?->format('Y-m-d H:i')} UTC

{$homeBlock}

{$awayBlock}

{$h2hBlock}{$statsContext}

═══ WHAT THE OTHER ANALYSTS PREDICTED ═══
{$panelBlock}

Instructions:
- Weigh the data yourself; do not just follow the majority. If they are right, confirm. If the data contradicts them, override with the outcome the data genuinely supports.
- Pick the SINGLE outcome you are most confident about. You may choose ANY market from the "OUR MODEL — PROBABILITY ACROSS ALL MARKETS" section above (handicaps, half-time, HT/FT, combos, exact goals, etc.), not only the standard shortlist. Use the exact market label shown there.
- Prefer the market with the best mix of high probability and genuine value over a bare 1X2 pick.
- Set "confirmed" to true if your final pick matches what most analysts said, false if you are overriding them.
- Rate your final confidence 0 to 100. Be honest — if the panel disagrees and the data is murky, lower it.
- Give a one-sentence rationale. Never use em dashes; use commas or full stops.

Respond with ONLY valid JSON (no markdown, no code fences):
{"outcome":"Home & Over 2.5","confidence":74,"confirmed":true,"rationale":"Home side unbeaten at home and both teams scoring freely."}

Standard shortlist (you are NOT limited to these — any market from the model board above is valid):
Home Win, Draw, Away Win, Home or Draw (1X), Draw or Away (X2), Home or Away (12), Over 1.5 Goals, Over 2.5 Goals, Under 2.5 Goals, Both Teams Score (GG), No Both Teams Score (NG), Draw No Bet - Home, Draw No Bet - Away
PROMPT;

        try {
            $response = Http::timeout(35)
                ->withHeaders([
                    'x-api-key'         => config('services.anthropic.key'),
                    'anthropic-version' => self::API_VERSION,
                ])
                ->acceptJson()
                ->post(self::API_URL, [
                    'model'      => config('services.anthropic.model', 'claude-opus-4-8'),
                    'max_tokens' => 250,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ]);
        } catch (ConnectionException | Throwable $e) {
            Log::info('ClaudeService finalVerdict failed', ['match' => $match->id, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) {
            Log::info('ClaudeService finalVerdict HTTP error', ['match' => $match->id, 'status' => $response->status()]);
            return null;
        }

        $raw  = trim((string) data_get($response->json(), 'content.0.text'));
        $raw  = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
        $data = json_decode(trim($raw), true);

        if (! is_array($data) || empty($data['outcome'])) return null;

        return [
            'outcome'    => trim((string) $data['outcome']),
            'confidence' => (int) ($data['confidence'] ?? 70),
            'confirmed'  => (bool) ($data['confirmed'] ?? true),
            'rationale'  => trim((string) ($data['rationale'] ?? '')),
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
