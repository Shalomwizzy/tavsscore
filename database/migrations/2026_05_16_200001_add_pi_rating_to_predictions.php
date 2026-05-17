<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            // Snapshot the pi-rating differential at prediction time so we can
            // track whether high pi-gap predictions are more accurate over time.
            $table->float('pi_rating_diff')->nullable()->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn('pi_rating_diff');
        });
    }
};
