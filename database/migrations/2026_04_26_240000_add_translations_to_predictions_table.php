<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->text('analysis_pidgin')->nullable()->after('analysis');
            $table->text('analysis_swahili')->nullable()->after('analysis_pidgin');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['analysis_pidgin', 'analysis_swahili']);
        });
    }
};
