<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingCode;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use App\Services\Booking\BookingOutcomeLearningService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingCodeController extends Controller
{
    public function index(BookingOutcomeLearningService $learning): View
    {
        $history = BookingCode::query()
            ->with('legs')
            ->latest('created_at')
            ->limit(50)
            ->get();

        $stats = [
            'total' => BookingCode::count(),
            'published' => BookingCode::where('status', 'published')->count(),
            'won' => BookingCode::where('status', 'won')->count(),
            'lost' => BookingCode::where('status', 'lost')->count(),
        ];
        $stats['settled'] = $stats['won'] + $stats['lost'];
        $workerReady = filled(config('services.booking_worker.token'));
        $learningFeedback = $learning->marketFeedback();

        return view('admin.booking-code.index', compact('history', 'stats', 'workerReady', 'learningFeedback'));
    }

    public function send(Request $request, TelegramService $telegram, OneSignalService $oneSignal)
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'max:50'],
            'code'     => ['required', 'string', 'max:50'],
            'note'     => ['nullable', 'string', 'max:200'],
            'total_odds' => ['required', 'numeric', 'min:2', 'max:500'],
            'pick_date' => ['required', 'date'],
        ]);

        BookingCode::create($data + [
            'source' => 'manual',
            'status' => 'published',
        ]);

        $affiliate    = \App\Models\AffiliateLink::where('slug', strtolower(str_replace(' ', '', $data['platform'])))->where('is_active', true)->first();
        $affiliateUrl = $affiliate?->register_url ?: null;

        $telegram->sendBookingCode($data['platform'], strtoupper($data['code']), $data['note'] ?? '', config('app.url'), $affiliateUrl);

        $oneSignal->sendMatchAlert(
            title:   "🎟️ Booking Code — {$data['platform']}",
            message: "Code: " . strtoupper($data['code']) . ($data['note'] ? " · {$data['note']}" : '') . " — Tap to view picks",
            path:    '/picks',
        );

        return back()->with('success', "Booking code sent to Telegram and push notifications.");
    }

    /** Clear only booking-code records after the admin has explicitly confirmed it. */
    public function clear()
    {
        $count = BookingCode::count();
        BookingCode::query()->delete();

        return back()->with('success', "Fresh start complete: {$count} booking code(s) deleted.");
    }

    /** Allow an admin to run the outcome check immediately; the scheduler also runs it automatically. */
    public function grade()
    {
        Artisan::call('booking:grade');

        return back()->with('success', trim(Artisan::output()) ?: 'Booking-code outcomes checked.');
    }

    /** Re-send today's active codes after a message-format or link update. */
    public function resend(TelegramService $telegram)
    {
        $codes = BookingCode::query()
            ->where('status', 'published')
            ->where('total_odds', '>=', 2)
            ->whereDate('pick_date', now('Africa/Lagos')->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($codes as $code) {
            $telegram->sendBookingCode(
                $code->platform,
                strtoupper($code->code),
                (string) ($code->note ?? ''),
                config('app.url'),
                ticketUrl: $code->link,
            );
        }

        return back()->with('success', "Re-sent {$codes->count()} active booking code message(s) to Telegram.");
    }

    public function destroy(BookingCode $bookingCode)
    {
        $bookingCode->delete();

        return back()->with('success', 'Booking code deleted.');
    }
}
