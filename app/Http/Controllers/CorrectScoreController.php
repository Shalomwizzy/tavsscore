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
            ->where('is_correct_score_pick', true)
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$today, $cutoff])
                ->whereNotIn('status', ['CANC', 'PST'])
            )
            ->orderBy('correct_score_rank')
            ->get()
            ->filter(fn ($p) => ! empty($p->likely_scores));

        return view('correct-score.index', compact('predictions'));
    }
}
