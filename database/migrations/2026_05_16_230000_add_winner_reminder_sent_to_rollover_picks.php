<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rollover_picks', function (Blueprint $table) {
            $table->boolean('winner_reminder_sent')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('rollover_picks', function (Blueprint $table) {
            $table->dropColumn('winner_reminder_sent');
        });
    }
};
