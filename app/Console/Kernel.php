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

        // Free RESULTS fallback (football-data.org) — only fires when the
        // API-Football quota is exhausted, so today's outcomes still settle
        // instead of waiting for the next-day catch-up. No-op if nothing pending.
        $schedule->command('results:fallback')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->when(fn () => (bool) \Illuminate\Support\Facades\Cache::get('api_football_quota_exhausted'));

        // API-Football quota resets at 01:00 Lagos each day.
        // At 01:30 we clear the quota-exhausted cache flag so fetch:matches
        // resumes immediately and has ~90 min to load today's fixtures before
        // picks are selected at 03:00.
        $schedule->call(function () {
            \Illuminate\Support\Facades\Cache::forget('api_football_quota_exhausted');
        })->dailyAt('01:30')->timezone('Africa/Lagos')->name('clear-quota-flag');

        // FIRST thing after the quota resets: reconcile any past match whose
        // result was missed (quota was down when it finished) and settle every
        // pending outcome, so nothing is left ungraded. 14-day catch-up window.
        $schedule->command('results:catch-up --days=14')
            ->dailyAt('01:35')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30)
            ->runInBackground();

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

        // Select today's rollover ticket (1-5 safest legs, ≤2.00 combined odds)
        // at 10:30 Lagos — after the 03:00-10:00 prediction runs have boards stored.
        $schedule->command('rollover:select')->dailyAt('10:30')->timezone('Africa/Lagos')->withoutOverlapping();

        // Post today's pick results to Telegram at 23:00 Lagos
        $schedule->command('results:send-telegram')->dailyAt('23:00')->timezone('Africa/Lagos')->withoutOverlapping();

        $schedule->command('blog:auto-post')->dailyAt('08:30')->timezone('Africa/Lagos');

        // Tennis source update: re-import the rolling current/previous season
        // every morning, then rebuild surface and overall Elo ratings.
        $schedule->command('tennis:sync --ratings')->dailyAt('01:50')->timezone('Africa/Lagos')->withoutOverlapping(120)
            ->when(fn () => filled(config('services.tennis_data.atp_url')) && filled(config('services.tennis_data.wta_url')));
        $schedule->command('tennis:fetch-fixtures')->hourly()->withoutOverlapping();
        $schedule->command('tennis:predict')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('tennis:settle-results')->everyTenMinutes()->withoutOverlapping();

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
        // Standings → weekly (Monday 02:30) to conserve quota. Tables persist in
        // the DB between runs; the tradeoff is they can be a few matchdays stale
        // mid-week. Runs before the Monday team/player-stat jobs that read team
        // IDs from the standings table.
        $schedule->command('stats:fetch-standings')
            ->weeklyOn(1, '02:30')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30)
            ->runInBackground();

        // Season stats we already hold rarely change week-to-week, so we only
        // refresh them ONCE a week (Monday) to conserve API quota — never daily.
        // Team season stats: Monday 06:20, after standings load (reads team IDs
        // from the standings table). ~1 call per team.
        $schedule->command('stats:fetch-teams')
            ->weeklyOn(1, '06:20')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(60)
            ->runInBackground();

        // Player stats are the heaviest job (paginated per league, ~1,000+ calls)
        // → weekly, Monday 20:00, in a quiet window clear of picks/odds.
        $schedule->command('stats:fetch-players')
            ->weeklyOn(1, '20:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(120)
            ->runInBackground();

        // Fantasy best XI — rebuilt from the fresh player stats (Monday 20:40,
        // after stats:fetch-players) and again Thursday before the weekend GW.
        $schedule->command('fantasy:build')
            ->weeklyOn(1, '20:40')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping();
        $schedule->command('fantasy:build')
            ->weeklyOn(4, '09:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping();

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

        // Post-match statistics (shots/corners/cards/xG) for finished fixtures —
        // daily 07:00, after results settle. Quota-light (only new fixtures).
        $schedule->command('stats:fetch-fixture-stats --days=3')
            ->dailyAt('07:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30)
            ->runInBackground();

        // Transfers + coaches — weekly (Tue 21:00, quiet window). Feeds the blog
        // writer and manager context. Quota-heavy (1-2 calls per team).
        $schedule->command('stats:fetch-team-meta')
            ->weeklyOn(2, '21:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(120)
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
