<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Support\LeagueCoverage;
use App\Support\PickHelpers;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PredictionService
{
    private const RECENT_MATCH_LIMIT  = 10;
    private const NEUTRAL_GOALS_RATE  = 1.10;
    private const HOME_ADVANTAGE      = 1.15;
    private const MIN_XG              = 0.30;
    private const MAX_XG              = 4.50;
    private const MAX_GOALS_GRID      = 8;
    private const MAX_DAILY_PICKS     = 50;   // quality over quantity
    private const COMPLETED_STATUSES  = ['FT', 'AET', 'PEN'];
    private const EXCLUDED_UPCOMING_STATUSES = ['FT', 'AET', 'PEN', 'CANC', 'PST', 'ABD', 'AWD', 'WO'];

    public function __construct(
        private readonly GroqService   $groqService,
        private readonly OddsService   $oddsService,
        private readonly GeminiService $geminiService,
        private readonly NewsService   $newsService,
        private readonly LineupService $lineupService,
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
        $homeXg    = $this->clampXg($this->attackStrength($homeStats) * $this->defenseWeakness($awayStats) * self::HOME_ADVANTAGE);
        $awayXg    = $this->clampXg($this->attackStrength($awayStats) * $this->defenseWeakness($homeStats));
        $poisson   = $this->poissonProbabilities($homeXg, $awayXg);

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
            return Prediction::query()->updateOrCreate(
                ['match_id' => $match->id],
                [
                    'predicted_outcome' => $primaryOutcome,
                    'confidence'        => $primaryConfidence,
                ]
            );
        }

        // ── Fetch news (multi-source: Google + BBC + Sky + ESPN) ──
        $homeNews     = $this->newsService->getFullContext($match->home_team);
        $awayNews     = $this->newsService->getFullContext($match->away_team);
        $matchPreview = $this->newsService->getMatchPreview($match->home_team, $match->away_team, $match->league ?? '');

        // ── Fetch confirmed lineup if within kickoff window ───────
        $lineupData = $this->lineupService->getLineup($match);

        // ── Call Groq with full context ───────────────────────────
        $groq = $this->groqService->getPrediction(
            $match, $poisson,
            $homeStats, $awayStats,
            $homeForm, $awayForm,
            $homeNews, $awayNews,
            $lineupData,
            $h2h,
            $matchPreview,
        );

        // Respect 30 RPM: 2.1 s between calls
        usleep(2_100_000);

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
            // Groq unavailable — store neutral Poisson + pending marker
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

        // Optional: Gemini as second-opinion AI on the headline tip. Only fires
        // when GEMINI_API_KEY is set. Marks the tip "🤝 Cross-validated" when
        // both AIs agree, or "⚠️ AI disagrees" when they don't.
        $tips = $this->annotateWithGeminiConsensus($tips, $match);

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

        $likelyScores = $this->topScorelines($homeXg, $awayXg);

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
                'predicted_outcome' => $primaryOutcome,
                'tips'              => $tips,
                'confidence'        => $primaryConfidence,
                'analysis'          => $analysis,
                'likely_scores'     => $likelyScores,
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

        $candidates = array_values(array_filter($candidates, fn ($c) => $c['confidence'] >= 50 && $c['confidence'] <= 95));
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
    private function annotateWithGeminiConsensus(array $tips, FootballMatch $match): array
    {
        if (empty($tips) || ! $this->geminiService->isConfigured()) return $tips;

        $geminiTip = null;
        try {
            $geminiTip = $this->geminiService->headlineTip($match);
        } catch (\Throwable) {
            // Gemini failure shouldn't block predictions
        }

        if ($geminiTip === null) return $tips;

        // Compare normalized strings — Gemini sometimes returns variants
        $geminiNorm = mb_strtolower(trim($geminiTip));
        $headlineNorm = mb_strtolower(trim($tips[0]['market'] ?? ''));

        $tips[0]['gemini_tip'] = $geminiTip;
        $tips[0]['gemini_agrees'] = $geminiNorm === $headlineNorm;

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
            $homeXg  = $this->clampXg($this->attackStrength($homeStats) * $this->defenseWeakness($awayStats) * self::HOME_ADVANTAGE);
            $awayXg  = $this->clampXg($this->attackStrength($awayStats) * $this->defenseWeakness($homeStats));
            $poisson = $this->poissonProbabilities($homeXg, $awayXg);
        }

        $groq = $this->groqService->getPrediction(
            $match, $poisson,
            $homeStats, $awayStats,
            $homeForm, $awayForm,
            $homeNews, $awayNews,
            $lineupData,
            $h2h,
            $matchPreview,
        );

        if (! $groq) {
            return false;
        }

        usleep(2_100_000);

        $tips = $this->annotateWithMarketConsensus($groq['tips'] ?? [], $match);
        $tips = $this->annotateWithGeminiConsensus($tips, $match);

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

        $likelyScores = $this->topScorelines($homeXgFinal, $awayXgFinal);
        $data['likely_scores'] = $likelyScores;

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

        return FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->whereNotIn('status', self::EXCLUDED_UPCOMING_STATUSES)
            ->whereBetween('match_time', [$today, $cutoff])
            ->orderByRaw("FIELD(league_id, {$order})")
            ->orderBy('match_time')
            ->limit(self::MAX_DAILY_PICKS)
            ->get();
    }

    public function generateForUpcomingMatches(): Collection
    {
        return $this->upcomingMatches()
            ->map(fn (FootballMatch $m): Prediction => $this->generateForMatch($m));
    }

    /**
     * @param  CarbonInterface|null  $date  If provided, returns predictions for that date only
     *                                       (browse archive). If null, today's upcoming matches.
     */
    public function allPredictions(?CarbonInterface $date = null): Collection
    {
        $tz = config('app.timezone');

        if ($date !== null) {
            // Archive view: predictions whose match was on the requested date
            $start = $date->copy()->startOfDay();
            $end   = $date->copy()->endOfDay();

            $predictions = Prediction::query()
                ->with('match')
                ->whereHas('match', fn ($q) => $q
                    ->where(fn ($w) => LeagueCoverage::scopeCovered($w))
                    ->whereBetween('match_time', [$start, $end])
                )
                ->orderByDesc('confidence')
                ->limit(self::MAX_DAILY_PICKS * 2)
                ->get();

            $this->autoResolveCollection($predictions);

            return $predictions->map(fn (Prediction $p): array => $this->formatPrediction($p));
        }

        $today = CarbonImmutable::now($tz)->startOfDay();

        $predictions = Prediction::query()
            ->with('match')
            ->whereHas('match', fn ($q) => $q
                ->where(fn ($w) => LeagueCoverage::scopeCovered($w))
                ->where('match_time', '>=', $today)
            )
            ->orderBy('created_at', 'desc')
            ->limit(self::MAX_DAILY_PICKS)
            ->get();

        if ($predictions->isEmpty()) {
            $predictions = Prediction::query()
                ->with('match')
                ->whereHas('match', fn ($q) => LeagueCoverage::scopeCovered($q))
                ->latest('created_at')
                ->limit(self::MAX_DAILY_PICKS)
                ->get();
        }

        $this->autoResolveCollection($predictions);

        return $predictions->map(fn (Prediction $p): array => $this->formatPrediction($p));
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

        // Clear all is_daily_pick rows whose underlying match falls in today's
        // window — match_time is the source of truth for which day a pick
        // belongs to, regardless of when the prediction was created. This
        // prevents duplicate pick_rank values when a previous day's pick had
        // a match_time that crossed midnight.
        Prediction::query()
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->update(['is_daily_pick' => false, 'pick_rank' => null]);

        // Only picks with genuine AI analysis and confidence ≥ 65%.
        // Draws are excluded — the hardest outcome to predict consistently.
        // We never backfill to hit a number: 1 quality pick beats 3 bad ones.
        $excludedInSelection = ['CANC', 'PST', 'ABD', 'AWD', 'WO'];
        $excludedOutcomes    = ['Competitive Match', 'Draw'];

        $candidates = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->whereNotNull('predicted_outcome')
            ->whereNotIn('predicted_outcome', $excludedOutcomes)
            ->where('confidence', '>=', 65)
            ->whereHas('match', fn ($q) => $q
                ->where(fn ($w) => LeagueCoverage::scopeCovered($w))
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excludedInSelection)
            )
            ->get();

        if ($candidates->isEmpty()) {
            return new EloquentCollection();
        }

        $accuracyWeights = $this->getMarketAccuracyWeights();

        $scored = $candidates->map(function (Prediction $p) use ($accuracyWeights) {
            $hw  = (float) $p->home_win_prob;
            $d   = (float) $p->draw_prob;
            $aw  = (float) $p->away_win_prob;

            $probs = [$hw, $d, $aw];
            rsort($probs);
            $gap    = $probs[0] - $probs[1];
            $aiConf = (int) ($p->confidence ?? 0);

            // Base score: AI confidence + gap bonus for clear favourites
            $score = $aiConf + ($gap * 0.3);

            // Apply historical accuracy multiplier for this market type.
            // If Over 2.5 Goals has only landed 45% historically, its score
            // is penalised. If Home Win lands 68%, it gets a boost.
            $outcome  = (string) $p->predicted_outcome;
            $accuracy = $accuracyWeights[$outcome] ?? 1.0;
            $score    = $score * $accuracy;

            return [
                'prediction' => $p,
                'score'      => $score,
                'tip_type'   => mb_strtolower($outcome),
                'gap'        => $gap,
                'ai_conf'    => $aiConf,
            ];
        })
        ->filter(fn ($s) => $s['ai_conf'] >= 65)
        ->sortByDesc('score');

        // Take up to 3, preferring different tip types — but never backfill
        // with a lower-quality pick just to reach 3. Quality over quantity.
        $picks = collect();
        $used  = [];

        foreach ($scored as $item) {
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
        $hw = $d = $aw = $o15 = $o25 = $o35 = $btts = $tot = 0.0;

        for ($h = 0; $h <= self::MAX_GOALS_GRID; $h++) {
            $ph = $this->poisson($homeXg, $h);
            for ($a = 0; $a <= self::MAX_GOALS_GRID; $a++) {
                $p = $ph * $this->poisson($awayXg, $a);
                $tot += $p;
                if ($h > $a)  { $hw += $p; }
                elseif ($h === $a) { $d  += $p; }
                else          { $aw += $p; }
                $g = $h + $a;
                if ($g >= 2) { $o15 += $p; }
                if ($g >= 3) { $o25 += $p; }
                if ($g >= 4) { $o35 += $p; }
                if ($h >= 1 && $a >= 1) { $btts += $p; }
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
        $formDetailed = [];

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
        return $s['matches_played'] === 0 ? self::NEUTRAL_GOALS_RATE : $s['goals_scored'] / $s['matches_played'];
    }

    private function defenseWeakness(array $s): float
    {
        return $s['matches_played'] === 0 ? self::NEUTRAL_GOALS_RATE : max(0.20, $s['goals_conceded'] / $s['matches_played']);
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
        // Signals that a key attacking player is out for the home side
        $homeReducers = ['home.*out', 'home.*injury', 'home.*suspend', 'home.*miss', 'striker.*out', 'forward.*out'];
        // Signals that a key attacking player is out for the away side
        $awayReducers = ['away.*out', 'away.*injury', 'away.*suspend', 'away.*miss'];
        // Signals full-strength attack
        $homeBoosts   = ['home.*full.*strength', 'home.*all.*available', 'home.*fit'];
        $awayBoosts   = ['away.*full.*strength', 'away.*all.*available', 'away.*fit'];

        $homeMultiplier = 1.0;
        $awayMultiplier = 1.0;

        foreach ($homeReducers as $pattern) {
            if (preg_match("/{$pattern}/i", $lineupText)) {
                $homeMultiplier = max(0.80, $homeMultiplier - 0.08);
            }
        }
        foreach ($awayReducers as $pattern) {
            if (preg_match("/{$pattern}/i", $lineupText)) {
                $awayMultiplier = max(0.80, $awayMultiplier - 0.08);
            }
        }
        foreach ($homeBoosts as $pattern) {
            if (preg_match("/{$pattern}/i", $lineupText)) {
                $homeMultiplier = min(1.12, $homeMultiplier + 0.06);
            }
        }
        foreach ($awayBoosts as $pattern) {
            if (preg_match("/{$pattern}/i", $lineupText)) {
                $awayMultiplier = min(1.12, $awayMultiplier + 0.06);
            }
        }

        return [
            $this->clampXg($homeXg * $homeMultiplier),
            $this->clampXg($awayXg * $awayMultiplier),
        ];
    }

    private function topScorelines(float $homeXg, float $awayXg, int $n = 4): array
    {
        $scores = [];
        for ($h = 0; $h <= 5; $h++) {
            for ($a = 0; $a <= 5; $a++) {
                $p = $this->poisson($homeXg, $h) * $this->poisson($awayXg, $a);
                $scores["{$h}-{$a}"] = round($p * 100, 1);
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
