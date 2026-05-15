<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('winner_submissions', function (Blueprint $table) {
            $table->string('platform', 60)->nullable()->after('currency');
            $table->string('match_details', 255)->nullable()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('winner_submissions', function (Blueprint $table) {
            $table->dropColumn(['platform', 'match_details']);
        });
    }
};
