<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->boolean('is_european_handicap_pick')->default(false)->after('handicap_notified');
            $table->unsignedSmallInteger('european_handicap_rank')->nullable()->after('is_european_handicap_pick');
            $table->string('european_handicap_label', 80)->nullable()->after('european_handicap_rank');
            $table->boolean('european_handicap_notified')->default(false)->after('european_handicap_label');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['is_european_handicap_pick', 'european_handicap_rank', 'european_handicap_label', 'european_handicap_notified']);
        });
    }
};
