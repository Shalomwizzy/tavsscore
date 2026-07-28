<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->boolean('is_corners_pick')->default(false)->index();
            $table->unsignedTinyInteger('corners_rank')->nullable();
            $table->string('corners_label', 40)->nullable(); // e.g. "Over 9.5 Corners"
            $table->boolean('corners_notified')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['is_corners_pick', 'corners_rank', 'corners_label', 'corners_notified']);
        });
    }
};
