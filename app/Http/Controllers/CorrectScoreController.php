<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Models\Prediction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CorrectScoreController extends Controller
{
    use ResolvesDateNav;

    public function index(Request $request): View
    {
        $tz       = config('app.timezone');
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, 'correct-score.index');
        $today    = $date->copy()->startOfDay();
        $cutoff   = $date->copy()->endOfDay();

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

        return view('correct-score.index', compact('predictions', 'dateMeta'));
    }
}
