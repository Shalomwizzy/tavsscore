<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_injuries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('match_id');
            $table->unsignedBigInteger('team_api_id')->nullable();
            $table->string('team_name');
            $table->unsignedBigInteger('player_api_id')->nullable();
            $table->string('player_name');
            $table->string('player_photo')->nullable();
            $table->string('type')->nullable();   // e.g. "Missing Fixture"
            $table->string('reason')->nullable();  // e.g. "Injury", "Suspended"
            $table->timestamps();

            $table->unique(['match_id', 'player_api_id'], 'match_injury_unique');
            $table->index('match_id');
            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_injuries');
    }
};
