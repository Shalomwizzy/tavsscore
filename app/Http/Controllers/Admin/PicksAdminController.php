<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\FootballPredictionBoardRefresher;
use App\Services\PredictionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class PicksAdminController extends Controller
{
    public function __construct(
        private readonly PredictionService $predictionService,
        private readonly FootballPredictionBoardRefresher $boardRefresher,
    )
    {
    }

    public function index(): View
    {
        $tz    = config('app.timezone');
        $today = now($tz)->startOfDay();
        $end   = now($tz)->endOfDay();

        $todayPicks = Prediction::with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $end]))
            ->orderBy('pick_rank')
            ->get();

        $history = Prediction::with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->where('match_time', '<', $today))
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->groupBy(fn ($p) => $p->match?->match_time?->format('Y-m-d') ?? $p->created_at->format('Y-m-d'));

        $resolvedAll = Prediction::where('is_daily_pick', true)
            ->whereNotNull('was_correct')
            ->get(['was_correct']);

        $totalAll   = $resolvedAll->count();
        $correctAll = $resolvedAll->where('was_correct', true)->count();
        $accuracy   = $totalAll > 0 ? round($correctAll / $totalAll * 100, 1) : null;

        $lineupPicks = Prediction::with('match')
            ->where('has_lineup', true)
            ->whereNotNull('confidence')
            ->whereNotNull('predicted_outcome')
            ->where('predicted_outcome', '!=', 'Competitive Match')
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $end]))
            ->orderByDesc('confidence')
            ->limit(10)
            ->get();

        $drawPicks = Prediction::with('match')
            ->where('is_draw_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $end]))
            ->orderBy('draw_rank')
            ->get();

        $ggPicks = Prediction::with('match')
            ->where('is_gg_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $end]))
            ->orderBy('gg_rank')
            ->get();

        // Draw accuracy
        $drawResolved = Prediction::where('is_draw_pick', true)->whereNotNull('was_correct')->get(['was_correct']);
        $drawAccuracy = $drawResolved->count() > 0
            ? round($drawResolved->where('was_correct', true)->count() / $drawResolved->count() * 100, 1)
            : null;

        // GG accuracy
        $ggResolved  = Prediction::where('is_gg_pick', true)->whereNotNull('was_correct')->get(['was_correct']);
        $ggAccuracy  = $ggResolved->count() > 0
            ? round($ggResolved->where('was_correct', true)->count() / $ggResolved->count() * 100, 1)
            : null;

        // Keep blade variable name consistent with existing template
        $today = $todayPicks;

        return view('admin.picks.index', compact(
            'today', 'history', 'totalAll', 'correctAll', 'accuracy',
            'lineupPicks', 'drawPicks', 'ggPicks', 'drawAccuracy', 'ggAccuracy',
        ));
    }

    public function refresh(): RedirectResponse
    {
        $picks = $this->predictionService->selectDailyPicks();

        if ($picks->isNotEmpty()) {
            Artisan::call('picks:notify', ['--type' => 'daily']);
        }

        return redirect()->route('admin.picks')
            ->with('success', "Re-selected {$picks->count()} daily picks.");
    }

    public function refreshDraw(): RedirectResponse
    {
        $picks = $this->predictionService->selectDrawPicks();

        if ($picks->isNotEmpty()) {
            Artisan::call('picks:notify', ['--type' => 'draw']);
        }

        return redirect()->route('admin.picks')
            ->with('success', "Re-selected {$picks->count()} draw picks.");
    }

    public function refreshGG(): RedirectResponse
    {
        $picks = $this->predictionService->selectGGPicks();

        if ($picks->isNotEmpty()) {
            Artisan::call('picks:notify', ['--type' => 'gg']);
        }

        return redirect()->route('admin.picks')
            ->with('success', "Re-selected {$picks->count()} GG picks.");
    }

    public function rebuildDaily(): RedirectResponse { return $this->rebuild('daily'); }
    public function rebuildDraw(): RedirectResponse { return $this->rebuild('draw'); }
    public function rebuildGG(): RedirectResponse { return $this->rebuild('gg'); }

    public function recheck(): RedirectResponse
    {
        Artisan::call('predictions:check-outcomes', ['--days' => 7]);

        return redirect()->route('admin.picks')
            ->with('success', 'Re-checked outcomes for the last 7 days.');
    }

    public function regeneratePick(Prediction $prediction, Request $request): RedirectResponse
    {
        $type  = $request->input('type', 'daily');
        $match = $prediction->match;

        if ($match) {
            if ($type === 'lineup') {
                $this->predictionService->regenerateWithLineup($match);
            } else {
                $this->predictionService->generateForMatch($match);
            }
        }

        Artisan::call('picks:notify', ['--type' => $type]);

        $label = $match ? "{$match->home_team} vs {$match->away_team}" : "match #{$prediction->id}";

        return back()->with('success', "Regenerated prediction for {$label} and pushed {$type} notification.");
    }

    private function rebuild(string $type): RedirectResponse
    {
        try {
            $this->boardRefresher->refreshFixturesAndBoards();
            $picks = match ($type) {
                'daily' => $this->predictionService->selectDailyPicks(),
                'draw' => $this->predictionService->selectDrawPicks(),
                'gg' => $this->predictionService->selectGGPicks(),
            };
            if ($picks->isNotEmpty()) Artisan::call('picks:notify', ['--type' => $type, '--force' => true]);

            return redirect()->route('admin.picks')->with('success', "Latest fixtures and model boards rebuilt. {$picks->count()} {$type} pick(s) qualified and were sent where available.");
        } catch (\Throwable $exception) {
            return redirect()->route('admin.picks')->with('error', $exception->getMessage());
        }
    }
}
