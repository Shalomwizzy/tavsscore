<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\RolloverChallenge;
use App\Models\BlogPost;
use App\Models\Setting;
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

        // ── Three strongest model signals for the redesigned homepage ──
        $featuredSignals = Prediction::query()
            ->with('match')
            ->whereNotNull('predicted_outcome')
            ->where('predicted_outcome', '!=', 'Competitive Match')
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderByDesc('is_daily_pick')
            ->orderByDesc('confidence')
            ->limit(3)
            ->get();

        // ── Live now + today's upcoming slate (for the cinematic hero) ──
        $liveCount = FootballMatch::query()
            ->whereIn('status', ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE'])
            ->count();

        $upcoming = FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->whereIn('status', ['NS', 'TBD'])
            ->whereBetween('match_time', [now($tz), now($tz)->addHours(30)])
            ->orderBy('match_time')
            ->limit(10)
            ->get();

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
        $rolloverWon = $activeChallenge ? $activeChallenge->picks->where('status', 'won')->count() : 0;

        // ── All-time track record ─────────────────────────────────
        $allResolved = Prediction::query()
            ->where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->orderByDesc('created_at')
            ->with('match')
            ->get(['match_id', 'was_correct', 'confidence', 'predicted_outcome', 'created_at']);

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

        $recentPosts = BlogPost::published()
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        $homeMedia = [
            'hero' => Setting::get('homepage_hero_image'),
            'feature' => Setting::get('homepage_feature_image'),
            'tennis' => Setting::get('homepage_tennis_image'),
        ];

        return view('home.index', [
            'topPick'           => $topPick,
            'featuredSignals'   => $featuredSignals,
            'todayPickCount'    => $todayPickCount,
            'liveCount'         => $liveCount,
            'upcoming'          => $upcoming,
            'rolloverWon'       => $rolloverWon,
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
            'recentPosts'       => $recentPosts,
            'homeMedia'         => $homeMedia,
        ]);
    }
}
