<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\CalibrationService;
use App\Support\LeagueCoverage;
use App\Services\GroqService;
use App\Services\PredictionService;
use App\Support\PickHelpers;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyPickController extends Controller
{
    public function __construct(
        private readonly PredictionService  $predictionService,
        private readonly CalibrationService $calibration,
    ) {}

    public function index(Request $request): View
    {
        $tz       = config('app.timezone');
        $todayStr = now($tz)->toDateString();

        // Parse ?date=YYYY-MM-DD — clamped to last 365 days, never future
        $requested = $this->resolveDate($request->query('date'), $tz);
        $isToday   = $requested->toDateString() === $todayStr;
        $isPast    = $requested->lt(now($tz)->startOfDay());

        $start  = $requested->copy()->startOfDay();
        $end    = $requested->copy()->endOfDay();

        // ── Picks for the selected date ───────────────────────────
        // Past days always show what was picked then (with results).
        // Today auto-selects fresh if empty.
        $picks = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$start, $end]))
            ->orderBy('pick_rank')
            ->get();

        if ($picks->isEmpty() && $isToday) {
            $picks = $this->predictionService->selectDailyPicks();
        }

        // Auto-resolve any finished picks that the scheduler hasn't settled yet.
        // This runs inline so the page always shows up-to-date correct/incorrect
        // results even if predictions:check-outcomes hasn't fired recently.
        $this->autoResolve($picks);

        // Show results for past days; only for today's "yesterday recap" otherwise
        $withResult = ! $isToday;
        $formatted  = $picks->map(fn ($p) => $this->formatPick($p, withResult: $withResult));

        // ── Yesterday's recap (only when viewing today) ──────────
        $yesterdayFormatted = collect();
        if ($isToday) {
            $yPicks = Prediction::query()
                ->with('match')
                ->where('is_daily_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [
                    now($tz)->subDay()->startOfDay(),
                    now($tz)->subDay()->endOfDay(),
                ]))
                ->orderBy('pick_rank')
                ->get();
            $this->autoResolve($yPicks);
            $yesterdayFormatted = $yPicks->map(fn ($p) => $this->formatPick($p, withResult: true));
        }

        // ── Rolling 7-day accuracy ────────────────────────────────
        $sevenDaysAgo = now($tz)->subDays(7)->startOfDay();

        $recentPicks = Prediction::query()
            ->where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->whereHas('match', fn ($q) => $q
                ->where('match_time', '>=', $sevenDaysAgo)
                ->where('match_time', '<=', now($tz)->endOfDay())
            )
            ->get();

        $accuracy = [
            'total'   => $recentPicks->count(),
            'correct' => $recentPicks->where('was_correct', true)->count(),
            'pct'     => $recentPicks->count() > 0
                ? round($recentPicks->where('was_correct', true)->count() / $recentPicks->count() * 100, 1)
                : null,
        ];

        // ── Streak (always all-time) ──────────────────────────────
        $resolvedAll = Prediction::query()
            ->where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->orderByDesc('created_at')
            ->limit(60)
            ->get(['was_correct', 'created_at']);

        $streak = PickHelpers::streakFromResolved($resolvedAll);

        // Date metadata for the date picker UI
        $dateMeta = [
            'iso'        => $requested->toDateString(),
            'is_today'   => $isToday,
            'is_past'    => $isPast,
            'prev_iso'   => $requested->copy()->subDay()->toDateString(),
            'next_iso'   => $isToday ? null : $requested->copy()->addDay()->toDateString(),
            'today_iso'  => $todayStr,
            'min_iso'    => now($tz)->subDays(365)->toDateString(),
            'max_iso'    => $todayStr,
            'pretty'     => $requested->format('l, F j Y'),
        ];

        $count = Prediction::query()
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$start, $end]))
            ->count();

        $date = $requested->format('l, F j Y');

        // ── Empty-state context ───────────────────────────────────
        // Distinguishes "no covered fixtures at all today" (pre-season / off-day)
        // from "matches exist but none cleared the confidence bar" so the page
        // doesn't wrongly blame the AI during an off-window.
        $emptyState = null;
        if ($formatted->isEmpty() && $isToday) {
            $coveredToday = FootballMatch::query()
                ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
                ->whereNotIn('status', ['CANC', 'PST', 'ABD'])
                ->whereBetween('match_time', [$start, $end])
                ->exists();

            if ($coveredToday) {
                $emptyState = ['reason' => 'below_threshold'];
            } else {
                $nextKickoff = FootballMatch::query()
                    ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
                    ->where('match_time', '>', now($tz))
                    ->orderBy('match_time')
                    ->value('match_time');

                $emptyState = [
                    'reason'      => 'off_window',
                    'resume_date' => $nextKickoff ? Carbon::parse($nextKickoff)->timezone($tz)->format('l, F j') : null,
                ];
            }
        }

        return view('picks.index', compact(
            'formatted',
            'yesterdayFormatted',
            'accuracy',
            'streak',
            'date',
            'dateMeta',
            'count',
            'emptyState',
        ));
    }

    /**
     * For any picks that are finished but not yet settled, compute and persist
     * was_correct inline. This makes the page self-healing when the scheduler
     * is delayed — no full-page refresh needed after outcomes are resolved.
     */
    private function autoResolve(EloquentCollection $picks): void
    {
        $finished = ['FT', 'AET', 'PEN'];
        foreach ($picks as $p) {
            if ($p->was_correct !== null) continue;
            if (! in_array($p->match?->status, $finished, true)) continue;
            if ($p->match?->home_score === null) continue;

            $result = PickHelpers::resolveOutcome($p);
            if ($result !== null) {
                $p->update(['was_correct' => $result]);
                $p->was_correct = $result;
            }
        }
    }

    /**
     * Validate ?date= parameter — must be YYYY-MM-DD, not in future, not >365 days back.
     * Falls back to today on any bad input.
     */
    private function resolveDate(?string $raw, string $tz): Carbon
    {
        $today = now($tz)->startOfDay();
        if (blank($raw)) return $today;

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $raw, $tz);
            if (! $parsed) return $today;
        } catch (\Throwable) {
            return $today;
        }

        $parsed = $parsed->startOfDay();
        if ($parsed->gt($today)) return $today;
        if ($parsed->lt($today->copy()->subDays(365))) return $today;
        return $parsed;
    }

    private function formatPick(Prediction $p, bool $withResult = false): array
    {
        $hw   = (float) $p->home_win_prob;
        $d    = (float) $p->draw_prob;
        $aw   = (float) $p->away_win_prob;
        $o25  = (float) ($p->over_25_prob ?? 0);
        $btts = (float) ($p->btts_prob    ?? 0);

        // Historical accuracy at this AI confidence band (null = insufficient data)
        $historicalPct = $p->confidence ? $this->calibration->historicalPct((int) $p->confidence) : null;

        // Odds movement: compare opening vs closing implied probabilities
        $oddsMovement = null;
        $opening      = is_array($p->opening_odds) ? $p->opening_odds : null;
        $closing      = is_array($p->closing_odds)  ? $p->closing_odds  : null;
        if ($opening && $closing) {
            $marketKey = match ($p->predicted_outcome) {
                'Home Win'         => 'home_win',
                'Away Win'         => 'away_win',
                'Draw'             => 'draw',
                'Over 2.5 Goals'   => 'over_25',
                'Both Teams Score' => 'btts',
                default            => null,
            };
            if ($marketKey && isset($opening[$marketKey], $closing[$marketKey])) {
                $delta = round($closing[$marketKey] - $opening[$marketKey], 1);
                $oddsMovement = [
                    'delta'     => $delta,
                    'direction' => $delta > 1.5 ? 'with' : ($delta < -1.5 ? 'against' : 'neutral'),
                ];
            }
        }

        $probs      = [$hw, $d, $aw];
        rsort($probs);
        $gap        = $probs[0] - $probs[1];
        $confidence = $gap >= 22 ? 'HIGH' : ($gap >= 14 ? 'MEDIUM' : 'SOLID');

        $outcome   = $p->predicted_outcome ?? 'Home Win';
        $homeName  = $p->match?->home_team ?? 'Home';
        $awayName  = $p->match?->away_team ?? 'Away';

        $pickLabel = match ($outcome) {
            'Home Win'         => $homeName . ' to Win',
            'Away Win'         => $awayName . ' to Win',
            'Draw'             => 'Match Ends in a Draw',
            'Over 2.5 Goals'   => 'Over 2.5 Goals',
            'Under 2.5 Goals'  => 'Under 2.5 Goals',
            'Both Teams Score' => 'Both Teams to Score',
            default            => $outcome,
        };

        $pickEmoji = match ($outcome) {
            'Home Win'         => '🏠',
            'Away Win'         => '✈️',
            'Draw'             => '🤝',
            'Over 2.5 Goals'   => '⚽',
            'Under 2.5 Goals'  => '🔒',
            'Both Teams Score' => '🎯',
            default            => '📊',
        };

        $isAi = ! blank($p->analysis)
            && $p->analysis !== GroqService::FALLBACK_ANALYSIS
            && $p->analysis !== 'Prediction pending';

        // Actual score for resolved picks
        $actualScore = null;
        if ($withResult && $p->match && $p->match->home_score !== null) {
            $actualScore = $p->match->home_score . '–' . $p->match->away_score;
        }

        // Live score (always available when status is in-progress)
        $liveScore = null;
        $isLive    = in_array($p->match?->status, ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'], true);
        $isFt      = in_array($p->match?->status, ['FT', 'AET', 'PEN'], true);
        if (($isLive || $isFt) && $p->match && $p->match->home_score !== null) {
            $liveScore = $p->match->home_score . '–' . ($p->match->away_score ?? 0);
        }

        return [
            'id'             => $p->id,
            'rank'           => $p->pick_rank,
            'pick_label'     => $pickLabel,
            'pick_emoji'     => $pickEmoji,
            'outcome'        => $outcome,
            'confidence'     => $confidence,
            'confidence_pct' => $p->confidence,
            'historical_pct' => $historicalPct,
            'odds_movement'  => $oddsMovement,
            'tips'           => is_array($p->tips) ? $p->tips : [],
            'hw'             => $hw,
            'd'              => $d,
            'aw'             => $aw,
            'o25'            => $o25,
            'btts'           => $btts,
            'analysis'       => $p->analysis,
            'analysis_pidgin'  => $p->analysis_pidgin,
            'analysis_swahili' => $p->analysis_swahili,
            'is_ai'          => $isAi,
            'was_correct'    => $p->was_correct,
            'actual_score'   => $actualScore,
            'live_score'     => $liveScore,
            'match'          => [
                'home'        => $homeName,
                'away'        => $awayName,
                'league'      => \App\Support\LeagueCoverage::formatName($p->match?->league, $p->match?->league_country),
                'time'        => $p->match?->match_time?->format('H:i'),
                'status'      => $p->match?->status ?? '',
                'home_score'  => $p->match?->home_score,
                'away_score'  => $p->match?->away_score,
                'elapsed'     => $p->match?->elapsed,
            ],
        ];
    }
}
