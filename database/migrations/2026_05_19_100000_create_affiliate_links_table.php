<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 60);
            $table->string('slug', 60);
            $table->string('register_url', 500);
            $table->string('website_url', 500)->nullable();
            $table->string('promo_text', 200)->nullable();
            $table->string('color', 7)->default('#f59e0b');
            $table->string('logo_emoji', 10)->default('🎯');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_links');
    }
};
