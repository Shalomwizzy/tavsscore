<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class LineupPicksController extends Controller
{
    public function index(): View
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();

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

        return view('lineup-picks.index', compact('picks'));
    }
}
