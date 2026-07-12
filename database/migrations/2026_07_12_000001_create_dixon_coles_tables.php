<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dixon-Coles model parameter storage (Phase 2).
 *
 * dc_league_params: one row per (league_id, model_version). Holds the
 *   league-wide parameters — home advantage γ, low-score correction ρ,
 *   time decay half-life used at fit time, training-set metadata.
 * dc_team_params: one row per (league_id, model_version, team). Holds each
 *   team's log attack strength α and log defense strength β.
 *
 * A fixture prediction combines the two: joint Poisson-DC matrix
 * P(x,y) with rates λ_home = exp(α_home + β_away + γ), λ_away = exp(α_away + β_home).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dc_league_params', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('league_id');
            $table->string('model_version', 32);

            $table->double('gamma')->comment('log home advantage');
            $table->double('rho')->comment('DC low-score correction');
            $table->double('half_life_days')->comment('time-decay half-life used at fit time');

            $table->timestamp('fit_at');
            $table->date('training_start');
            $table->date('training_end');
            $table->unsignedInteger('training_matches');
            $table->double('final_log_likelihood')->nullable();
            $table->unsignedInteger('iterations')->nullable();
            $table->boolean('converged')->default(false);

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['league_id', 'model_version'], 'dc_league_version_uq');
        });

        Schema::create('dc_team_params', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('league_id');
            $table->string('model_version', 32);
            $table->string('team_name');

            $table->double('attack')->comment('log attack strength α');
            $table->double('defense')->comment('log defense strength β');
            $table->unsignedInteger('matches_used');
            $table->boolean('is_shrunk')->default(false)->comment('true if fewer than n_min matches → shrunk to league mean');

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['league_id', 'model_version', 'team_name'], 'dc_team_version_uq');
            $table->index(['league_id', 'model_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_team_params');
        Schema::dropIfExists('dc_league_params');
    }
};
