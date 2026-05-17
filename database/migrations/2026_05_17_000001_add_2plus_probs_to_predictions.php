<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->decimal('home_2plus_prob', 5, 2)->nullable()->after('home_3plus_prob');
            $table->decimal('away_2plus_prob', 5, 2)->nullable()->after('away_3plus_prob');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['home_2plus_prob', 'away_2plus_prob']);
        });
    }
};
