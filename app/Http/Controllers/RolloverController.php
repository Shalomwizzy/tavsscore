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

        $todayPick = null;
        $allPicks  = collect();

        if ($challenge) {
            $todayPick = RolloverPick::query()
                ->with(['match', 'prediction'])
                ->where('challenge_id', $challenge->id)
                ->where('pick_date', $viewDate->toDateString())
                ->first();

            $allPicks = RolloverPick::query()
                ->with(['match'])
                ->where('challenge_id', $challenge->id)
                ->orderBy('day_number')
                ->get();
        }

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
            'challenge', 'todayPick', 'allPicks', 'viewDate', 'prevPick', 'nextPick', 'pastChallenges'
        ));
    }
}
