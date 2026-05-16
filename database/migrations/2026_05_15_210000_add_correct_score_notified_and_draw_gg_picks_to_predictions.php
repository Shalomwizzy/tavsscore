<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            // Bug fix: DB-backed flag so correct score notifications don't
            // re-fire after cache:clear on every production deploy
            $table->boolean('correct_score_notified')->default(false)->after('likely_scores');

            // Draw picks page — top 5 predictions where all 3 AIs agree on Draw
            $table->boolean('is_draw_pick')->default(false)->after('correct_score_notified');
            $table->unsignedTinyInteger('draw_rank')->nullable()->after('is_draw_pick');

            // GG picks page — top 5 predictions where all 3 AIs agree on Both Teams Score
            $table->boolean('is_gg_pick')->default(false)->after('draw_rank');
            $table->unsignedTinyInteger('gg_rank')->nullable()->after('is_gg_pick');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['correct_score_notified', 'is_draw_pick', 'draw_rank', 'is_gg_pick', 'gg_rank']);
        });
    }
};
