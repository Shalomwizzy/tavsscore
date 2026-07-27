<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fantasy_squads', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('league_id')->default(39);   // Premier League
            $table->unsignedSmallInteger('season');
            $table->string('gameweek', 40);                       // e.g. "Week of Jul 28"
            $table->string('formation', 12)->default('3-4-3');
            $table->decimal('budget_used', 5, 1)->default(0);     // in £m
            $table->unsignedInteger('total_points')->default(0);
            $table->string('captain')->nullable();
            $table->string('vice_captain')->nullable();
            $table->json('starting_xi');                          // [{player fields, role, is_captain}]
            $table->json('bench');
            $table->json('transfers_in');                         // "players to buy" suggestions
            $table->timestamp('built_at')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'season', 'gameweek']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fantasy_squads');
    }
};
