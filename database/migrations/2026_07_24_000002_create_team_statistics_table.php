<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('league_id');
            $table->unsignedSmallInteger('season');
            $table->unsignedBigInteger('team_api_id');
            $table->string('team_name');
            $table->string('team_logo')->nullable();
            $table->string('form')->nullable();

            $table->unsignedSmallInteger('played_total')->default(0);
            $table->unsignedSmallInteger('played_home')->default(0);
            $table->unsignedSmallInteger('played_away')->default(0);

            $table->unsignedSmallInteger('wins_total')->default(0);
            $table->unsignedSmallInteger('wins_home')->default(0);
            $table->unsignedSmallInteger('wins_away')->default(0);
            $table->unsignedSmallInteger('draws_total')->default(0);
            $table->unsignedSmallInteger('draws_home')->default(0);
            $table->unsignedSmallInteger('draws_away')->default(0);
            $table->unsignedSmallInteger('loses_total')->default(0);
            $table->unsignedSmallInteger('loses_home')->default(0);
            $table->unsignedSmallInteger('loses_away')->default(0);

            $table->unsignedSmallInteger('goals_for_total')->default(0);
            $table->unsignedSmallInteger('goals_for_home')->default(0);
            $table->unsignedSmallInteger('goals_for_away')->default(0);
            $table->unsignedSmallInteger('goals_against_total')->default(0);
            $table->unsignedSmallInteger('goals_against_home')->default(0);
            $table->unsignedSmallInteger('goals_against_away')->default(0);

            $table->decimal('goals_for_avg', 5, 2)->nullable();
            $table->decimal('goals_against_avg', 5, 2)->nullable();

            $table->unsignedSmallInteger('clean_sheets_total')->default(0);
            $table->unsignedSmallInteger('failed_to_score_total')->default(0);

            // Full API payload for anything we didn't flatten into columns.
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'season', 'team_api_id'], 'team_stats_unique');
            $table->index(['league_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_statistics');
    }
};
