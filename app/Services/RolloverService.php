<?php

namespace App\Services;

use App\Models\Prediction;
use App\Models\RolloverChallenge;
use App\Models\RolloverPick;
use App\Support\PickHelpers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RolloverService
{
    private const TZ           = 'Africa/Lagos';
    private const MIN_CONF     = 78;
    private const DAYS_PER_RUN = 10;

    // Safe markets for rollover — avoid exotic/unpredictable markets
    private const SAFE_MARKETS = [
        'Home Win', 'Away Win', 'Draw',
        'Home or Draw (1X)', 'Draw or Away (X2)', 'Home or Away (12)',
        'Both Teams Score (GG)', 'No Both Teams Score (NG)',
        'Over 1.5 Goals', 'Over 2.5 Goals',
        'Draw No Bet - Home', 'Draw No Bet - Away',
    ];

    public function __construct(
        private readonly GeminiService     $gemini,
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
            )
            ->orderByDesc('confidence')
            ->limit(20)
            ->get();

        $best = null;

        foreach ($candidates as $pred) {
            $match = $pred->match;
            if (! $match) continue;

            // Cross-validate with Gemini using full extended stats + H2H
            $cacheKey = 'rollover_stats_' . $match->id;
            [$homeStats, $awayStats, $h2h] = Cache::remember($cacheKey, now()->addHours(3), function () use ($match) {
                return [
                    $this->predictionService->extendedTeamStats($match->home_team, $match->match_time, $match->id),
                    $this->predictionService->extendedTeamStats($match->away_team, $match->match_time, $match->id),
                    $this->predictionService->headToHead($match->home_team, $match->away_team, $match->match_time, $match->id),
                ];
            });

            $gemini    = $this->gemini->rolloverVerdict(
                match:          $match,
                groqOutcome:    $pred->predicted_outcome,
                groqConfidence: $pred->confidence,
                homeStats:      $homeStats,
                awayStats:      $awayStats,
                h2h:            $h2h,
            );

            // Both AIs must agree — no fallback to single-AI picks
            if ($gemini === null || ! $gemini['agree']) {
                continue;
            }

            // Prefer highest confidence among agreed picks
            if ($best === null || $pred->confidence > $best['prediction']->confidence) {
                $best = [
                    'prediction' => $pred,
                    'gemini'     => $gemini,
                    'bothAgree'  => true,
                ];
            }
            // Strong dual-AI + high confidence — stop searching
            if ($pred->confidence >= 85) break;
        }

        if (! $best) {
            Log::info('RolloverService: no match had dual-AI agreement today — no pick selected.');
            return null;
        }

        $pred        = $best['prediction'];
        $displayOdds = $this->impliedOdds($pred); // display only, not selection criteria

        return RolloverPick::create([
            'challenge_id'     => $challenge->id,
            'match_id'         => $pred->match_id,
            'prediction_id'    => $pred->id,
            'day_number'       => $dayNumber,
            'pick_date'        => $today,
            'implied_odds'     => $displayOdds,
            'stake_amount'     => $stake,
            'potential_return' => round($stake * $displayOdds, 2),
            'groq_verdict'     => $pred->predicted_outcome,
            'gemini_verdict'   => $best['gemini']['outcome'] ?? null,
            'both_agree'       => $best['bothAgree'],
            'status'           => 'pending',
        ]);
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
                $league     = $match->league ?? '';

                if ($newStatus === 'won') {
                    $this->oneSignal->sendPickOutcome(
                        title: "🔥 Rollover Day {$pick->day_number} WON! 💰",
                        body:  ($league ? "{$league} | " : '') . "{$matchLabel} {$score} — {$tip} ✅ The pot grows! Tap to track your returns.",
                        path:  '/rollover',
                    );
                } else {
                    $this->oneSignal->sendPickOutcome(
                        title: "😔 Rollover Day {$pick->day_number} — Lost",
                        body:  ($league ? "{$league} | " : '') . "{$matchLabel} {$score} — Football can be cruel. Fresh challenge coming soon 💪",
                        path:  '/rollover',
                    );
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
