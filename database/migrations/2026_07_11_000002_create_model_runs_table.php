<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per training / refit of a prediction model. `model_version` is the
 * link to `prediction_logs.model_version` — every ship gate decision, backtest
 * comparison, and A/B run traces back here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_runs', function (Blueprint $table) {
            $table->id();
            $table->string('model_version', 32)->unique();
            $table->timestamp('trained_at')->nullable();
            $table->date('training_data_start')->nullable();
            $table->date('training_data_end')->nullable();
            $table->json('hyperparameters')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_runs');
    }
};
