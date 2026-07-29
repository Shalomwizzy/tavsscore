<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicit source timestamps let the publication gate distinguish a
     * recently checked empty injury list from data that simply was never
     * refreshed. They are deliberately separate from updated_at: a fixture
     * response with no score/status change still counts as fresh data.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('fixture_data_checked_at')->nullable()->index()->after('match_time');
            $table->timestamp('intel_checked_at')->nullable()->index()->after('fixture_data_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['fixture_data_checked_at', 'intel_checked_at']);
        });
    }
};
