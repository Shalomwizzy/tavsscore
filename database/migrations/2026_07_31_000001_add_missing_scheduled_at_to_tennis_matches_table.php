<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some production installs created the Tennis table before this field
        // existed. Keep the migration conditional so both fresh and existing
        // TavsScore databases deploy safely.
        if (! Schema::hasTable('tennis_matches') || Schema::hasColumn('tennis_matches', 'scheduled_at')) {
            return;
        }

        Schema::table('tennis_matches', function (Blueprint $table): void {
            $table->timestamp('scheduled_at')->nullable()->after('match_date');
            $table->index(['status', 'scheduled_at'], 'tennis_matches_status_scheduled_at_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tennis_matches') || ! Schema::hasColumn('tennis_matches', 'scheduled_at')) {
            return;
        }

        Schema::table('tennis_matches', function (Blueprint $table): void {
            $table->dropIndex('tennis_matches_status_scheduled_at_index');
            $table->dropColumn('scheduled_at');
        });
    }
};
