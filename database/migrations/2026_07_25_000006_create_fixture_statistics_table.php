<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixture_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('match_id');
            $table->unsignedBigInteger('team_api_id')->nullable();
            $table->string('team_name');
            $table->unsignedSmallInteger('shots_total')->nullable();
            $table->unsignedSmallInteger('shots_on')->nullable();
            $table->unsignedSmallInteger('shots_off')->nullable();
            $table->unsignedTinyInteger('possession')->nullable();  // %
            $table->unsignedSmallInteger('corners')->nullable();
            $table->unsignedSmallInteger('offsides')->nullable();
            $table->unsignedSmallInteger('fouls')->nullable();
            $table->unsignedSmallInteger('yellow_cards')->nullable();
            $table->unsignedSmallInteger('red_cards')->nullable();
            $table->unsignedSmallInteger('saves')->nullable();
            $table->unsignedSmallInteger('passes_total')->nullable();
            $table->unsignedSmallInteger('passes_accurate')->nullable();
            $table->decimal('expected_goals', 5, 2)->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'team_api_id'], 'fixture_stat_unique');
            $table->index('match_id');
            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixture_statistics');
    }
};
