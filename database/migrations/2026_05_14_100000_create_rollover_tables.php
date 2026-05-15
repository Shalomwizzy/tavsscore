<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollover_challenges', function (Blueprint $table) {
            $table->id();
            $table->date('started_at');
            $table->decimal('initial_stake', 12, 2)->default(10000.00);
            $table->enum('status', ['active', 'complete', 'resting'])->default('active');
            $table->timestamps();
        });

        Schema::create('rollover_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('rollover_challenges')->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('prediction_id')->nullable()->constrained('predictions')->nullOnDelete();
            $table->unsignedTinyInteger('day_number');
            $table->date('pick_date');
            $table->decimal('implied_odds', 6, 2)->default(1.20);
            $table->decimal('stake_amount', 12, 2);
            $table->decimal('potential_return', 12, 2);
            $table->boolean('was_correct')->nullable();
            $table->string('result_score', 10)->nullable();
            $table->string('groq_verdict', 100)->nullable();
            $table->string('gemini_verdict', 100)->nullable();
            $table->boolean('both_agree')->default(false);
            $table->enum('status', ['pending', 'won', 'lost', 'void'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rollover_picks');
        Schema::dropIfExists('rollover_challenges');
    }
};
