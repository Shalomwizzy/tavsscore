<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BroadcastController extends Controller
{
    public function __construct(
        private readonly TelegramService $telegram,
        private readonly OneSignalService $oneSignal,
    ) {}

    public function index(): View
    {
        return view('admin.broadcast.index', [
            'telegramOk' => $this->telegram->isConfigured(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'title'   => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'],
            'channel' => ['required', 'in:telegram,push,both'],
            'path'    => ['nullable', 'string', 'max:120'],
        ]);

        $title   = trim($request->input('title'));
        $message = trim($request->input('message'));
        $channel = $request->input('channel');
        $path    = trim($request->input('path', '/')) ?: '/';

        $sent = [];

        if (in_array($channel, ['telegram', 'both'], true)) {
            $this->telegram->send("*{$title}*\n\n{$message}");
            $sent[] = 'Telegram';
        }

        if (in_array($channel, ['push', 'both'], true)) {
            $this->oneSignal->sendMatchAlert($title, $message, $path);
            $sent[] = 'Push notification';
        }

        $label = implode(' + ', $sent);

        return back()->with('success', "{$label} sent successfully.");
    }
}
