<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tennis_matches') || Schema::hasColumn('tennis_matches', 'scheduled_at')) {
            return;
        }

        Schema::table('tennis_matches', function (Blueprint $table): void {
            $table->timestamp('scheduled_at')->nullable()->after('match_date');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tennis_matches') && Schema::hasColumn('tennis_matches', 'scheduled_at')) {
            Schema::table('tennis_matches', function (Blueprint $table): void {
                $table->dropColumn('scheduled_at');
            });
        }
    }
};
