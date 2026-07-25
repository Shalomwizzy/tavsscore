<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_api_id');
            $table->string('player_name');
            $table->string('player_photo')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('nationality')->nullable();

            $table->unsignedBigInteger('team_api_id');
            $table->string('team_name');
            $table->unsignedInteger('league_id');
            $table->unsignedSmallInteger('season');

            $table->string('position')->nullable();
            $table->unsignedSmallInteger('appearances')->default(0);
            $table->unsignedSmallInteger('lineups')->default(0);
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->unsignedSmallInteger('goals')->default(0);
            $table->unsignedSmallInteger('assists')->default(0);
            $table->unsignedSmallInteger('yellow_cards')->default(0);
            $table->unsignedSmallInteger('red_cards')->default(0);
            $table->decimal('rating', 4, 2)->nullable();

            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['player_api_id', 'team_api_id', 'league_id', 'season'], 'player_stats_unique');
            $table->index(['league_id', 'season']);
            $table->index(['team_api_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_statistics');
    }
};
