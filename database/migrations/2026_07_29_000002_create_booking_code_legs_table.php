<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_code_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            // Stable identity for re-runs, including a worker leg that could
            // not be matched to a local fixture at posting time.
            $table->string('source_key', 80);
            $table->string('home_team')->nullable();
            $table->string('away_team')->nullable();
            $table->string('market', 120);
            $table->decimal('model_probability', 5, 2)->nullable();
            $table->decimal('estimated_odds', 8, 2)->nullable();
            $table->string('status', 16)->default('pending'); // pending | won | lost | unresolved
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique(['booking_code_id', 'source_key'], 'booking_code_leg_source_uq');
            $table->index(['status', 'settled_at']);
            $table->index('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_code_legs');
    }
};
