<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->boolean('is_daily_pick')->default(false)->after('predicted_outcome');
            $table->unsignedTinyInteger('pick_rank')->nullable()->after('is_daily_pick');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['is_daily_pick', 'pick_rank']);
        });
    }
};
