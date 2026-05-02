<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            // AI-recommended bet markets for this match. Each entry:
            //   { market: string, confidence: int 0-100, rationale: string|null }
            $table->json('tips')->nullable()->after('predicted_outcome');
            // Confidence (%) of the primary `predicted_outcome` — surfaces on cards
            // so users (and the daily-pick selector) can apply a real floor.
            $table->unsignedTinyInteger('confidence')->nullable()->after('tips');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['tips', 'confidence']);
        });
    }
};
