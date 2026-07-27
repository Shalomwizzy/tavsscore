<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tennis_matches', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 40);
            $table->string('source_key', 191);
            $table->string('tour', 12); // ATP or WTA
            $table->string('tournament')->nullable();
            $table->string('surface', 20)->nullable();
            $table->date('match_date')->nullable();
            $table->string('round', 30)->nullable();
            $table->unsignedTinyInteger('best_of')->nullable();
            $table->string('player_one');
            $table->string('player_two');
            $table->string('winner')->nullable();
            $table->unsignedInteger('player_one_rank')->nullable();
            $table->unsignedInteger('player_two_rank')->nullable();
            $table->string('score')->nullable();
            $table->string('status', 20)->default('completed');
            $table->json('stats')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_key']);
            $table->index(['tour', 'match_date']);
            $table->index(['player_one', 'match_date']);
            $table->index(['player_two', 'match_date']);
        });

        Schema::create('tennis_player_ratings', function (Blueprint $table): void {
            $table->id();
            $table->string('tour', 12);
            $table->string('player_name');
            $table->string('surface', 20)->default('all');
            $table->decimal('rating', 8, 2)->default(1500);
            $table->unsignedInteger('matches_played')->default(0);
            $table->date('as_of_date')->nullable();
            $table->timestamps();

            $table->unique(['tour', 'player_name', 'surface']);
        });

        Schema::create('tennis_predictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tennis_match_id')->unique()->constrained('tennis_matches')->cascadeOnDelete();
            $table->decimal('player_one_win_prob', 5, 2);
            $table->decimal('player_two_win_prob', 5, 2);
            $table->string('predicted_winner');
            $table->unsignedTinyInteger('confidence');
            $table->json('features');
            $table->json('ai_panel')->nullable();
            $table->text('analysis')->nullable();
            $table->boolean('was_correct')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tennis_predictions');
        Schema::dropIfExists('tennis_player_ratings');
        Schema::dropIfExists('tennis_matches');
    }
};
