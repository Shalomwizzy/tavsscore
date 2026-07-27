<?php

namespace App\Http\Controllers;

use App\Models\RolloverChallenge;
use App\Models\RolloverPick;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RolloverController extends Controller
{
    private const TZ = 'Africa/Lagos';

    public function index(): View
    {
        return $this->renderFor(null);
    }

    public function show(string $date): View
    {
        return $this->renderFor($date);
    }

    private function renderFor(?string $dateStr): View
    {
        $tz       = self::TZ;
        $viewDate = $dateStr
            ? Carbon::createFromFormat('Y-m-d', $dateStr, $tz)->startOfDay()
            : CarbonImmutable::now($tz)->startOfDay();

        // Find the challenge that covers this date
        $challenge = RolloverChallenge::query()
            ->where('started_at', '<=', $viewDate->toDateString())
            ->latest('started_at')
            ->first();

        $todayLegs = collect();
        $allPicks  = collect();
        $dayGroups = collect();

        if ($challenge) {
            $todayLegs = RolloverPick::query()
                ->with(['match', 'prediction'])
                ->where('challenge_id', $challenge->id)
                ->where('pick_date', $viewDate->toDateString())
                ->orderByDesc('implied_odds')
                ->get();

            $allPicks = RolloverPick::query()
                ->with(['match'])
                ->where('challenge_id', $challenge->id)
                ->orderBy('day_number')
                ->get();

            // Legs grouped into daily tickets, newest day first.
            $dayGroups = $allPicks->groupBy('day_number')->sortKeysDesc();
        }

        // A day counts as a single rollover step: won when it has legs and none
        // lost or still pending (voids-only days push and continue).
        $dayStat = static function (Collection $legs): string {
            if ($legs->contains(fn ($l) => $l->status === 'lost'))    return 'lost';
            if ($legs->contains(fn ($l) => $l->status === 'pending')) return 'pending';
            return 'won';
        };

        // Day-level tallies drive the hero + progress dots (a day = one step,
        // regardless of how many legs the ticket held).
        $wonDays   = $dayGroups->filter(fn ($legs) => $dayStat($legs) === 'won')->count();
        $totalDays = $dayGroups->count();

        // Current winning streak, counted over settled days newest-first.
        $streak = 0;
        foreach ($dayGroups as $legs) {
            $s = $dayStat($legs);
            if ($s === 'won') { $streak++; }
            elseif ($s === 'pending') { continue; }
            else { break; }
        }

        // Per-day status map for the 10 progress dots (day_number => status).
        $dayStatuses = $dayGroups->mapWithKeys(fn ($legs, $day) => [$day => $dayStat($legs)]);

        // Date navigation: find previous and next pick dates globally
        $prevPick = RolloverPick::query()
            ->where('pick_date', '<', $viewDate->toDateString())
            ->orderByDesc('pick_date')
            ->first();

        $nextPick = RolloverPick::query()
            ->where('pick_date', '>', $viewDate->toDateString())
            ->orderBy('pick_date')
            ->first();

        // All completed challenges with their picks (excluding the current one)
        $pastChallenges = RolloverChallenge::query()
            ->where('status', 'complete')
            ->when($challenge, fn ($q) => $q->where('id', '!=', $challenge->id))
            ->latest('started_at')
            ->with(['picks' => fn ($q) => $q->with('match')->orderBy('day_number')])
            ->limit(5)
            ->get();

        return view('rollover.index', compact(
            'challenge', 'todayLegs', 'allPicks', 'dayGroups', 'viewDate', 'prevPick', 'nextPick',
            'pastChallenges', 'wonDays', 'totalDays', 'streak', 'dayStatuses'
        ));
    }
}
