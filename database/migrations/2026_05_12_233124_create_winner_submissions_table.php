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
        Schema::create('winner_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('username', 60);
            $table->string('screenshot_path');
            $table->string('pick_description', 255)->nullable();
            $table->decimal('winning_amount', 10, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('winner_submissions');
    }
};
