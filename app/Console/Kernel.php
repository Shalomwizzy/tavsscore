<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Match fetch — every 2 min when live matches exist, every 15 min otherwise.
        // Conserves API-Football quota (free tier = 100 req/day).
        $schedule->command('fetch:matches')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->when(fn () => \App\Models\FootballMatch::whereIn('status', ['1H','HT','2H','ET','BT','P','LIVE'])->exists());

        $schedule->command('fetch:matches')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->when(fn () => ! \App\Models\FootballMatch::whereIn('status', ['1H','HT','2H','ET','BT','P','LIVE'])->exists());

        $schedule->command('predict:matches')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('predictions:check-outcomes')->everyFiveMinutes()->withoutOverlapping();
        // Reset daily picks at midnight Lagos (Africa/Lagos = WAT, UTC+1).
        // The selector also re-runs at 06:00 in case morning fixtures load late.
        $schedule->command('picks:select --force')->dailyAt('00:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:select')->dailyAt('06:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('blog:auto-post')->dailyAt('08:00')->timezone('Africa/Lagos');

        // Newsletter — send today's 3 picks at 09:00 Lagos to confirmed subscribers
        $schedule->command('newsletter:send-daily')->dailyAt('09:00')->timezone('Africa/Lagos')->withoutOverlapping();

        // Trim request_logs older than 30 days so the table never grows forever.
        $schedule->call(function () {
            \App\Models\RequestLog::where('created_at', '<', now()->subDays(30))->delete();
        })->dailyAt('03:00')->timezone('Africa/Lagos')->name('prune-request-logs')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
