<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical team reference (Phase 1.5.2). Team names come in from
 * API-Football as free-form strings; the same team can appear as
 * "Man United", "Manchester United", or "Manchester Utd" across payloads.
 *
 * Permissive design: unmapped names are auto-registered as unreviewed
 * aliases pointing at a fresh canonical team. Admin can later merge
 * duplicates via /admin/team-aliases. Never blocks ingestion — silent
 * gaps in match ingestion are worse than a duplicate canonical team.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_name');
            $table->timestamp('first_seen_at')->useCurrent();
            $table->index('canonical_name');
        });

        Schema::create('team_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('alias');
            $table->string('provider', 32)->default('api-football');
            $table->boolean('reviewed')->default(false);
            $table->timestamp('first_seen_at')->useCurrent();

            $table->unique(['alias', 'provider'], 'team_alias_provider_uq');
            $table->index(['reviewed', 'first_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_aliases');
        Schema::dropIfExists('teams');
    }
};
