<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_codes', function (Blueprint $table) {
            $table->string('link')->nullable()->after('code');       // shareable betslip URL
            $table->string('slip_ref')->nullable()->after('link');   // which spec slip this fulfils (daily-acca, rollover…)
            $table->json('fixtures')->nullable()->after('slip_ref'); // the selections in the slip
            $table->decimal('total_odds', 8, 2)->nullable()->after('fixtures');
            $table->string('source')->default('manual')->after('total_odds');  // manual | auto
            $table->string('status')->default('published')->after('source');    // pending | published | failed | expired
            $table->date('pick_date')->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('pick_date');

            $table->index(['platform', 'pick_date']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_codes', function (Blueprint $table) {
            $table->dropColumn(['link', 'slip_ref', 'fixtures', 'total_odds', 'source', 'status', 'pick_date', 'expires_at']);
        });
    }
};
