<?php

namespace App\Services;

use App\Models\FootballMatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optional second AI for cross-validation. Only fires if GEMINI_API_KEY is set.
 *
 * We use this as a "second opinion" — if Gemini and Groq agree on the headline
 * tip, we mark it as cross-validated. Disagreement lowers our confidence.
 *
 * Free tier: 15 RPM, 1500 RPD on gemini-2.0-flash. Plenty for daily-pick
 * cross-validation (we only need 3 calls/day for that).
 */
class GeminiService
{
    public function isConfigured(): bool
    {
        return ! blank(config('services.gemini.key'));
    }

    /**
     * Get Gemini's predicted headline market for a match. Returns the market
     * label string or null on any failure.
     */
    /**
     * Full-context rollover verdict. Receives Groq's recommendation plus extended
     * team stats, H2H history, and match preview news. Returns whether Gemini
     * independently agrees, its own confidence, and a one-line reason.
     */
    public function rolloverVerdict(
        FootballMatch $match,
        string $groqOutcome,
        ?int $groqConfidence,
        array $homeStats = [],
        array $awayStats = [],
        array $h2h = [],
        string $matchPreview = '',
    ): ?array {
        if (! $this->isConfigured()) return null;

        $homeBlock    = $this->buildStatsBlock($match->home_team, $homeStats);
        $awayBlock    = $this->buildStatsBlock($match->away_team, $awayStats);
        $h2hBlock     = $this->buildH2HBlock($h2h);
        $previewBlock = $matchPreview ? "LATEST NEWS & MATCH PREVIEW:\n{$matchPreview}" : '';

        $prompt = <<<PROMPT
You are an elite football betting analyst specialising in SAFE, high-confidence single bets for a ROLLOVER challenge.
A rollover demands the SAFEST possible prediction — only pick markets where you have ≥75% confidence.

MATCH:   {$match->home_team} vs {$match->away_team}
LEAGUE:  {$match->league} ({$match->league_country})
KICKOFF: {$match->match_time?->format('Y-m-d H:i')} UTC

{$homeBlock}

{$awayBlock}

{$h2hBlock}

{$previewBlock}

PRIMARY AI (GROQ) RECOMMENDATION: {$groqOutcome} (confidence: {$groqConfidence}%)

Your task:
1. Analyse this fixture INDEPENDENTLY using all data above — do not blindly defer to Groq.
2. Decide if you AGREE with the recommendation.
3. Rate your own confidence 0–100%.
4. Write one concise sentence as your reason.

Rules:
- If you see clear evidence against the pick (bad form, strong H2H against, injury news), DISAGREE and name your preferred market.
- Only agree if the data genuinely supports the pick at ≥75% confidence.
- For a rollover we need safety — prefer lower-odds safe bets over high-risk picks.

Respond ONLY with valid JSON (no markdown, no code fences):
{"agree":true,"outcome":"Home Win","confidence":82,"reason":"Home side unbeaten in last 7, strong defensive record, and news confirms no key injuries."}

If you disagree: set agree to false and put your preferred market in outcome.
PROMPT;

        try {
            $response = Http::timeout(30)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/' . config('services.gemini.model', 'gemini-2.0-flash') . ':generateContent', [
                    'contents'         => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.15, 'maxOutputTokens' => 200],
                ]);
        } catch (ConnectionException | Throwable $e) {
            Log::info('Gemini rolloverVerdict failed', ['match' => $match->id, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) return null;

        $raw  = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text'));
        $raw  = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
        $data = json_decode(trim($raw), true);

        if (! is_array($data) || ! isset($data['agree'])) return null;

        return [
            'agree'      => (bool) $data['agree'],
            'outcome'    => $data['outcome']    ?? $groqOutcome,
            'confidence' => (int)  ($data['confidence'] ?? $groqConfidence ?? 75),
            'reason'     => $data['reason']     ?? '',
        ];
    }

    private function buildStatsBlock(string $team, array $s): string
    {
        if (empty($s) || ($s['matches_played'] ?? 0) === 0) {
            return "{$team} STATS: No recent data available.";
        }

        $n       = max(1, $s['matches_played']);
        $csRate  = round(($s['clean_sheets']     ?? 0) / $n * 100);
        $bttRate = round(($s['btts_count']       ?? 0) / $n * 100);
        $o25Rate = round(($s['over25_count']     ?? 0) / $n * 100);
        $ftsRate = round(($s['failed_to_score']  ?? 0) / $n * 100);

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
        if (!empty($s['form_detailed'])) {
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

    /**
     * Full-context prediction verdict for daily picks and lineup picks.
     * Receives Groq's recommendation plus team stats and H2H — same depth
     * as rolloverVerdict but tuned for regular predictions, not just safe markets.
     */
    public function predictionVerdict(
        FootballMatch $match,
        string        $groqOutcome,
        ?int          $groqConfidence,
        array         $homeStats = [],
        array         $awayStats = [],
        array         $h2h = [],
    ): ?array {
        if (! $this->isConfigured()) return null;

        $homeBlock = $this->buildStatsBlock($match->home_team, $homeStats);
        $awayBlock = $this->buildStatsBlock($match->away_team, $awayStats);
        $h2hBlock  = $this->buildH2HBlock($h2h);

        $prompt = <<<PROMPT
You are an expert football betting analyst. Analyse this match independently and decide whether you agree with the primary AI's recommendation.

MATCH:   {$match->home_team} vs {$match->away_team}
LEAGUE:  {$match->league} ({$match->league_country})
KICKOFF: {$match->match_time?->format('Y-m-d H:i')} UTC

{$homeBlock}

{$awayBlock}

{$h2hBlock}

PRIMARY AI (GROQ) SAYS: {$groqOutcome} (confidence: {$groqConfidence}%)

Your task:
1. Analyse the fixture using the stats and H2H data above — do NOT blindly follow Groq.
2. Decide if you AGREE with the recommendation.
3. Rate your own confidence 0–100%.

Rules:
- If the data genuinely supports Groq's pick, agree.
- If you see clear evidence against it (poor recent form, H2H strongly against, etc.), disagree and name your preferred market.
- Be analytical and honest.

Respond ONLY with valid JSON (no markdown, no code fences):
{"agree":true,"outcome":"Home Win","confidence":78}

Allowed market labels: Home Win, Draw, Away Win, Home or Draw (1X), Draw or Away (X2), Home or Away (12), Over 1.5 Goals, Over 2.5 Goals, Under 2.5 Goals, Both Teams Score (GG), No Both Teams Score (NG), Draw No Bet - Home, Draw No Bet - Away
PROMPT;

        try {
            $response = Http::timeout(25)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/' . config('services.gemini.model', 'gemini-2.0-flash') . ':generateContent', [
                    'contents'         => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.15, 'maxOutputTokens' => 100],
                ]);
        } catch (ConnectionException | Throwable $e) {
            Log::info('Gemini predictionVerdict failed', ['match' => $match->id, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) return null;

        $raw  = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text'));
        $raw  = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
        $data = json_decode(trim($raw), true);

        if (! is_array($data) || ! isset($data['agree'])) return null;

        return [
            'agree'      => (bool) $data['agree'],
            'outcome'    => $data['outcome']    ?? $groqOutcome,
            'confidence' => (int)  ($data['confidence'] ?? $groqConfidence ?? 70),
        ];
    }
}
