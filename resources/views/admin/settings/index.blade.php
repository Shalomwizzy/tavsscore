@extends('layouts.admin')

@section('title', 'Site Settings')

@section('content')
<div style="max-width:680px;">
    <h1 style="font-size:1.3rem; font-weight:700; margin:0 0 1.5rem;">Site Settings</h1>

    @if(session('success'))
        <div style="background:#d1fae5; border:1px solid #6ee7b7; color:#065f46; padding:.75rem 1rem; border-radius:6px; margin-bottom:1.25rem; font-size:.85rem;">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf

        <div style="background:var(--card); border:1px solid var(--border); border-radius:10px; padding:1.5rem; display:flex; flex-direction:column; gap:1.25rem;">

            <div class="sb-section-label" style="margin:0 0 .25rem;">Social Media</div>

            <div>
                <label style="display:block; font-size:.78rem; font-weight:600; color:var(--dim); margin-bottom:.4rem;">
                    Telegram Channel URL
                </label>
                <input type="url" name="telegram_url"
                       value="{{ old('telegram_url', $settings['telegram_url']->value ?? '') }}"
                       placeholder="https://t.me/yourchannel"
                       style="width:100%; padding:.55rem .75rem; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text); font-size:.85rem; box-sizing:border-box;">
                @error('telegram_url')
                    <p style="color:#f87171; font-size:.75rem; margin:.3rem 0 0;">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label style="display:block; font-size:.78rem; font-weight:600; color:var(--dim); margin-bottom:.4rem;">
                    Twitter / X Profile URL
                </label>
                <input type="url" name="twitter_url"
                       value="{{ old('twitter_url', $settings['twitter_url']->value ?? '') }}"
                       placeholder="https://x.com/yourprofile"
                       style="width:100%; padding:.55rem .75rem; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text); font-size:.85rem; box-sizing:border-box;">
                @error('twitter_url')
                    <p style="color:#f87171; font-size:.75rem; margin:.3rem 0 0;">{{ $message }}</p>
                @enderror
            </div>

            <div class="sb-section-label" style="margin:.5rem 0 .25rem;">General</div>

            <div>
                <label style="display:block; font-size:.78rem; font-weight:600; color:var(--dim); margin-bottom:.4rem;">
                    Site Tagline
                </label>
                <input type="text" name="site_tagline"
                       value="{{ old('site_tagline', $settings['site_tagline']->value ?? '') }}"
                       placeholder="Real-Time Football Scores & AI Predictions"
                       maxlength="200"
                       style="width:100%; padding:.55rem .75rem; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text); font-size:.85rem; box-sizing:border-box;">
                @error('site_tagline')
                    <p style="color:#f87171; font-size:.75rem; margin:.3rem 0 0;">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label style="display:block; font-size:.78rem; font-weight:600; color:var(--dim); margin-bottom:.4rem;">
                    Contact Email
                </label>
                <input type="email" name="contact_email"
                       value="{{ old('contact_email', $settings['contact_email']->value ?? '') }}"
                       placeholder="hello@tavsscore.com"
                       maxlength="200"
                       style="width:100%; padding:.55rem .75rem; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text); font-size:.85rem; box-sizing:border-box;">
                @error('contact_email')
                    <p style="color:#f87171; font-size:.75rem; margin:.3rem 0 0;">{{ $message }}</p>
                @enderror
            </div>

        </div>

        <div style="margin-top:1.25rem;">
            <button type="submit"
                    style="background:var(--accent); color:#fff; border:none; border-radius:7px; padding:.6rem 1.4rem; font-size:.85rem; font-weight:600; cursor:pointer;">
                Save Settings
            </button>
        </div>
    </form>
</div>
@endsection
