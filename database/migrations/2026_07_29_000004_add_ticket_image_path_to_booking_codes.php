<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_codes', function (Blueprint $table) {
            $table->string('ticket_image_path')->nullable()->after('link');
        });
    }

    public function down(): void
    {
        Schema::table('booking_codes', function (Blueprint $table) {
            $table->dropColumn('ticket_image_path');
        });
    }
};
