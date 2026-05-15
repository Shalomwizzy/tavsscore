<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('label', 150)->nullable();
            $table->timestamps();
        });

        // Seed defaults
        DB::table('settings')->insert([
            ['key' => 'telegram_url',  'value' => 'https://t.me/tavsscore',                                                          'label' => 'Telegram Channel URL',    'created_at' => now(), 'updated_at' => now()],
            ['key' => 'twitter_url',   'value' => 'https://x.com/tavsscore?s=21&t=jhwX7gvlXFMKMK7nX024RA',                          'label' => 'Twitter / X Profile URL', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_tagline',  'value' => 'Real-Time Football Scores & AI Predictions',                                      'label' => 'Site Tagline',            'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_email', 'value' => 'hello@tavsscore.com',                                                             'label' => 'Contact Email',           'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
