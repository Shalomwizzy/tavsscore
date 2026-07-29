<?php

namespace App\Services\Booking;

use App\Models\Setting;

/**
 * Personal auto-bet staking rules, all admin-controlled via Setting keys.
 *
 * Sizing is inverse to odds — bigger stakes on safer, lower-odds slips; tiny
 * stakes on longshots — then clamped to the global [min, max]. High-risk tickets
 * get their own band. Everything here is configuration + arithmetic only; it
 * never places a bet.
 */
class StakePlanService
{
    public const DEFAULTS = [
        'autobet_enabled'         => '0',
        'autobet_min_stake'       => '100',
        'autobet_max_stake'       => '5000',
        'autobet_daily_cap'       => '20000',
        'autobet_stake_low_odds'  => '2000', // odds 2–20   (safest)
        'autobet_stake_mid_odds'  => '500',  // odds 20–200
        'autobet_stake_high_odds' => '100',  // odds ≥ 200  (longshots)
        'autobet_stake_high_risk' => '200',  // high-risk tickets
    ];

    /** @return array<string,int> the numeric config, defaults applied. */
    public function config(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = (int) round((float) Setting::get($key, $default));
        }
        return $out;
    }

    public function isArmed(): bool
    {
        return (int) Setting::get('autobet_enabled', self::DEFAULTS['autobet_enabled']) === 1;
    }

    /**
     * Stake for a slip of the given total odds. High-risk uses its own band.
     * Result is clamped to the admin min/max.
     */
    public function stakeFor(float $odds, bool $highRisk = false): int
    {
        $c = $this->config();

        $base = match (true) {
            $highRisk    => $c['autobet_stake_high_risk'],
            $odds < 20.0 => $c['autobet_stake_low_odds'],
            $odds < 200. => $c['autobet_stake_mid_odds'],
            default      => $c['autobet_stake_high_odds'],
        };

        return (int) max($c['autobet_min_stake'], min($c['autobet_max_stake'], $base));
    }
}
