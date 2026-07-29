<?php

namespace App\Console\Commands;

use App\Models\RolloverPick;
use App\Services\RolloverService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Re-fetches the matches behind recently-voided rollover legs from API-Football
 * (so half-time scores are present) and re-grades them: a leg that voided only
 * because a market couldn't be graded (e.g. "HT Over 0.5" with no half-time
 * data) becomes Won/Lost once the data is available.
 */
class RegradeVoidRolloverPicks extends Command
{
    protected $signature = 'rollover:regrade-voids {--days=10 : How many past days of void legs to reconcile}';

    protected $description = 'Re-fetch results (with half-time) and re-grade voided rollover legs.';

    public function handle(RolloverService $rollover): int
    {
        $days  = max(1, (int) $this->option('days'));
        $tz    = config('app.timezone');

        $voids = RolloverPick::query()
            ->with('match')
            ->where('status', 'void')
            ->where('pick_date', '>=', now($tz)->subDays($days)->toDateString())
            ->get();

        if ($voids->isEmpty()) {
            $this->info('No void rollover legs to reconcile.');
            return self::SUCCESS;
        }

        // 1. Refresh each affected date from API-Football so half-time scores land.
        $dates = $voids
            ->map(fn (RolloverPick $p) => $p->match?->match_time
                ? Carbon::parse($p->match->match_time)->timezone($tz)->toDateString()
                : null)
            ->filter()->unique()->values();

        foreach ($dates as $date) {
            $this->line("  re-fetching {$date} from API-Football…");
            Artisan::call('fetch:date', ['date' => $date]);
        }

        // 2. Reset the void legs to pending and restore each day's full return,
        //    then let the normal settler re-grade (Won/Lost, or Void again if the
        //    data still isn't available).
        foreach ($voids as $pick) {
            $pick->update(['status' => 'pending', 'was_correct' => null]);
        }
        foreach ($voids->groupBy(fn (RolloverPick $p) => "{$p->challenge_id}-{$p->day_number}") as $group) {
            $this->restoreDayReturn($group->first());
        }

        $rollover->checkPendingPicks();

        // 3. Report the new state.
        foreach ($voids as $pick) {
            $fresh = $pick->fresh();
            $this->line("  {$fresh->groq_verdict} — {$fresh->match?->home_team} vs {$fresh->match?->away_team} "
                . "({$fresh->result_score}) → " . strtoupper($fresh->status));
        }

        $this->info('Void rollover legs reconciled.');
        return self::SUCCESS;
    }

    /** Recompute a day's return with every non-void leg back in the accumulator. */
    private function restoreDayReturn(RolloverPick $pick): void
    {
        $legs = RolloverPick::query()
            ->where('challenge_id', $pick->challenge_id)
            ->where('day_number', $pick->day_number)
            ->get();
        if ($legs->isEmpty()) return;

        $stake    = (float) $legs->first()->stake_amount;
        $combined = 1.0;
        foreach ($legs as $leg) {
            if ($leg->status === 'void') continue;
            $combined *= max(1.0, (float) $leg->implied_odds);
        }

        RolloverPick::query()
            ->where('challenge_id', $pick->challenge_id)
            ->where('day_number', $pick->day_number)
            ->update(['potential_return' => round($stake * $combined, 2)]);
    }
}
