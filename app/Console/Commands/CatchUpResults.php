<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Support\LeagueCoverage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Re-fetches results for past matches that never reached a final status —
 * typically because the API-Football quota was exhausted when they finished, so
 * their score was never pulled and their prediction stayed pending forever.
 *
 * Scheduled right after the daily quota reset so clearing stale pending results
 * is the FIRST thing that happens each day. Re-fetches the affected dates, then
 * grades every outcome so nothing is left pending.
 */
class CatchUpResults extends Command
{
    protected $signature = 'results:catch-up {--days=14 : How many past days to reconcile}';

    protected $description = 'Re-fetch missed past results and settle any pending prediction outcomes.';

    private const NON_FINAL = ['FT', 'AET', 'PEN', 'CANC', 'PST', 'ABD', 'AWD', 'WO'];

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $tz   = config('app.timezone');

        if (Cache::get('api_football_quota_exhausted')) {
            $this->warn('API quota exhausted — will only grade already-final matches; re-fetch skipped.');
        } else {
            $this->refetchStuckDates($days, $tz);
        }

        // Grade everything (this also settles prediction_logs, idempotently).
        $this->info('Settling outcomes…');
        Artisan::call('predictions:check-outcomes', ['--days' => $days], $this->getOutput());

        $stillPending = FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->where('match_time', '<', now()->subMinutes(150))
            ->where('match_time', '>=', now()->subDays($days))
            ->whereNotIn('status', self::NON_FINAL)
            ->count();

        if ($stillPending > 0) {
            $this->warn("{$stillPending} past match(es) still not final (no result from provider yet). Will retry next run.");
        } else {
            $this->info('All past matches in window are final. No pending results.');
        }

        return self::SUCCESS;
    }

    private function refetchStuckDates(int $days, string $tz): void
    {
        // Past matches (finished by wall-clock) that never reached a final status.
        $stuck = FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->where('match_time', '<', now()->subMinutes(150))
            ->where('match_time', '>=', now()->subDays($days))
            ->whereNotIn('status', self::NON_FINAL)
            ->get(['id', 'match_time']);

        if ($stuck->isEmpty()) {
            $this->info('No stuck past matches to re-fetch.');
            return;
        }

        $dates = $stuck
            ->map(fn (FootballMatch $m) => Carbon::parse($m->match_time)->timezone($tz)->toDateString())
            ->unique()
            ->values();

        $this->info("Re-fetching {$dates->count()} date(s) with unresolved results…");

        foreach ($dates as $date) {
            if (Cache::get('api_football_quota_exhausted')) {
                $this->warn('Quota hit mid-run — stopping re-fetch, grading what we have.');
                break;
            }
            $this->line("  fetching {$date}");
            Artisan::call('fetch:date', ['date' => $date]);
        }
    }
}
