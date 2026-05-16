<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\PredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class Team3PlusAdminController extends Controller
{
    public function __construct(private readonly PredictionService $predictionService) {}

    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $todayPicks = Prediction::query()
            ->with('match')
            ->where('is_team3plus_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('team3plus_rank')
            ->get();

        $history = Prediction::query()
            ->with('match')
            ->where('is_team3plus_pick', true)
            ->whereHas('match', fn ($q) => $q->where('match_time', '<', $today))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->groupBy(fn ($p) => $p->match?->match_time?->setTimezone($tz)->format('Y-m-d') ?? 'unknown');

        $resolved = Prediction::where('is_team3plus_pick', true)->where('team3plus_notified', true)->with('match')->get();
        $total    = $resolved->count();
        $correct  = $resolved->filter(function ($p) {
            if (! $p->match) return false;
            $label = $p->team3plus_label ?? 'Home';
            return $label === 'Home' ? (int)$p->match->home_score < 3 : (int)$p->match->away_score < 3;
        })->count();
        $accuracy = $total > 0 ? round($correct / $total * 100, 1) : null;

        return view('admin.team3plus.index', compact('todayPicks', 'history', 'total', 'correct', 'accuracy'));
    }

    public function refresh(): RedirectResponse
    {
        $picks = $this->predictionService->selectTeam3PlusPicks();
        return redirect()->route('admin.team3plus.index')
            ->with('success', "Re-selected {$picks->count()} Team 3+ picks.");
    }
}
