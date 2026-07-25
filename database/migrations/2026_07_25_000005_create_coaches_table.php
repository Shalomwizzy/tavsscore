<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coach_api_id');
            $table->string('name');
            $table->unsignedBigInteger('team_api_id')->nullable();
            $table->string('team_name')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('nationality')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['coach_api_id', 'team_api_id'], 'coach_team_unique');
            $table->index('team_api_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaches');
    }
};
