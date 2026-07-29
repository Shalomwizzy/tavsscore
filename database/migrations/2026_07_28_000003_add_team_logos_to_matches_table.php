<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('home_team_logo', 500)->nullable()->after('home_team');
            $table->string('away_team_logo', 500)->nullable()->after('away_team');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['home_team_logo', 'away_team_logo']);
        });
    }
};
