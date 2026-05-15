<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class CorrectScoreAdminController extends Controller
{
    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $todayPredictions = Prediction::query()
            ->with('match')
            ->whereNotNull('likely_scores')
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', ['CANC', 'PST'])
            )
            ->orderByDesc('confidence')
            ->get()
            ->filter(fn ($p) => ! empty($p->likely_scores));

        $history = Prediction::query()
            ->with('match')
            ->whereNotNull('likely_scores')
            ->whereHas('match', fn ($q) => $q
                ->where('match_time', '<', $today)
                ->whereNotIn('status', ['CANC', 'PST'])
            )
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->filter(fn ($p) => ! empty($p->likely_scores))
            ->groupBy(fn ($p) => $p->match?->match_time?->setTimezone($tz)->format('Y-m-d') ?? 'unknown');

        return view('admin.correct-score.index', compact('todayPredictions', 'history'));
    }
}
