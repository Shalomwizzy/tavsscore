<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_predictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('match_id');
            $table->string('winner_name')->nullable();
            $table->string('winner_comment')->nullable();
            $table->string('advice')->nullable();
            $table->string('percent_home')->nullable();
            $table->string('percent_draw')->nullable();
            $table->string('percent_away')->nullable();
            $table->string('under_over')->nullable();
            $table->decimal('goals_home', 5, 2)->nullable();
            $table->decimal('goals_away', 5, 2)->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique('match_id', 'api_prediction_match_unique');
            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_predictions');
    }
};
