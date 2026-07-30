<?php

namespace App\Console;

use App\Models\FootballMatch;
use App\Models\RequestLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

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
            ->when(fn () => FootballMatch::whereIn('status', ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE'])->exists());

        $schedule->command('fetch:matches')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->when(fn () => ! FootballMatch::whereIn('status', ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE'])->exists());

        // Expiry (10 min) so a killed run can't leave a stale lock that blocks
        // predictions for the default 24h — they'd stop generating until manual clear.
        $schedule->command('predict:matches')->everyFifteenMinutes()->withoutOverlapping(10);
        $schedule->command('predictions:check-outcomes')->everyFiveMinutes()->withoutOverlapping();

        // Settle booking codes (accumulator win/loss) + push the outcome.
        $schedule->command('booking:grade')->everyThirtyMinutes()->withoutOverlapping();

        // Remove legacy failed placeholders. New worker failures are retried
        // locally and are never stored as booking codes in the first place.
        $schedule->command('booking:clear --failed --force')->hourly()->withoutOverlapping();

        // Free results fallback: do not rely only on the API quota cache flag.
        // A failed/expired flag must never leave a finished predicted match
        // pending. The command is a no-op unless a predicted fixture has been
        // stale for 150+ minutes, then football-data.org and ESPN reconcile it.
        $schedule->command('results:fallback')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->when(fn () => (bool) Cache::get('api_football_quota_exhausted')
                || FootballMatch::query()
                    ->whereHas('prediction')
                    ->where('match_time', '<', now()->subMinutes(150))
                    ->where('match_time', '>=', now()->subDays(4))
                    ->whereNotIn('status', ['FT', 'AET', 'PEN', 'CANC', 'PST', 'ABD', 'AWD', 'WO'])
                    ->exists());

        // ── Early-morning pipeline, sequenced so picks are ready by 01:30 ──
        // API-Football quota resets ~01:00 Lagos. Clear the exhausted flag at
        // 01:00 so the chain below can fetch immediately.
        $schedule->call(function () {
            Cache::forget('api_football_quota_exhausted');
        })->dailyAt('01:00')->timezone('Africa/Lagos')->name('clear-quota-flag');

        // 01:03 — load today's fixtures. 01:05 — reconcile/settle yesterday.
        $schedule->command('fetch:matches')->dailyAt('01:03')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('results:catch-up --days=14')
            ->dailyAt('01:05')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30)
            ->runInBackground();

        // 01:27 — generate predictions (after standings + intel at 01:25).
        $schedule->command('predict:matches')->dailyAt('01:27')->timezone('Africa/Lagos')->withoutOverlapping(15);

        // Picks: force-select at 01:30 (fixtures + predictions + intel ready).
        // Silent re-runs at 05:00, 08:00, 10:00 — these are blocked by the
        // "picks_sent_{type}_{date}" cache flags so notified picks are never replaced.
        $schedule->command('picks:select --force')->dailyAt('01:30')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:select')->dailyAt('05:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:select')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:select')->dailyAt('10:00')->timezone('Africa/Lagos')->withoutOverlapping();

        // Staggered notifications 02:00–02:56 Lagos — one after another, ~30 min
        // after the 01:30 picks:select --force. Once a type is notified, a cache
        // flag prevents later picks:select runs from overwriting those picks.
        $schedule->command('picks:notify --type=daily')->dailyAt('02:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=draw')->dailyAt('02:08')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=gg')->dailyAt('02:16')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=over15')->dailyAt('02:24')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=over25')->dailyAt('02:32')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=under35')->dailyAt('02:36')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=under45')->dailyAt('02:40')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=handicap')->dailyAt('02:44')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=europeanhandicap')->dailyAt('02:48')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=team3plus')->dailyAt('02:52')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=doublechance')->dailyAt('03:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=correctscore')->dailyAt('03:08')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=corners')->dailyAt('03:16')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=goalscorer')->dailyAt('03:24')->timezone('Africa/Lagos')->withoutOverlapping();
        // Backup runs at 08:00 — covers types where predictions aren't ready by the primary run.
        // Cache guards in NotifyDailyPicks prevent double-sending if the primary already fired.
        $schedule->command('picks:notify --type=draw')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=gg')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=over15')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=over25')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=under35')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=under45')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=handicap')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=europeanhandicap')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=team3plus')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=doublechance')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=correctscore')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=corners')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:notify --type=goalscorer')->dailyAt('08:00')->timezone('Africa/Lagos')->withoutOverlapping();

        // Re-predict daily pick matches the moment their confirmed lineup drops
        // Runs every minute (same as live fetch) — only fires Groq when lineup is new
        $schedule->command('picks:update-lineups')->everyMinute()->withoutOverlapping();
        // Applies the same calibrated gate after late lineups, injury intel,
        // odds movement and fixture refreshes; sends a correction only when a
        // published board actually changes.
        $schedule->command('picks:revalidate')->everyTenMinutes()->withoutOverlapping();

        // Fetch near-closing bookmaker odds at 10:00 and 14:00 Lagos.
        // Runs after most European morning kickoffs have odds settled, and again
        // before afternoon/evening fixtures to capture late-market drift.
        $schedule->command('picks:fetch-closing-odds')->dailyAt('10:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('picks:fetch-closing-odds')->dailyAt('14:00')->timezone('Africa/Lagos')->withoutOverlapping();

        // Select today's rollover ticket (1-5 safest legs, ≤2.00 combined odds)
        // at 06:00 Lagos — after the 01:30 selection has stored the market boards.
        $schedule->command('rollover:select')->dailyAt('04:30')->timezone('Africa/Lagos')->withoutOverlapping();

        // Post today's pick results to Telegram at 23:00 Lagos
        $schedule->command('results:send-telegram')->dailyAt('23:00')->timezone('Africa/Lagos')->withoutOverlapping();

        // Football newsroom: six independent slots each day. A slot may only
        // publish once; later slots receive today's titles and must choose a
        // different verified subject, avoiding rewritten versions of one story.
        $schedule->command('blog:auto-post --desk=transfers --slot=transfers-am')
            ->dailyAt('07:30')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('blog:auto-post --desk=club --slot=club-am')
            ->dailyAt('11:30')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('blog:auto-post --desk=football --slot=football-analysis')
            ->dailyAt('15:30')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('blog:auto-post --desk=transfers --slot=transfers-pm')
            ->dailyAt('18:30')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('blog:auto-post --desk=club --slot=club-pm')
            ->dailyAt('21:00')->timezone('Africa/Lagos')->withoutOverlapping();
        $schedule->command('blog:auto-post --desk=controversy --slot=controversy-pm')
            ->dailyAt('23:30')->timezone('Africa/Lagos')->withoutOverlapping();

        // Tennis source update: re-import the rolling current/previous season
        // every morning, then rebuild surface and overall Elo ratings.
        $schedule->command('tennis:sync --ratings')->dailyAt('01:50')->timezone('Africa/Lagos')->withoutOverlapping(120)
            ->when(fn () => filled(config('services.tennis_data.atp_url')) && filled(config('services.tennis_data.wta_url')));
        $schedule->command('tennis:fetch-fixtures')->hourly()->withoutOverlapping();
        $schedule->command('tennis:predict')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('tennis:settle-results')->everyTenMinutes()->withoutOverlapping();
        // Daily top tennis picks → Telegram + OneSignal (after the morning predict).
        $schedule->command('tennis:notify')->dailyAt('03:30')->timezone('Africa/Lagos')->withoutOverlapping();

        // Monthly calibration snapshot — runs on the 1st at 02:00 Lagos.
        // Builds up the public Track Record timeline that proves system improvement.
        $schedule->command('calibration:snapshot')->monthlyOn(1, '02:00')->timezone('Africa/Lagos');

        // Newsletter — send today's 3 picks at 09:00 Lagos to confirmed subscribers
        $schedule->command('newsletter:send-daily')->dailyAt('09:00')->timezone('Africa/Lagos')->withoutOverlapping();

        // Trim request_logs older than 30 days so the table never grows forever.
        $schedule->call(function () {
            RequestLog::where('created_at', '<', now()->subDays(30))->delete();
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

        // ── Shalom AI (first-party shadow lab) ─────────────────────────
        // Completely isolated from public picks and publishing. It trains,
        // predicts and settles its own versioned record for admin review.
        $schedule->command('shalom:train')
            ->weeklyOn(1, '05:30')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping()
            ->runInBackground();
        $schedule->command('shalom:predict --hours-ahead=48')
            ->dailyAt('03:35')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping();
        $schedule->command('shalom:settle')
            ->everyFifteenMinutes()
            ->withoutOverlapping();
        $schedule->command('shalom:draft')
            ->dailyAt('11:00')
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
            Artisan::call('matches:backfill', [
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
        // Standings → weekly (Monday 01:06) to conserve quota, before the Monday
        // team/player-stat jobs that read team IDs from it and before the 01:12
        // prediction run. Tables persist between runs (a few matchdays stale
        // mid-week is the accepted tradeoff).
        $schedule->command('stats:fetch-standings')
            ->weeklyOn(1, '01:25')
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
            ->dailyAt('01:25')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30)
            ->runInBackground();
        $schedule->command('stats:fetch-fixture-intel --hours-ahead=48')
            ->dailyAt('09:30')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30)
            ->runInBackground();
        // Shorter late-day windows keep injury/suspension intelligence fresh
        // for evening kickoffs without repeatedly spending quota on the full
        // 48-hour fixture slate.
        $schedule->command('stats:fetch-fixture-intel --hours-ahead=12')
            ->dailyAt('15:30')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(20)
            ->runInBackground();
        $schedule->command('stats:fetch-fixture-intel --hours-ahead=12')
            ->dailyAt('21:30')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(20)
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
