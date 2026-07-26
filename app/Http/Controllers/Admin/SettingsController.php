<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = Setting::all()->keyBy('key');
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'telegram_url'  => ['nullable', 'url', 'max:500'],
            'twitter_url'   => ['nullable', 'url', 'max:500'],
            'site_tagline'  => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'rollover_min_board_prob' => ['nullable', 'numeric', 'min:50', 'max:99'],
            'pick_strong_bonus'       => ['nullable', 'numeric', 'min:1', 'max:2'],
            'pick_conflict_penalty'   => ['nullable', 'numeric', 'min:0.4', 'max:1'],
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?: null);
        }

        return back()->with('success', 'Settings saved.');
    }
}
