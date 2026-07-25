<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_api_id');
            $table->string('player_name');
            $table->date('transfer_date')->nullable();
            $table->string('type')->nullable();          // "Loan", "Free", "€ 20M", etc.
            $table->unsignedBigInteger('team_in_id')->nullable();
            $table->string('team_in_name')->nullable();
            $table->unsignedBigInteger('team_out_id')->nullable();
            $table->string('team_out_name')->nullable();
            $table->timestamps();

            $table->unique(['player_api_id', 'transfer_date', 'team_in_id'], 'transfer_unique');
            $table->index('team_in_id');
            $table->index('team_out_id');
            $table->index('transfer_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
