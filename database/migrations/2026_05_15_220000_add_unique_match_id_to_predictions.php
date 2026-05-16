<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate match_id rows before adding the unique index.
        // For each match_id with duplicates, keep only the highest id (latest prediction).
        $duplicates = DB::table('predictions')
            ->select('match_id')
            ->groupBy('match_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('match_id');

        foreach ($duplicates as $matchId) {
            $keepId = DB::table('predictions')->where('match_id', $matchId)->max('id');
            DB::table('predictions')->where('match_id', $matchId)->where('id', '!=', $keepId)->delete();
        }

        Schema::table('predictions', function (Blueprint $table) {
            $table->unique('match_id');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropUnique(['match_id']);
        });
    }
};
