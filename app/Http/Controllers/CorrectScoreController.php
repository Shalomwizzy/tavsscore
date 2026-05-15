<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class CorrectScoreController extends Controller
{
    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

        $predictions = Prediction::query()
            ->with('match')
            ->whereNotNull('likely_scores')
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', ['CANC', 'PST'])
            )
            ->orderByDesc('confidence')
            ->get()
            ->filter(fn ($p) => ! empty($p->likely_scores));

        return view('correct-score.index', compact('predictions'));
    }
}
