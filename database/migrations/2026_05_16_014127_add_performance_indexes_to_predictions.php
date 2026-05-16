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
        Schema::table('predictions', function (Blueprint $table) {
            // These columns are filtered on every pick-selection query, stats page,
            // and outcome resolution pass. Without indexes, full-table scans happen
            // on every request as the predictions table grows.
            $table->index(['is_daily_pick', 'was_correct'],  'pred_daily_correct_idx');
            $table->index(['is_draw_pick',  'was_correct'],  'pred_draw_correct_idx');
            $table->index(['is_gg_pick',    'was_correct'],  'pred_gg_correct_idx');
            $table->index(['correct_score_notified'],        'pred_score_notified_idx');
            // Confidence is filtered in pick selection and calibration queries
            $table->index(['is_daily_pick', 'confidence'],   'pred_daily_conf_idx');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropIndex('pred_daily_correct_idx');
            $table->dropIndex('pred_draw_correct_idx');
            $table->dropIndex('pred_gg_correct_idx');
            $table->dropIndex('pred_score_notified_idx');
            $table->dropIndex('pred_daily_conf_idx');
        });
    }
};
