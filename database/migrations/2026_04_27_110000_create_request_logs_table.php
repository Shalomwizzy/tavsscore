<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500)->index();
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('country', 2)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('referer', 500)->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
