<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('home_score_ht')->nullable()->after('away_score');
            $table->unsignedTinyInteger('away_score_ht')->nullable()->after('home_score_ht');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['home_score_ht', 'away_score_ht']);
        });
    }
};
