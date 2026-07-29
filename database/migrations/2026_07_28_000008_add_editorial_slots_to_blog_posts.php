<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('editorial_desk', 32)->nullable()->after('category');
            $table->string('editorial_slot', 64)->nullable()->after('editorial_desk');
            $table->index(['editorial_slot', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex(['editorial_slot', 'published_at']);
            $table->dropColumn(['editorial_desk', 'editorial_slot']);
        });
    }
};
