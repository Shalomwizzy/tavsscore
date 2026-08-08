<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_posts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32)->index();          // booking_code / booking_outcome / growth / manual
            $table->text('text');
            $table->string('tweet_id')->nullable();
            $table->string('status', 16)->default('posted'); // posted / failed
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_posts');
    }
};
