<?php

namespace App\Services\Booking;

use App\Models\BookingCode;
use App\Models\FootballMatch;
use App\Support\PickHelpers;

/** Synchronises ticket legs and grades each saved selection against final scores. */
class BookingCodeLedgerService
{
    private const FINISHED = ['FT', 'AET', 'PEN'];

    public function __construct(private readonly BookingOutcomeLearningService $learning) {}

    /** Persist the worker's exact ticket so users can always see what was booked. */
    public function syncLegs(BookingCode $code): void
    {
        foreach ((array) $code->fixtures as $fixture) {
            $requestedMatchId = isset($fixture['match_id']) && is_numeric($fixture['match_id']) ? (int) $fixture['match_id'] : null;
            // The worker can post a stale/deleted local id. Keep the leg in
            // the ledger as unresolved instead of violating the FK or losing
            // the selection the user actually received.
            $matchId = $requestedMatchId && FootballMatch::whereKey($requestedMatchId)->exists() ? $requestedMatchId : null;
            $home = (string) ($fixture['home'] ?? '');
            $away = (string) ($fixture['away'] ?? '');
            $market = (string) ($fixture['market'] ?? 'Selection');
            $key = $requestedMatchId ? 'match:'.$requestedMatchId.':'.$market : 'text:'.sha1($home.'|'.$away.'|'.$market);

            $code->legs()->updateOrCreate(
                ['source_key' => $key],
                [
                    'match_id' => $matchId,
                    'home_team' => $home ?: null,
                    'away_team' => $away ?: null,
                    'market' => $market,
                    'model_probability' => isset($fixture['model_prob']) ? (float) $fixture['model_prob'] : null,
                    'estimated_odds' => isset($fixture['est_odds']) ? (float) $fixture['est_odds'] : null,
                ],
            );
        }
    }

    /**
     * Grade every saved leg, return whether the accumulator is settled and its
     * outcome. Unknown or ungradeable legs remain pending for safe review.
     *
     * @return array{settled:bool,won:bool}
     */
    public function grade(BookingCode $code): array
    {
        $this->syncLegs($code);
        $legs = $code->legs()->with('match')->get();
        if ($legs->isEmpty()) {
            return ['settled' => false, 'won' => false];
        }

        $anyLost = false;
        $pending = false;
        $decided = 0;

        foreach ($legs as $leg) {
            $match = $leg->match;
            if (! $match && $leg->match_id) {
                $match = FootballMatch::find($leg->match_id);
            }
            if (! $match || ! in_array($match->status, self::FINISHED, true)) {
                $leg->update(['status' => $match ? 'pending' : 'unresolved']);
                $pending = true;
                continue;
            }

            $result = PickHelpers::resolveForMatch($match, $leg->market);
            if ($result === null) {
                // Push/void on a finished match (e.g. Draw No Bet on a draw): the
                // leg drops out of the accumulator at odds 1.0 — it neither wins
                // nor loses and must not keep the ticket pending.
                $leg->update([
                    'status' => 'void',
                    'home_score' => $match->home_score,
                    'away_score' => $match->away_score,
                    'settled_at' => $leg->settled_at ?? now(),
                ]);
                continue;
            }

            $leg->update([
                'status' => $result ? 'won' : 'lost',
                'home_score' => $match->home_score,
                'away_score' => $match->away_score,
                'settled_at' => $leg->settled_at ?? now(),
            ]);
            $decided++;
            $anyLost = $anyLost || ! $result;
        }

        if ($decided > 0) {
            // Booking results are a selected sample. They provide a controlled
            // ticket-builder safety veto, while the independent prediction log
            // remains the only source of global probability calibration.
            $this->learning->forget();
        }

        if ($anyLost) {
            return ['settled' => true, 'won' => false];
        }

        return ['settled' => ! $pending && $decided > 0, 'won' => ! $pending && $decided > 0];
    }
}
