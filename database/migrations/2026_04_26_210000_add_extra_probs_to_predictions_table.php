<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->decimal('over_15_prob', 5, 2)->default(0)->after('away_win_prob');
            $table->decimal('over_25_prob', 5, 2)->default(0)->after('over_15_prob');
            $table->decimal('over_35_prob', 5, 2)->default(0)->after('over_25_prob');
            $table->decimal('btts_prob',    5, 2)->default(0)->after('over_35_prob');
            $table->string('predicted_outcome', 30)->nullable()->after('btts_prob');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['over_15_prob', 'over_25_prob', 'over_35_prob', 'btts_prob', 'predicted_outcome']);
        });
    }
};
