<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (Schema::hasTable('settings')) {
            $telegramUrl = Setting::get('telegram_url', 'https://t.me/tavsscore');
            $twitterUrl  = Setting::get('twitter_url',  'https://x.com/tavsscore');

            View::share('telegramUrl', $telegramUrl);
            View::share('twitterUrl',  $twitterUrl);
        } else {
            View::share('telegramUrl', 'https://t.me/tavsscore');
            View::share('twitterUrl',  'https://x.com/tavsscore');
        }
    }
}
