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

        // Expiry (10 min) so a killed run can't leave a stale lock that blocks
        // predictions for the default 24h — they'd stop generating until manual clear.
        $schedule->command('predict:matches')->everyFifteenMinutes()->withoutOverlapping(10);
        $schedule->command('predictions:check-outcomes')->everyFiveMinutes()->withoutOverlapping();

        // API-Football quota resets at 01:00 Lagos each day.
        // At 01:30 we clear the quota-exhausted cache flag so fetch:matches
        // resumes immediately and has ~90 min to load today's fixtures before
        // picks are selected at 03:00.
        $schedule->call(function () {
            \Illuminate\Support\Facades\Cache::forget('api_football_quota_exhausted');
        })->dailyAt('01:30')->timezone('Africa/Lagos')->name('clear-quota-flag');

        // Picks: force-select at 03:00 (fixtures loaded, predictions generated).
        // Silent re-runs at 05:00, 08:00, 10:00 — these are blocked by the
        // "picks_sent_{type}_{date}" cache flags so notified picks are never replaced.
        $schedule->command('picks:select --force')->dailyAt('03:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:select')->dailyAt('05:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:select')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:select')->dailyAt('10:00')->timezone('Africa/Lagos')->withoutOverlapping();

        // Staggered notifications 03:30–04:30 Lagos — 30 min after picks:select --force.
        // Once a type is notified, a cache flag prevents later picks:select runs
        // from overwriting those picks, so what users receive is always what stays.
        $schedule->command('picks:notify --type=daily')->dailyAt('03:30')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=draw')->dailyAt('03:40')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=gg')->dailyAt('03:50')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=over15')->dailyAt('04:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=over25')->dailyAt('04:10')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=team3plus')->dailyAt('04:20')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=doublechance')->dailyAt('04:30')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=correctscore')->dailyAt('04:40')->timezone('Africa/Lagos')->withoutOverlapping();
        // Backup runs at 08:00 — covers types where predictions aren't ready by the primary run.
        // Cache guards in NotifyDailyPicks prevent double-sending if the primary already fired.
        $schedule->command('picks:notify --type=draw')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=gg')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=over15')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=over25')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=team3plus')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=doublechance')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=correctscore')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();

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

        // ── Dixon-Coles (Phase 2) ────────────────────────────────────────
        // Weekly refit every Monday at 04:00 Lagos — after weekend fixtures
        // have settled, before Tuesday's midweek slate. Fits all 9 priority
        // leagues in ~90s total.
        $schedule->command('dc:fit')
            ->weeklyOn(1, '04:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping()
            ->runInBackground();

        // Shadow-log DC predictions into prediction_logs alongside every
        // pick-selection pass so the /admin/model-metrics dashboard can
        // compare dc-v1.0 vs dc-hybrid-v1 vs groq-poisson-v0 vs
        // market-closing on the same fixtures.
        $schedule->command('dc:shadow-log --hours-ahead=48')
            ->dailyAt('03:15')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping();
        $schedule->command('dc:shadow-log --hours-ahead=48')
            ->dailyAt('10:15')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping();

        // ── Data integrity (Phase 1.5.2) ─────────────────────────────────
        // Weekly ingestion / prediction coverage sanity check.
        $schedule->command('coverage:report --days=7')
            ->weeklyOn(0, '07:00')
            ->timezone('Africa/Lagos');

        // ── Continuous evaluation (Phase 5) ──────────────────────────────
        // Weekly Brier / hit-rate snapshot per (model_version × market ×
        // league). Fires degradation warnings to the log if any live DC
        // combo drifts > 10% worse than backtest expectations.
        $schedule->command('metrics:snapshot --days=7')
            ->weeklyOn(1, '05:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping();

        // ── Yearly season-fixture ingestion ──────────────────────────────
        // API-Football publishes each new European season's full fixture list
        // by mid-July (season=2026 means 2026-27). Without this, the DB has
        // zero future fixtures for DC leagues until each matchday, which
        // starves dc:shadow-log and ahead-of-time prediction generation.
        // Runs monthly Jul–Sep to catch late fixture publications and
        // schedule changes; matches:backfill is idempotent per api_id.
        $schedule->call(function () {
            \Illuminate\Support\Facades\Artisan::call('matches:backfill', [
                '--seasons' => (string) now('Africa/Lagos')->year,
            ]);
        })->monthlyOn(15, '02:30')->timezone('Africa/Lagos')
          ->when(fn () => in_array(now('Africa/Lagos')->month, [7, 8, 9], true))
          ->name('yearly-season-ingest')->withoutOverlapping();

        // ── Deep settlement sweep ────────────────────────────────────────
        // The 5-minute predictions:check-outcomes only looks back 3 days.
        // Anything that finishes while the pipeline is degraded (API outage,
        // crash-loop) falls out of that window and would stay unsettled
        // forever. Weekly 30-day sweep catches all stragglers.
        $schedule->command('predictions:check-outcomes --days=30')
            ->weeklyOn(0, '06:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping();

        // ── API-Football stats ingestion ─────────────────────────────────
        // Scheduled into quiet windows so they never starve the API quota that
        // pick-selection (03:00) and odds (10:00/14:00) depend on. Each fetcher
        // short-circuits the moment the daily quota flag trips.
        //
        // Standings change every matchday → refresh daily at 06:00 (~1 call/league).
        $schedule->command('stats:fetch-standings')
            ->dailyAt('06:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30)
            ->runInBackground();

        // Team season stats → twice weekly (Mon/Thu 06:20), after standings load
        // (it reads team IDs from the standings table). ~1 call per team.
        $schedule->command('stats:fetch-teams')
            ->days([1, 4])
            ->at('06:20')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(60)
            ->runInBackground();

        // Player stats are quota-heavy (paginated per league) → weekly, Sunday
        // 20:00, well clear of every critical API window for the day.
        $schedule->command('stats:fetch-players')
            ->weeklyOn(0, '20:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(120)
            ->runInBackground();

        // Fixture intel (injuries + API-Football predictions) feeds the LLM
        // arbiter chain. Refreshed before the primary pick-selection at 03:00
        // and again mid-morning so late injury/suspension news is captured.
        $schedule->command('stats:fetch-fixture-intel --hours-ahead=48')
            ->dailyAt('02:40')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30)
            ->runInBackground();
        $schedule->command('stats:fetch-fixture-intel --hours-ahead=48')
            ->dailyAt('09:30')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30)
            ->runInBackground();
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
