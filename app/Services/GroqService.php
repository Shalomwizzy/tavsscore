<?php

namespace App\Services;

use App\Models\FootballMatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroqService
{
    public const FALLBACK_ANALYSIS = 'Analysis currently unavailable';

    /**
     * Ask Groq for a full prediction — probabilities + detailed analysis.
     * Returns array with keys: home_win, draw, away_win, over_25, btts, analysis
     * Returns null on failure (rate limit, timeout, parse error).
     */
    public function getPrediction(FootballMatch $match, array $poissonFallback): ?array
    {
        $apiKey = config('services.groq.key');

        if (blank($apiKey) || $apiKey === 'your_api_key_here') {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()->asJson()->timeout(30)
                ->retry(2, 3000, fn ($e, $r) => !($r && $r->status() === 429))
                ->post(config('services.groq.url'), [
                    'model'           => config('services.groq.model', 'llama-3.3-70b-versatile'),
                    'temperature'     => 0.20,
                    'max_tokens'      => 500,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user',   'content' => $this->userPrompt($match)],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Groq connection failed', ['match_id' => $match->id, 'error' => $e->getMessage()]);
            return null;
        } catch (Throwable $e) {
            Log::warning('Groq request error', ['match_id' => $match->id, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->status() === 429) {
            Log::warning('Groq rate limit — will retry next cycle', ['match_id' => $match->id]);
            return null;
        }

        if ($response->failed()) {
            Log::warning('Groq API error', ['status' => $response->status()]);
            return null;
        }

        $raw = trim((string) data_get($response->json(), 'choices.0.message.content'));

        return blank($raw) ? null : $this->parse($raw, $match->id);
    }

    /**
     * Translate a longer-form English text (e.g. blog post body) into Pidgin or
     * Swahili. Preserves HTML tags and structural markup. Returns null on failure.
     */
    public function translateLongform(string $english, string $language): ?string
    {
        $apiKey = config('services.groq.key');
        if (blank($apiKey) || $apiKey === 'your_api_key_here' || blank($english)) {
            return null;
        }

        $instruction = match (strtolower($language)) {
            'pidgin' => <<<'PIDGIN'
Translate the football article HTML below into natural Nigerian Pidgin English (the kind real Lagos/Naija football fans speak).
- Preserve ALL HTML tags exactly: <p>, <h2>, <h3>, <strong>, <em>, <a>, <ul>, <li>, <br>, etc. Translate only the text inside tags.
- Keep team names, scores, dates, league names, and proper nouns in the original form.
- Use natural Pidgin phrasing — "dey fire", "no go fit", "well well", "no shaking" etc — but stay clear and informative.
- Return ONLY the translated HTML. No commentary, no markdown fences.
PIDGIN,
            'swahili' => <<<'SWAHILI'
Translate the football article HTML below into natural East African Swahili (Kenya/Tanzania style).
- Preserve ALL HTML tags exactly: <p>, <h2>, <h3>, <strong>, <em>, <a>, <ul>, <li>, <br>, etc. Translate only the text inside tags.
- Keep team names, scores, dates, league names, and proper nouns in the original form.
- Use natural sentence flow, not literal/word-for-word translation.
- Return ONLY the translated HTML. No commentary, no markdown fences.
SWAHILI,
            default => null,
        };

        if ($instruction === null) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()->asJson()->timeout(60)
                ->retry(2, 3000, fn ($e, $r) => !($r && $r->status() === 429))
                ->post(config('services.groq.url'), [
                    'model'       => config('services.groq.model'),
                    'temperature' => 0.30,
                    'max_tokens'  => 4000,
                    'messages'    => [
                        ['role' => 'system', 'content' => $instruction],
                        ['role' => 'user',   'content' => $english],
                    ],
                ]);
        } catch (Throwable $e) {
            Log::warning('Groq longform translate error', ['lang' => $language, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) return null;

        $out = trim((string) data_get($response->json(), 'choices.0.message.content'));
        $out = preg_replace('/^```(?:\w+)?\s*|\s*```$/m', '', $out);
        return trim($out) ?: null;
    }

    /**
     * Translate an existing English analysis into Nigerian Pidgin or Swahili,
     * preserving the 💡 Tip sentence at the end. Returns null on failure.
     *
     * @param  string  $language  'pidgin' | 'swahili'
     */
    public function translateAnalysis(string $english, string $language): ?string
    {
        $apiKey = config('services.groq.key');
        if (blank($apiKey) || $apiKey === 'your_api_key_here' || blank($english)) {
            return null;
        }

        $instruction = match (strtolower($language)) {
            'pidgin' => <<<'PIDGIN'
Translate the football analysis below into natural Nigerian Pidgin English — the kind real Lagos/Naija football fans speak.
- Keep football terminology recognisable (e.g. "win", "draw", "over 2.5 goals", team names) but make the prose sound like street/community Pidgin.
- Use phrases like "go win the match", "dey fire", "no go fit", "well well", "no shaking", "dem dey hot" where natural.
- Keep the meaning identical — same teams, same probabilities, same tip.
- The final sentence MUST start with "💡 Tip:" followed by the same recommendation, but phrased in Pidgin.
- Return ONLY the translated paragraph, nothing else. No quotes, no labels, no markdown.
PIDGIN,
            'swahili' => <<<'SWAHILI'
Translate the football analysis below into natural East African Swahili (Kenya/Tanzania style).
- Keep football terms (e.g. team names, "over 2.5 goals", "BTTS") if they don't have natural Swahili equivalents.
- Use natural sentence flow — not literal/word-for-word translation.
- Keep all probabilities, teams and the recommended tip exactly the same.
- The final sentence MUST start with "💡 Tip:" followed by the same recommendation, in Swahili.
- Return ONLY the translated paragraph, nothing else.
SWAHILI,
            default => null,
        };

        if ($instruction === null) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()->asJson()->timeout(20)
                ->retry(2, 2000, fn ($e, $r) => !($r && $r->status() === 429))
                ->post(config('services.groq.url'), [
                    'model'       => config('services.groq.model'),
                    'temperature' => 0.40,
                    'max_tokens'  => 400,
                    'messages'    => [
                        ['role' => 'system', 'content' => $instruction],
                        ['role' => 'user',   'content' => $english],
                    ],
                ]);
        } catch (Throwable $e) {
            Log::warning('Groq translate error', ['lang' => $language, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $out = trim((string) data_get($response->json(), 'choices.0.message.content'));
        $out = preg_replace('/^```(?:\w+)?\s*|\s*```$/m', '', $out);
        return trim($out) ?: null;
    }

    // ──────────────────────────────────────────────────────────────
    //  Prompts
    // ──────────────────────────────────────────────────────────────

    private function systemPrompt(): string
    {
        return <<<SYSTEM
You are a senior football analyst and data scientist for TavsScore, a leading football statistics platform.

Your predictions are trusted by thousands of football fans and must be ACCURATE, not generic.

For every match you analyse:
- Draw on your full knowledge of both clubs' current season form, squad strength, and playing style
- Factor in head-to-head history between the clubs
- Consider home advantage (home teams win ~46% of top-league matches on average)
- Reflect real quality gaps — a title contender vs a relegation side should show 70%+ for the stronger team
- Account for any injury or suspension context you know about
- Consider match stakes: title deciders, relegation battles, cup ties affect intensity

Be SPECIFIC and CONFIDENT. Vague predictions are useless.
Return ONLY valid JSON. No markdown, no code fences, no commentary outside the JSON.
home_win + draw + away_win MUST sum to exactly 100.
SYSTEM;
    }

    private function userPrompt(FootballMatch $match): string
    {
        $kickoff  = $match->match_time?->format('l, d M Y, H:i') ?? 'Today';
        $league   = $match->league;
        $country  = $match->league_country ?? '';
        $home     = $match->home_team;
        $away     = $match->away_team;

        return <<<PROMPT
Analyse this fixture using your football knowledge:

Match: {$home} vs {$away}
Competition: {$league} ({$country})
Kickoff: {$kickoff}

Consider carefully:
1. Current league table position and points tally for both clubs this season
2. Form over last 6 matches for each team (wins, draws, losses, goals scored & conceded)
3. Head-to-head record at this venue
4. Any key injuries, suspensions, rotation or motivation you know about
5. Home advantage factor for {$home}
6. Attacking & defensive style — possession-heavy, counter-attacking, set-piece reliant?
7. Foul tendencies (dirty/disciplined teams), corner counts (high-pressing teams force more), card profile
8. Match stakes (title race, European spots, relegation, cup leg)

You MUST recommend exactly 3 tips for this match: 1 STRONGEST pick (the one bet you'd stake on yourself), plus 2 ALTERNATIVES that are still solid but lower-confidence. The strongest pick should sit at the top of the array.

Pick markets only where you have genuine conviction. If you can only confidently recommend 1, give just 1 tip. Don't pad to 3 with weak filler.

Available markets to choose from (pick whichever fits THIS match best):

  - 1X2: "Home Win", "Draw", "Away Win"
  - Double Chance: "Home or Draw (1X)", "Draw or Away (X2)", "Home or Away (12)"
  - Goals: "Over 1.5 Goals", "Under 1.5 Goals", "Over 2.5 Goals", "Under 2.5 Goals", "Over 3.5 Goals", "Under 3.5 Goals"
  - Both Teams to Score: "Both Teams Score (GG)", "No Both Teams Score (NG)"
  - Half-time: "Home Lead at HT", "Away Lead at HT", "Draw at HT", "Over 1.5 First Half", "Over 0.5 First Half"
  - Both halves: "Both Halves Over 0.5", "Both Halves Over 1.5", "Both Halves BTTS"
  - Win to nil / Clean sheet: "Home Win to Nil", "Away Win to Nil", "Home Clean Sheet", "Away Clean Sheet"
  - Asian Handicap: "Home -1 Handicap", "Home -2 Handicap", "Away +1 Handicap", "Away +2 Handicap"
  - Corners: "Over 8.5 Corners", "Over 9.5 Corners", "Over 10.5 Corners", "Under 9.5 Corners", "Home Most Corners", "Away Most Corners"
  - Cards: "Over 3.5 Cards", "Over 4.5 Cards", "Over 5.5 Cards", "Under 3.5 Cards"
  - Score-related: "Home Team to Score", "Away Team to Score", "Team to Score First (Home)", "Team to Score First (Away)"

Pick whichever 5 markets are MOST LIKELY to land in THIS match — not always the obvious 1X2.
A high-corners high-press team? Recommend Over 10.5 Corners. A dirty derby? Recommend Over 4.5 Cards.

Required JSON output (ALL fields mandatory):
{
  "home_win": <integer 0-100>,
  "draw":     <integer 0-100>,
  "away_win": <integer 0-100>,
  "over_25":  <integer 0-100>,
  "btts":     <integer 0-100>,
  "tips": [
    { "market": "<your STRONGEST pick>",       "confidence": <integer 60-95>, "rationale": "<≤14 words why>" },
    { "market": "<alternative 1, optional>",   "confidence": <integer 50-90>, "rationale": "<...>" },
    { "market": "<alternative 2, optional>",   "confidence": <integer 50-85>, "rationale": "<...>" }
  ],
  "analysis": "<3-4 sentences: lead with the key reason for your prediction, cover both teams' form, mention any significant factor like injuries/stakes. REQUIRED FINAL SENTENCE: start it with exactly the token 💡 Tip: followed by your single highest-confidence recommendation, e.g. '💡 Tip: Back over 2.5 goals — both attacks firing.'>"
}

Rules:
- home_win + draw + away_win MUST equal exactly 100.
- Each tip's confidence MUST be ≥50 (don't recommend anything you wouldn't bet on yourself).
- Order tips by confidence DESCENDING — your strongest pick first.
- Prefer 1 STRONG tip over 3 mediocre ones. Padding with filler hurts trust.
- The alternatives should be DIFFERENT bet types from the headline (e.g. headline is 1X2 → alternatives are goal-line and BTTS).
- Use league knowledge: NPFL/PSL/Botola Pro etc. — adjust style assumptions accordingly.
- Be specific about these actual clubs — generic answers are unacceptable.
PROMPT;
    }

    // ──────────────────────────────────────────────────────────────
    //  Response parsing
    // ──────────────────────────────────────────────────────────────

    private function parse(string $raw, int $matchId): ?array
    {
        $json = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $json = preg_replace('/\s*```\s*$/i', '', trim($json));
        $data = json_decode(trim($json), true);

        if (!is_array($data)) {
            Log::warning('Groq unparseable JSON', ['match_id' => $matchId, 'raw' => substr($raw, 0, 300)]);
            return null;
        }

        $hw = (int) ($data['home_win'] ?? 0);
        $d  = (int) ($data['draw']     ?? 0);
        $aw = (int) ($data['away_win'] ?? 0);

        if ($hw <= 0 || $d <= 0 || $aw <= 0) {
            Log::warning('Groq zero probabilities', ['match_id' => $matchId]);
            return null;
        }

        $sum = $hw + $d + $aw;
        if ($sum < 90 || $sum > 110) {
            Log::warning('Groq probabilities off', ['match_id' => $matchId, 'sum' => $sum]);
            return null;
        }

        // Normalise to exactly 100
        $hw = round($hw / $sum * 100, 1);
        $d  = round($d  / $sum * 100, 1);
        $aw = round(100 - $hw - $d, 1);

        $analysis = trim((string) ($data['analysis'] ?? ''));

        return [
            'home_win' => $hw,
            'draw'     => $d,
            'away_win' => $aw,
            'over_25'  => min(99, max(1, (int) ($data['over_25'] ?? 50))),
            'btts'     => min(99, max(1, (int) ($data['btts']    ?? 45))),
            'tips'     => $this->normaliseTips($data['tips'] ?? []),
            'analysis' => $analysis !== '' ? $analysis : self::FALLBACK_ANALYSIS,
        ];
    }

    /**
     * Normalise the AI's tips array — drop malformed entries, clamp confidence,
     * sort highest first, cap at 5.
     */
    private function normaliseTips(mixed $raw): array
    {
        if (! is_array($raw)) return [];

        $valid = [];
        foreach ($raw as $t) {
            if (! is_array($t)) continue;
            $market = trim((string) ($t['market'] ?? ''));
            $conf   = (int) ($t['confidence'] ?? 0);
            if ($market === '' || $conf < 50 || $conf > 100) continue;

            $valid[] = [
                'market'     => mb_substr($market, 0, 60),
                'confidence' => min(95, $conf),
                'rationale'  => mb_substr(trim((string) ($t['rationale'] ?? '')), 0, 200),
                'verifiable' => \App\Support\PickHelpers::isVerifiable($market),
            ];
        }

        usort($valid, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        return array_slice($valid, 0, 3);
    }
}
