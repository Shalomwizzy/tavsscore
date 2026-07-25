<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('league_id');
            $table->unsignedSmallInteger('season');
            $table->unsignedBigInteger('team_api_id');
            $table->string('team_name');
            $table->string('team_logo')->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->string('group_label')->nullable();
            $table->integer('points')->default(0);
            $table->integer('goals_diff')->default(0);
            $table->string('form', 20)->nullable();
            $table->string('status_desc')->nullable();
            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('win')->default(0);
            $table->unsignedSmallInteger('draw')->default(0);
            $table->unsignedSmallInteger('lose')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);
            $table->timestamps();

            $table->unique(['league_id', 'season', 'team_api_id'], 'standings_unique');
            $table->index(['league_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standings');
    }
};
