<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingCode;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class BookingCodeController extends Controller
{
    public function index()
    {
        $history = BookingCode::latest()->limit(20)->get();

        return view('admin.booking-code.index', compact('history'));
    }

    public function send(Request $request, TelegramService $telegram, OneSignalService $oneSignal)
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'max:50'],
            'code'     => ['required', 'string', 'max:50'],
            'note'     => ['nullable', 'string', 'max:200'],
        ]);

        BookingCode::create($data);

        $telegram->sendBookingCode($data['platform'], strtoupper($data['code']), $data['note'] ?? '', config('app.url'));

        $oneSignal->sendMatchAlert(
            title:   "🎟️ Booking Code — {$data['platform']}",
            message: "Code: " . strtoupper($data['code']) . ($data['note'] ? " · {$data['note']}" : '') . " — Tap to view picks",
            path:    '/picks',
        );

        return back()->with('success', "Booking code sent to Telegram and push notifications.");
    }
}
