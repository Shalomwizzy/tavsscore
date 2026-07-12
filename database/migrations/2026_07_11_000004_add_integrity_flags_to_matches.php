<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixture-integrity annotations (Phase 1.5.2).
 *
 * `integrity_flags` (JSON) holds an array of flag codes discovered on the
 * match: 'duplicate', 'back_to_back', 'blowout', 'result_before_kickoff'.
 * Kept as an array so we can add new checks without another migration.
 *
 * `held_for_review` gates the settler — blowout scorelines (8+ per side)
 * often signal data corruption; we exclude them from Brier / hit-rate
 * metrics until a human confirms. Statistical models are worthless on
 * dirty data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->json('integrity_flags')->nullable()->after('match_time');
            $table->boolean('held_for_review')->default(false)->after('integrity_flags');
            $table->index('held_for_review', 'matches_held_for_review_idx');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('matches_held_for_review_idx');
            $table->dropColumn(['integrity_flags', 'held_for_review']);
        });
    }
};
