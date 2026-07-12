<?php

namespace App\Providers;

use App\Models\Prediction;
use App\Models\Setting;
use App\Observers\PredictionObserver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $telegramUrl = 'https://t.me/tavsscore';
        $twitterUrl  = 'https://x.com/tavsscore';

        try {
            if (Schema::hasTable('settings')) {
                $telegramUrl = Setting::get('telegram_url', $telegramUrl);
                $twitterUrl  = Setting::get('twitter_url',  $twitterUrl);
            }
        } catch (\Throwable) {
            // DB unavailable during early boot (artisan commands, test setup, fresh install)
        }

        View::share('telegramUrl', $telegramUrl);
        View::share('twitterUrl',  $twitterUrl);

        Prediction::observe(PredictionObserver::class);
    }
}
