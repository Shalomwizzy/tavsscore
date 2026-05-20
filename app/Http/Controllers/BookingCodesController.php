<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Models\BookingCode;
use Illuminate\View\View;

class BookingCodesController extends Controller
{
    public function index(): View
    {
        // Most recent booking code per platform from the last 30 days
        $codes = BookingCode::where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->get()
            ->unique('platform');

        $affiliates = AffiliateLink::active()->get()->keyBy('slug');

        return view('booking-codes.index', compact('codes', 'affiliates'));
    }
}
