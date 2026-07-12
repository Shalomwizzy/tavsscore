<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only measurement log. One row per (match, market, model_version).
 * The operational `predictions` table stays as-is and drives the UI; this
 * table exists purely so Brier score / calibration / hit-rate can be computed
 * per model_version, and so a new engine can shadow-run alongside `llm-legacy`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prediction_id')->nullable()->constrained('predictions')->nullOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedBigInteger('league_id')->nullable()->index();

            $table->string('market', 32);
            $table->string('predicted_outcome');

            $table->decimal('p_outcome', 6, 5);
            $table->decimal('p_home', 6, 5)->nullable();
            $table->decimal('p_draw', 6, 5)->nullable();
            $table->decimal('p_away', 6, 5)->nullable();

            $table->string('model_version', 32);
            // Post-lineup predictions are made closer to kickoff with strictly
            // more information than pre-lineup ones — comparing them against
            // pre-lineup predictions in the same pool would poison every future
            // Brier comparison. Dashboard filters like-for-like on this column.
            $table->enum('prediction_stage', ['pre_lineup', 'post_lineup'])->default('pre_lineup');
            // True for rows retro-materialized by predictions:seed-logs from
            // existing Prediction rows. Excludable from the dashboard if the
            // retro-computed baseline turns out to differ from what users saw.
            $table->boolean('is_backfill')->default(false);
            $table->timestamp('kickoff_at');
            $table->timestamp('created_at')->useCurrent();

            $table->string('actual_result', 8)->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->unique(['match_id', 'market', 'model_version', 'prediction_stage'], 'plog_match_market_version_stage_uq');
            $table->index(['model_version', 'market'],       'plog_version_market_idx');
            $table->index(['league_id',     'model_version'], 'plog_league_version_idx');
            $table->index(['settled_at'],                     'plog_settled_at_idx');
            $table->index(['kickoff_at'],                     'plog_kickoff_idx');
            $table->index(['is_backfill'],                    'plog_backfill_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_logs');
    }
};
