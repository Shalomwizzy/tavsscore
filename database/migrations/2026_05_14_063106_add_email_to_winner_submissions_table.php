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
        Schema::table('winner_submissions', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('winner_submissions', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
