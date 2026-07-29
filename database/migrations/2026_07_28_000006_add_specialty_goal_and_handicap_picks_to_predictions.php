<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->boolean('is_under35_pick')->default(false)->after('double_chance_notified');
            $table->unsignedSmallInteger('under35_rank')->nullable()->after('is_under35_pick');
            $table->boolean('under35_notified')->default(false)->after('under35_rank');
            $table->boolean('is_under45_pick')->default(false)->after('under35_notified');
            $table->unsignedSmallInteger('under45_rank')->nullable()->after('is_under45_pick');
            $table->boolean('under45_notified')->default(false)->after('under45_rank');
            $table->boolean('is_handicap_pick')->default(false)->after('under45_notified');
            $table->unsignedSmallInteger('handicap_rank')->nullable()->after('is_handicap_pick');
            $table->string('handicap_label', 64)->nullable()->after('handicap_rank');
            $table->boolean('handicap_notified')->default(false)->after('handicap_label');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn([
                'is_under35_pick', 'under35_rank', 'under35_notified',
                'is_under45_pick', 'under45_rank', 'under45_notified',
                'is_handicap_pick', 'handicap_rank', 'handicap_label', 'handicap_notified',
            ]);
        });
    }
};
