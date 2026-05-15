<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rollover_picks', function (Blueprint $table) {
            $table->string('mistral_verdict', 100)->nullable()->after('gemini_verdict');
        });
    }

    public function down(): void
    {
        Schema::table('rollover_picks', function (Blueprint $table) {
            $table->dropColumn('mistral_verdict');
        });
    }
};
