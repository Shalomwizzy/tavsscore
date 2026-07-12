<?php

namespace App\Services;

use App\Models\FootballMatch;
use Illuminate\Support\Facades\Log;

/**
 * Sanity checks on ingested fixtures (Phase 1.5.2).
 *
 * Runs after each match is upserted. Flags are additive — the same match can
 * carry several — and stored on `integrity_flags` as an array of codes.
 * Only `blowout` currently sets `held_for_review = true`, which excludes the
 * match from settlement and metrics until a human clears it.
 */
class FixtureIntegrityService
{
    public const FLAG_DUPLICATE            = 'duplicate';
    public const FLAG_BACK_TO_BACK         = 'back_to_back';
    public const FLAG_BLOWOUT              = 'blowout';
    public const FLAG_RESULT_BEFORE_KICKOFF = 'result_before_kickoff';

    /**
     * Statistical models are worthless on dirty data — anything above this
     * on a single side is either a data glitch or a truly historic anomaly.
     * Either way, hold it out of training until confirmed.
     */
    public const BLOWOUT_THRESHOLD = 8;

    private const DUPLICATE_WINDOW_HOURS     = 24;
    private const BACK_TO_BACK_WINDOW_HOURS  = 48;
    private const FINISHED_STATUSES          = ['FT', 'AET', 'PEN'];

    /**
     * Run every check against the match and persist flags + held_for_review.
     * Returns the array of flag codes discovered on this pass.
     *
     * @return string[]
     */
    public function evaluate(FootballMatch $match): array
    {
        $flags = [];

        if ($this->hasDuplicate($match))     $flags[] = self::FLAG_DUPLICATE;
        if ($this->hasBackToBack($match))    $flags[] = self::FLAG_BACK_TO_BACK;
        if ($this->hasResultBeforeKickoff($match)) $flags[] = self::FLAG_RESULT_BEFORE_KICKOFF;

        $blowout = $this->hasBlowout($match);
        if ($blowout) $flags[] = self::FLAG_BLOWOUT;

        if (empty($flags) && $match->integrity_flags === null && ! $match->held_for_review) {
            return [];
        }

        $held = $match->held_for_review || $blowout;

        $match->forceFill([
            'integrity_flags' => $flags ?: null,
            'held_for_review' => $held,
        ])->save();

        if ($flags) {
            Log::info('FixtureIntegrity: flags recorded', [
                'match_id' => $match->id,
                'api_id'   => $match->api_id,
                'flags'    => $flags,
                'held'     => $held,
            ]);
        }

        return $flags;
    }

    public function hasDuplicate(FootballMatch $match): bool
    {
        if (! $match->match_time) return false;

        $windowStart = $match->match_time->copy()->subHours(self::DUPLICATE_WINDOW_HOURS);
        $windowEnd   = $match->match_time->copy()->addHours(self::DUPLICATE_WINDOW_HOURS);

        return FootballMatch::query()
            ->where('id', '!=', $match->id)
            ->where('home_team', $match->home_team)
            ->where('away_team', $match->away_team)
            ->whereBetween('match_time', [$windowStart, $windowEnd])
            ->exists();
    }

    public function hasBackToBack(FootballMatch $match): bool
    {
        if (! $match->match_time) return false;

        $windowStart = $match->match_time->copy()->subHours(self::BACK_TO_BACK_WINDOW_HOURS);
        $windowEnd   = $match->match_time->copy()->addHours(self::BACK_TO_BACK_WINDOW_HOURS);

        return FootballMatch::query()
            ->where('id', '!=', $match->id)
            ->whereBetween('match_time', [$windowStart, $windowEnd])
            ->where(function ($q) use ($match) {
                $q->whereIn('home_team', [$match->home_team, $match->away_team])
                  ->orWhereIn('away_team', [$match->home_team, $match->away_team]);
            })
            ->exists();
    }

    public function hasBlowout(FootballMatch $match): bool
    {
        if (! in_array($match->status, self::FINISHED_STATUSES, true)) return false;
        if ($match->home_score === null || $match->away_score === null) return false;

        return (int) $match->home_score >= self::BLOWOUT_THRESHOLD
            || (int) $match->away_score >= self::BLOWOUT_THRESHOLD;
    }

    /**
     * Result data present but match hasn't kicked off yet — the provider
     * shipped stale data or a phantom scoreline. Reject the numbers by
     * flagging; downstream can decide whether to clear the score or hold.
     */
    public function hasResultBeforeKickoff(FootballMatch $match): bool
    {
        if (! $match->match_time) return false;
        if ($match->home_score === null && $match->away_score === null) return false;

        return $match->match_time->isFuture();
    }
}
