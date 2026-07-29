<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auto-bet was removed (SportyBet blocks reliable automated placement). Purge its
 * settings — including the encrypted SportyBet credentials — so nothing is left
 * stored in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->whereIn('key', [
            'autobet_enabled', 'autobet_min_stake', 'autobet_max_stake', 'autobet_daily_cap',
            'autobet_stake_low_odds', 'autobet_stake_mid_odds', 'autobet_stake_high_odds', 'autobet_stake_high_risk',
            'sporty_phone_enc', 'sporty_password_enc',
        ])->delete();
    }

    public function down(): void
    {
        // One-way cleanup; nothing to restore.
    }
};
