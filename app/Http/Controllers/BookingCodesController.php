<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Models\BookingCode;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingCodesController extends Controller
{
    public function index(Request $request): View
    {
        $today = CarbonImmutable::now('Africa/Lagos')->startOfDay();
        $requested = $request->query('date');
        try {
            $selectedDate = filled($requested)
                ? CarbonImmutable::createFromFormat('Y-m-d', (string) $requested, 'Africa/Lagos')->startOfDay()
                : $today;
        } catch (\Throwable) {
            $selectedDate = $today;
        }
        // Do not let the public date controls browse into a future ticket day.
        if ($selectedDate->greaterThan($today)) {
            $selectedDate = $today;
        }
        $selected = $selectedDate->toDateString();

        // Show only the requested day. Auto codes are idempotent per
        // (platform, slip_ref, pick_date), so re-runs update rather than pile up.
        $dayCodes = BookingCode::query()
            ->with('legs')
            ->where('total_odds', '>=', 2)
            ->where(function ($q) use ($selected, $selectedDate, $today) {
                $q->whereDate('pick_date', $selected);
                // Retain older manual records without a pick_date on today's
                // default view, but never leak them into another date's board.
                if ($selectedDate->equalTo($today)) {
                    $q->orWhere(fn ($w) => $w->whereNull('pick_date')->where('created_at', '>=', $today));
                }
            })
            ->orderBy('platform')
            ->orderByDesc('total_odds')
            ->latest()
            ->get();

        $codes = $dayCodes->where('status', 'published')->values();
        $history = $dayCodes->whereIn('status', ['won', 'lost'])->values();

        $wonCount = $history->where('status', 'won')->count();
        $settledCount = $history->count();
        $affiliates = AffiliateLink::active()->get()->keyBy('slug');
        $previousDate = $selectedDate->subDay()->toDateString();
        $nextDate = $selectedDate->lessThan($today) ? $selectedDate->addDay()->toDateString() : null;
        $isToday = $selectedDate->equalTo($today);

        return view('booking-codes.index', compact('codes', 'affiliates', 'history', 'wonCount', 'settledCount', 'selectedDate', 'previousDate', 'nextDate', 'isToday'));
    }
}
