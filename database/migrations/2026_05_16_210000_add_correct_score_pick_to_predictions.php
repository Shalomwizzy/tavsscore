<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->boolean('is_correct_score_pick')->default(false)->after('correct_score_notified');
            $table->unsignedTinyInteger('correct_score_rank')->nullable()->after('is_correct_score_pick');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['is_correct_score_pick', 'correct_score_rank']);
        });
    }
};
