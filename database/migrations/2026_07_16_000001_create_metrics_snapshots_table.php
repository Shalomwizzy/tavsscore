<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — continuous evaluation. Weekly cron writes one row per
 * (model_version, market, league_id) with the last-7-day Brier score
 * and hit rate on live predictions. Historical rows accumulate; the
 * dashboard can chart drift over time.
 *
 * A separate CalibrationSnapshot table already exists for the monthly
 * calibration story. This table is the weekly *live* signal for
 * per-league / per-market degradation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->string('model_version', 32);
            $table->string('market', 32);
            $table->unsignedBigInteger('league_id')->nullable();
            $table->unsignedInteger('n');
            $table->unsignedInteger('wins');
            $table->double('brier')->nullable();
            $table->double('log_loss')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['period_start', 'model_version', 'market', 'league_id'], 'metrics_snapshots_uq');
            $table->index(['model_version', 'market', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics_snapshots');
    }
};
