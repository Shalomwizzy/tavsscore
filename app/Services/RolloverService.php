<?php

namespace App\Services;

use App\Models\Prediction;
use App\Models\RolloverChallenge;
use App\Models\RolloverPick;
use App\Support\LeagueCoverage;
use App\Support\PickHelpers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RolloverService
{
    private const TZ           = 'Africa/Lagos';
    private const MIN_CONF     = 78;

    // Rollover legs must clear a high model-probability floor from the market
    // board — this is the real safety metric (not just the AI's self-rated
    // confidence). Only genuinely high-probability legs go into the accumulator.
    private const MIN_BOARD_PROB = 80.0;
    private const DAYS_PER_RUN = 10;

    // Safe markets for rollover — avoid exotic/unpredictable markets.
    // Strings must match the exact predicted_outcome values PredictionService
    // emits (see PickHelpers::resolveForMatch for the canonical list). The
    // (GG)/(NG) suffixed variants used to be here but were causing every BTTS
    // pick to be filtered out — the pipeline stopped emitting them in early
    // May 2026 which is when rollover automation silently stalled.
    private const SAFE_MARKETS = [
        'Home Win', 'Away Win', 'Draw',
        'Home or Draw (1X)', 'Draw or Away (X2)', 'Home or Away (12)',
        'Both Teams Score', 'No Both Teams Score',
        'Over 0.5 Goals', 'Over 1.5 Goals', 'Over 2.5 Goals',
        'Draw No Bet - Home', 'Draw No Bet - Away',
    ];

    /** predicted_outcome label -> market_board key, where they differ. */
    private const BOARD_KEY_MAP = [
        'Both Teams Score'    => 'Both Teams Score (GG)',
        'No Both Teams Score' => 'No Both Teams Score (NG)',
    ];

    public function __construct(
        private readonly GeminiService     $gemini,
        private readonly MistralService    $mistral,
        private readonly PredictionService $predictionService,
        private readonly TelegramService   $telegram,
        private readonly OneSignalService  $oneSignal,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  Challenge management
    // ──────────────────────────────────────────────────────────────

    public function getActiveChallenge(): ?RolloverChallenge
    {
        return RolloverChallenge::query()->where('status', 'active')->latest('started_at')->first();
    }

    public function getOrCreateChallenge(float $initialStake = 10000.00): RolloverChallenge
    {
        $existing = $this->getActiveChallenge();
        if ($existing) return $existing;

        return RolloverChallenge::create([
            'started_at'    => CarbonImmutable::now(self::TZ)->toDateString(),
            'initial_stake' => $initialStake,
            'status'        => 'active',
        ]);
    }

    public function startNewChallenge(float $initialStake = 10000.00): RolloverChallenge
    {
        // Mark any active challenge as complete first
        RolloverChallenge::query()->where('status', 'active')->update(['status' => 'complete']);

        return RolloverChallenge::create([
            'started_at'    => CarbonImmutable::now(self::TZ)->toDateString(),
            'initial_stake' => $initialStake,
            'status'        => 'active',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Pick selection
    // ──────────────────────────────────────────────────────────────

    public function selectTodaysPick(): ?RolloverPick
    {
        $tz    = self::TZ;
        $today = CarbonImmutable::now($tz)->toDateString();

        // Check rest day: after a perfect 10-win challenge we rest 1 day
        $lastComplete = RolloverChallenge::query()
            ->where('status', 'complete')
            ->latest('updated_at')
            ->first();

        if ($lastComplete) {
            $allWon     = $lastComplete->picks()->where('status', 'won')->count() >= self::DAYS_PER_RUN;
            $updatedToday = $lastComplete->updated_at?->timezone($tz)->toDateString() === $today;
            if ($allWon && $updatedToday) {
                Log::info('RolloverService: rest day after 10-win challenge — no pick today.');
                return null;
            }
        }

        $challenge = $this->getOrCreateChallenge();

        // Already have a pick today
        if ($challenge->picks()->where('pick_date', $today)->exists()) {
            return null;
        }

        $dayNumber = $challenge->picks()->count() + 1;

        // Challenge already ran its 10 days
        if ($dayNumber > self::DAYS_PER_RUN) {
            $challenge->update(['status' => 'complete']);
            return null;
        }

        // Previous day must have won before we can continue
        if ($dayNumber > 1) {
            $prevPick = $challenge->picks()->where('day_number', $dayNumber - 1)->first();
            if ($prevPick && $prevPick->status === 'lost') {
                $challenge->update(['status' => 'complete']);
                return null;
            }
            if ($prevPick && $prevPick->status === 'pending') {
                // Previous pick not settled yet — wait
                return null;
            }
        }

        $stake = $this->calculateStake($challenge, $dayNumber);

        // Find the best match from today's predictions
        // Rollover stakes real money — restrict to top European + CAF continental only.
        // African domestic leagues (NPFL, PSL, etc.) are excluded here because bookmaker
        // odds are thin, data is sparse, and AI calibration is weaker on those fixtures.
        $eligibleLeagues = array_merge(
            \App\Support\LeagueCoverage::topEuropean(),
            \App\Support\LeagueCoverage::africaContinental(),
        );

        // Selection is driven by AI confidence + dual-AI agreement, NOT odds
        $candidates = Prediction::query()
            ->with('match')
            ->whereNotNull('confidence')
            ->whereNotNull('predicted_outcome')
            ->where('confidence', '>=', self::MIN_CONF)
            ->whereIn('predicted_outcome', self::SAFE_MARKETS)
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [
                    CarbonImmutable::now($tz)->startOfDay(),
                    CarbonImmutable::now($tz)->endOfDay(),
                ])
                ->whereNotIn('status', ['CANC', 'PST', 'FT', 'AET', 'PEN', 'ABD'])
                ->whereIn('league_id', $eligibleLeagues)
            )
            ->orderByDesc('confidence')
            ->limit(20)
            ->get();

        // Diagnostic: log the funnel size at each step. Silent misses are the
        // exact class of failure that stalled rollover automation for two
        // months — surface it so future ones are debuggable in one grep.
        Log::info('RolloverService: candidate funnel', [
            'day_number'         => $dayNumber,
            'eligible_leagues'   => count($eligibleLeagues),
            'min_confidence'     => self::MIN_CONF,
            'candidates_found'   => $candidates->count(),
        ]);

        // Markets used in the last 3 rollover picks — used for diversity enforcement.
        $recentPicks = RolloverPick::query()
            ->latest('pick_date')
            ->limit(3)
            ->get(['groq_verdict', 'pick_date']);

        $yesterdayMarket  = $recentPicks->first()?->groq_verdict;
        $recentMarkets    = $recentPicks->pluck('groq_verdict')->toArray();

        $minBoardProb = (float) \App\Models\Setting::get('rollover_min_board_prob', (string) self::MIN_BOARD_PROB);

        $qualifiedPicks = [];

        foreach ($candidates as $pred) {
            // Collect up to 8 candidates so we have enough diversity options
            if (count($qualifiedPicks) >= 8) break;

            $match = $pred->match;
            if (! $match) continue;

            // Triple-AI cross-validation: all three AIs analyse independently
            $cacheKey = 'rollover_stats_' . $match->id;
            [$homeStats, $awayStats, $h2h] = Cache::remember($cacheKey, now()->addHours(3), function () use ($match) {
                return [
                    $this->predictionService->extendedTeamStats($match->home_team, $match->match_time, $match->id),
                    $this->predictionService->extendedTeamStats($match->away_team, $match->match_time, $match->id),
                    $this->predictionService->headToHead($match->home_team, $match->away_team, $match->match_time, $match->id),
                ];
            });

            $geminiVerdict  = $this->gemini->independentVerdict($match, $homeStats, $awayStats, $h2h);
            $mistralVerdict = $this->mistral->independentVerdict($match, $homeStats, $awayStats, $h2h);

            $groqNorm = mb_strtolower(trim($pred->predicted_outcome));

            // Any AI below 60% confidence → skip this match
            if ($geminiVerdict  !== null && $geminiVerdict['confidence']  < 60) continue;
            if ($mistralVerdict !== null && $mistralVerdict['confidence'] < 60) continue;

            // Every configured AI must independently reach the same outcome as Groq
            $geminiOk  = $geminiVerdict  === null || mb_strtolower(trim($geminiVerdict['outcome']))  === $groqNorm;
            $mistralOk = $mistralVerdict === null || mb_strtolower(trim($mistralVerdict['outcome'])) === $groqNorm;

            if (! $geminiOk || ! $mistralOk) {
                continue;
            }

            // Safety floor: the pick's own market must clear a high model
            // probability on the board. This is the real "will it win" metric.
            // Floor is admin-tunable (Settings › Prediction Tuning).
            $boardProb = $this->boardProbabilityFor($pred);
            if ($boardProb !== null && $boardProb < $minBoardProb) {
                continue;
            }

            $qualifiedPicks[] = [
                'prediction'     => $pred,
                'geminiVerdict'  => $geminiVerdict,
                'mistralVerdict' => $mistralVerdict,
                'boardProb'      => $boardProb ?? (float) $pred->confidence,
                'allAgree'       => true,
            ];
        }

        if (empty($qualifiedPicks)) {
            Log::info('RolloverService: no match had triple-AI agreement today — no pick selected.', [
                'candidates_examined' => $candidates->count(),
            ]);
            return null;
        }

        // Market diversity — hard-split into "fresh market" vs "repeat market" buckets.
        // A pick whose market was used yesterday is put in the fallback bucket.
        // We always pick from the fresh bucket first; only fall back if it's empty,
        // ensuring the same market never repeats on consecutive days unless unavoidable.
        $freshPicks  = array_values(array_filter($qualifiedPicks, fn ($p) => $p['prediction']->predicted_outcome !== $yesterdayMarket));
        $repeatPicks = array_values(array_filter($qualifiedPicks, fn ($p) => $p['prediction']->predicted_outcome === $yesterdayMarket));

        // Within each bucket sort by: used in last 3 days → confidence desc
        // Sort by: not-recently-used → highest model board probability (safest leg).
        $sortFn = function (array $a, array $b) use ($recentMarkets): int {
            $aUsed = in_array($a['prediction']->predicted_outcome, $recentMarkets, true);
            $bUsed = in_array($b['prediction']->predicted_outcome, $recentMarkets, true);
            if ($aUsed !== $bUsed) return $aUsed ? 1 : -1;
            return $b['boardProb'] <=> $a['boardProb'];
        };
        usort($freshPicks,  $sortFn);
        usort($repeatPicks, $sortFn);

        $pool = ! empty($freshPicks) ? $freshPicks : $repeatPicks;

        if (! empty($freshPicks)) {
            Log::info('RolloverService: selected from fresh-market pool (avoided ' . $yesterdayMarket . ').');
        } else {
            Log::info('RolloverService: no fresh-market alternative — falling back to ' . $yesterdayMarket . '.');
        }

        $best = $pool[0];

        $pred        = $best['prediction'];
        $displayOdds = $this->impliedOdds($pred); // display only, not selection criteria

        $potentialReturn = round($stake * $displayOdds, 2);

        // A challenge's real clock starts with its first pick, not its row
        // creation. Without this, a challenge created during a dry spell
        // (e.g. off-season, or the May-July selection outage) shows a
        // months-old start date with zero picks on the public page.
        if ($dayNumber === 1) {
            $challenge->update(['started_at' => $today]);
        }

        $pick = RolloverPick::create([
            'challenge_id'     => $challenge->id,
            'match_id'         => $pred->match_id,
            'prediction_id'    => $pred->id,
            'day_number'       => $dayNumber,
            'pick_date'        => $today,
            'implied_odds'     => $displayOdds,
            'stake_amount'     => $stake,
            'potential_return' => $potentialReturn,
            'groq_verdict'     => $pred->predicted_outcome,
            'gemini_verdict'   => $best['geminiVerdict']['outcome']  ?? null,
            'both_agree'       => $best['allAgree'],
            'status'           => 'pending',
        ]);

        // Notify users about the new rollover pick
        $matchLabel = "{$pred->match?->home_team} vs {$pred->match?->away_team}";
        $league     = LeagueCoverage::formatName($pred->match?->league, $pred->match?->league_country);
        $siteUrl    = config('app.url');

        $this->oneSignal->notifyRolloverPick($dayNumber, $matchLabel, $pred->predicted_outcome, $stake, $potentialReturn);

        $this->telegram->sendRolloverPick(
            $matchLabel,
            $pred->predicted_outcome,
            $dayNumber,
            $stake,
            $potentialReturn,
            $siteUrl,
            $league,
        );

        return $pick;
    }

    /**
     * Model probability of a prediction's own market, read from its stored
     * market board. Returns null if the board or the market key is missing
     * (caller then falls back to the AI confidence).
     */
    private function boardProbabilityFor(Prediction $pred): ?float
    {
        $board = $pred->market_board;
        if (empty($board) || ! is_array($board) || blank($pred->predicted_outcome)) {
            return null;
        }

        $key = self::BOARD_KEY_MAP[$pred->predicted_outcome] ?? $pred->predicted_outcome;

        return isset($board[$key]) ? (float) $board[$key] : null;
    }

    // ──────────────────────────────────────────────────────────────
    //  Outcome checking (called from CheckPredictionOutcomes)
    // ──────────────────────────────────────────────────────────────

    public function checkPendingPicks(): void
    {
        $pending = RolloverPick::query()
            ->with(['match', 'challenge', 'prediction'])
            ->where('status', 'pending')
            ->get();

        foreach ($pending as $pick) {
            $match = $pick->match;
            if (! $match || ! in_array($match->status, ['FT', 'AET', 'PEN'], true)) {
                continue;
            }

            // Always evaluate against the rollover pick's OWN tip, not the linked
            // prediction's was_correct. The prediction may have been regenerated by
            // lineup updates and its predicted_outcome changed after the pick was made.
            // e.g. rollover picked "Over 2.5 Goals" but prediction later changed to
            // "Away Win" — grading via was_correct would give the wrong result.
            $tip = $pick->groq_verdict ?? $pick->gemini_verdict;
            $wasCorrect = PickHelpers::resolveForMatch($match, $tip);

            if ($wasCorrect === null) {
                Log::info("RolloverService: pick {$pick->id} tip='{$tip}' — outcome unresolvable, skipping.");
                continue;
            }

            $score   = "{$match->home_score}-{$match->away_score}";
            $newStatus = $wasCorrect ? 'won' : 'lost';

            $pick->update([
                'was_correct'  => $wasCorrect,
                'result_score' => $score,
                'status'       => $newStatus,
            ]);

            // If it's a loss and this was the active challenge's pick, mark challenge complete
            if ($newStatus === 'lost' && $pick->challenge?->status === 'active') {
                $pick->challenge->update(['status' => 'complete']);
            }

            // If day 10 won, mark complete and schedule rest
            if ($newStatus === 'won' && $pick->day_number >= self::DAYS_PER_RUN) {
                $pick->challenge?->update(['status' => 'complete']);
            }

            // Notify — once per pick
            $cacheKey = "rollover_notified_{$pick->id}";
            if (! Cache::has($cacheKey)) {
                $matchLabel = "{$match->home_team} vs {$match->away_team}";
                $tip        = $pick->groq_verdict ?? $pick->gemini_verdict ?? '—';
                $siteUrl    = config('app.url');
                $league     = LeagueCoverage::formatName($match->league, $match->league_country);

                if ($newStatus === 'won') {
                    $this->oneSignal->notifyRolloverWon($pick->day_number, $matchLabel, $score, (float) $pick->potential_return);
                } else {
                    $this->oneSignal->notifyRolloverLost($pick->day_number, $matchLabel, $score);
                }

                $this->telegram->sendRolloverOutcome(
                    match:   $matchLabel,
                    tip:     $tip,
                    score:   $score,
                    status:  $newStatus,
                    day:     $pick->day_number,
                    stake:   (float) $pick->stake_amount,
                    returns: (float) $pick->potential_return,
                    siteUrl: $siteUrl,
                    league:  $league,
                );

                // Winner upload reminder — DB flag so it survives cache:clear and deploys
                if ($newStatus === 'won' && ! $pick->winner_reminder_sent) {
                    $this->oneSignal->notifyWinnerReminder();
                    $this->telegram->sendWinnerUploadReminder($siteUrl);
                    $pick->update(['winner_reminder_sent' => true]);
                }

                Cache::put($cacheKey, true, now()->addDays(3));
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    private function calculateStake(RolloverChallenge $challenge, int $dayNumber): float
    {
        if ($dayNumber === 1) return (float) $challenge->initial_stake;

        $lastWon = $challenge->picks()
            ->where('day_number', $dayNumber - 1)
            ->where('status', 'won')
            ->first();

        return $lastWon ? (float) $lastWon->potential_return : (float) $challenge->initial_stake;
    }

    private function impliedOdds(Prediction $pred): float
    {
        // Try bookmaker odds embedded in the tips array first
        $tips = is_array($pred->tips) ? $pred->tips : [];
        foreach ($tips as $tip) {
            if (($tip['market'] ?? '') === $pred->predicted_outcome && isset($tip['bookmaker_odds'])) {
                $o = (float) $tip['bookmaker_odds'];
                if ($o >= 1.01) return round($o, 2);
            }
        }

        // Derive from AI confidence: e.g. 80% → 1/0.80 = 1.25
        $conf = max(1, (float) $pred->confidence);
        return round(1 / ($conf / 100), 2);
    }

}
