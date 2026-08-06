<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_codes', function (Blueprint $table) {
            $table->timestamp('x_posted_at')->nullable()->after('settled_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_codes', function (Blueprint $table) {
            $table->dropColumn('x_posted_at');
        });
    }
};
