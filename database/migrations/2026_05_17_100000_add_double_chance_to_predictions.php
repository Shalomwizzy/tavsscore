<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->boolean('is_double_chance_pick')->default(false)->after('is_team3plus_pick');
            $table->unsignedTinyInteger('double_chance_rank')->nullable()->after('is_double_chance_pick');
            $table->string('double_chance_label', 4)->nullable()->after('double_chance_rank'); // '1X' or '2X'
            $table->boolean('double_chance_notified')->default(false)->after('double_chance_label');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['is_double_chance_pick', 'double_chance_rank', 'double_chance_label', 'double_chance_notified']);
        });
    }
};
