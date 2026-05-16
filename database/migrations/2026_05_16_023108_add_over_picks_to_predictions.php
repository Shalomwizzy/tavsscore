<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            // Over 1.5 Goals daily picks
            $table->boolean('is_over15_pick')->default(false)->after('is_gg_pick');
            $table->unsignedSmallInteger('over15_rank')->nullable()->after('is_over15_pick');
            $table->boolean('over15_notified')->default(false)->after('over15_rank');

            // Over 2.5 Goals daily picks
            $table->boolean('is_over25_pick')->default(false)->after('over15_notified');
            $table->unsignedSmallInteger('over25_rank')->nullable()->after('is_over25_pick');
            $table->boolean('over25_notified')->default(false)->after('over25_rank');

            // Team to Score 3+ Goals daily picks
            $table->boolean('is_team3plus_pick')->default(false)->after('over25_notified');
            $table->unsignedSmallInteger('team3plus_rank')->nullable()->after('is_team3plus_pick');
            $table->string('team3plus_label', 10)->nullable()->after('team3plus_rank'); // 'Home' or 'Away'
            $table->boolean('team3plus_notified')->default(false)->after('team3plus_label');

            // Poisson-derived per-team 3+ goals probabilities
            $table->decimal('home_3plus_prob', 5, 2)->nullable()->after('team3plus_notified');
            $table->decimal('away_3plus_prob', 5, 2)->nullable()->after('home_3plus_prob');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn([
                'is_over15_pick', 'over15_rank', 'over15_notified',
                'is_over25_pick', 'over25_rank', 'over25_notified',
                'is_team3plus_pick', 'team3plus_rank', 'team3plus_label', 'team3plus_notified',
                'home_3plus_prob', 'away_3plus_prob',
            ]);
        });
    }
};
