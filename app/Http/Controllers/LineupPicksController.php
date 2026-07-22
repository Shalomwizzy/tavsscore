<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Models\Prediction;
use App\Support\PickHelpers;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LineupPicksController extends Controller
{
    use ResolvesDateNav;

    public function index(Request $request): View
    {
        $tz       = config('app.timezone');
        $date     = $this->resolveDate($request->query('date'), $tz);
        $dateMeta = $this->buildDateMeta($date, $tz, 'lineup-picks.index');
        $today    = $date->copy()->startOfDay();
        $cutoff   = $date->copy()->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('has_lineup', true)
            ->whereNotNull('confidence')
            ->whereNotNull('predicted_outcome')
            ->where('predicted_outcome', '!=', 'Competitive Match')
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderByDesc('confidence')
            ->limit(10)
            ->get();

        // Resolve was_correct for any finished matches that haven't been graded yet
        foreach ($picks as $pick) {
            if ($pick->was_correct !== null) continue;
            if (! in_array($pick->match?->status, ['FT', 'AET', 'PEN'], true)) continue;
            if ($pick->match?->home_score === null) continue;

            $result = PickHelpers::resolveOutcome($pick);
            if ($result !== null) {
                $pick->update(['was_correct' => $result]);
                $pick->was_correct = $result;
            }
        }

        $offWindow = $this->offWindowState($date, $tz);

        return view('lineup-picks.index', compact('picks', 'dateMeta', 'offWindow'));
    }
}
