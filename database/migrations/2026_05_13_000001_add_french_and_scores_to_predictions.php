<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->text('analysis_french')->nullable()->after('analysis_swahili');
            $table->json('likely_scores')->nullable()->after('analysis_french');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['analysis_french', 'likely_scores']);
        });
    }
};
