<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\Markets\MarketEngine;
use App\Support\LeagueCoverage;
use App\Support\MatchStatsContext;
use App\Support\PickHelpers;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PredictionService
{
    private const RECENT_MATCH_LIMIT  = 10;
    private const NEUTRAL_GOALS_RATE  = 1.10;
    private const HOME_ADVANTAGE      = 1.15;
    private const MIN_XG              = 0.30;
    private const MAX_XG              = 4.50;
    private const MAX_GOALS_GRID      = 8;
    private const MAX_DAILY_PICKS     = 100;  // covers full European slate on busy Saturdays
    private const COMPLETED_STATUSES  = ['FT', 'AET', 'PEN'];
    private const EXCLUDED_UPCOMING_STATUSES = ['FT', 'AET', 'PEN', 'CANC', 'PST', 'ABD', 'AWD', 'WO'];

    public function __construct(
        private readonly GroqService              $groqService,
        private readonly OddsService              $oddsService,
        private readonly GeminiService            $geminiService,
        private readonly MistralService           $mistralService,
        private readonly ClaudeService            $claudeService,
        private readonly NewsService              $newsService,
        private readonly LineupService            $lineupService,
        private readonly AdaptiveThresholdService $adaptive,
        private readonly PiRatingService          $piRating,
        private readonly \App\Services\DixonColes\Predictor $dcPredictor,
    ) {}

    /** League priority order — most prestigious first; African coverage appended. */
    private function leaguePriorityIds(): array
    {
        return array_values(array_unique(array_merge(
            LeagueCoverage::topEuropean(),
            LeagueCoverage::africaContinental(),
        )));
    }

    // ──────────────────────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────────────────────

    public function generateForMatch(FootballMatch $match): Prediction
    {
        // ── Extended stats (powers both Poisson and the AI prompt) ──
        $homeStats = $this->extendedTeamStats($match->home_team, $match->match_time, $match->id);
        $awayStats = $this->extendedTeamStats($match->away_team, $match->match_time, $match->id);

        // Venue-aware xG: use home-specific scoring vs away-specific conceding rates
        // when enough split samples exist (≥3), blended 60/40 with overall rates for stability.
        $homeXgVenue   = $this->homeAttackStrength($homeStats) * $this->awayConceding($awayStats) * self::HOME_ADVANTAGE;
        $homeXgOverall = $this->attackStrength($homeStats)     * $this->defenseWeakness($awayStats) * self::HOME_ADVANTAGE;
        $awayXgVenue   = $this->awayAttackStrength($awayStats) * $this->homeConceding($homeStats);
        $awayXgOverall = $this->attackStrength($awayStats)     * $this->defenseWeakness($homeStats);

        $homeXg = $this->clampXg($homeXgVenue * 0.60 + $homeXgOverall * 0.40);
        $awayXg = $this->clampXg($awayXgVenue * 0.60 + $awayXgOverall * 0.40);
        $poisson   = $this->poissonProbabilities($homeXg, $awayXg);

        // Pi-rating differential: positive = home stronger, negative = away stronger
        $piRatings = $this->piRating->ratingsFor($match->home_team, $match->away_team);

        // Match importance flags (derby, rivalry, late season, struggling teams)
        $importance = $this->matchImportanceContext($match, $homeStats, $awayStats);

        // Per-league calibration (draw rate, predictability tier)
        $leagueId  = (int) $match->league_id;
        $leagueDrawDesc = \App\Support\LeagueCalibration::drawRateDescription($leagueId);

        // ── H2H history ───────────────────────────────────────────
        $h2h = $this->headToHead($match->home_team, $match->away_team, $match->match_time, $match->id);

        // ── Form guides (simple W/L/D for frontend) ───────────────
        $homeForm = $this->formGuide($match->home_team, $match->match_time, $match->id);
        $awayForm = $this->formGuide($match->away_team, $match->match_time, $match->id);

        // ── Check for existing good prediction ────────────────────
        $existing      = Prediction::query()->where('match_id', $match->id)->first();
        $hasAiAnalysis = $existing
            && !blank($existing->analysis)
            && $existing->analysis !== GroqService::FALLBACK_ANALYSIS
            && $existing->analysis !== 'Prediction pending';

        if ($hasAiAnalysis) {
            $tips = is_array($existing->tips) ? $existing->tips : [];
            if (! empty($tips)) {
                $primaryOutcome    = $tips[0]['market'];
                $primaryConfidence = $tips[0]['confidence'];
            } else {
                $primaryOutcome    = $this->verdict(
                    (float) $existing->home_win_prob,
                    (float) $existing->draw_prob,
                    (float) $existing->away_win_prob,
                    (float) ($existing->over_15_prob ?? $poisson['over_15']),
                    (float) ($existing->over_25_prob ?? $poisson['over_25']),
                    (float) ($existing->over_35_prob ?? $poisson['over_35']),
                    (float) ($existing->btts_prob    ?? $poisson['btts']),
                );
                $primaryConfidence = $primaryOutcome === 'Competitive Match' ? null : $this->confidenceForOutcome(
                    $primaryOutcome,
                    (float) $existing->home_win_prob,
                    (float) $existing->draw_prob,
                    (float) $existing->away_win_prob,
                    (float) ($existing->over_15_prob ?? $poisson['over_15']),
                    (float) ($existing->over_25_prob ?? $poisson['over_25']),
                    (float) ($existing->over_35_prob ?? $poisson['over_35']),
                    (float) ($existing->btts_prob    ?? $poisson['btts']),
                    (float) ($poisson['home_clean_sheet'] ?? 0),
                    (float) ($poisson['away_clean_sheet'] ?? 0),
                );
            }

            // Backfill opening_odds if not already stored (no extra API call — OddsService caches)
            $updates = ['predicted_outcome' => $primaryOutcome, 'confidence' => $primaryConfidence];
            if ($existing->opening_odds === null && in_array((int) $match->league_id, LeagueCoverage::topEuropean(), true)) {
                try {
                    $updates['opening_odds'] = $this->oddsService->impliedProbabilities($match);
                } catch (\Throwable) {}
            }

            return Prediction::query()->updateOrCreate(['match_id' => $match->id], $updates);
        }

        // ── Fetch news (multi-source: Google + BBC + Sky + ESPN) ──
        $homeNews     = $this->newsService->getFullContext($match->home_team);
        $awayNews     = $this->newsService->getFullContext($match->away_team);
        $matchPreview = $this->newsService->getMatchPreview($match->home_team, $match->away_team, $match->league ?? '');

        // ── Fetch confirmed lineup if within kickoff window ───────
        $lineupData = $this->lineupService->getLineup($match);

        // ── Live API-Football context (standings + season stats) ──
        // Fed to every LLM as extra signal; never touches the Poisson/DC numbers.
        $statsContext = MatchStatsContext::build($match);

        // ── Call Groq with full context ───────────────────────────
        $groq = $this->groqService->getPrediction(
            $match, $poisson,
            $homeStats, $awayStats,
            $homeForm, $awayForm,
            $homeNews, $awayNews,
            $lineupData,
            $h2h,
            $matchPreview,
            $piRatings,
            $homeXg,
            $awayXg,
            $importance,
            $leagueDrawDesc,
            statsContext: $statsContext,
        );

        // Respect 30 RPM: 2.1 s between calls
        usleep(2_100_000);

        if ($groq === null) {
            // Groq failed — try Gemini then Mistral for at least an outcome verdict
            $fallback = $this->geminiService->independentVerdict($match, $homeStats, $awayStats, $h2h, $statsContext)
                ?? $this->mistralService->independentVerdict($match, $homeStats, $awayStats, $h2h, $statsContext)
                ?? $this->claudeService->independentVerdict($match, $homeStats, $awayStats, $h2h, $statsContext);

            if ($fallback !== null) {
                $groq = [
                    'home_win' => $poisson['home_win'],
                    'draw'     => $poisson['draw'],
                    'away_win' => $poisson['away_win'],
                    'over_25'  => $poisson['over_25'],
                    'btts'     => $poisson['btts'],
                    'tips'     => [['market' => $fallback['outcome'], 'confidence' => $fallback['confidence'], 'rationale' => 'AI verdict (fallback)']],
                    'analysis' => GroqService::FALLBACK_ANALYSIS,
                ];
            }
        }

        if ($groq !== null) {
            $homeWin = $groq['home_win'];
            $draw    = $groq['draw'];
            $awayWin = $groq['away_win'];
            $over25  = round(($groq['over_25'] + $poisson['over_25']) / 2, 1);
            $btts    = round(($groq['btts']    + $poisson['btts'])    / 2, 1);
            $over15  = $poisson['over_15'];
            $over35  = $poisson['over_35'];
            $tips    = $groq['tips'] ?? [];
            $analysis = $groq['analysis'];
        } else {
            // All AIs unavailable — store neutral Poisson + pending marker
            $homeWin  = $poisson['home_win'];
            $draw     = $poisson['draw'];
            $awayWin  = $poisson['away_win'];
            $over15   = $poisson['over_15'];
            $over25   = $poisson['over_25'];
            $over35   = $poisson['over_35'];
            $btts     = $poisson['btts'];
            $tips     = [];
            $analysis = GroqService::FALLBACK_ANALYSIS;
        }

        // ── Dixon-Coles override (Phase 2 ship gate) ─────────────────────
        // For leagues where DC beat the naive baseline in the 2026-07-12
        // backtest, replace the Groq/Poisson numbers with DC's for the
        // markets DC ships. Groq keeps ownership of the narrative + tips.
        // Fallback is silent — if DC has no params for a team, keep whatever
        // Groq produced.
        $dcActive = false;
        if (config('prediction.dc_enabled') && $match->league_id) {
            $lid = (int) $match->league_id;
            $dcForecast = $this->dcPredictor->predict($match, config('prediction.model_version'));

            if ($dcForecast) {
                if (in_array($lid, (array) config('prediction.dc_1x2_leagues'), true)) {
                    $homeWin = round($dcForecast['home_win'] * 100, 1);
                    $draw    = round($dcForecast['draw']     * 100, 1);
                    $awayWin = round($dcForecast['away_win'] * 100, 1);
                    $dcActive = true;
                }
                if (in_array($lid, (array) config('prediction.dc_over25_leagues'), true)) {
                    $over25 = round($dcForecast['over_25'] * 100, 1);
                    $dcActive = true;
                }
                if (in_array($lid, (array) config('prediction.dc_btts_leagues'), true)) {
                    $btts = round($dcForecast['btts'] * 100, 1);
                    $dcActive = true;
                }
                // Over 1.5 / 3.5 are always DC-derived when we have params —
                // DC's full-matrix computation is strictly more informative
                // than raw Poisson goals rate and adds no risk (backtest ties).
                $over15 = round($dcForecast['over_15'] * 100, 1);
                $over35 = round($dcForecast['over_35'] * 100, 1);
            }
        }
        if ($dcActive) {
            \Illuminate\Support\Facades\Log::info('PredictionService: DC active for match', [
                'match_id'  => $match->id,
                'league_id' => $match->league_id,
                'home_win'  => $homeWin, 'draw' => $draw, 'away_win' => $awayWin,
            ]);
        }

        // Fallback: if Groq returned no tips at all, generate from probabilities
        // so /picks never goes empty when the LLM hiccups. We only fill in if
        // empty — never PAD a 1-tip Groq response with filler alternatives.
        if (empty($tips)) {
            $tips = $this->tipsFromProbabilities(
                $homeWin, $draw, $awayWin, $over15, $over25, $over35, $btts,
                (float) ($poisson['home_clean_sheet'] ?? 0),
                (float) ($poisson['away_clean_sheet'] ?? 0),
            );
        }

        // Bookmaker consensus check — annotate each tip with whether the
        // market agrees. AI tips that disagree by >15pp with bookmakers get
        // flagged; users see "⚠️ market disagrees" badge.
        $tips = $this->annotateWithMarketConsensus($tips, $match);
        $tips = $this->annotateWithGeminiConsensus($tips, $match, $homeStats, $awayStats, $h2h, $statsContext);

        // Snapshot bookmaker implied probabilities at prediction time.
        // Cached by OddsService, so no extra API call if annotateWithMarketConsensus
        // already fetched them. Stored as opening_odds for CLV / movement tracking.
        $openingOdds = null;
        if (in_array((int) $match->league_id, LeagueCoverage::topEuropean(), true)) {
            try {
                $openingOdds = $this->oddsService->impliedProbabilities($match);
            } catch (\Throwable) {}
        }

        // Primary outcome = the strongest tip
        if (! empty($tips)) {
            $primaryOutcome    = $tips[0]['market'];
            $primaryConfidence = $tips[0]['confidence'];
        } else {
            $primaryOutcome    = $this->verdict($homeWin, $draw, $awayWin, $over15, $over25, $over35, $btts);
            $primaryConfidence = $primaryOutcome === 'Competitive Match'
                ? null
                : $this->confidenceForOutcome(
                    $primaryOutcome, $homeWin, $draw, $awayWin, $over15, $over25, $over35, $btts,
                    (float) ($poisson['home_clean_sheet'] ?? 0),
                    (float) ($poisson['away_clean_sheet'] ?? 0),
                );
        }

        [$homeXgScore, $awayXgScore] = $this->h2hXgCalibration($h2h, $homeXg, $awayXg);
        $likelyScores = $this->topScorelines($homeXgScore, $awayXgScore);
        $marketBoard  = MarketEngine::fromExpectedGoals($homeXgScore, $awayXgScore);

        return Prediction::query()->updateOrCreate(
            ['match_id' => $match->id],
            [
                'home_win_prob'     => $homeWin,
                'draw_prob'         => $draw,
                'away_win_prob'     => $awayWin,
                'over_15_prob'      => $over15,
                'over_25_prob'      => $over25,
                'over_35_prob'      => $over35,
                'btts_prob'         => $btts,
                'home_2plus_prob'   => $poisson['home_2plus'],
                'away_2plus_prob'   => $poisson['away_2plus'],
                'home_3plus_prob'   => $poisson['home_3plus'],
                'away_3plus_prob'   => $poisson['away_3plus'],
                'predicted_outcome' => $primaryOutcome,
                'tips'              => $tips,
                'confidence'        => $primaryConfidence,
                'pi_rating_diff'    => $piRatings['diff'],
                'analysis'          => $analysis,
                'likely_scores'     => $likelyScores,
                'market_board'      => $marketBoard,
                'opening_odds'      => $openingOdds,
            ]
        );
    }

    /**
     * Generate market tips directly from Poisson probabilities — used as a
     * fallback when Groq returns nothing. Emits tips that pass the 50% floor.
     * Corners/HT/handicap are AI-only markets — Poisson can't derive those.
     */
    private function tipsFromProbabilities(
        float $hw, float $d, float $aw,
        float $over15, float $over25, float $over35, float $btts,
        float $homeClean = 0.0, float $awayClean = 0.0
    ): array {
        $candidates = [
            ['market' => 'Home Win',            'confidence' => (int) round($hw),           'rationale' => 'Strongest 1X2 probability'],
            ['market' => 'Draw',                'confidence' => (int) round($d),            'rationale' => 'Strongest 1X2 probability'],
            ['market' => 'Away Win',            'confidence' => (int) round($aw),           'rationale' => 'Strongest 1X2 probability'],
            ['market' => 'Home or Draw (1X)',   'confidence' => (int) round($hw + $d),      'rationale' => 'Double chance from Poisson'],
            ['market' => 'Draw or Away (X2)',   'confidence' => (int) round($d + $aw),      'rationale' => 'Double chance from Poisson'],
            ['market' => 'Home or Away (12)',   'confidence' => (int) round($hw + $aw),     'rationale' => 'Double chance from Poisson'],
            ['market' => 'Draw No Bet - Home',  'confidence' => (int) round($hw),           'rationale' => 'DNB home — draw refunds stake'],
            ['market' => 'Draw No Bet - Away',  'confidence' => (int) round($aw),           'rationale' => 'DNB away — draw refunds stake'],
            ['market' => 'Over 1.5 Goals',      'confidence' => (int) round($over15),       'rationale' => 'Goal-line projection'],
            ['market' => 'Under 1.5 Goals',     'confidence' => (int) round(100 - $over15), 'rationale' => 'Goal-line projection'],
            ['market' => 'Over 2.5 Goals',      'confidence' => (int) round($over25),       'rationale' => 'Goal-line projection'],
            ['market' => 'Under 2.5 Goals',     'confidence' => (int) round(100 - $over25), 'rationale' => 'Goal-line projection'],
            ['market' => 'Over 3.5 Goals',      'confidence' => (int) round($over35),       'rationale' => 'Goal-line projection'],
            ['market' => 'Under 3.5 Goals',     'confidence' => (int) round(100 - $over35), 'rationale' => 'Goal-line projection'],
            ['market' => 'Both Teams Score',    'confidence' => (int) round($btts),         'rationale' => 'BTTS Poisson estimate'],
            ['market' => 'No Both Teams Score', 'confidence' => (int) round(100 - $btts),   'rationale' => 'BTTS Poisson estimate'],
        ];

        // Clean sheet markets: P(away=0) = home clean sheet, P(home=0) = away clean sheet
        if ($homeClean > 0) {
            $candidates[] = ['market' => 'Home Clean Sheet',       'confidence' => (int) round($homeClean), 'rationale' => 'Poisson P(away scores 0)'];
            $candidates[] = ['market' => 'Away Team NOT to Score', 'confidence' => (int) round($homeClean), 'rationale' => 'Poisson P(away scores 0)'];
        }
        if ($awayClean > 0) {
            $candidates[] = ['market' => 'Away Clean Sheet',       'confidence' => (int) round($awayClean), 'rationale' => 'Poisson P(home scores 0)'];
            $candidates[] = ['market' => 'Home Team NOT to Score', 'confidence' => (int) round($awayClean), 'rationale' => 'Poisson P(home scores 0)'];
        }

        $candidates = array_values(array_filter($candidates, fn ($c) => $c['confidence'] >= 60 && $c['confidence'] <= 95));
        usort($candidates, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        // Diversify: only one of each category so we don't return 3 goal-line tips
        $picked = [];
        $usedCategory = [];
        foreach ($candidates as $c) {
            $cat = $this->marketCategory($c['market']);
            if (in_array($cat, $usedCategory, true)) continue;
            $picked[] = $c;
            $usedCategory[] = $cat;
            if (count($picked) >= 3) break;
        }
        return $picked;
    }

    private function marketCategory(string $market): string
    {
        return match (true) {
            in_array($market, ['Home Win', 'Draw', 'Away Win'], true)                                            => '1x2',
            str_contains($market, 'Draw No Bet')                                                                 => 'dnb',
            str_contains($market, '(1X)') || str_contains($market, '(X2)') || str_contains($market, '(12)')    => 'double_chance',
            str_contains($market, 'Goals')                                                                       => 'goals',
            str_contains($market, 'Both Teams Score') || str_contains($market, 'BTTS')                          => 'btts',
            str_contains($market, 'Clean Sheet') || str_contains($market, 'Win to Nil')                         => 'clean_sheet',
            str_contains($market, 'NOT to Score') || str_contains($market, 'Team to Score')                     => 'team_score',
            str_contains($market, 'Corner')                                                                      => 'corners',
            str_contains($market, 'Win Either Half')                                                             => 'win_either_half',
            str_contains($market, 'Asian Handicap') || str_contains($market, 'Handicap')                        => 'handicap',
            str_contains($market, 'Half') || str_contains($market, ' HT')                                       => 'halftime',
            default                                                                                              => 'other',
        };
    }

    /**
     * Pull pre-match odds from API-Football, convert to implied probabilities,
     * and annotate each tip with `market_implied` (%) and `market_agrees` (bool).
     * Disagreement threshold = 15pp.
     *
     * Only fires for top-priority leagues to conserve API quota — bookmakers
     * don't price African club fixtures consistently anyway.
     */
    private function annotateWithMarketConsensus(array $tips, FootballMatch $match): array
    {
        if (empty($tips)) return $tips;

        // Skip lower-tier / African leagues: bookmakers rarely price these well
        // and we save API calls for matches where the consensus actually matters
        if (! in_array((int) $match->league_id, LeagueCoverage::topEuropean(), true)) {
            return $tips;
        }

        $market = null;
        try {
            $market = $this->oddsService->impliedProbabilities($match);
        } catch (\Throwable) {
            // Odds endpoint failure shouldn't block predictions
        }

        if (! $market) return $tips;

        foreach ($tips as $i => $tip) {
            $marketPct = $this->marketImpliedFor($tip['market'] ?? '', $market);
            if ($marketPct === null) continue;

            $delta = abs(($tip['confidence'] ?? 0) - $marketPct);
            $tips[$i]['market_implied'] = (int) round($marketPct);
            $tips[$i]['market_agrees']  = $delta <= 15;
        }
        return $tips;
    }

    /**
     * Cross-validate the headline tip against Gemini. Skips silently if Gemini
     * isn't configured.
     */
    private function annotateWithGeminiConsensus(
        array         $tips,
        FootballMatch $match,
        array         $homeStats = [],
        array         $awayStats = [],
        array         $h2h = [],
        string        $statsContext = '',
    ): array {
        if (empty($tips)) return $tips;

        $groqOutcome    = $tips[0]['market']     ?? '';
        $groqConfidence = (int) ($tips[0]['confidence'] ?? 70);

        // Groq itself is too uncertain — no point calling other AIs
        if ($groqConfidence < 60) {
            $tips[0]['gemini_agrees']   = false;
            $tips[0]['agreement_level'] = 'speculative';
            return $tips;
        }

        // ── Call Gemini independently ─────────────────────────────
        $geminiVerdict = null;
        if ($this->geminiService->isConfigured()) {
            try {
                $geminiVerdict = $this->geminiService->independentVerdict(
                    match: $match, homeStats: $homeStats, awayStats: $awayStats, h2h: $h2h, statsContext: $statsContext,
                );
            } catch (\Throwable) {}
        }

        // ── Call Mistral independently ────────────────────────────
        $mistralVerdict = null;
        if ($this->mistralService->isConfigured()) {
            try {
                $mistralVerdict = $this->mistralService->independentVerdict(
                    match: $match, homeStats: $homeStats, awayStats: $awayStats, h2h: $h2h, statsContext: $statsContext,
                );
            } catch (\Throwable) {}
        }

        // Store the panel's raw verdicts for audit / display
        if ($geminiVerdict !== null) {
            $tips[0]['gemini_tip']  = $geminiVerdict['outcome'];
            $tips[0]['gemini_conf'] = $geminiVerdict['confidence'];
        }
        if ($mistralVerdict !== null) {
            $tips[0]['mistral_tip']  = $mistralVerdict['outcome'];
            $tips[0]['mistral_conf'] = $mistralVerdict['confidence'];
        }

        // ── Claude is the ARBITER ─────────────────────────────────
        // It reviews the data AND the whole panel, then issues the final call.
        // If Claude is unavailable/over-limit we fall through to the raw
        // Groq+Gemini+Mistral consensus so predictions never stop.
        $arbiter = null;
        if ($this->claudeService->isConfigured()) {
            try {
                $arbiter = $this->claudeService->finalVerdict(
                    match: $match,
                    panel: [
                        'groq'    => ['outcome' => $groqOutcome, 'confidence' => $groqConfidence],
                        'gemini'  => $geminiVerdict,
                        'mistral' => $mistralVerdict,
                    ],
                    homeStats: $homeStats, awayStats: $awayStats, h2h: $h2h, statsContext: $statsContext,
                );
            } catch (\Throwable) {}
        }

        if ($arbiter !== null) {
            $finalMarket = $arbiter['outcome'];
            $finalNorm   = mb_strtolower(trim($finalMarket));

            // How much of the panel backs Claude's final pick (≥60% confident)?
            $supporters = 0;
            foreach ([
                ['outcome' => $groqOutcome, 'confidence' => $groqConfidence],
                $geminiVerdict,
                $mistralVerdict,
            ] as $v) {
                if ($v !== null && mb_strtolower(trim($v['outcome'])) === $finalNorm && $v['confidence'] >= 60) {
                    $supporters++;
                }
            }

            $tips[0]['market']       = $finalMarket;
            $tips[0]['confidence']   = $arbiter['confidence'];
            $tips[0]['claude_tip']   = $finalMarket;
            $tips[0]['claude_conf']  = $arbiter['confidence'];
            $tips[0]['decided_by']   = 'claude';
            if (! blank($arbiter['rationale'])) {
                $tips[0]['claude_rationale'] = $arbiter['rationale'];
            }
            $tips[0]['agreement_level'] = $supporters >= 3 ? 'strong'
                : ($supporters === 2 ? 'partial'
                : ($arbiter['confirmed'] ? 'partial' : 'arbiter-call'));
            // Publish unless it's a low-support contrarian call at low confidence
            $tips[0]['gemini_agrees'] = $supporters >= 2 || $arbiter['confidence'] >= 70;

            return $tips;
        }

        // ── Fallback: raw Groq+Gemini+Mistral consensus (Claude unavailable) ──
        $groqNorm = mb_strtolower(trim($groqOutcome));

        // An AI "agrees" only when it reaches the same outcome AND is ≥60% confident
        $geminiOk  = $geminiVerdict  === null
            || (mb_strtolower(trim($geminiVerdict['outcome']))  === $groqNorm && $geminiVerdict['confidence']  >= 60);
        $mistralOk = $mistralVerdict === null
            || (mb_strtolower(trim($mistralVerdict['outcome'])) === $groqNorm && $mistralVerdict['confidence'] >= 60);

        $allAgree      = $geminiOk && $mistralOk;
        $configuredAIs = 1 + ($geminiVerdict !== null ? 1 : 0) + ($mistralVerdict !== null ? 1 : 0);

        // Collect confidences from agreeing AIs only (Groq always included)
        $confs = [$groqConfidence];
        if ($geminiOk  && $geminiVerdict  !== null) $confs[] = $geminiVerdict['confidence'];
        if ($mistralOk && $mistralVerdict !== null)  $confs[] = $mistralVerdict['confidence'];
        $avgAgreeConf = (int) round(array_sum($confs) / count($confs));

        // ── Calibrated confidence + agreement level ───────────────
        if ($configuredAIs === 1) {
            $agreementLevel = 'unverified';
            $finalConf      = $groqConfidence;
        } elseif ($allAgree) {
            $agreementLevel = 'strong';
            $finalConf      = $avgAgreeConf;
        } elseif (count($confs) >= 2) {
            $agreementLevel = 'partial';
            $finalConf      = max(0, $avgAgreeConf - 10);
        } else {
            $agreementLevel = 'conflict';
            $finalConf      = (int) round($groqConfidence * 0.75);
        }

        $tips[0]['agreement_level'] = $agreementLevel;
        $tips[0]['gemini_agrees']   = $allAgree;
        $tips[0]['confidence']      = $finalConf;

        return $tips;
    }

    /**
     * Map a market label to the bookmaker-implied probability we have.
     * Returns null if we don't track that market.
     */
    private function marketImpliedFor(string $marketLabel, array $market): ?float
    {
        return match (true) {
            $marketLabel === 'Home Win'                     => $market['home_win']       ?? null,
            $marketLabel === 'Draw'                         => $market['draw']           ?? null,
            $marketLabel === 'Away Win'                     => $market['away_win']       ?? null,
            $marketLabel === 'Over 2.5 Goals'               => $market['over_25']        ?? null,
            $marketLabel === 'Under 2.5 Goals'              => $market['over_25'] !== null ? 100 - $market['over_25'] : null,
            in_array($marketLabel, ['Both Teams Score', 'Both Teams Score (GG)']) => $market['btts'] ?? null,
            in_array($marketLabel, ['No Both Teams Score', 'No Both Teams Score (NG)']) => $market['btts'] !== null ? 100 - $market['btts'] : null,
            $marketLabel === 'Home or Draw (1X)'            => isset($market['home_win'], $market['draw'])   ? $market['home_win'] + $market['draw'] : null,
            $marketLabel === 'Draw or Away (X2)'            => isset($market['draw'], $market['away_win'])   ? $market['draw'] + $market['away_win'] : null,
            $marketLabel === 'Home or Away (12)'            => isset($market['home_win'], $market['away_win'])? $market['home_win'] + $market['away_win'] : null,
            default                                         => null,
        };
    }

    /**
     * Confidence (%) of a non-AI verdict — derived directly from probabilities.
     * homeClean = P(away scores 0), awayClean = P(home scores 0).
     */
    private function confidenceForOutcome(
        string $outcome,
        float $hw, float $d, float $aw,
        float $over15, float $over25, float $over35, float $btts,
        float $homeClean = 0.0, float $awayClean = 0.0
    ): int {
        return (int) round(match (true) {
            $outcome === 'Home Win'                                            => $hw,
            $outcome === 'Draw'                                                => $d,
            $outcome === 'Away Win'                                            => $aw,
            $outcome === 'Over 1.5 Goals'                                      => $over15,
            $outcome === 'Under 1.5 Goals'                                     => 100 - $over15,
            $outcome === 'Over 2.5 Goals'                                      => $over25,
            $outcome === 'Under 2.5 Goals'                                     => 100 - $over25,
            $outcome === 'Over 3.5 Goals'                                      => $over35,
            $outcome === 'Under 3.5 Goals'                                     => 100 - $over35,
            in_array($outcome, ['Both Teams Score', 'Both Teams Score (GG)'], true)      => $btts,
            in_array($outcome, ['No Both Teams Score', 'No Both Teams Score (NG)'], true) => 100 - $btts,
            $outcome === 'Home or Draw (1X)'                                   => $hw + $d,
            $outcome === 'Draw or Away (X2)'                                   => $d  + $aw,
            $outcome === 'Home or Away (12)'                                   => $hw + $aw,
            // Draw No Bet: removes draw — home/away win probability is the confidence
            $outcome === 'Draw No Bet - Home'                                  => $hw,
            $outcome === 'Draw No Bet - Away'                                  => $aw,
            // Clean sheet: P(opponent scores 0)
            $outcome === 'Home Clean Sheet'                                    => $homeClean ?: round($hw * 0.65),
            $outcome === 'Away Clean Sheet'                                    => $awayClean ?: round($aw * 0.65),
            // Win to nil: need both a win AND a clean sheet
            $outcome === 'Home Win to Nil'                                     => $homeClean > 0 ? round(min($hw, $homeClean) * 0.95) : round($hw * 0.55),
            $outcome === 'Away Win to Nil'                                     => $awayClean > 0 ? round(min($aw, $awayClean) * 0.95) : round($aw * 0.55),
            // Team NOT to score: P(that team scores 0)
            // Home NOT to score = P(home=0) = awayClean
            // Away NOT to score = P(away=0) = homeClean
            $outcome === 'Home Team NOT to Score'                              => $awayClean ?: round(100 - $hw - ($btts * 0.5)),
            $outcome === 'Away Team NOT to Score'                              => $homeClean ?: round(100 - $aw - ($btts * 0.5)),
            // Team to score: complement of NOT to score
            $outcome === 'Home Team to Score'                                  => $awayClean > 0 ? round(100 - $awayClean) : round($hw + $btts * 0.4),
            $outcome === 'Away Team to Score'                                  => $homeClean > 0 ? round(100 - $homeClean) : round($aw + $btts * 0.4),
            // Corners / HT / Handicap / Win Either Half: AI provides these; default to 55 as neutral above-baseline
            str_contains($outcome, 'Corner')                                   => 55,
            str_contains($outcome, 'Win Either Half')                          => round(max($hw, $aw) * 0.85),
            str_contains($outcome, 'Asian Handicap') || str_contains($outcome, 'Handicap') => round(max($hw, $aw) * 0.9),
            str_contains($outcome, 'Half') || str_contains($outcome, ' HT')    => 55,
            default                                                            => 50,
        });
    }

    /**
     * Calculate historical win rate per market type using resolved predictions.
     * Returns a multiplier per outcome: >1.0 = boost, <1.0 = penalise.
     * Requires at least 5 resolved samples per market to apply — fewer means
     * the data is too thin to trust, so we default to 1.0 (neutral).
     */
    private function getMarketAccuracyWeights(): array
    {
        $resolved = Prediction::query()
            ->whereNotNull('was_correct')
            ->whereNotNull('predicted_outcome')
            ->where('created_at', '>=', now()->subDays(90))
            ->get(['predicted_outcome', 'was_correct']);

        if ($resolved->count() < 10) {
            return [];
        }

        $weights = [];

        foreach ($resolved->groupBy('predicted_outcome') as $market => $preds) {
            $total = $preds->count();
            if ($total < 5) continue;

            $winRate = $preds->where('was_correct', true)->count() / $total;

            // Map win rate to a multiplier:
            // 70%+ accuracy  → 1.25 boost (strongly prefer this market)
            // 55-70%         → 1.10 slight boost
            // 45-55%         → 1.00 neutral
            // 35-45%         → 0.85 slight penalty
            // <35%           → 0.70 strong penalty (avoid this market)
            $weights[$market] = match (true) {
                $winRate >= 0.70 => 1.25,
                $winRate >= 0.55 => 1.10,
                $winRate >= 0.45 => 1.00,
                $winRate >= 0.35 => 0.85,
                default          => 0.70,
            };
        }

        return $weights;
    }

    /**
     * Re-run Groq prediction for a daily pick match once its confirmed lineup
     * is available. Only fires once per match per day (cache-gated).
     * Returns true if the prediction was updated, false if skipped.
     */
    public function regenerateWithLineup(FootballMatch $match): bool
    {
        $cacheKey = "lineup_repredicted_{$match->id}";
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return false;
        }

        $lineupData = $this->lineupService->getLineup($match);
        if (blank($lineupData)) {
            return false;
        }

        $existing = Prediction::query()->where('match_id', $match->id)->first();

        $homeStats    = $this->extendedTeamStats($match->home_team, $match->match_time, $match->id);
        $awayStats    = $this->extendedTeamStats($match->away_team, $match->match_time, $match->id);
        $homeForm     = $this->formGuide($match->home_team, $match->match_time, $match->id);
        $awayForm     = $this->formGuide($match->away_team, $match->match_time, $match->id);
        $homeNews     = $this->newsService->getFullContext($match->home_team);
        $awayNews     = $this->newsService->getFullContext($match->away_team);
        $matchPreview = $this->newsService->getMatchPreview($match->home_team, $match->away_team, $match->league ?? '');
        $h2h          = $this->headToHead($match->home_team, $match->away_team, $match->match_time, $match->id);

        // Build Poisson baseline — use existing probabilities if available, otherwise compute fresh
        $homeXgBase = $this->clampXg($this->homeAttackStrength($homeStats) * $this->homeConceding($awayStats) * self::HOME_ADVANTAGE);
        $awayXgBase = $this->clampXg($this->awayAttackStrength($awayStats) * $this->awayConceding($homeStats));

        if ($existing) {
            $poisson = [
                'home_win' => (float) ($existing->home_win_prob ?? 33),
                'draw'     => (float) ($existing->draw_prob     ?? 33),
                'away_win' => (float) ($existing->away_win_prob ?? 34),
                'over_15'  => (float) ($existing->over_15_prob  ?? 70),
                'over_25'  => (float) ($existing->over_25_prob  ?? 50),
                'over_35'  => (float) ($existing->over_35_prob  ?? 30),
                'btts'     => (float) ($existing->btts_prob     ?? 45),
            ];
        } else {
            $poisson = $this->poissonProbabilities($homeXgBase, $awayXgBase);
        }

        $piRatings      = $this->piRating->ratingsFor($match->home_team, $match->away_team);
        $importance     = $this->matchImportanceContext($match, $homeForm, $awayForm);
        $leagueDrawDesc = \App\Support\LeagueCalibration::drawRateDescription((int) $match->league_id);
        $statsContext   = MatchStatsContext::build($match);

        $groq = $this->groqService->getPrediction(
            $match, $poisson,
            $homeStats, $awayStats,
            $homeForm, $awayForm,
            $homeNews, $awayNews,
            $lineupData,
            $h2h,
            $matchPreview,
            $piRatings,
            $homeXgBase,
            $awayXgBase,
            $importance,
            $leagueDrawDesc,
            statsContext: $statsContext,
        );

        if (! $groq) {
            // Groq failed — try Gemini, then Mistral, then Claude for a fallback verdict
            $fallback = $this->geminiService->independentVerdict($match, $homeStats, $awayStats, $h2h, $statsContext)
                ?? $this->mistralService->independentVerdict($match, $homeStats, $awayStats, $h2h, $statsContext)
                ?? $this->claudeService->independentVerdict($match, $homeStats, $awayStats, $h2h, $statsContext);

            if (! $fallback) {
                return false;
            }

            $groq = [
                'home_win'          => (float) ($existing->home_win_prob ?? $poisson['home_win']),
                'draw'              => (float) ($existing->draw_prob     ?? $poisson['draw']),
                'away_win'          => (float) ($existing->away_win_prob ?? $poisson['away_win']),
                'over_25'           => (float) ($existing->over_25_prob  ?? $poisson['over_25']),
                'btts'              => (float) ($existing->btts_prob     ?? $poisson['btts']),
                'predicted_outcome' => $fallback['outcome'],
                'tips'              => [['market' => $fallback['outcome'], 'confidence' => $fallback['confidence'], 'rationale' => 'AI verdict (fallback)']],
                'analysis'          => GroqService::FALLBACK_ANALYSIS,
            ];
        }

        usleep(2_100_000);

        $tips = $this->annotateWithMarketConsensus($groq['tips'] ?? [], $match);
        $tips = $this->annotateWithGeminiConsensus($tips, $match, $homeStats, $awayStats, $h2h, $statsContext);

        $primaryOutcome = ! empty($tips) ? $tips[0]['market']     : ($groq['predicted_outcome'] ?? $existing?->predicted_outcome ?? 'Competitive Match');
        $primaryConf    = ! empty($tips) ? $tips[0]['confidence'] : ($existing?->confidence ?? null);

        $data = [
            'home_win_prob'     => $groq['home_win'],
            'draw_prob'         => $groq['draw'],
            'away_win_prob'     => $groq['away_win'],
            'over_25_prob'      => $groq['over_25'],
            'btts_prob'         => $groq['btts'],
            'predicted_outcome' => $primaryOutcome,
            'tips'              => $tips,
            'confidence'        => $primaryConf,
            'analysis'          => $groq['analysis'],
            'has_lineup'        => true,
        ];

        $homeXgBase = $this->clampXg($this->attackStrength($homeStats) * $this->defenseWeakness($awayStats) * self::HOME_ADVANTAGE);
        $awayXgBase = $this->clampXg($this->attackStrength($awayStats) * $this->defenseWeakness($homeStats));

        // Refine xG with lineup context: more starters = more attacking threat
        [$homeXgFinal, $awayXgFinal] = $this->lineupAdjustedXg($homeXgBase, $awayXgBase, $lineupData);
        [$homeXgFinal, $awayXgFinal] = $this->h2hXgCalibration($h2h, $homeXgFinal, $awayXgFinal);

        $likelyScores = $this->topScorelines($homeXgFinal, $awayXgFinal);
        $data['likely_scores'] = $likelyScores;
        $data['market_board']  = MarketEngine::fromExpectedGoals($homeXgFinal, $awayXgFinal);

        if ($existing) {
            $existing->update($data);
        } else {
            Prediction::create(array_merge(['match_id' => $match->id], $data));
        }

        \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->endOfDay());

        return true;
    }

    /**
     * Returns the top MAX_DAILY_PICKS matches for today, sorted by league
     * importance. Only top-competition fixtures qualify.
     */
    public function upcomingMatches(): EloquentCollection
    {
        $order  = implode(',', $this->leaguePriorityIds());
        $tz     = config('app.timezone');
        $today  = now($tz)->startOfDay();
        $cutoff = now($tz)->endOfDay();

        // FIELD() returns 0 for IDs not in the list — without correction, unlisted
        // African domestic leagues sort FIRST (0 < 1). IF(...) remaps 0 → 9999
        // so unlisted leagues always sort after all explicitly ranked leagues.
        return FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->whereNotIn('status', self::EXCLUDED_UPCOMING_STATUSES)
            ->whereBetween('match_time', [$today, $cutoff])
            ->orderByRaw("IF(FIELD(league_id, {$order}) = 0, 9999, FIELD(league_id, {$order}))")
            ->orderBy('match_time')
            ->limit(self::MAX_DAILY_PICKS)
            ->get();
    }

    public function generateForUpcomingMatches(): Collection
    {
        // One match throwing must never abort the whole daily run — log it and skip.
        return $this->upcomingMatches()
            ->map(function (FootballMatch $m): ?Prediction {
                try {
                    return $this->generateForMatch($m);
                } catch (\Throwable $e) {
                    Log::error('Prediction generation failed for a match; skipping.', [
                        'match_id'  => $m->id,
                        'home_team' => $m->home_team,
                        'away_team' => $m->away_team,
                        'message'   => $e->getMessage(),
                    ]);

                    return null;
                }
            })
            ->filter()
            ->values();
    }

    /**
     * @param  CarbonInterface|null  $date  If provided, returns predictions for that date only
     *                                       (browse archive). If null, today's upcoming matches.
     */
    public function allPredictions(?CarbonInterface $date = null, int $page = 1): array
    {
        $tz      = config('app.timezone');
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;

        if ($date !== null) {
            $start = $date->copy()->startOfDay();
            $end   = $date->copy()->endOfDay();

            $total = Prediction::query()
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$start, $end]))
                ->count();

            $predictions = Prediction::query()
                ->with('match')
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$start, $end]))
                ->orderByDesc('confidence')
                ->skip($offset)->take($perPage)
                ->get();

            $this->autoResolveCollection($predictions);

            return [
                'data'    => $predictions->values()->map(fn (Prediction $p) => $this->formatPrediction($p))->all(),
                'meta'    => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'has_more' => ($offset + $perPage) < $total],
            ];
        }

        $today = CarbonImmutable::now($tz)->startOfDay();

        $baseQuery = Prediction::query()
            ->whereHas('match', fn ($q) => $q->where('match_time', '>=', $today));

        $total = $baseQuery->count();

        $predictions = Prediction::query()
            ->with('match')
            ->whereHas('match', fn ($q) => $q->where('match_time', '>=', $today))
            ->orderByDesc('confidence')
            ->skip($offset)->take($perPage)
            ->get();

        if ($predictions->isEmpty() && $page === 1) {
            $total = Prediction::query()->count();
            $predictions = Prediction::query()
                ->with('match')
                ->latest('created_at')
                ->skip($offset)->take($perPage)
                ->get();
        }

        $this->autoResolveCollection($predictions);

        return [
            'data'    => $predictions->values()->map(fn (Prediction $p) => $this->formatPrediction($p))->all(),
            'meta'    => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'has_more' => ($offset + $perPage) < $total],
        ];
    }

    /**
     * Score today's AI-analyzed predictions and mark the top 3 as daily picks.
     * Returns the 3 selected Prediction models ordered by rank.
     */
    public function selectDailyPicks(): EloquentCollection
    {
        // Always use Lagos for the "today" boundary so picks reset at WAT midnight
        // regardless of where the server is running.
        $today  = now('Africa/Lagos')->startOfDay();
        $cutoff = now('Africa/Lagos')->endOfDay();

        // Fetch candidates BEFORE clearing so existing picks survive if nothing qualifies.
        $minConfidence       = $this->adaptive->minimumConfidenceThreshold();
        $coldMarkets         = $this->adaptive->coldMarkets();
        $excludedInSelection = ['CANC', 'PST', 'ABD', 'AWD', 'WO'];
        $excludedOutcomes    = ['Competitive Match', 'Draw'];
        $europeanIds         = LeagueCoverage::topEuropean();
        $euroMinConf         = max(55, $minConfidence - 5);

        $candidates = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->whereNotNull('predicted_outcome')
            ->whereNotIn('predicted_outcome', $excludedOutcomes)
            ->where(fn ($q) => $q
                ->where(fn ($inner) => $inner
                    ->whereHas('match', fn ($m) => $m->whereIn('league_id', $europeanIds))
                    ->where('confidence', '>=', $euroMinConf)
                )
                ->orWhere(fn ($inner) => $inner
                    ->whereHas('match', fn ($m) => $m->whereNotIn('league_id', $europeanIds))
                    ->where('confidence', '>=', $minConfidence)
                )
            )
            ->whereHas('match', fn ($q) => $q
                ->where(fn ($w) => LeagueCoverage::scopeCovered($w))
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excludedInSelection)
            )
            ->get();

        if ($candidates->isEmpty()) {
            // No qualifying predictions at all — clear stale picks so we don't
            // keep showing yesterday's or low-quality picks as if they're fresh.
            Prediction::query()
                ->where('is_daily_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->update(['is_daily_pick' => false, 'pick_rank' => null]);
            return new EloquentCollection();
        }

        // Clear existing today's picks now that we know new ones are available.
        Prediction::query()
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->update(['is_daily_pick' => false, 'pick_rank' => null]);

        $accuracyWeights = $this->getMarketAccuracyWeights();

        $scored = $candidates->map(function (Prediction $p) use ($accuracyWeights, $coldMarkets) {
            $tips         = is_array($p->tips) ? $p->tips : [];
            $geminiAgrees = $tips[0]['gemini_agrees'] ?? null;

            // Hard exclude: Gemini analysed with full stats and explicitly disagreed.
            // null means Gemini wasn't configured or tip pre-dates dual-AI — still eligible.
            if ($geminiAgrees === false) {
                return null;
            }

            $hw  = (float) $p->home_win_prob;
            $d   = (float) $p->draw_prob;
            $aw  = (float) $p->away_win_prob;

            $outcome = (string) $p->predicted_outcome;

            $probs = [$hw, $d, $aw];
            rsort($probs);
            $gap    = $probs[0] - $probs[1];
            $aiConf = (int) ($p->confidence ?? 0);

            $tierBonus = in_array((int) $p->match?->league_id, LeagueCoverage::topEuropean(), true) ? 8 : 0;
            $score = $aiConf + ($gap * 0.3) + $tierBonus;

            // Apply historical accuracy multiplier for this market type.
            $accuracy = $accuracyWeights[$outcome] ?? 1.0;
            $score    = $score * $accuracy;

            // Cold-market penalty: down-weight when this market type has been
            // in a losing streak (< 40% win rate over last 14 days).
            if (in_array($outcome, $coldMarkets, true)) {
                $score *= 0.60;
            }

            return [
                'prediction' => $p,
                'score'      => $score,
                'tip_type'   => mb_strtolower($outcome),
                'gap'        => $gap,
                'ai_conf'    => $aiConf,
            ];
        })
        ->filter(fn ($s) => $s !== null && $s['ai_conf'] >= (
            in_array((int) $s['prediction']->match?->league_id, LeagueCoverage::topEuropean(), true)
                ? $euroMinConf
                : $minConfidence
        ))
        ->sortByDesc('score');

        // Tier-first selection: European/global leagues are always preferred over
        // African domestic leagues regardless of confidence scores.
        // Only backfill with non-European picks if we can't reach 3 from European.
        $europeanIds     = LeagueCoverage::topEuropean();
        $europeanScored  = $scored->filter(fn ($s) => in_array((int) $s['prediction']->match?->league_id, $europeanIds, true));
        $nonEuroScored   = $scored->reject(fn ($s) => in_array((int) $s['prediction']->match?->league_id, $europeanIds, true));

        $picks = collect();
        $used  = [];

        foreach ($europeanScored as $item) {
            if ($picks->count() >= 3) break;
            if (! in_array($item['tip_type'], $used, true)) {
                $picks->push($item);
                $used[] = $item['tip_type'];
            }
        }

        // Backfill with non-European only if European pool didn't reach 3
        foreach ($nonEuroScored as $item) {
            if ($picks->count() >= 3) break;
            if (! in_array($item['tip_type'], $used, true)) {
                $picks->push($item);
                $used[] = $item['tip_type'];
            }
        }

        // Persist
        $picks->each(function ($item, int $idx) {
            $item['prediction']->update([
                'is_daily_pick' => true,
                'pick_rank'     => $idx + 1,
            ]);
        });

        return $picks->map(fn ($item) => $item['prediction'])->values()->pipe(
            fn ($col) => new EloquentCollection($col->all())
        );
    }

    /**
     * Select up to 5 predictions for today where all 3 AIs independently agree
     * on "Draw" as the primary outcome. Stored as is_draw_pick / draw_rank.
     */
    public function selectDrawPicks(): EloquentCollection
    {
        $today    = now('Africa/Lagos')->startOfDay();
        $cutoff   = now('Africa/Lagos')->endOfDay();
        $excluded = ['CANC', 'PST', 'ABD', 'AWD', 'WO'];

        // Primary pool: AI explicitly recommended Draw at ≥60% confidence
        $primary = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->where('predicted_outcome', 'Draw')
            ->where('confidence', '>=', 60)
            ->whereHas('match', fn ($q) => $q
                ->where(fn ($w) => LeagueCoverage::scopeCovered($w))
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excluded)
            )
            ->get()
            ->filter(function (Prediction $p): bool {
                $tips = is_array($p->tips) ? $p->tips : [];
                return ($tips[0]['gemini_agrees'] ?? null) !== false;
            });

        // Secondary pool: draw_prob ≥ 45% with composite draw signals ≥ 3.
        // Lowered from 55% — when 3+ independent draw indicators align the
        // composite score requirement is strong enough to justify the lower
        // Poisson threshold. Draws are structurally underrepresented otherwise.
        $secondary = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->where('draw_prob', '>=', 45)
            ->where('confidence', '>=', 45)  // relaxed threshold for statistically strong draws
            ->whereHas('match', fn ($q) => $q
                ->where(fn ($w) => LeagueCoverage::scopeCovered($w))
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excluded)
            )
            ->get()
            ->filter(function (Prediction $p): bool {
                $tips = is_array($p->tips) ? $p->tips : [];
                // Hard-block only when AIs explicitly conflict (all other AIs disagree
                // with Groq's outcome). 'speculative' = Groq was uncertain but no one
                // was called yet — draw_prob (Poisson) is still reliable in that case.
                $agreementLevel = $tips[0]['agreement_level'] ?? 'unverified';
                if ($agreementLevel === 'conflict') return false;
                // Require ≥3 draw composite indicators to maintain quality bar
                $h2h = $this->headToHead(
                    $p->match?->home_team ?? '',
                    $p->match?->away_team ?? '',
                    $p->match?->match_time ?? now(),
                    (int) $p->match_id,
                );
                return $this->drawCompositeScore($p, $h2h) >= 3;
            });

        // Merge: primary first, then secondary, deduplicated by match_id
        $candidates = $primary->merge($secondary)
            ->unique('match_id')
            ->sortByDesc(fn (Prediction $p) => (in_array((int) $p->match?->league_id, LeagueCoverage::topEuropean(), true) ? 1000 : 0) + (int) $p->draw_prob)
            ->take(5)
            ->values();

        if ($candidates->isEmpty()) {
            return Prediction::query()
                ->where('is_draw_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('draw_rank')->get();
        }

        Prediction::query()
            ->where('is_draw_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->update(['is_draw_pick' => false, 'draw_rank' => null]);

        $candidates->each(function (Prediction $p, int $idx) {
            $p->update(['is_draw_pick' => true, 'draw_rank' => $idx + 1]);
        });

        return new EloquentCollection($candidates->all());
    }

    /**
     * Select up to 5 predictions for today where all 3 AIs independently agree
     * on "Both Teams Score" (GG) as the primary outcome.
     */
    public function selectGGPicks(): EloquentCollection
    {
        $today    = now('Africa/Lagos')->startOfDay();
        $cutoff   = now('Africa/Lagos')->endOfDay();
        $excluded = ['CANC', 'PST', 'ABD', 'AWD', 'WO'];

        $ggOutcomes = ['Both Teams Score', 'Both Teams Score (GG)'];

        // Primary: AI explicitly predicted GG with ≥60% confidence and no AI conflict
        $primary = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->whereIn('predicted_outcome', $ggOutcomes)
            ->where('confidence', '>=', 60)
            ->whereHas('match', fn ($q) => $q
                ->where(fn ($w) => LeagueCoverage::scopeCovered($w))
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excluded)
            )
            ->get()
            ->filter(function (Prediction $p): bool {
                $tips = is_array($p->tips) ? $p->tips : [];
                return ($tips[0]['gemini_agrees'] ?? null) !== false;
            });

        // Secondary: Poisson btts_prob ≥ 68% — reliable regardless of what the AI
        // named as its primary outcome (Groq often labels these "Over 2.5 Goals").
        // Same pattern as selectOver15Picks() which gates on over_15_prob alone.
        $secondary = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->whereNotNull('btts_prob')
            ->where('btts_prob', '>=', 68)
            ->where('confidence', '>=', 55)
            ->whereHas('match', fn ($q) => $q
                ->where(fn ($w) => LeagueCoverage::scopeCovered($w))
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excluded)
            )
            ->get();

        $candidates = $primary->merge($secondary)
            ->unique('match_id')
            ->sortByDesc(fn (Prediction $p) =>
                (in_array((int) $p->match?->league_id, LeagueCoverage::topEuropean(), true) ? 1000 : 0)
                + (int) max($p->confidence, (float) ($p->btts_prob ?? 0))
            )
            ->take(5)
            ->values();

        if ($candidates->isEmpty()) {
            return Prediction::query()
                ->where('is_gg_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('gg_rank')->get();
        }

        Prediction::query()
            ->where('is_gg_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->update(['is_gg_pick' => false, 'gg_rank' => null]);

        $candidates->each(function (Prediction $p, int $idx) {
            $p->update(['is_gg_pick' => true, 'gg_rank' => $idx + 1]);
        });

        return new EloquentCollection($candidates->all());
    }

    /**
     * Select up to 5 Over 1.5 Goals picks for today.
     * Gates on a very high Poisson probability (≥82%) — at that level the
     * signal is robust regardless of what the AI named as its primary outcome.
     */
    public function selectOver15Picks(): EloquentCollection
    {
        $today  = now('Africa/Lagos')->startOfDay();
        $cutoff = now('Africa/Lagos')->endOfDay();
        $excluded = ['CANC', 'PST', 'ABD', 'AWD', 'WO'];

        // Fetch candidates BEFORE clearing so existing picks survive if nothing qualifies.
        $candidates = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->whereNotNull('over_15_prob')
            ->where('over_15_prob', '>=', 82)
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excluded)
            )
            ->get()
            ->sortByDesc(fn (Prediction $p) => (in_array((int) $p->match?->league_id, LeagueCoverage::topEuropean(), true) ? 1000 : 0) + (float) $p->over_15_prob)
            ->take(5)
            ->values();

        if ($candidates->isEmpty()) {
            return Prediction::query()
                ->where('is_over15_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('over15_rank')->get();
        }

        Prediction::query()
            ->where('is_over15_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->update(['is_over15_pick' => false, 'over15_rank' => null]);

        $candidates->each(function (Prediction $p, int $idx) {
            $p->update(['is_over15_pick' => true, 'over15_rank' => $idx + 1]);
        });

        return new EloquentCollection($candidates->all());
    }

    /**
     * Select up to 5 Over 2.5 Goals picks for today.
     * Uses Poisson over_25_prob ≥65% — meaningful edge over the ~53% base rate.
     * Gemini must not have explicitly disagreed on a goal-heavy outcome.
     */
    public function selectOver25Picks(): EloquentCollection
    {
        $today  = now('Africa/Lagos')->startOfDay();
        $cutoff = now('Africa/Lagos')->endOfDay();
        $excluded = ['CANC', 'PST', 'ABD', 'AWD', 'WO'];

        // Fetch candidates BEFORE clearing so existing picks survive if nothing qualifies.
        $candidates = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->whereNotNull('over_25_prob')
            ->where('over_25_prob', '>=', 60)
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excluded)
            )
            ->get()
            ->filter(function (Prediction $p): bool {
                $tips = is_array($p->tips) ? $p->tips : [];
                return ($tips[0]['gemini_agrees'] ?? null) !== false;
            })
            ->sortByDesc(fn (Prediction $p) => (in_array((int) $p->match?->league_id, LeagueCoverage::topEuropean(), true) ? 1000 : 0) + (float) $p->over_25_prob)
            ->take(5)
            ->values();

        if ($candidates->isEmpty()) {
            return Prediction::query()
                ->where('is_over25_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('over25_rank')->get();
        }

        Prediction::query()
            ->where('is_over25_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->update(['is_over25_pick' => false, 'over25_rank' => null]);

        $candidates->each(function (Prediction $p, int $idx) {
            $p->update(['is_over25_pick' => true, 'over25_rank' => $idx + 1]);
        });

        return new EloquentCollection($candidates->all());
    }

    /**
     * Select up to 5 "A Team to Score 3+ Goals" picks for today.
     * For the "A Team to Score 3+" YES/NO market, we predict NO on the team
     * with the lowest Poisson P(goals ≥ 3) — i.e. the team we are most confident
     * will NOT score 3 goals. We require that team's probability ≤ 8%.
     * Stored with team3plus_label = 'Home 3+', 'Away 3+', 'Home 2+', or 'Away 2+'.
     * We predict NO on both the 2+ and 3+ markets and pick the single safest NO:
     * — 3+ NO (≤15% threshold): team predicted not to score 3 or more goals.
     * — 2+ NO (≤25% threshold): team predicted not to score 2 or more goals (fallback).
     * Lower Poisson probability = more confident NO = preferred pick.
     */
    public function selectTeam3PlusPicks(): EloquentCollection
    {
        $today  = now('Africa/Lagos')->startOfDay();
        $cutoff = now('Africa/Lagos')->endOfDay();
        $excluded = ['CANC', 'PST', 'ABD', 'AWD', 'WO'];

        $candidates = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->whereNotNull('home_3plus_prob')
            ->whereNotNull('away_3plus_prob')
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excluded)
            )
            ->get()
            ->map(function (Prediction $p): ?array {
                $home3 = (float) ($p->home_3plus_prob ?? 0);
                $away3 = (float) ($p->away_3plus_prob ?? 0);
                $home2 = (float) ($p->home_2plus_prob ?? 0);
                $away2 = (float) ($p->away_2plus_prob ?? 0);

                // Collect all qualifying NO picks; lower probability = more confident NO
                $options = [];
                if ($home3 > 0 && $home3 <= 15.0) $options[] = ['label' => 'Home 3+', 'prob' => $home3];
                if ($away3 > 0 && $away3 <= 15.0) $options[] = ['label' => 'Away 3+', 'prob' => $away3];
                // 2+ NO is a fallback: only surface when 3+ doesn't qualify but 2+ is very low
                if ($home2 > 0 && $home2 <= 25.0 && ($home3 <= 0 || $home3 > 15.0)) {
                    $options[] = ['label' => 'Home 2+', 'prob' => $home2];
                }
                if ($away2 > 0 && $away2 <= 25.0 && ($away3 <= 0 || $away3 > 15.0)) {
                    $options[] = ['label' => 'Away 2+', 'prob' => $away2];
                }

                if (empty($options)) return null;

                usort($options, fn ($a, $b) => $a['prob'] <=> $b['prob']);
                $best = $options[0];

                return ['prediction' => $p, 'label' => $best['label'], 'prob' => $best['prob']];
            })
            ->filter()
            ->sortBy(fn ($item) =>
                (in_array((int) $item['prediction']->match?->league_id, LeagueCoverage::topEuropean(), true) ? -1000 : 0)
                + $item['prob']
            )
            ->take(5)
            ->values();

        if ($candidates->isEmpty()) {
            return Prediction::query()
                ->where('is_team3plus_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('team3plus_rank')->get();
        }

        Prediction::query()
            ->where('is_team3plus_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->update(['is_team3plus_pick' => false, 'team3plus_rank' => null, 'team3plus_label' => null]);

        $candidates->each(function (array $item, int $idx) {
            $item['prediction']->update([
                'is_team3plus_pick' => true,
                'team3plus_rank'    => $idx + 1,
                'team3plus_label'   => $item['label'],
            ]);
        });

        return $candidates->map(fn ($i) => $i['prediction'])->pipe(
            fn ($col) => new EloquentCollection($col->all())
        );
    }

    /**
     * Select up to 5 correct score picks for today.
     * Picks are the highest-confidence games that have likely_scores computed.
     * Top European league games are prioritised over domestic leagues at equal confidence.
     */
    public function selectCorrectScorePicks(): EloquentCollection
    {
        $today    = now('Africa/Lagos')->startOfDay();
        $cutoff   = now('Africa/Lagos')->endOfDay();
        $excluded = ['CANC', 'PST', 'ABD', 'AWD', 'WO'];

        $candidates = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->whereNotNull('likely_scores')
            ->where('confidence', '>=', 60)
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excluded)
            )
            ->get()
            ->filter(fn (Prediction $p) => ! empty($p->likely_scores))
            ->sortByDesc(fn (Prediction $p) =>
                (in_array((int) $p->match?->league_id, LeagueCoverage::topEuropean(), true) ? 1000 : 0)
                + (int) ($p->confidence ?? 0)
            )
            ->take(5)
            ->values();

        if ($candidates->isEmpty()) {
            return Prediction::query()
                ->where('is_correct_score_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('correct_score_rank')->get();
        }

        Prediction::query()
            ->where('is_correct_score_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->update(['is_correct_score_pick' => false, 'correct_score_rank' => null]);

        $candidates->each(function (Prediction $p, int $idx) {
            $p->update(['is_correct_score_pick' => true, 'correct_score_rank' => $idx + 1]);
        });

        return new EloquentCollection($candidates->all());
    }

    /**
     * Select up to 5 Double Chance picks for today.
     * Label '1X' = Home Win or Draw (home_win_prob + draw_prob).
     * Label '2X' = Away Win or Draw (away_win_prob + draw_prob).
     * For each match we take whichever label has the higher combined probability,
     * require ≥ 72%, then rank by probability descending and take the top 5.
     */
    public function selectDoubleChancePicks(): EloquentCollection
    {
        $today    = now('Africa/Lagos')->startOfDay();
        $cutoff   = now('Africa/Lagos')->endOfDay();
        $excluded = ['CANC', 'PST', 'ABD', 'AWD', 'WO'];

        $allScoredPredictions = Prediction::query()
            ->with('match')
            ->whereNotNull('home_win_prob')
            ->whereNotNull('draw_prob')
            ->whereNotNull('away_win_prob')
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excluded)
            )
            ->get()
            ->map(function (Prediction $p): array {
                $dc1x = (float) $p->home_win_prob + (float) $p->draw_prob;
                $dc2x = (float) $p->away_win_prob + (float) $p->draw_prob;
                $bestDc = $dc1x >= $dc2x ? $dc1x : $dc2x;
                $label  = $dc1x >= $dc2x ? '1X' : '2X';
                return ['prediction' => $p, 'label' => $label, 'prob' => $bestDc, 'dc1x' => $dc1x, 'dc2x' => $dc2x];
            });

        // Primary: >= 72% DC confidence
        $candidates = $allScoredPredictions
            ->filter(fn ($item) => $item['prob'] >= 72.0)
            ->sortByDesc(fn ($item) =>
                (in_array((int) $item['prediction']->match?->league_id, LeagueCoverage::topEuropean(), true) ? 1000 : 0)
                + $item['prob']
            )
            ->take(5)
            ->values();

        // Fallback: best 3 by DC score with minimum 60% (ensures picks always exist)
        if ($candidates->isEmpty()) {
            $candidates = $allScoredPredictions
                ->filter(fn ($item) => $item['prob'] >= 60.0)
                ->sortByDesc(fn ($item) =>
                    (in_array((int) $item['prediction']->match?->league_id, LeagueCoverage::topEuropean(), true) ? 1000 : 0)
                    + $item['prob']
                )
                ->take(3)
                ->values();
        }

        if ($candidates->isEmpty()) {
            return Prediction::query()
                ->where('is_double_chance_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('double_chance_rank')->get();
        }

        Prediction::query()
            ->where('is_double_chance_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->update(['is_double_chance_pick' => false, 'double_chance_rank' => null, 'double_chance_label' => null]);

        $candidates->each(function (array $item, int $idx) {
            $item['prediction']->update([
                'is_double_chance_pick' => true,
                'double_chance_rank'    => $idx + 1,
                'double_chance_label'   => $item['label'],
            ]);
        });

        return $candidates->map(fn ($i) => $i['prediction'])->pipe(
            fn ($col) => new EloquentCollection($col->all())
        );
    }

    /**
     * Settle any finished predictions that the scheduler hasn't resolved yet.
     * Runs inline so API responses always include up-to-date was_correct values.
     */
    private function autoResolveCollection(EloquentCollection $predictions): void
    {
        $finished = ['FT', 'AET', 'PEN'];
        foreach ($predictions as $p) {
            if ($p->was_correct !== null) continue;
            if (! in_array($p->match?->status, $finished, true)) continue;
            if ($p->match?->home_score === null) continue;

            $result = PickHelpers::resolveOutcome($p);
            if ($result !== null) {
                $p->update(['was_correct' => $result]);
                $p->was_correct = $result;
            }
        }
    }

    private function formGuide(string $team, CarbonInterface $before, int $excludeMatchId): array
    {
        return FootballMatch::query()
            ->where('id', '!=', $excludeMatchId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->where('match_time', '<', $before)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->where(fn ($q) => $q->where('home_team', $team)->orWhere('away_team', $team))
            ->latest('match_time')
            ->limit(5)
            ->get()
            ->map(function (FootballMatch $m) use ($team): string {
                $isHome   = $m->home_team === $team;
                $teamGoals = $isHome ? (int) $m->home_score : (int) $m->away_score;
                $oppGoals  = $isHome ? (int) $m->away_score : (int) $m->home_score;
                return $teamGoals > $oppGoals ? 'W' : ($teamGoals < $oppGoals ? 'L' : 'D');
            })
            ->reverse()
            ->values()
            ->toArray();
    }

    public function predictionForMatch(int $matchId): ?array
    {
        $p = Prediction::query()->with('match')->where('match_id', $matchId)->first();
        return $p ? $this->formatPrediction($p) : null;
    }

    // ──────────────────────────────────────────────────────────────
    //  Poisson engine
    // ──────────────────────────────────────────────────────────────

    private function poissonProbabilities(float $homeXg, float $awayXg): array
    {
        // Dixon-Coles rho correction (ρ ≈ -0.13).
        // Basic Poisson underestimates 0-0 and 1-1 draws. The τ function applies
        // a small adjustment to four scorelines to correct for goal correlation,
        // directly improving draw prediction accuracy.
        $rho = -0.13;
        $dc  = function (int $h, int $a) use ($homeXg, $awayXg, $rho): float {
            if ($h === 0 && $a === 0) return 1 - $homeXg * $awayXg * $rho;
            if ($h === 1 && $a === 0) return 1 + $awayXg * $rho;
            if ($h === 0 && $a === 1) return 1 + $homeXg * $rho;
            if ($h === 1 && $a === 1) return 1 - $rho;
            return 1.0;
        };

        $hw = $d = $aw = $o15 = $o25 = $o35 = $btts = $tot = $h3p = $a3p = $h2p = $a2p = 0.0;

        for ($h = 0; $h <= self::MAX_GOALS_GRID; $h++) {
            $ph = $this->poisson($homeXg, $h);
            for ($a = 0; $a <= self::MAX_GOALS_GRID; $a++) {
                $p = $ph * $this->poisson($awayXg, $a) * $dc($h, $a);
                $tot += $p;
                if ($h > $a)       { $hw += $p; }
                elseif ($h === $a) { $d  += $p; }
                else               { $aw += $p; }
                $g = $h + $a;
                if ($g >= 2)  { $o15 += $p; }
                if ($g >= 3)  { $o25 += $p; }
                if ($g >= 4)  { $o35 += $p; }
                if ($h >= 1 && $a >= 1) { $btts += $p; }
                if ($h >= 2)  { $h2p += $p; }
                if ($a >= 2)  { $a2p += $p; }
                if ($h >= 3)  { $h3p += $p; }
                if ($a >= 3)  { $a3p += $p; }
            }
        }

        $hwPct  = round($hw / $tot * 100, 1);
        $dPct   = round($d  / $tot * 100, 1);
        $awPct  = round(100 - $hwPct - $dPct, 1);

        // Clean sheet: probability away team scores 0 (home clean sheet) and vice versa
        $homeClean = round($this->poisson($awayXg, 0) * 100, 1); // away scores 0 = home clean sheet
        $awayClean = round($this->poisson($homeXg, 0) * 100, 1); // home scores 0 = away clean sheet

        return [
            'home_win'         => $hwPct,
            'draw'             => $dPct,
            'away_win'         => $awPct,
            'over_15'          => round($o15  / $tot * 100, 1),
            'over_25'          => round($o25  / $tot * 100, 1),
            'over_35'          => round($o35  / $tot * 100, 1),
            'btts'             => round($btts / $tot * 100, 1),
            'home_clean_sheet' => $homeClean,
            'away_clean_sheet' => $awayClean,
            'home_2plus'       => round($h2p  / $tot * 100, 1),
            'away_2plus'       => round($a2p  / $tot * 100, 1),
            'home_3plus'       => round($h3p  / $tot * 100, 1),
            'away_3plus'       => round($a3p  / $tot * 100, 1),
        ];
    }

    private function poisson(float $lambda, int $k): float
    {
        static $cache = [1, 1, 2, 6, 24, 120, 720, 5040, 40320];
        return exp(-$lambda) * ($lambda ** $k) / ($cache[$k] ?? (float) array_product(range(1, $k)));
    }

    // ──────────────────────────────────────────────────────────────
    //  Verdict
    // ──────────────────────────────────────────────────────────────

    /**
     * Score every supported betting market by its edge over a neutral baseline,
     * then return whichever has the strongest edge. Falls back to "Competitive
     * Match" when no market has a meaningful edge.
     *
     * Baselines (where "neutral" sits):
     *   1X2          → 33.3% (3 outcomes)
     *   double-chance → 66.7% (2 of 3 outcomes)
     *   yes/no markets → 50%
     */
    private function verdict(
        float $hw, float $d, float $aw,
        float $over15, float $over25, float $over35,
        float $btts
    ): string {
        $candidates = [
            // 1X2
            'Home Win'        => $hw - 33.3,
            'Away Win'        => $aw - 33.3,

            // Goal-line
            'Over 1.5 Goals'  => $over15 - 50,
            'Under 1.5 Goals' => 50 - $over15,
            'Over 2.5 Goals'  => $over25 - 50,
            'Under 2.5 Goals' => 50 - $over25,
            'Over 3.5 Goals'  => $over35 - 50,
            'Under 3.5 Goals' => 50 - $over35,

            // BTTS
            'Both Teams Score (GG)'    => $btts - 50,
            'No Both Teams Score (NG)' => 50 - $btts,

            // Double-chance
            'Home or Draw (1X)' => ($hw + $d)  - 66.7,
            'Draw or Away (X2)' => ($d  + $aw) - 66.7,
            'Home or Away (12)' => ($hw + $aw) - 66.7,

            // Draw No Bet (removes draw, treated as 2-way 50% baseline)
            'Draw No Bet - Home' => $hw - 50,
            'Draw No Bet - Away' => $aw - 50,
        ];

        arsort($candidates);
        $top  = array_key_first($candidates);
        $edge = $candidates[$top];

        return $edge >= 8 ? $top : 'Competitive Match';
    }

    // ──────────────────────────────────────────────────────────────
    //  Team stats helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Full extended stats for a team over the last RECENT_MATCH_LIMIT matches.
     * Returns everything we need for both Poisson (goals_scored, goals_conceded,
     * matches_played) and the AI prompt (rates, splits, detailed form).
     */
    public function extendedTeamStats(string $team, CarbonInterface $before, int $excludedMatchId): array
    {
        $matches = FootballMatch::query()
            ->where('id', '!=', $excludedMatchId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->where('match_time', '<', $before)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->where(fn ($q) => $q->where('home_team', $team)->orWhere('away_team', $team))
            ->latest('match_time')
            ->limit(self::RECENT_MATCH_LIMIT)
            ->get();

        $wins = $draws = $losses = 0;
        $cleanSheets = $failedToScore = $bttsCount = $over25Count = 0;
        $scored = $conceded = $htScored = $htConceded = 0;
        $homeMatches = $awayMatches = 0;
        $homeScored  = $homeConceded = $awayScored = $awayConceded = 0;
        $formDetailed  = [];
        $streak2plus   = 0;
        $streak3plus   = 0;
        $inStreak2plus = true;
        $inStreak3plus = true;

        foreach ($matches as $m) {
            $isHome = $m->home_team === $team;
            $gf     = $isHome ? (int) $m->home_score : (int) $m->away_score;
            $ga     = $isHome ? (int) $m->away_score : (int) $m->home_score;
            $htGf   = $isHome ? (int) ($m->home_score_ht ?? 0) : (int) ($m->away_score_ht ?? 0);
            $htGa   = $isHome ? (int) ($m->away_score_ht ?? 0) : (int) ($m->home_score_ht ?? 0);

            $scored    += $gf; $conceded  += $ga;
            $htScored  += $htGf; $htConceded += $htGa;

            if ($gf > $ga)       $wins++;
            elseif ($gf === $ga) $draws++;
            else                 $losses++;

            if ($ga === 0)            $cleanSheets++;
            if ($gf === 0)            $failedToScore++;
            if ($gf >= 1 && $ga >= 1) $bttsCount++;
            if ($gf + $ga >= 3)       $over25Count++;

            if ($isHome) { $homeMatches++; $homeScored += $gf;  $homeConceded += $ga; }
            else         { $awayMatches++; $awayScored += $gf;  $awayConceded += $ga; }

            $result   = $gf > $ga ? 'W' : ($gf < $ga ? 'L' : 'D');
            $opponent = $isHome ? $m->away_team : $m->home_team;
            $venue    = $isHome ? 'H' : 'A';
            $date     = $m->match_time?->format('d M');
            $formDetailed[] = "{$result}({$gf}-{$ga}) {$venue} {$date} vs {$opponent}";

            // Consecutive scoring streak counters (matches are newest-first)
            if ($inStreak2plus && $gf >= 2) $streak2plus++; else $inStreak2plus = false;
            if ($inStreak3plus && $gf >= 3) $streak3plus++; else $inStreak3plus = false;
        }

        $n = max(1, $matches->count());

        return [
            // Backward-compatible for attackStrength/defenseWeakness
            'matches_played' => $matches->count(),
            'goals_scored'   => $scored,
            'goals_conceded' => $conceded,
            // Extended
            'wins'           => $wins,
            'draws'          => $draws,
            'losses'         => $losses,
            'clean_sheets'   => $cleanSheets,
            'failed_to_score'=> $failedToScore,
            'btts_count'     => $bttsCount,
            'over25_count'   => $over25Count,
            'ht_scored'      => $htScored,
            'ht_conceded'    => $htConceded,
            'home_matches'   => $homeMatches,
            'home_scored'    => $homeScored,
            'home_conceded'  => $homeConceded,
            'away_matches'   => $awayMatches,
            'away_scored'    => $awayScored,
            'away_conceded'  => $awayConceded,
            'form_detailed'  => $formDetailed, // newest first
            'gpg'            => round($scored  / $n, 2),
            'cpg'            => round($conceded / $n, 2),
            'ht_gpg'         => round($htScored   / $n, 2),
            'ht_cpg'         => round($htConceded  / $n, 2),
            'streak_2plus'   => $streak2plus,
            'streak_3plus'   => $streak3plus,
        ];
    }

    /**
     * Last 6 H2H meetings between these two sides, with outcomes from the
     * perspective of the home team in the UPCOMING match.
     */
    public function headToHead(string $homeTeam, string $awayTeam, CarbonInterface $before, int $excludedMatchId): array
    {
        $matches = FootballMatch::query()
            ->where('id', '!=', $excludedMatchId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->where('match_time', '<', $before)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('home_team', $homeTeam)->where('away_team', $awayTeam))
                ->orWhere(fn ($w) => $w->where('home_team', $awayTeam)->where('away_team', $homeTeam))
            )
            ->latest('match_time')
            ->limit(6)
            ->get();

        if ($matches->isEmpty()) {
            return ['results' => [], 'home_wins' => 0, 'draws' => 0, 'away_wins' => 0, 'total' => 0];
        }

        $results  = [];
        $homeWins = $draws = $awayWins = 0;

        foreach ($matches as $m) {
            $flipped = ($m->home_team !== $homeTeam);
            $hScore  = (int) ($flipped ? $m->away_score : $m->home_score);
            $aScore  = (int) ($flipped ? $m->home_score : $m->away_score);
            $date    = $m->match_time?->format('d M Y');
            $venue   = $flipped ? "at {$awayTeam}" : "at {$homeTeam}";

            if ($hScore > $aScore)       { $homeWins++; $res = "{$homeTeam} won"; }
            elseif ($hScore === $aScore) { $draws++;    $res = 'Draw'; }
            else                         { $awayWins++; $res = "{$awayTeam} won"; }

            $results[] = "{$date} ({$venue}): {$homeTeam} {$hScore}-{$aScore} {$awayTeam} → {$res}";
        }

        return [
            'results'   => $results,
            'home_wins' => $homeWins,
            'draws'     => $draws,
            'away_wins' => $awayWins,
            'total'     => $matches->count(),
        ];
    }

    private function attackStrength(array $s): float
    {
        $n = (int) ($s['matches_played'] ?? 0);
        return $n === 0 ? self::NEUTRAL_GOALS_RATE : (float) ($s['goals_scored'] ?? 0) / $n;
    }

    private function defenseWeakness(array $s): float
    {
        $n = (int) ($s['matches_played'] ?? 0);
        return $n === 0 ? self::NEUTRAL_GOALS_RATE : max(0.20, (float) ($s['goals_conceded'] ?? 0) / $n);
    }

    // Venue-split attack/defense — used when enough split samples exist.
    // Falls back to the overall rate when the venue sample is < 3 matches.
    private function homeAttackStrength(array $s): float
    {
        return $s['home_matches'] >= 3
            ? $s['home_scored'] / $s['home_matches']
            : $this->attackStrength($s);
    }

    private function homeConceding(array $s): float
    {
        return $s['home_matches'] >= 3
            ? max(0.20, $s['home_conceded'] / $s['home_matches'])
            : $this->defenseWeakness($s);
    }

    private function awayAttackStrength(array $s): float
    {
        return $s['away_matches'] >= 3
            ? $s['away_scored'] / $s['away_matches']
            : $this->attackStrength($s);
    }

    private function awayConceding(array $s): float
    {
        return $s['away_matches'] >= 3
            ? max(0.20, $s['away_conceded'] / $s['away_matches'])
            : $this->defenseWeakness($s);
    }

    private function clampXg(float $xg): float
    {
        return round(min(self::MAX_XG, max(self::MIN_XG, $xg)), 3);
    }

    /**
     * Adjust xG values based on confirmed lineup text.
     * If the lineup mentions many key starters (e.g. 11 names found) the base
     * xG is used as-is. A partial lineup (injuries/rotations mentioned) nudges
     * xG slightly down. This keeps the Poisson probabilities realistic once
     * the actual starting XI is known rather than relying purely on season stats.
     */
    private function lineupAdjustedXg(float $homeXg, float $awayXg, string $lineupText): array
    {
        // Split into home section (first block) and away section (second block)
        $sections = preg_split('/\n\n+/', trim($lineupText), 2);

        $homeMultiplier = $this->attackMultiplierFromLineupSection($sections[0] ?? '');
        $awayMultiplier = $this->attackMultiplierFromLineupSection($sections[1] ?? '');

        return [
            $this->clampXg($homeXg * $homeMultiplier),
            $this->clampXg($awayXg * $awayMultiplier),
        ];
    }

    private function attackMultiplierFromLineupSection(string $section): float
    {
        if (blank($section)) return 1.0;

        // Lineup format: "Arsenal [4-3-3] Coach: Arteta\nStarters: 1. Raya (G), 11. Saka (F), ..."
        // Count actual position tags from the starters list
        $forwards  = preg_match_all('/\(F\)/i', $section, $m) ? count($m[0]) : 0;
        $defenders = preg_match_all('/\(D\)/i', $section, $m) ? count($m[0]) : 0;

        $multiplier = 1.0;

        // 3+ forwards → attacking intent (4-3-3, 3-4-3)
        // 1 forward  → defensive / counter setup (4-5-1, 5-4-1)
        if ($forwards >= 3) {
            $multiplier += 0.09;
        } elseif ($forwards === 1) {
            $multiplier -= 0.08;
        }

        // 5+ defenders → low-block, likely fewer goals
        if ($defenders >= 5) {
            $multiplier -= 0.07;
        }

        // Formation string adjustment (e.g. [4-3-3] → last number = attackers)
        if (preg_match('/\[(\d+(?:-\d+)+)\]/', $section, $fm)) {
            $parts = array_map('intval', explode('-', $fm[1]));
            $attackLine = (int) end($parts);
            if ($attackLine >= 3 && $forwards < 3) {
                $multiplier += 0.05; // formation says 3-up but pos tags say otherwise
            } elseif ($attackLine === 1 && $forwards > 1) {
                $multiplier -= 0.04;
            }
        }

        return max(0.82, min(1.18, $multiplier));
    }

    private function h2hXgCalibration(array $h2h, float $homeXg, float $awayXg): array
    {
        $results = $h2h['results'] ?? [];
        if (count($results) < 2) {
            return [$homeXg, $awayXg]; // Not enough history to bias
        }

        $totalHome = 0;
        $totalAway = 0;
        $count     = 0;

        foreach ($results as $result) {
            // Format: "15 Mar 2024 (at X): TeamA 2-1 TeamB → TeamA won"
            if (preg_match('/\s(\d+)-(\d+)\s/', $result, $m)) {
                $totalHome += (int) $m[1];
                $totalAway += (int) $m[2];
                $count++;
            }
        }

        if ($count === 0) {
            return [$homeXg, $awayXg];
        }

        $h2hHome = $totalHome / $count;
        $h2hAway = $totalAway / $count;

        // Blend Poisson xG (65%) with H2H historical average (35%)
        return [
            $this->clampXg($homeXg * 0.65 + $h2hHome * 0.35),
            $this->clampXg($awayXg * 0.65 + $h2hAway * 0.35),
        ];
    }

    private function topScorelines(float $homeXg, float $awayXg, int $n = 5): array
    {
        $scores = [];
        for ($h = 0; $h <= 5; $h++) {
            for ($a = 0; $a <= 5; $a++) {
                $p   = $this->poisson($homeXg, $h) * $this->poisson($awayXg, $a);
                $pct = round($p * 100, 1);
                if ($pct >= 5.0) { // Only include scores with at least 5% probability
                    $scores["{$h}-{$a}"] = $pct;
                }
            }
        }
        arsort($scores);
        $top = array_slice($scores, 0, $n, true);
        return array_map(
            fn ($score, $pct) => ['score' => $score, 'pct' => $pct],
            array_keys($top),
            array_values($top)
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Match importance detection
    // ──────────────────────────────────────────────────────────────

    /**
     * Derive contextual match importance flags from form data and timing.
     * We don't have standings, so we use form + season timing as proxies.
     *
     * Returns an array of flags and a human-readable context string for
     * inclusion in the Groq prompt.
     */
    private function matchImportanceContext(
        FootballMatch $match,
        array $homeStats,
        array $awayStats,
    ): array {
        $flags   = [];
        $context = [];
        $month   = (int) ($match->match_time?->format('n') ?? date('n'));

        // ── Late-season pressure (Apr–Jun = European seasons closing) ──
        $lateSeasonMonths = [4, 5, 6];
        if (in_array($month, $lateSeasonMonths, true)) {
            $flags[]   = 'late_season';
            $context[] = 'This is a late-season fixture — title races, relegation battles, and European qualification are likely at stake. Weigh match importance heavily.';
        }

        // ── Derby detection: team names sharing a city keyword ──
        $derbyKeywords = [
            'Manchester', 'Liverpool', 'London', 'Madrid', 'Milan', 'Rome', 'Roma',
            'Glasgow', 'Lisbon', 'Porto', 'Seville', 'Sevilla', 'Istanbul',
            'Buenos Aires', 'São Paulo', 'Rio', 'Lagos', 'Accra', 'Nairobi',
            'Barcelona', 'Turin', 'Torino', 'Dortmund', 'Gelsenkirchen',
        ];
        foreach ($derbyKeywords as $city) {
            if (stripos($match->home_team, $city) !== false && stripos($match->away_team, $city) !== false) {
                $flags[]   = 'derby';
                $context[] = "This appears to be a local derby ({$city}). Derby matches have higher draw probability than form alone suggests — intensity elevates and favourites underperform. Treat draw as a realistic outcome.";
                break;
            }
        }

        // ── Classic rivalry detection ──
        $rivalries = [
            ['Manchester United', 'Manchester City'],
            ['Arsenal', 'Tottenham'],
            ['Liverpool', 'Everton'],
            ['Real Madrid', 'Barcelona'],
            ['Real Madrid', 'Atletico Madrid'],
            ['Barcelona', 'Atletico Madrid'],
            ['Inter', 'AC Milan'],
            ['Juventus', 'Inter'],
            ['Juventus', 'AC Milan'],
            ['Roma', 'Lazio'],
            ['Napoli', 'Juventus'],
            ['Dortmund', 'Bayern'],
            ['Ajax', 'Feyenoord'],
            ['Celtic', 'Rangers'],
            ['Porto', 'Benfica'],
            ['Porto', 'Sporting'],
            ['Boca Juniors', 'River Plate'],
        ];
        $home = $match->home_team;
        $away = $match->away_team;
        foreach ($rivalries as [$a, $b]) {
            if ((stripos($home, $a) !== false && stripos($away, $b) !== false)
             || (stripos($home, $b) !== false && stripos($away, $a) !== false)) {
                $flags[]   = 'rivalry';
                $context[] = "This is a high-profile rivalry match ({$home} vs {$away}). Historical data shows draw probability is elevated in rivalry games. Do not dismiss draw as headline.";
                break;
            }
        }

        // ── Poor recent form proxy: might be relegation-threatened ──
        // A team may have zero recent matches (newly promoted, mid-transfer window
        // gap, or extendedTeamStats returned an empty shape). Guard every read.
        $homeMatches = (int) ($homeStats['matches_played'] ?? 0);
        $homeLosses  = (int) ($homeStats['losses']         ?? 0);
        $awayMatches = (int) ($awayStats['matches_played'] ?? 0);
        $awayLosses  = (int) ($awayStats['losses']         ?? 0);
        $homeLossRate = $homeMatches > 0 ? $homeLosses / $homeMatches : 0;
        $awayLossRate = $awayMatches > 0 ? $awayLosses / $awayMatches : 0;

        if ($homeLossRate >= 0.55) {
            $flags[]   = 'home_struggling';
            $context[] = "The home team is in very poor form (losing {$homeLosses} of last {$homeMatches} matches) — may be in a relegation battle, which can produce unpredictable results driven by desperation.";
        }
        if ($awayLossRate >= 0.55) {
            $flags[]   = 'away_struggling';
            $context[] = "The away team is in very poor form — may be under relegation pressure, which distorts normal form predictions.";
        }

        return [
            'flags'   => $flags,
            'context' => implode(' ', $context),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Draw composite signal
    // ──────────────────────────────────────────────────────────────

    /**
     * Calculate how many draw-favourable indicators are present for this prediction.
     * Used in selectDrawPicks() to lower the Gemini agreement threshold when
     * multiple statistical signals all point toward a draw outcome.
     *
     * Returns an integer count (0–5). Score ≥ 3 triggers the relaxed threshold.
     */
    private function drawCompositeScore(Prediction $p, array $h2h): int
    {
        $score = 0;

        // Signal 1: High Poisson draw probability
        if ((float) $p->draw_prob >= 55) $score++;

        // Signal 2: Neither team is dominant — evenly contested
        if ((float) $p->home_win_prob < 45 && (float) $p->away_win_prob < 45) $score++;

        // Signal 3: Both teams have similar strength (small 1X2 gap)
        $hwAbs = abs((float) $p->home_win_prob - (float) $p->away_win_prob);
        if ($hwAbs <= 10) $score++;

        // Signal 4: H2H draw rate above 33%
        if ($h2h['total'] >= 3 && ($h2h['draws'] / $h2h['total']) >= 0.33) $score++;

        // Signal 5: Low BTTS and low Over 2.5 — tight defensive match
        if ((float) ($p->btts_prob ?? 50) < 45 && (float) ($p->over_25_prob ?? 50) < 45) $score++;

        return $score;
    }

    // ──────────────────────────────────────────────────────────────
    //  Formatting
    // ──────────────────────────────────────────────────────────────

    private function matchDisplayStatus(FootballMatch $match): string
    {
        if (in_array($match->status, ['1H', '2H', 'ET', 'BT', 'P', 'LIVE'], true) && $match->elapsed !== null) {
            return $match->elapsed . "'";
        }
        return $match->status;
    }

    private function formatPrediction(Prediction $p): array
    {
        $hw = (float) $p->home_win_prob;
        $d  = (float) $p->draw_prob;
        $aw = (float) $p->away_win_prob;

        // Derive confidence from probability spread
        $sorted = [$hw, $d, $aw];
        rsort($sorted);
        $gap = $sorted[0] - $sorted[1];
        $confidence = $gap >= 20 ? 'HIGH' : ($gap >= 10 ? 'MEDIUM' : 'LOW');

        $isAi = !blank($p->analysis)
            && $p->analysis !== GroqService::FALLBACK_ANALYSIS
            && $p->analysis !== 'Prediction pending';

        $homeForm = $p->match
            ? $this->formGuide($p->match->home_team, $p->match->match_time, $p->match->id)
            : [];
        $awayForm = $p->match
            ? $this->formGuide($p->match->away_team, $p->match->match_time, $p->match->id)
            : [];

        return [
            'id'                => $p->id,
            'match_id'          => $p->match_id,
            'home_win_prob'     => $hw,
            'draw_prob'         => $d,
            'away_win_prob'     => $aw,
            'over_15_prob'      => (float) ($p->over_15_prob ?? 0),
            'over_25_prob'      => (float) ($p->over_25_prob ?? 0),
            'over_35_prob'      => (float) ($p->over_35_prob ?? 0),
            'btts_prob'         => (float) ($p->btts_prob    ?? 0),
            'predicted_outcome' => $p->predicted_outcome,
            'confidence'        => $confidence,
            'confidence_pct'    => $p->confidence,
            'tips'              => is_array($p->tips) ? $p->tips : [],
            'was_correct'       => $p->was_correct,
            'is_ai'             => $isAi,
            'analysis'          => $p->analysis,
            'analysis_pidgin'   => $p->analysis_pidgin,
            'analysis_swahili'  => $p->analysis_swahili,
            'analysis_french'   => $p->analysis_french,
            'likely_scores'     => is_array($p->likely_scores) ? $p->likely_scores : [],
            'home_form'         => $homeForm,
            'away_form'         => $awayForm,
            'created_at'        => $p->created_at?->toIso8601String(),
            'match'             => $p->match ? [
                'id'             => $p->match->id,
                'api_id'         => $p->match->api_id,
                'league_id'      => $p->match->league_id,
                'league'         => $p->match->league,
                'league_country' => $p->match->league_country,
                'home_team'      => $p->match->home_team,
                'away_team'      => $p->match->away_team,
                'home_score'     => $p->match->home_score,
                'away_score'     => $p->match->away_score,
                'status'         => $p->match->status,
                'elapsed'        => $p->match->elapsed,
                'display_status' => $this->matchDisplayStatus($p->match),
                'match_time'     => $p->match->match_time?->toIso8601String(),
            ] : null,
        ];
    }
}
