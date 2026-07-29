<?php

namespace App\Http\Controllers;

use App\Models\BookingCode;
use Illuminate\View\View;

/**
 * Public "High Risk" section — the model's confident calls stacked into a
 * big-odds accumulator (booked as a code). Deliberately risky; shown with a
 * prominent warning.
 */
class HighRiskController extends Controller
{
    public function index(): View
    {
        $today = now('Africa/Lagos')->toDateString();

        $codes = BookingCode::query()
            ->where('slip_ref', 'high-risk')
            ->where('status', 'published')
            ->whereDate('pick_date', $today)
            ->orderByDesc('total_odds')
            ->get();

        $history = BookingCode::query()
            ->where('slip_ref', 'high-risk')
            ->whereIn('status', ['won', 'lost'])
            ->whereNotNull('settled_at')
            ->orderByDesc('settled_at')
            ->limit(20)
            ->get();

        $wonCount = $history->where('status', 'won')->count();

        return view('high-risk.index', compact('codes', 'history', 'wonCount'));
    }
}
