<?php

namespace App\Services\Booking;

use App\Models\BookingCodeLeg;
use Illuminate\Support\Facades\Cache;

/**
 * Cautious feedback from completed booking-code legs.
 *
 * This is deliberately separate from probability calibration: booking legs are
 * selected tickets, not a random sample of every forecast. Their results are a
 * useful safety signal for the ticket builder, but must never be double-counted
 * as the model's global accuracy. Once a market has a meaningful sample, a
 * persistently weak selected-market record prevents it from being used in a new
 * booking ticket until its record recovers.
 */
class BookingOutcomeLearningService
{
    public const MIN_SAMPLE = 20;
    public const MIN_WIN_RATE = 0.50;

    /** @return array<string,array{settled:int,wins:int,win_rate:float}> */
    public function marketFeedback(): array
    {
        return Cache::remember('booking_market_feedback_v1', now()->addHours(3), function (): array {
            return BookingCodeLeg::query()
                ->selectRaw("market, COUNT(*) AS settled, SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) AS wins")
                ->whereIn('status', ['won', 'lost'])
                ->where('settled_at', '>=', now('Africa/Lagos')->subDays(180))
                ->groupBy('market')
                ->get()
                ->mapWithKeys(fn ($row) => [(string) $row->market => [
                    'settled' => (int) $row->settled,
                    'wins' => (int) $row->wins,
                    'win_rate' => $row->settled ? round(((int) $row->wins / (int) $row->settled) * 100, 1) : 0.0,
                ]])
                ->all();
        });
    }

    /** A safety veto only after 20 settled selected legs; new markets stay eligible. */
    public function permits(string $market): bool
    {
        $feedback = $this->marketFeedback()[$market] ?? null;

        return ! $feedback
            || $feedback['settled'] < self::MIN_SAMPLE
            || ($feedback['win_rate'] / 100) >= self::MIN_WIN_RATE;
    }

    public function forget(): void
    {
        Cache::forget('booking_market_feedback_v1');
    }
}
