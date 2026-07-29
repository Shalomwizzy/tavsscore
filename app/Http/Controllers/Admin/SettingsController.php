<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = Setting::all()->keyBy('key');
        return view('admin.settings.index', compact('settings'));
    }

    public function homepageMedia(): View
    {
        $settings = Setting::all()->keyBy('key');

        return view('admin.homepage-media.index', compact('settings'));
    }

    public function tennisMedia(): View
    {
        $settings = Setting::all()->keyBy('key');

        return view('admin.tennis.media', compact('settings'));
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
            'homepage_hero_image'     => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'homepage_feature_image'  => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'homepage_tennis_image'   => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'fantasy_feature_image'   => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'tennis_page_hero_image'  => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        foreach ($data as $key => $value) {
            if ($value instanceof \Illuminate\Http\UploadedFile) continue;
            Setting::set($key, $value ?: null);
        }

        foreach ([
            'homepage_hero_image' => 'homepage_hero_image',
            'homepage_feature_image' => 'homepage_feature_image',
            'homepage_tennis_image' => 'homepage_tennis_image',
            'fantasy_feature_image' => 'fantasy_feature_image',
            'tennis_page_hero_image' => 'tennis_page_hero_image',
        ] as $input => $setting) {
            if (! $request->hasFile($input)) continue;

            $directory = public_path('images/home');
            File::ensureDirectoryExists($directory);
            $file = $request->file($input);
            $filename = $setting . '-' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $filename);
            Setting::set($setting, 'images/home/' . $filename);
        }

        return back()->with('success', 'Settings saved.');
    }
}
