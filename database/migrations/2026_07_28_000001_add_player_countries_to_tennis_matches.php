<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            $table->string('player_one_country', 3)->nullable()->after('player_one');
            $table->string('player_two_country', 3)->nullable()->after('player_two');
        });
    }

    public function down(): void
    {
        Schema::table('tennis_matches', function (Blueprint $table) {
            $table->dropColumn(['player_one_country', 'player_two_country']);
        });
    }
};
