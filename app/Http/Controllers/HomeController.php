<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\RolloverChallenge;
use App\Services\GroqService;
use App\Support\LeagueCoverage;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $tz     = config('app.timezone');
        $today  = now($tz)->startOfDay();
        $cutoff = now($tz)->endOfDay();

        // ── African matches ───────────────────────────────────────
        $africanCountries   = config('leagues.africa_countries', []);
        $africanContinental = config('leagues.africa_continental', []);

        $africanQuery = FootballMatch::query()
            ->with('prediction')
            ->where(fn ($q) => $q
                ->whereIn('league_country', $africanCountries)
                ->orWhereIn('league_id', $africanContinental)
            );

        $africanMatches = (clone $africanQuery)
            ->where('match_time', '>=', now($tz))
            ->where('match_time', '<=', now($tz)->addHours(36))
            ->whereNotIn('status', ['CANC', 'PST', 'ABD', 'AWD', 'WO'])
            ->orderBy('match_time')
            ->limit(6)
            ->get();

        if ($africanMatches->isEmpty()) {
            $africanMatches = (clone $africanQuery)
                ->whereIn('status', ['FT', 'AET', 'PEN', '1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'])
                ->orderByDesc('match_time')
                ->limit(6)
                ->get();
        }

        // ── Today's top daily pick ────────────────────────────────
        $topPick = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->where('pick_rank', 1)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->first();

        $todayPickCount = Prediction::query()
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->count();

        // ── Rollover challenge snapshot ───────────────────────────
        $rollover        = null;
        $rolloverDay     = 0;
        $rolloverBalance = 0;
        $rolloverPick    = null;
        $rolloverStatus  = null;

        $activeChallenge = RolloverChallenge::query()
            ->where('status', 'active')
            ->with(['picks' => fn ($q) => $q->orderBy('day_number')])
            ->latest('started_at')
            ->first();

        if ($activeChallenge) {
            $rollover       = $activeChallenge;
            $rolloverDay    = $activeChallenge->picks->count();
            $rolloverStatus = $activeChallenge->status;

            $lastWon = $activeChallenge->picks
                ->where('status', 'won')
                ->sortByDesc('day_number')
                ->first();

            $rolloverBalance = $lastWon
                ? (float) $lastWon->potential_return
                : (float) $activeChallenge->initial_stake;

            $rolloverPick = $activeChallenge->picks
                ->where('pick_date', now($tz)->toDateString())
                ->first();
        }

        // ── All-time track record ─────────────────────────────────
        $allResolved = Prediction::query()
            ->where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->orderByDesc('created_at')
            ->get(['was_correct', 'confidence', 'predicted_outcome', 'created_at']);

        $totalResolved = $allResolved->count();
        $totalCorrect  = $allResolved->where('was_correct', true)->count();
        $overallAcc    = $totalResolved > 0 ? round($totalCorrect / $totalResolved * 100, 1) : null;

        // Last 7 days
        $last7        = $allResolved->filter(fn ($p) => $p->created_at >= now($tz)->subDays(7));
        $last7Total   = $last7->count();
        $last7Correct = $last7->where('was_correct', true)->count();
        $last7Acc     = $last7Total > 0 ? round($last7Correct / $last7Total * 100, 1) : null;

        // Last 10 results for the streak dots
        $recentResults = $allResolved->take(10)->values();

        // Current hot/cold streak
        $streak     = 0;
        $streakType = null;
        foreach ($allResolved as $p) {
            $r = (bool) $p->was_correct;
            if ($streakType === null) {
                $streakType = $r;
                $streak     = 1;
            } elseif ($r === $streakType) {
                $streak++;
            } else {
                break;
            }
        }

        // ── Today's correct score and lineup counts ───────────────
        $correctScoreCount = Prediction::query()
            ->whereNotNull('likely_scores')
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', ['CANC', 'PST'])
            )
            ->count();

        $lineupPickCount = Prediction::query()
            ->where('has_lineup', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->count();

        return view('home.index', [
            'africanMatches'    => $africanMatches,
            'topPick'           => $topPick,
            'todayPickCount'    => $todayPickCount,
            'rollover'          => $rollover,
            'rolloverDay'       => $rolloverDay,
            'rolloverBalance'   => $rolloverBalance,
            'rolloverPick'      => $rolloverPick,
            'overallAcc'        => $overallAcc,
            'last7Acc'          => $last7Acc,
            'totalResolved'     => $totalResolved,
            'recentResults'     => $recentResults,
            'streak'            => $streak,
            'streakType'        => $streakType,
            'correctScoreCount' => $correctScoreCount,
            'lineupPickCount'   => $lineupPickCount,
        ]);
    }
}
