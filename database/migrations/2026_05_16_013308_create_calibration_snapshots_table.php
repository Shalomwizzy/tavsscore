<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('calibration_snapshots', function (Blueprint $table) {
            $table->id();
            // "2026-05" — one row per month, enforced unique
            $table->string('period_label', 7)->unique();
            // Learned minimum confidence threshold at snapshot time
            $table->unsignedTinyInteger('threshold');
            // 30-day accuracy at snapshot time (null = insufficient data)
            $table->decimal('acc_pct', 5, 2)->nullable();
            // How many picks resolved in the 30 days before snapshot
            $table->unsignedSmallInteger('total_picks')->default(0);
            // Average calibration error: actual_win_pct − ai_confidence_midpoint.
            // Negative = AI overconfident. Getting closer to 0 over months = learning.
            $table->decimal('calibration_error_avg', 5, 2)->nullable();
            // How many cold markets detected at snapshot time
            $table->unsignedTinyInteger('cold_markets_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_snapshots');
    }
};
