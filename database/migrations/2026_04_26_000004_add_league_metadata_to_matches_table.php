<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedBigInteger('league_id')->nullable()->after('api_id');
            $table->string('league_country')->nullable()->after('league');

            $table->index(['league_id', 'match_time']);
            $table->index(['league_country', 'league']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['league_id', 'match_time']);
            $table->dropIndex(['league_country', 'league']);
            $table->dropColumn(['league_id', 'league_country']);
        });
    }
};
