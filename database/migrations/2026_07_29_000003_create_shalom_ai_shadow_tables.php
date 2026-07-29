<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shalom_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('model_version', 40)->default('shalom-ai-v1');
            $table->decimal('home_win_probability', 5, 2);
            $table->decimal('draw_probability', 5, 2);
            $table->decimal('away_win_probability', 5, 2);
            $table->decimal('over_25_probability', 5, 2)->nullable();
            $table->decimal('btts_probability', 5, 2)->nullable();
            $table->string('predicted_outcome', 80);
            $table->unsignedTinyInteger('confidence');
            $table->json('explanation')->nullable();
            $table->boolean('is_shadow')->default(true);
            $table->boolean('was_correct')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['match_id', 'model_version'], 'shalom_match_model_uq');
            $table->index(['model_version', 'was_correct']);
        });

        Schema::create('shalom_blog_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->foreignId('shalom_prediction_id')->nullable()->constrained('shalom_predictions')->nullOnDelete();
            $table->string('title');
            $table->text('excerpt');
            $table->longText('content');
            $table->string('status', 20)->default('draft');
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index(['status', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shalom_blog_drafts');
        Schema::dropIfExists('shalom_predictions');
    }
};
