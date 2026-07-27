<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Models\BookingCode;
use Illuminate\View\View;

class BookingCodesController extends Controller
{
    public function index(): View
    {
        // Show today's full set of tickets (one card per market/ticket per
        // platform), plus any recent manual codes. Auto codes are idempotent per
        // (platform, slip_ref, pick_date), so re-runs update rather than pile up.
        $today = now('Africa/Lagos')->toDateString();

        $codes = BookingCode::query()
            ->where('status', 'published')
            ->where(function ($q) use ($today) {
                $q->whereDate('pick_date', $today)
                  ->orWhere(fn ($w) => $w->whereNull('pick_date')->where('created_at', '>=', now()->subDay()));
            })
            ->orderBy('platform')
            ->orderByDesc('total_odds')
            ->latest()
            ->get();

        $affiliates = AffiliateLink::active()->get()->keyBy('slug');

        return view('booking-codes.index', compact('codes', 'affiliates'));
    }
}
