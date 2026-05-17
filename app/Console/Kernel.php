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
        // Live matches: every minute for near-real-time goal alerts.
        // No live matches: every 15 min to conserve API quota.
        $schedule->command('fetch:matches')
            ->everyMinute()
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
        // Picks: select at midnight + retry at 06:00 for late-loading fixtures
        $schedule->command('picks:select --force')->dailyAt('00:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:select')->dailyAt('05:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:select')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:select')->dailyAt('10:00')->timezone('Africa/Lagos')->withoutOverlapping();

        // Staggered notifications 01:00–02:00 Lagos — sent right after midnight selection.
        // Once a type is notified, a cache flag prevents the 05:00/08:00 picks:select
        // from overwriting those picks, so what users receive is always what stays.
        $schedule->command('picks:notify --type=daily')->dailyAt('01:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=draw')->dailyAt('01:10')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=gg')->dailyAt('01:20')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=over15')->dailyAt('01:30')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=over25')->dailyAt('01:40')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=team3plus')->dailyAt('01:50')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=doublechance')->dailyAt('02:00')->timezone('Africa/Lagos')->withoutOverlapping();

        // Re-predict daily pick matches the moment their confirmed lineup drops
        // Runs every minute (same as live fetch) — only fires Groq when lineup is new
        $schedule->command('picks:update-lineups')->everyMinute()->withoutOverlapping();

        // Fetch near-closing bookmaker odds at 10:00 and 14:00 Lagos.
        // Runs after most European morning kickoffs have odds settled, and again
        // before afternoon/evening fixtures to capture late-market drift.
        $schedule->command('picks:fetch-closing-odds')->dailyAt('10:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:fetch-closing-odds')->dailyAt('14:00')->timezone('Africa/Lagos')->withoutOverlapping();

        // Select today's rollover pick at 10:30 Lagos — after the 10:00 odds fetch
        // so impliedOdds() finds real bookmaker prices instead of AI-derived ones.
        $schedule->command('rollover:select')->dailyAt('10:30')->timezone('Africa/Lagos')->withoutOverlapping();

        // Post today's pick results to Telegram at 23:00 Lagos
        $schedule->command('results:send-telegram')->dailyAt('23:00')->timezone('Africa/Lagos')->withoutOverlapping();

        $schedule->command('blog:auto-post')->dailyAt('08:30')->timezone('Africa/Lagos');

        // Monthly calibration snapshot — runs on the 1st at 02:00 Lagos.
        // Builds up the public Track Record timeline that proves system improvement.
        $schedule->command('calibration:snapshot')->monthlyOn(1, '02:00')->timezone('Africa/Lagos');

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
