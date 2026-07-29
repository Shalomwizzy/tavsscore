<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingCode;
use Illuminate\View\View;

/**
 * Admin visibility for the auto-built High-Risk accumulators. Read-only — they
 * generate with the daily spec and are booked by the Mac worker; this just
 * surfaces today's ticket + the won/lost history.
 */
class HighRiskAdminController extends Controller
{
    public function index(): View
    {
        $today = now('Africa/Lagos')->toDateString();

        $today_codes = BookingCode::query()
            ->where('slip_ref', 'high-risk')
            ->whereDate('pick_date', $today)
            ->orderByDesc('created_at')
            ->get();

        $history = BookingCode::query()
            ->where('slip_ref', 'high-risk')
            ->whereIn('status', ['won', 'lost'])
            ->whereNotNull('settled_at')
            ->orderByDesc('settled_at')
            ->limit(30)
            ->get();

        return view('admin.high-risk.index', compact('today_codes', 'history'));
    }
}
