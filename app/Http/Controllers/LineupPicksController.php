<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateNav;
use App\Models\Prediction;
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

        return view('lineup-picks.index', compact('picks', 'dateMeta'));
    }
}
