<?php

namespace App\Services\Booking;

use App\Models\Prediction;
use App\Models\RolloverChallenge;
use App\Services\DixonColes\TeamNameNormalizer;

/**
 * Builds today's "betslip spec" — the structured set of selections the external
 * automation worker uses to find each fixture on SportyBet / 1xBet and build a
 * booking code. Contains only match data + our market intent; no odds are
 * asserted (the worker reads live odds on the bookmaker).
 */
class BetslipSpecService
{
    public function today(): array
    {
        $tz    = config('app.timezone');
        $start = now($tz)->startOfDay();
        $end   = now($tz)->endOfDay();

        $slips = [];

        // ── Daily accumulator: today's ranked daily picks ──
        $daily = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$start, $end])
                ->whereNotIn('status', ['CANC', 'PST', 'ABD', 'FT', 'AET', 'PEN']))
            ->orderBy('pick_rank')
            ->get();

        if ($daily->isNotEmpty()) {
            $slips[] = [
                'ref'        => 'daily-acca',
                'title'      => "Daily Picks Accumulator",
                'selections' => $daily->map(fn ($p) => $this->selection($p))->filter()->values()->all(),
            ];
        }

        // ── Rollover: today's single safest leg ──
        $challenge = RolloverChallenge::query()->where('status', 'active')->latest('started_at')->first();
        if ($challenge) {
            $rolloverPick = $challenge->picks()
                ->with('match', 'prediction')
                ->where('pick_date', now($tz)->toDateString())
                ->first();

            if ($rolloverPick && $rolloverPick->match) {
                $slips[] = [
                    'ref'        => 'rollover',
                    'title'      => "Rollover — Day {$rolloverPick->day_number}",
                    'selections' => array_filter([$this->selectionFromMatch(
                        $rolloverPick->match,
                        $rolloverPick->groq_verdict ?? $rolloverPick->prediction?->predicted_outcome,
                    )]),
                ];
            }
        }

        return [
            'generated_at' => now($tz)->toIso8601String(),
            'pick_date'    => now($tz)->toDateString(),
            'platforms'    => ['sportybet', '1xbet'],
            'slips'        => array_values(array_filter($slips, fn ($s) => ! empty($s['selections']))),
        ];
    }

    private function selection(Prediction $p): ?array
    {
        return $p->match ? $this->selectionFromMatch($p->match, $p->predicted_outcome) : null;
    }

    private function selectionFromMatch($match, ?string $market): ?array
    {
        if (! $match || blank($market)) {
            return null;
        }

        return [
            'home'          => $match->home_team,
            'away'          => $match->away_team,
            'home_norm'     => TeamNameNormalizer::key($match->home_team),
            'away_norm'     => TeamNameNormalizer::key($match->away_team),
            'league'        => $match->league,
            'country'       => $match->league_country,
            'kickoff'       => $match->match_time?->toIso8601String(),
            'market'        => $market,          // e.g. "Home or Draw (1X)", "Over 2.5 Goals"
        ];
    }
}
