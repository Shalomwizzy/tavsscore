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
    private const TZ = 'Africa/Lagos';

    // Rollover legs must clear a high model-probability floor. 90% so a 10-day
    // run has a real chance of surviving. Admin-tunable in Settings.
    private const MIN_BOARD_PROB = 90.0;
    private const DAYS_PER_RUN   = 10;

    // A day's pick is a small accumulator, not necessarily one match: 1-5 of
    // the safest legs combined, targeting (but never exceeding) ~2.00 total
    // odds. Both admin-tunable in Settings.
    private const MAX_LEGS_PER_DAY = 5;
    private const TARGET_DAY_ODDS  = 2.0;

    public function __construct(
        private readonly TelegramService  $telegram,
        private readonly OneSignalService $oneSignal,
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
            $allWon = $lastComplete->picks()->where('status', 'lost')->doesntExist()
                && $lastComplete->picks()->where('day_number', self::DAYS_PER_RUN)->where('status', 'won')->exists();
            $updatedToday = $lastComplete->updated_at?->timezone($tz)->toDateString() === $today;
            if ($allWon && $updatedToday) {
                Log::info('RolloverService: rest day after 10-win challenge — no pick today.');
                return null;
            }
        }

        $challenge = $this->getOrCreateChallenge();

        // Already have a ticket today
        if ($challenge->picks()->where('pick_date', $today)->exists()) {
            return null;
        }

        // A day may hold several legs, so day number = highest day so far + 1.
        $dayNumber = (int) ($challenge->picks()->max('day_number') ?? 0) + 1;

        // Challenge already ran its 10 days
        if ($dayNumber > self::DAYS_PER_RUN) {
            $challenge->update(['status' => 'complete']);
            return null;
        }

        // Previous day's whole ticket must have settled without a loss first.
        if ($dayNumber > 1) {
            $prevLegs = $challenge->picks()->where('day_number', $dayNumber - 1)->get();
            if ($prevLegs->contains(fn ($leg) => $leg->status === 'lost')) {
                $challenge->update(['status' => 'complete']);
                return null;
            }
            if ($prevLegs->contains(fn ($leg) => $leg->status === 'pending')) {
                // Previous ticket not fully settled yet — wait
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

        // Safety-first: consider every covered fixture that has a market board;
        // we derive the leg from the board, not from the arbiter's headline pick.
        $candidates = Prediction::query()
            ->with('match')
            ->whereNotNull('market_board')
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [
                    CarbonImmutable::now($tz)->startOfDay(),
                    CarbonImmutable::now($tz)->endOfDay(),
                ])
                ->whereNotIn('status', ['CANC', 'PST', 'FT', 'AET', 'PEN', 'ABD'])
                ->whereIn('league_id', $eligibleLeagues)
            )
            ->get();

        // Diagnostic: log the funnel size at each step. Silent misses are the
        // exact class of failure that stalled rollover automation for two
        // months — surface it so future ones are debuggable in one grep.
        Log::info('RolloverService: candidate funnel', [
            'day_number'         => $dayNumber,
            'eligible_leagues'   => count($eligibleLeagues),
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
            $match = $pred->match;
            if (! $match) continue;

            // Skip fixtures the panel flagged as genuinely uncertain.
            $tips  = is_array($pred->tips) ? $pred->tips : [];
            if (($tips[0]['agreement_level'] ?? null) === 'conflict') continue;

            // Take the SAFEST market on this fixture's board that clears the floor.
            $leg = $this->safestLeg($pred, $minBoardProb);
            if ($leg === null) continue;

            $qualifiedPicks[] = [
                'prediction' => $pred,
                'market'     => $leg['market'],
                'boardProb'  => $leg['prob'],
                'allAgree'   => ($tips[0]['agreement_level'] ?? null) === 'strong',
            ];
        }

        Log::info('RolloverService: candidate funnel', [
            'day_number'       => $dayNumber,
            'min_board_prob'   => $minBoardProb,
            'candidates_found' => $candidates->count(),
            'qualified'        => count($qualifiedPicks),
        ]);

        if (empty($qualifiedPicks)) {
            Log::info('RolloverService: no fixture cleared the safety floor today — no pick selected.', [
                'candidates_examined' => $candidates->count(),
                'floor'               => $minBoardProb,
            ]);
            return null;
        }

        // Market diversity — legs whose market was used yesterday sort last, so
        // the ticket leads with fresh markets but can still use a repeat when
        // it's the only way to fill the day. Safest (highest board prob) first.
        $freshPicks  = array_values(array_filter($qualifiedPicks, fn ($p) => $p['market'] !== $yesterdayMarket));
        $repeatPicks = array_values(array_filter($qualifiedPicks, fn ($p) => $p['market'] === $yesterdayMarket));

        // Sort by: not-recently-used → highest model board probability (safest leg).
        $sortFn = function (array $a, array $b) use ($recentMarkets): int {
            $aUsed = in_array($a['market'], $recentMarkets, true);
            $bUsed = in_array($b['market'], $recentMarkets, true);
            if ($aUsed !== $bUsed) return $aUsed ? 1 : -1;
            return $b['boardProb'] <=> $a['boardProb'];
        };
        usort($freshPicks,  $sortFn);
        usort($repeatPicks, $sortFn);

        // Build the day's ticket: greedily stack the safest legs (one per match)
        // until we approach — but never exceed — the target combined odds, or
        // run out of legs / hit the per-day cap. Each leg's odds come from its
        // own board probability (90% → 1.11), so 4-5 safe legs ≈ 1.5-1.8 total.
        $maxLegs    = (int) \App\Models\Setting::get('rollover_max_legs', (string) self::MAX_LEGS_PER_DAY);
        $targetOdds = (float) \App\Models\Setting::get('rollover_target_odds', (string) self::TARGET_DAY_ODDS);

        $ordered = array_merge($freshPicks, $repeatPicks);

        $legs         = [];
        $combinedOdds = 1.0;
        $usedMatches  = [];

        foreach ($ordered as $candidate) {
            if (count($legs) >= $maxLegs) break;

            $matchId = $candidate['prediction']->match_id;
            if (isset($usedMatches[$matchId])) continue;

            $legOdds = round(1 / ($candidate['boardProb'] / 100), 2);
            if (! empty($legs) && $combinedOdds * $legOdds > $targetOdds) continue;

            $legs[] = array_merge($candidate, ['odds' => $legOdds]);
            $combinedOdds *= $legOdds;
            $usedMatches[$matchId] = true;
        }

        $combinedOdds    = round($combinedOdds, 2);
        $potentialReturn = round($stake * $combinedOdds, 2);

        // A challenge's real clock starts with its first pick, not its row
        // creation. Without this, a challenge created during a dry spell
        // (e.g. off-season, or the May-July selection outage) shows a
        // months-old start date with zero picks on the public page.
        if ($dayNumber === 1) {
            $challenge->update(['started_at' => $today]);
        }

        // One row per leg. stake_amount and potential_return are DAY-level values
        // (identical on every leg of the ticket) so the next day's stake carries
        // the full combo return; implied_odds is the leg's own price.
        $firstPick = null;
        foreach ($legs as $leg) {
            $pick = RolloverPick::create([
                'challenge_id'     => $challenge->id,
                'match_id'         => $leg['prediction']->match_id,
                'prediction_id'    => $leg['prediction']->id,
                'day_number'       => $dayNumber,
                'pick_date'        => $today,
                'implied_odds'     => $leg['odds'],
                'stake_amount'     => $stake,
                'potential_return' => $potentialReturn,
                'groq_verdict'     => $leg['market'],
                'gemini_verdict'   => null,
                'both_agree'       => $leg['allAgree'],
                'status'           => 'pending',
            ]);
            $firstPick ??= $pick;
        }

        Log::info('RolloverService: ticket built', [
            'day_number'    => $dayNumber,
            'legs'          => count($legs),
            'combined_odds' => $combinedOdds,
            'target_odds'   => $targetOdds,
        ]);

        // Notify users about the day's ticket (single message covering all legs).
        $legLines = array_map(
            fn ($leg) => "{$leg['prediction']->match?->home_team} vs {$leg['prediction']->match?->away_team} — {$leg['market']} @ {$leg['odds']}",
            $legs,
        );
        $matchLabel  = implode("\n", $legLines);
        $marketLabel = count($legs) > 1
            ? count($legs) . "-leg ticket @ {$combinedOdds} odds"
            : $legs[0]['market'];
        $league  = count($legs) === 1
            ? LeagueCoverage::formatName($legs[0]['prediction']->match?->league, $legs[0]['prediction']->match?->league_country)
            : 'Multi-league ticket';
        $siteUrl = config('app.url');

        $this->oneSignal->notifyRolloverPick($dayNumber, $matchLabel, $marketLabel, $stake, $potentialReturn);

        $this->telegram->sendRolloverPick(
            $matchLabel,
            $marketLabel,
            $dayNumber,
            $stake,
            $potentialReturn,
            $siteUrl,
            $league,
        );

        return $firstPick;
    }

    /**
     * The safest gradeable leg on a fixture's board: highest-probability market
     * from the ENTIRE board that clears the floor. Uses the same "meaningful
     * markets only" blocklist as the headline pick so a leg is both ~90%+ safe
     * AND carries real odds — never a valueless "Under 5.5 Goals" at 1.02.
     */
    private function safestLeg(Prediction $pred, float $minProb): ?array
    {
        return PickHelpers::safestBoardMarket($pred->market_board, $minProb, PickHelpers::headlineBlock());
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
                // Void / push (e.g. Draw No Bet on a draw, postponed leg): the leg
                // drops out of the ticket at odds 1.0 — the day's potential return
                // shrinks accordingly on EVERY leg of that day, and the challenge
                // continues. It is settled, not a loss, not left pending.
                $pick->update([
                    'result_score' => "{$match->home_score}-{$match->away_score}",
                    'status'       => 'void',
                ]);

                $legOdds = max(1.0, (float) $pick->implied_odds);
                if ($legOdds > 1.0) {
                    RolloverPick::query()
                        ->where('challenge_id', $pick->challenge_id)
                        ->where('day_number', $pick->day_number)
                        ->get()
                        ->each(fn (RolloverPick $leg) => $leg->update([
                            'potential_return' => round((float) $leg->potential_return / $legOdds, 2),
                        ]));
                }

                Log::info("RolloverService: pick {$pick->id} tip='{$tip}' voided (push) — day return adjusted, challenge continues.");
                continue;
            }

            $score   = "{$match->home_score}-{$match->away_score}";
            $newStatus = $wasCorrect ? 'won' : 'lost';

            $pick->update([
                'was_correct'  => $wasCorrect,
                'result_score' => $score,
                'status'       => $newStatus,
            ]);

            // Any lost leg kills the whole ticket — challenge over.
            if ($newStatus === 'lost' && $pick->challenge?->status === 'active') {
                $pick->challenge->update(['status' => 'complete']);
            }

            // Day 10 completes the challenge only once EVERY leg of the final
            // ticket has settled without a loss.
            if ($newStatus === 'won' && $pick->day_number >= self::DAYS_PER_RUN) {
                $finalDayOpen = RolloverPick::query()
                    ->where('challenge_id', $pick->challenge_id)
                    ->where('day_number', $pick->day_number)
                    ->where('status', 'pending')
                    ->exists();
                if (! $finalDayOpen) {
                    $pick->challenge?->update(['status' => 'complete']);
                }
            }

            $this->notifyDayOutcome($pick);
        }
    }

    /**
     * Notify ONCE per day, after the day's whole ticket has settled — with
     * multi-leg tickets, per-leg pushes would spam users and misreport the day
     * as won while sibling legs were still open. A lost leg triggers the loss
     * notification immediately (the day is decided the moment one leg dies).
     */
    private function notifyDayOutcome(RolloverPick $pick): void
    {
        $legs = RolloverPick::query()
            ->with('match')
            ->where('challenge_id', $pick->challenge_id)
            ->where('day_number', $pick->day_number)
            ->get();

        $anyLost = $legs->contains(fn ($l) => $l->status === 'lost');
        $allDone = $legs->every(fn ($l) => $l->status !== 'pending');

        if (! $anyLost && ! $allDone) {
            return; // ticket still open and not yet dead — wait for remaining legs
        }

        $cacheKey = "rollover_notified_day_{$pick->challenge_id}_{$pick->day_number}";
        if (Cache::has($cacheKey)) {
            return;
        }

        $dayStatus  = $anyLost ? 'lost' : 'won';
        $legLines   = $legs->map(function (RolloverPick $l) {
            $icon = match ($l->status) { 'won' => '✅', 'lost' => '❌', 'void' => '↩️', default => '⏳' };
            return "{$l->match?->home_team} vs {$l->match?->away_team} — {$l->groq_verdict} ({$l->result_score}) {$icon}";
        })->implode("\n");
        $tipLabel = $legs->count() > 1 ? $legs->count() . '-leg ticket' : ($legs->first()?->groq_verdict ?? '—');
        $scoreLbl = $legs->count() > 1 ? '—' : (string) ($legs->first()?->result_score ?? '—');
        $siteUrl  = config('app.url');
        $league   = $legs->count() > 1
            ? 'Multi-league ticket'
            : LeagueCoverage::formatName($legs->first()?->match?->league, $legs->first()?->match?->league_country);

        if ($dayStatus === 'won') {
            $this->oneSignal->notifyRolloverWon($pick->day_number, $legLines, $scoreLbl, (float) $pick->potential_return);
        } else {
            $this->oneSignal->notifyRolloverLost($pick->day_number, $legLines, $scoreLbl);
        }

        $this->telegram->sendRolloverOutcome(
            match:   $legLines,
            tip:     $tipLabel,
            score:   $scoreLbl,
            status:  $dayStatus,
            day:     $pick->day_number,
            stake:   (float) $pick->stake_amount,
            returns: (float) $pick->potential_return,
            siteUrl: $siteUrl,
            league:  $league,
        );

        // Winner upload reminder — DB flag so it survives cache:clear and deploys
        if ($dayStatus === 'won' && ! $pick->winner_reminder_sent) {
            $this->oneSignal->notifyWinnerReminder();
            $this->telegram->sendWinnerUploadReminder($siteUrl);
            $pick->update(['winner_reminder_sent' => true]);
        }

        Cache::put($cacheKey, true, now()->addDays(3));
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Day N's stake = day N-1's full ticket return. potential_return is stored
     * day-level (identical on every leg), so any won leg carries it. If every
     * leg voided, the stake simply carries over unchanged.
     */
    private function calculateStake(RolloverChallenge $challenge, int $dayNumber): float
    {
        if ($dayNumber === 1) return (float) $challenge->initial_stake;

        $prevLegs = $challenge->picks()->where('day_number', $dayNumber - 1)->get();
        if ($prevLegs->isEmpty() || $prevLegs->contains(fn ($l) => $l->status === 'lost')) {
            return (float) $challenge->initial_stake;
        }

        $won = $prevLegs->firstWhere('status', 'won');
        if ($won) {
            return (float) $won->potential_return;
        }

        // All legs voided — stake rolls forward untouched.
        return (float) $prevLegs->first()->stake_amount;
    }

}
