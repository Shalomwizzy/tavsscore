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
        private readonly GroqService $groqService,
        private readonly OddsService $oddsService,
        private readonly GeminiService $geminiService,
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
        // ── Poisson baseline (goal-line stats) ────────────────────
        $homeStats = $this->teamStats($match->home_team, $match->match_time, $match->id);
        $awayStats = $this->teamStats($match->away_team, $match->match_time, $match->id);
        $homeXg    = $this->clampXg($this->attackStrength($homeStats) * $this->defenseWeakness($awayStats) * self::HOME_ADVANTAGE);
        $awayXg    = $this->clampXg($this->attackStrength($awayStats) * $this->defenseWeakness($homeStats));
        $poisson   = $this->poissonProbabilities($homeXg, $awayXg);

        // ── Check for existing good prediction ────────────────────
        $existing      = Prediction::query()->where('match_id', $match->id)->first();
        $hasAiAnalysis = $existing
            && !blank($existing->analysis)
            && $existing->analysis !== GroqService::FALLBACK_ANALYSIS
            && $existing->analysis !== 'Prediction pending';

        if ($hasAiAnalysis) {
            // We already have a full AI analysis — keep it. Don't burn another
            // Groq call. Just refresh the primary verdict from existing probs.
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

        // ── Call Groq ─────────────────────────────────────────────
        $groq = $this->groqService->getPrediction($match, $poisson);

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
            $tips = $this->tipsFromProbabilities($homeWin, $draw, $awayWin, $over15, $over25, $over35, $btts);
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
                : $this->confidenceForOutcome($primaryOutcome, $homeWin, $draw, $awayWin, $over15, $over25, $over35, $btts);
        }

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
            ]
        );
    }

    /**
     * Generate market tips directly from Poisson probabilities — used as a
     * fallback when Groq returns nothing or fewer than 3 tips. Only emits
     * tips that pass the 50% confidence floor.
     *
     * Limited to verifiable markets (1X2, double-chance, goal lines, BTTS).
     * No corners/cards/HT here — Poisson can't predict those.
     */
    private function tipsFromProbabilities(
        float $hw, float $d, float $aw,
        float $over15, float $over25, float $over35, float $btts
    ): array {
        $candidates = [
            ['market' => 'Home Win',             'confidence' => (int) round($hw),         'rationale' => 'Strongest 1X2 probability'],
            ['market' => 'Draw',                 'confidence' => (int) round($d),          'rationale' => 'Strongest 1X2 probability'],
            ['market' => 'Away Win',             'confidence' => (int) round($aw),         'rationale' => 'Strongest 1X2 probability'],
            ['market' => 'Home or Draw (1X)',    'confidence' => (int) round($hw + $d),    'rationale' => 'Double chance from Poisson'],
            ['market' => 'Draw or Away (X2)',    'confidence' => (int) round($d + $aw),    'rationale' => 'Double chance from Poisson'],
            ['market' => 'Home or Away (12)',    'confidence' => (int) round($hw + $aw),   'rationale' => 'Double chance from Poisson'],
            ['market' => 'Over 1.5 Goals',       'confidence' => (int) round($over15),     'rationale' => 'Goal-line projection'],
            ['market' => 'Under 1.5 Goals',      'confidence' => (int) round(100 - $over15),'rationale' => 'Goal-line projection'],
            ['market' => 'Over 2.5 Goals',       'confidence' => (int) round($over25),     'rationale' => 'Goal-line projection'],
            ['market' => 'Under 2.5 Goals',      'confidence' => (int) round(100 - $over25),'rationale' => 'Goal-line projection'],
            ['market' => 'Over 3.5 Goals',       'confidence' => (int) round($over35),     'rationale' => 'Goal-line projection'],
            ['market' => 'Under 3.5 Goals',      'confidence' => (int) round(100 - $over35),'rationale' => 'Goal-line projection'],
            ['market' => 'Both Teams Score',     'confidence' => (int) round($btts),       'rationale' => 'BTTS Poisson estimate'],
            ['market' => 'No Both Teams Score',  'confidence' => (int) round(100 - $btts), 'rationale' => 'BTTS Poisson estimate'],
        ];

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
            in_array($market, ['Home Win', 'Draw', 'Away Win'], true) => '1x2',
            str_contains($market, '(1X)'), str_contains($market, '(X2)'), str_contains($market, '(12)') => 'double_chance',
            str_contains($market, 'Goals') => 'goals',
            str_contains($market, 'Both Teams Score') => 'btts',
            default => 'other',
        };
    }

    /**
     * Merge AI-supplied tips with fallback tips, dedup by market, keep AI ones first.
     */
    private function mergeTips(array $primary, array $secondary): array
    {
        $seen = array_map(fn ($t) => $t['market'] ?? '', $primary);
        foreach ($secondary as $t) {
            if (count($primary) >= 5) break;
            if (in_array($t['market'], $seen, true)) continue;
            $primary[] = $t;
            $seen[]    = $t['market'];
        }
        return $primary;
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
     */
    private function confidenceForOutcome(
        string $outcome,
        float $hw, float $d, float $aw,
        float $over15, float $over25, float $over35, float $btts
    ): int {
        return (int) round(match ($outcome) {
            'Home Win'             => $hw,
            'Draw'                 => $d,
            'Away Win'             => $aw,
            'Over 1.5 Goals'       => $over15,
            'Under 1.5 Goals'      => 100 - $over15,
            'Over 2.5 Goals'       => $over25,
            'Under 2.5 Goals'      => 100 - $over25,
            'Over 3.5 Goals'       => $over35,
            'Under 3.5 Goals'      => 100 - $over35,
            'Both Teams Score'     => $btts,
            'No Both Teams Score'  => 100 - $btts,
            'Home or Draw (1X)'    => $hw + $d,
            'Draw or Away (X2)'    => $d  + $aw,
            'Home or Away (12)'    => $hw + $aw,
            default                => 50,
        });
    }

    /**
     * Returns the top MAX_DAILY_PICKS matches for today, sorted by league
     * importance. Only top-competition fixtures qualify.
     */
    public function upcomingMatches(): EloquentCollection
    {
        $order  = implode(',', $this->leaguePriorityIds());
        $today  = now()->startOfDay();
        $cutoff = now()->endOfDay();

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

        // Fetch today's predictions that have genuine AI analysis AND a primary
        // confidence ≥ 50% — anything weaker isn't worthy of a daily pick.
        // We include FT/AET/PEN matches so retroactive selection still works
        // when picks:select ran late or the scheduler missed its window.
        $excludedInSelection = ['CANC', 'PST', 'ABD', 'AWD', 'WO'];

        $candidates = Prediction::query()
            ->with('match')
            ->where('analysis', '!=', GroqService::FALLBACK_ANALYSIS)
            ->where('analysis', '!=', 'Prediction pending')
            ->whereNotNull('analysis')
            ->whereNotNull('predicted_outcome')
            ->where('predicted_outcome', '!=', 'Competitive Match')
            ->where(function ($q) {
                // Require either AI-set confidence ≥ 50 OR (legacy) a strong
                // probability gap so old predictions still qualify.
                $q->where('confidence', '>=', 50)->orWhereNull('confidence');
            })
            ->whereHas('match', fn ($q) => $q
                ->where(fn ($w) => LeagueCoverage::scopeCovered($w))
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', $excludedInSelection)
            )
            ->get();

        if ($candidates->isEmpty()) {
            return new EloquentCollection();
        }

        // Score: prefer AI's stated confidence if available, else fall back to
        // the probability-gap heuristic.
        $scored = $candidates->map(function (Prediction $p) {
            $hw   = (float) $p->home_win_prob;
            $d    = (float) $p->draw_prob;
            $aw   = (float) $p->away_win_prob;
            $o25  = (float) ($p->over_25_prob ?? 50);
            $btts = (float) ($p->btts_prob    ?? 50);

            $probs = [$hw, $d, $aw];
            rsort($probs);
            $gap   = $probs[0] - $probs[1];

            // Confidence floor for picks: 50pp absolute. Use AI conf if present.
            $aiConf = (int) ($p->confidence ?? 0);
            if ($aiConf >= 50) {
                $score = $aiConf;
            } else {
                // Legacy gap-based score with goal-line bonus
                $glBonus  = 0;
                $glBonus += max(0, $o25  - 60) * 0.5;
                $glBonus += max(0, 40   - $o25) * 0.3;
                $glBonus += max(0, $btts - 62) * 0.3;
                $score = $gap + $glBonus;
            }

            // Variety bucket — group same primary outcome together so we can
            // pick across different bet types (e.g. one home win, one over, one BTTS).
            $tipType = mb_strtolower((string) $p->predicted_outcome);

            return [
                'prediction' => $p,
                'score'      => $score,
                'tip_type'   => $tipType,
                'gap'        => $gap,
                'ai_conf'    => $aiConf,
            ];
        })
        // 50% floor: AI conf >= 50 OR legacy fallback (12pp gap)
        ->filter(fn ($s) => $s['ai_conf'] >= 50 || $s['gap'] >= 12)
        ->sortByDesc('score');

        // Pick 3 with variety: prefer a different tip type each time
        $picks    = collect();
        $used     = [];

        foreach ($scored as $item) {
            if ($picks->count() >= 3) break;
            if (! in_array($item['tip_type'], $used, true)) {
                $picks->push($item);
                $used[] = $item['tip_type'];
            }
        }

        // Backfill if < 3 — take highest remaining regardless of type
        foreach ($scored as $item) {
            if ($picks->count() >= 3) break;
            $alreadyIn = $picks->contains(fn ($x) => $x['prediction']->id === $item['prediction']->id);
            if (! $alreadyIn) {
                $picks->push($item);
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

        return [
            'home_win' => $hwPct,
            'draw'     => $dPct,
            'away_win' => $awPct,
            'over_15'  => round($o15 / $tot * 100, 1),
            'over_25'  => round($o25 / $tot * 100, 1),
            'over_35'  => round($o35 / $tot * 100, 1),
            'btts'     => round($btts / $tot * 100, 1),
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
            // 1X2 (single outcome)
            'Home Win'        => $hw - 33.3,
            'Draw'            => $d  - 33.3,
            'Away Win'        => $aw - 33.3,

            // Goal-line markets
            'Over 1.5 Goals'  => $over15 - 50,
            'Under 1.5 Goals' => 50 - $over15,
            'Over 2.5 Goals'  => $over25 - 50,
            'Under 2.5 Goals' => 50 - $over25,
            'Over 3.5 Goals'  => $over35 - 50,
            'Under 3.5 Goals' => 50 - $over35,

            // BTTS
            'Both Teams Score'    => $btts - 50,
            'No Both Teams Score' => 50 - $btts,

            // Double-chance (safer, lower max edge)
            'Home or Draw (1X)' => ($hw + $d)  - 66.7,
            'Draw or Away (X2)' => ($d  + $aw) - 66.7,
            'Home or Away (12)' => ($hw + $aw) - 66.7,
        ];

        arsort($candidates);
        $top  = array_key_first($candidates);
        $edge = $candidates[$top];

        // Need at least 8pp edge over baseline to call it a tip
        return $edge >= 8 ? $top : 'Competitive Match';
    }

    // ──────────────────────────────────────────────────────────────
    //  Team stats helpers
    // ──────────────────────────────────────────────────────────────

    private function teamStats(string $team, CarbonInterface $before, int $excludedMatchId): array
    {
        $matches = FootballMatch::query()
            ->where('id', '!=', $excludedMatchId)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->where('match_time', '<', $before)
            ->whereNotNull('home_score')->whereNotNull('away_score')
            ->where(fn ($q) => $q->where('home_team', $team)->orWhere('away_team', $team))
            ->latest('match_time')
            ->limit(self::RECENT_MATCH_LIMIT)
            ->get();

        $scored = $conceded = 0;
        foreach ($matches as $m) {
            if ($m->home_team === $team) { $scored += (int) $m->home_score; $conceded += (int) $m->away_score; }
            else                         { $scored += (int) $m->away_score; $conceded += (int) $m->home_score; }
        }

        return ['matches_played' => $matches->count(), 'goals_scored' => $scored, 'goals_conceded' => $conceded];
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
