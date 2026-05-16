<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            // Bookmaker implied probabilities at prediction-generation time
            $table->json('opening_odds')->nullable()->after('likely_scores');
            // Same snapshot fetched close to kickoff — delta reveals market drift
            $table->json('closing_odds')->nullable()->after('opening_odds');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['opening_odds', 'closing_odds']);
        });
    }
};
