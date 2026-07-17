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
     * Ask Groq for a full prediction - probabilities + detailed analysis.
     * Returns array with keys: home_win, draw, away_win, over_25, btts, analysis
     * Returns null on failure (rate limit, timeout, parse error).
     */
    /**
     * @param  string|null  $modelOverride  Force a specific Groq model instead of
     *   config('services.groq.model'). Used by `groq:test-model` to validate a
     *   replacement model returns the expected JSON schema before flipping GROQ_MODEL
     *   in .env. Not intended for the normal prediction path.
     */
    public function getPrediction(
        FootballMatch $match,
        array $poissonFallback,
        array $homeStats      = [],
        array $awayStats      = [],
        array $homeForm       = [],
        array $awayForm       = [],
        string $homeNews      = '',
        string $awayNews      = '',
        string $lineupData    = '',
        array $h2h            = [],
        string $matchPreview  = '',
        array $piRatings      = [],
        float $homeXg         = 0.0,
        float $awayXg         = 0.0,
        array $importance     = [],
        string $leagueDrawDesc = '',
        ?string $modelOverride = null,
    ): ?array {
        $apiKey = config('services.groq.key');

        if (blank($apiKey) || $apiKey === 'your_api_key_here') {
            return null;
        }

        $model = $modelOverride ?: config('services.groq.model', 'llama-3.3-70b-versatile');

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()->asJson()->timeout(30)
                ->retry(2, 3000, fn ($_, $r) => !($r && $r->status() === 429))
                ->post(config('services.groq.url'), [
                    'model'           => $model,
                    'temperature'     => 0.15,
                    'max_tokens'      => 1500,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user',   'content' => $this->userPrompt($match, $poissonFallback, $homeStats, $awayStats, $homeForm, $awayForm, $homeNews, $awayNews, $lineupData, $h2h, $matchPreview, $piRatings, $homeXg, $awayXg, $importance, $leagueDrawDesc)],
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
            Log::warning('Groq rate limit - will retry next cycle', ['match_id' => $match->id]);
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
- Use natural Pidgin phrasing - "dey fire", "no go fit", "well well", "no shaking" etc - but stay clear and informative.
- Return ONLY the translated HTML. No commentary, no markdown fences.
PIDGIN,
            'swahili' => <<<'SWAHILI'
Translate the football article HTML below into natural East African Swahili (Kenya/Tanzania style).
- Preserve ALL HTML tags exactly: <p>, <h2>, <h3>, <strong>, <em>, <a>, <ul>, <li>, <br>, etc. Translate only the text inside tags.
- Keep team names, scores, dates, league names, and proper nouns in the original form.
- Use natural sentence flow, not literal/word-for-word translation.
- Return ONLY the translated HTML. No commentary, no markdown fences.
SWAHILI,
            'french' => <<<'FRENCH'
Translate the football article HTML below into natural French.
- Preserve ALL HTML tags exactly: <p>, <h2>, <h3>, <strong>, <em>, <a>, <ul>, <li>, <br>, etc. Translate only the text inside tags.
- Keep team names, scores, dates, league names, and proper nouns in the original form.
- Use natural sentence flow, not literal/word-for-word translation.
- Return ONLY the translated HTML. No commentary, no markdown fences.
FRENCH,
            default => null,
        };

        if ($instruction === null) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()->asJson()->timeout(90)
                ->post(config('services.groq.url'), [
                    'model'       => config('services.groq.model'),
                    'temperature' => 0.30,
                    'max_tokens'  => 8000,
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
Translate the football analysis below into natural Nigerian Pidgin English - the kind real Lagos/Naija football fans speak.
- Keep football terminology recognisable (e.g. "win", "draw", "over 2.5 goals", team names) but make the prose sound like street/community Pidgin.
- Use phrases like "go win the match", "dey fire", "no go fit", "well well", "no shaking", "dem dey hot" where natural.
- Keep the meaning identical - same teams, same probabilities, same tip.
- The final sentence MUST start with "💡 Tip:" followed by the same recommendation, but phrased in Pidgin.
- Return ONLY the translated paragraph, nothing else. No quotes, no labels, no markdown.
PIDGIN,
            'swahili' => <<<'SWAHILI'
Translate the football analysis below into natural East African Swahili (Kenya/Tanzania style).
- Keep football terms (e.g. team names, "over 2.5 goals", "BTTS") if they don't have natural Swahili equivalents.
- Use natural sentence flow - not literal/word-for-word translation.
- Keep all probabilities, teams and the recommended tip exactly the same.
- The final sentence MUST start with "💡 Tip:" followed by the same recommendation, in Swahili.
- Return ONLY the translated paragraph, nothing else.
SWAHILI,
            'french' => <<<'FRENCH'
Translate the football analysis below into natural French, as written by a French sports journalist.
- Use natural football vocabulary in French (e.g. "victoire a domicile", "les deux equipes marquent", "plus d'1,5 buts").
- Keep team names, scores, and league names in their original form.
- Keep all probabilities and the recommended tip exactly the same.
- The final sentence MUST start with "💡 Conseil:" followed by the same recommendation, in French.
- Return ONLY the translated paragraph, nothing else. No quotes, no labels, no markdown.
FRENCH,
            default => null,
        };

        if ($instruction === null) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()->asJson()->timeout(30)
                ->post(config('services.groq.url'), [
                    'model'       => config('services.groq.model'),
                    'temperature' => 0.40,
                    'max_tokens'  => 1200,
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
You are TavsScore's senior football intelligence analyst. Real users stake real money on your picks — every prediction must be forensically justified.

═══ DATA HIERARCHY ═══
1. Our database stats (goals, form, H2H, rates) are GROUND TRUTH — weight them highest.
2. News / injury / lineup reports come second — if a key player is out, recalculate.
3. Your own deep football knowledge comes third — you know these clubs' styles, managers, tactical setups, key players, and historical tendencies. Apply this actively.
4. Never invent statistics not provided. Reason from what you have.

═══ CORE ACCURACY RULES ═══
5. home_win + draw + away_win MUST equal exactly 100.
6. When Poisson draw probability ≥ 60%, you MUST seriously consider "Draw" as your headline pick. Only choose Home Win or Away Win instead if form, H2H, or squad data gives clear evidence one team will break the deadlock. If you are not fully sure, use "Home or Draw (1X)" or "Draw or Away (X2)" rather than forcing a pure win pick.
7. 1 STRONG tip beats 3 weak ones. Only include tips you can genuinely justify.
8. Return ONLY valid JSON — no markdown fences, no text outside the object.
9. Never use em dashes (—) in any text field. Use commas, colons, or full stops instead.
9. Over 1.5 Goals hits ~75% — far safer than Over 2.5 (~52%). Prefer it unless data clearly shows goals.

═══ MARKET SELECTION INTELLIGENCE ═══
10. CLEAN SHEET: use when clean sheet % ≥ 35% AND opponent's goals/game ≤ 0.9 AND their failed-to-score rate ≥ 25%.
11. TEAM NOT TO SCORE: use when opponent scores ≤ 0.8/game AND team's clean sheets ≥ 30% of recent matches.
12. BOTH TEAMS SCORE (GG): use when both teams' BTTS rate ≥ 55% — strong signal regardless of 1X2 outcome.
13. NO BTTS (NG): use when at least one team failed to score ≥ 35% AND their BTTS rate is below 40%.
14. DRAW NO BET: use when one team is clearly stronger but a draw is a real risk. Removes draw exposure.
15. OVER/UNDER GOALS: match the line to the data. If avg goals/game combined ≥ 3.0 → Over 2.5. If ≤ 2.0 → Under 2.5 or Over 1.5.
16. HALF-TIME MARKETS: if home team leads at HT in ≥ 60% of home matches → "Home Lead at HT" is viable.
17. WIN EITHER HALF: use for strong favourites (>65% win chance) where the opponent may threaten in one half.
18. ASIAN HANDICAP -1: only when a team has won their last 5+ and opponent concedes 2+/game. Else use -0.5 or DNB.
19. CORNERS: top attacking sides (Man City, Liverpool, Barcelona, Bayern, PSG etc.) generate 6+ corners/game. "Over 9.5 Corners" viable for these teams vs weaker sides.
20. H2H MATTERS: if one team has won the last 4+ meetings, include that as a confidence booster. If H2H is even, reduce winner confidence slightly.

═══ PLAYER & SQUAD KNOWLEDGE ═══
21. You know elite players at every major club — Haaland (Man City), Salah (Liverpool), Mbappé (Real Madrid), Vinicius (Real Madrid), Kane (Bayern), Son (Spurs), Bellingham (Real Madrid), Saka (Arsenal), De Bruyne (Man City), Osimhen, Diallo, Leão, Martínez (Inter), Lewandowski (Barcelona), etc.
22. When confirmed lineup is provided: identify the most dangerous player in each XI. Star striker starting → boost attack by up to 15pp. Star striker absent → reduce that team's attack by 10-20pp.
23. Mention specific players by name in analysis. "With Haaland on the pitch, City average 2.8 goals at home..." is better than generic statements.
24. Squad depth matters: a team missing 2-3 starters is significantly weakened, especially in attack.

═══ TACTICAL KNOWLEDGE (apply actively) ═══
25. High-press teams (Liverpool, Man City, Arsenal, Dortmund, Atlético) tire opponents and generate late goals — consider Over 2.5 and BTTS.
26. Defensive-minded managers (Mourinho-style, some lower-table clubs) suppress scoring — lean toward Under 2.5, Clean Sheet, or NG.
27. Home advantage is real but varies: elite clubs at home (Anfield, Etihad, Bernabéu, Allianz, San Siro) = +10-15% win probability. Lower-league home sides get a smaller boost.
28. Teams fighting relegation often show desperate energy — can upset form lines. Factor into your analysis if visible.
SYSTEM;
    }

    private function userPrompt(
        FootballMatch $match,
        array $poisson        = [],
        array $homeStats      = [],
        array $awayStats      = [],
        array $homeForm       = [],
        array $awayForm       = [],
        string $homeNews      = '',
        string $awayNews      = '',
        string $lineupData    = '',
        array $h2h            = [],
        string $matchPreview  = '',
        array $piRatings      = [],
        float $homeXg         = 0.0,
        float $awayXg         = 0.0,
        array $importance     = [],
        string $leagueDrawDesc = '',
    ): string {
        $kickoff = $match->match_time?->format('l, d M Y, H:i') ?? 'Today';
        $home    = $match->home_team;
        $away    = $match->away_team;
        $league  = $match->league . ($match->league_country ? " ({$match->league_country})" : '');

        // ── Poisson block ─────────────────────────────────────────
        $hw        = $poisson['home_win']        ?? '?';
        $d         = $poisson['draw']             ?? '?';
        $aw        = $poisson['away_win']         ?? '?';
        $o15       = $poisson['over_15']          ?? '?';
        $o25       = $poisson['over_25']          ?? '?';
        $o35       = $poisson['over_35']          ?? '?';
        $btt       = $poisson['btts']             ?? '?';
        $u25       = isset($poisson['over_25'])   ? round(100 - $poisson['over_25'], 1) : '?';
        $u15       = isset($poisson['over_15'])   ? round(100 - $poisson['over_15'], 1) : '?';
        $homeClean = isset($poisson['home_clean_sheet']) ? $poisson['home_clean_sheet'] . '%' : 'N/A';
        $awayClean = isset($poisson['away_clean_sheet']) ? $poisson['away_clean_sheet'] . '%' : 'N/A';

        // ── Build a rich team stats block ─────────────────────────
        $statsBlock = $this->buildStatsBlock($home, $homeStats) . "\n\n" . $this->buildStatsBlock($away, $awayStats);

        // ── H2H block ─────────────────────────────────────────────
        $h2hBlock = '';
        if (! empty($h2h['results'])) {
            $h2hBlock = "\n\n═══ HEAD-TO-HEAD HISTORY (last {$h2h['total']} meetings) ═══\n";
            $h2hBlock .= implode("\n", $h2h['results']) . "\n";
            $h2hBlock .= "Summary: {$home} {$h2h['home_wins']}W | {$h2h['draws']}D | {$h2h['away_wins']}W {$away}";
        } else {
            $h2hBlock = "\n\nH2H: No recorded meetings in our database (first encounter or new teams in system). Apply your football knowledge.";
        }

        // ── News blocks ───────────────────────────────────────────
        $newsBlock = '';
        if (! blank($homeNews) || ! blank($awayNews)) {
            $newsBlock = "\n\n═══ TEAM NEWS & INJURIES ═══";
            if (! blank($homeNews)) $newsBlock .= "\n{$home}:\n{$homeNews}";
            if (! blank($awayNews)) $newsBlock .= "\n{$away}:\n{$awayNews}";
        }
        $previewBlock = blank($matchPreview)
            ? ''
            : "\n\n═══ MATCH PREVIEW & EXPERT ANALYSIS (from news) ═══\n{$matchPreview}";

        // ── Lineup block ──────────────────────────────────────────
        $lineupBlock = blank($lineupData)
            ? ''
            : "\n\n⚡ CONFIRMED LINEUPS — STAR PLAYER ANALYSIS REQUIRED:\nIdentify the most dangerous player in each XI. Star striker starting → boost attack. Star absent → reduce. Name them explicitly in your analysis.\n{$lineupData}";

        // ── Pi-ratings block ──────────────────────────────────────
        $piBlock = '';
        if (! empty($piRatings) && ($piRatings['home_games'] > 0 || $piRatings['away_games'] > 0)) {
            $diff    = $piRatings['diff'];
            $diffStr = $diff > 0
                ? "{$home} stronger by " . abs($diff) . " pi-points (home venue)"
                : ($diff < 0
                    ? "{$away} stronger by " . abs($diff) . " pi-points (away venue)"
                    : 'Teams evenly matched by pi-ratings');
            $piBlock = "\n\n═══ PI-RATINGS (dynamic strength, updated after every match) ═══\n"
                . "  {$home} home pi-rating: {$piRatings['home_pi']} | {$away} away pi-rating: {$piRatings['away_pi']}\n"
                . "  Differential: {$diffStr}\n"
                . "  Unlike league table, pi-ratings update on goal difference vs expectation — more accurate than position alone.";
        }

        // ── xG block ─────────────────────────────────────────────
        $xgBlock = '';
        if ($homeXg > 0 || $awayXg > 0) {
            $xgBlock = "\n\n═══ EXPECTED GOALS (venue-adjusted Poisson xG) ═══\n"
                . "  {$home} xG: {$homeXg} | {$away} xG: {$awayXg}\n"
                . "  These are venue-split expected goals (home team's home scoring rate × away team's away conceding rate). More accurate than season averages.";
        }

        // ── Match importance block ────────────────────────────────
        $importanceBlock = '';
        if (! empty($importance['context'])) {
            $importanceBlock = "\n\n═══ MATCH CONTEXT & IMPORTANCE ═══\n" . $importance['context'];
        }

        // ── League draw rate ─────────────────────────────────────
        $drawRateBlock = '';
        if (! blank($leagueDrawDesc)) {
            $drawRateBlock = "\n\n📊 LEAGUE DRAW RATE: {$leagueDrawDesc} Factor this into your draw probability assessment.";
        }

        return <<<PROMPT
MATCH: {$home} vs {$away}
COMPETITION: {$league}
KICKOFF: {$kickoff}

═══ TEAM STATISTICS (last {$homeStats['matches_played']} / {$awayStats['matches_played']} matches in our database) ═══

{$statsBlock}
{$h2hBlock}
{$piBlock}{$xgBlock}

═══ POISSON MODEL PROBABILITIES (Dixon-Coles corrected) ═══
  1X2:        Home Win {$hw}% | Draw {$d}% | Away Win {$aw}%
  Goals:      Over 1.5 Goals {$o15}% | Under 1.5 Goals {$u15}% | Over 2.5 Goals {$o25}% | Under 2.5 Goals {$u25}% | Over 3.5 Goals {$o35}%
  BTTS:       Both Teams Score {$btt}%
  Clean Sheet: Home Clean Sheet {$homeClean} | Away Clean Sheet {$awayClean}
{$drawRateBlock}{$importanceBlock}{$newsBlock}{$previewBlock}{$lineupBlock}

═══ YOUR ANALYSIS TASK ═══
Using ALL data above PLUS your deep knowledge of these clubs (playing style, key players, manager tactics, stadium atmosphere, historical tendencies):

1. Set final match probabilities — adjust Poisson where pi-ratings, news, lineups or your knowledge justifies it.
2. Choose your 1 STRONGEST tip from the markets list. This is what users stake money on.
3. Add up to 2 genuine alternatives (only if you are truly confident).
4. Mention specific players by name in the analysis when relevant.
5. Apply market selection rules from your training — do NOT default to Home Win / Away Win every time.
   - If clean sheet % is high → consider Clean Sheet or Win to Nil.
   - If BTTS rate is high for both teams → consider GG over 1X2.
   - If draw probability ≥ 60% OR match context flags a derby/rivalry → seriously consider Draw.
   - If one team is clearly better but draw is possible → Draw No Bet.
   - If one team rarely scores → Team NOT to Score.
   - If it is a top attacking team → consider Corners market.
   - If pi-rating differential is small (< 0.1) → teams are evenly matched; lean toward draw or double chance.

AVAILABLE MARKETS:
  1X2:           "Home Win", "Draw", "Away Win"
  Double Chance: "Home or Draw (1X)", "Draw or Away (X2)", "Home or Away (12)"
  Draw No Bet:   "Draw No Bet - Home", "Draw No Bet - Away"
  Win Either Half:"Win Either Half - Home", "Win Either Half - Away"
  Goals:         "Over 0.5 Goals", "Over 1.5 Goals", "Over 2.5 Goals", "Over 3.5 Goals", "Over 4.5 Goals", "Under 1.5 Goals", "Under 2.5 Goals", "Under 3.5 Goals"
  BTTS:          "Both Teams Score (GG)", "No Both Teams Score (NG)"
  First Half:    "Home Lead at HT", "Away Lead at HT", "HT Draw", "Over 0.5 First Half Goals", "Over 1.5 First Half Goals"
  Team Score:    "Home Team to Score", "Away Team to Score", "Home Team NOT to Score", "Away Team NOT to Score"
  Clean Sheet:   "Home Clean Sheet", "Away Clean Sheet", "Home Win to Nil", "Away Win to Nil"
  Asian Handicap:"Home -1 Handicap", "Home -0.5 Handicap", "Away +1 Handicap", "Away +0.5 Handicap"
  Corners:       "Over 8.5 Corners", "Over 9.5 Corners", "Over 10.5 Corners", "Under 9.5 Corners", "Home Most Corners", "Away Most Corners"
  Cards:         "Over 3.5 Cards", "Over 4.5 Cards"

REQUIRED JSON OUTPUT:
{
  "home_win": <int 0-100>,
  "draw":     <int 0-100>,
  "away_win": <int 0-100>,
  "over_25":  <int 0-100>,
  "btts":     <int 0-100>,
  "tips": [
    { "market": "<strongest pick>", "confidence": <int 65-95>, "rationale": "<≤15 words>" },
    { "market": "<2nd best>",       "confidence": <int 60-90>, "rationale": "<≤15 words>" },
    { "market": "<3rd best>",       "confidence": <int 60-85>, "rationale": "<≤15 words>" }
  ],
  "analysis": "<4-5 sentences: form gap, attacking threat, defensive strength, key player or injury impact, H2H significance. End: 💡 Tip: [top market in one sentence]. No em dashes.>"
}

RULES: home_win + draw + away_win = 100. Tips confidence descending. Only include tips you can genuinely justify with the data. Respond with JSON only.
PROMPT;
    }

    /**
     * Build a detailed stats block for a single team from extended stats array.
     */
    private function buildStatsBlock(string $teamName, array $s): string
    {
        if (empty($s) || ($s['matches_played'] ?? 0) === 0) {
            return "{$teamName}: No historical data in DB — apply your football knowledge.";
        }

        $n       = $s['matches_played'];
        $wins    = $s['wins']    ?? '?';
        $draws   = $s['draws']   ?? '?';
        $losses  = $s['losses']  ?? '?';
        $gpg     = $s['gpg']     ?? round(($s['goals_scored']   ?? 0) / $n, 2);
        $cpg     = $s['cpg']     ?? round(($s['goals_conceded'] ?? 0) / $n, 2);
        $htGpg   = $s['ht_gpg']  ?? '?';
        $htCpg   = $s['ht_cpg']  ?? '?';
        $cs      = $s['clean_sheets']    ?? 0;
        $fts     = $s['failed_to_score'] ?? 0;
        $btts    = $s['btts_count']      ?? 0;
        $o25     = $s['over25_count']    ?? 0;
        $csPct   = $n > 0 ? round($cs   / $n * 100) : 0;
        $ftsPct  = $n > 0 ? round($fts  / $n * 100) : 0;
        $bttsPct = $n > 0 ? round($btts / $n * 100) : 0;
        $o25Pct  = $n > 0 ? round($o25  / $n * 100) : 0;

        $hM = $s['home_matches'] ?? 0;
        $aM = $s['away_matches'] ?? 0;
        $hGpg = $hM > 0 ? round(($s['home_scored']   ?? 0) / $hM, 1) : '—';
        $hCpg = $hM > 0 ? round(($s['home_conceded'] ?? 0) / $hM, 1) : '—';
        $aGpg = $aM > 0 ? round(($s['away_scored']   ?? 0) / $aM, 1) : '—';
        $aCpg = $aM > 0 ? round(($s['away_conceded'] ?? 0) / $aM, 1) : '—';

        $formLines = '';
        if (! empty($s['form_detailed'])) {
            $formLines = "\n  Recent form (newest first): " . implode(' | ', array_slice($s['form_detailed'], 0, 6));
        }

        return <<<BLOCK
{$teamName} (last {$n} matches):
  Record: {$wins}W {$draws}D {$losses}L | Goals: {$gpg}/game scored, {$cpg}/game conceded
  HT avg: {$htGpg} scored, {$htCpg} conceded per game
  Clean sheets: {$cs}/{$n} ({$csPct}%) | Failed to score: {$fts}/{$n} ({$ftsPct}%)
  Both teams scored: {$btts}/{$n} ({$bttsPct}%) | Over 2.5 goals: {$o25}/{$n} ({$o25Pct}%)
  AT HOME ({$hM} matches): {$hGpg}/game scored, {$hCpg}/game conceded
  AWAY    ({$aM} matches): {$aGpg}/game scored, {$aCpg}/game conceded{$formLines}
BLOCK;
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
     * Normalise the AI's tips array - drop malformed entries, clamp confidence,
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
            if ($market === '' || $conf < 60 || $conf > 100) continue;

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
