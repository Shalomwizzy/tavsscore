<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->longText('content_pidgin')->nullable()->after('content');
            $table->longText('content_swahili')->nullable()->after('content_pidgin');
            $table->string('image_path')->nullable()->after('featured_image');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['content_pidgin', 'content_swahili', 'image_path']);
        });
    }
};
