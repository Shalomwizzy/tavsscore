<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_pi_ratings', function (Blueprint $table) {
            $table->id();
            $table->string('team')->unique();
            // Separate home and away pi-ratings (key advantage over Elo).
            // Updated after every resolved match using the goal-difference vs expectation.
            $table->float('pi_home')->default(0.0);
            $table->float('pi_away')->default(0.0);
            $table->unsignedInteger('matches_rated')->default(0);
            $table->timestamp('last_match_at')->nullable();
            $table->timestamps();

            $table->index('team');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_pi_ratings');
    }
};
