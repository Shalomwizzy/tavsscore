<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TavsScore | Football Live Scores & AI Predictions')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#10b981">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="TavsScore">
    <meta name="description" content="@yield('meta_description', 'TavsScore delivers real-time football live scores, AI-powered match predictions and football news covering Premier League, Champions League, La Liga, Serie A, Bundesliga and more.')">

    {{-- Open Graph --}}
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="TavsScore">
    <meta property="og:title"       content="@yield('og_title', 'TavsScore | Football Live Scores & AI Predictions')">
    <meta property="og:description" content="@yield('og_description', 'Real-time football live scores, AI-powered match predictions and football news - all free on TavsScore.')">
    @hasSection('og_image')
    <meta property="og:image"       content="@yield('og_image')">
    @endif
    @hasSection('canonical')
    <meta property="og:url"         content="@yield('canonical')">
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="@yield('og_title', 'TavsScore | Football Live Scores & AI Predictions')">
    <meta name="twitter:description" content="@yield('og_description', 'Real-time football live scores, AI-powered match predictions and football news.')">
    @hasSection('og_image')
    <meta name="twitter:image"       content="@yield('og_image')">
    @endif

    {{-- Canonical --}}
    @hasSection('canonical')
    <link rel="canonical" href="@yield('canonical')">
    @endif

    {{-- WebSite structured data --}}
    <script type="application/ld+json">{"@context":"https://schema.org","@type":"WebSite","name":"TavsScore","url":"{{ config('app.url') }}","description":"Real-time football live scores, AI-powered match predictions and football news.","potentialAction":{"@type":"SearchAction","target":{"@type":"EntryPoint","urlTemplate":"{{ url('/blog') }}?q={search_term_string}"},"query-input":"required name=search_term_string"}}</script>

    {{-- Google Analytics 4 (consent-gated) --}}
    @if(config('services.ga.id'))
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', {
            'ad_storage':         'denied',
            'analytics_storage':  'denied',
            'ad_user_data':       'denied',
            'ad_personalization': 'denied',
        });
        gtag('js', new Date());
        gtag('config', @json(config('services.ga.id')), { 'anonymize_ip': true });
        if (localStorage.getItem('ts_cookie_consent') === 'accepted') {
            gtag('consent', 'update', {
                'ad_storage':         'granted',
                'analytics_storage':  'granted',
                'ad_user_data':       'granted',
                'ad_personalization': 'granted',
            });
        }
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.ga.id') }}"></script>
    @endif

    {{-- Google AdSense --}}
    @if(config('services.adsense.client'))
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('services.adsense.client') }}"
            crossorigin="anonymous"></script>
    <meta name="google-adsense-account" content="{{ config('services.adsense.client') }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:           #080d1a;
            --surface:      #0e1525;
            --card:         #131d30;
            --card-hover:   #192338;
            --border:       rgba(255,255,255,0.07);
            --border-bright:rgba(99,179,237,0.28);

            --text:         #e2e8f0;
            --text-dim:     #64748b;
            --text-muted:   #3d4a5c;

            --green:        #10b981;
            --green-dim:    rgba(16,185,129,0.12);
            --green-border: rgba(16,185,129,0.28);
            --blue:         #3b82f6;
            --blue-dim:     rgba(59,130,246,0.12);
            --red:          #ef4444;
            --red-dim:      rgba(239,68,68,0.12);
            --yellow:       #f59e0b;
            --yellow-dim:   rgba(245,158,11,0.12);
        }

        body {
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Topnav ── */
        .topnav {
            position: sticky;
            top: 0;
            z-index: 200;
            height: 56px;
            background: rgba(8,13,26,0.94);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--border);
        }

        .topnav-inner {
            max-width: 1020px;
            margin: 0 auto;
            padding: 0 1rem;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            position: relative;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            text-decoration: none;
            color: #fff;
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: -0.02em;
            flex-shrink: 0;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg,#10b981,#059669);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .brand:hover { color: #fff; }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }

        .nav-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            color: var(--text-dim);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.82rem;
            transition: color 160ms, background 160ms, border-color 160ms;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .nav-pill:hover {
            color: var(--text);
            background: rgba(255,255,255,0.05);
        }

        .nav-pill.active {
            color: #fff;
            background: var(--green-dim);
            border-color: var(--green-border);
        }

        .nav-live-badge {
            display: none;
            align-items: center;
            gap: 3px;
            padding: 1px 6px;
            border-radius: 999px;
            background: var(--red-dim);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
            font-size: 10px;
            font-weight: 700;
        }

        .nav-live-badge.visible { display: inline-flex; }

        .live-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--red);
            flex-shrink: 0;
            animation: pulseDot 1.6s infinite;
        }

        @keyframes pulseDot {
            0%   { box-shadow: 0 0 0 0   rgba(239,68,68,.6); }
            70%  { box-shadow: 0 0 0 5px rgba(239,68,68,0); }
            100% { box-shadow: 0 0 0 0   rgba(239,68,68,0); }
        }

        .nav-toggle { display: none; }

        /* ── Page shell ── */
        .page-shell {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 56px);
        }

        .page-main { flex: 1; }

        .wrap {
            max-width: 1020px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* ── Cards / Surfaces ── */
        .ts-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .ts-surface {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        /* ── Tabs ── */
        .ts-tabs {
            display: flex;
            gap: 3px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 3px;
        }

        .ts-tab {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            padding: 0.45rem 0.7rem;
            border-radius: 7px;
            color: var(--text-dim);
            font-weight: 600;
            font-size: 0.78rem;
            text-decoration: none;
            transition: color 160ms, background 160ms;
            cursor: pointer;
            border: none;
            background: transparent;
            white-space: nowrap;
        }

        .ts-tab:hover { color: var(--text); background: rgba(255,255,255,0.04); }

        .ts-tab.active {
            color: #fff;
            background: var(--card);
            box-shadow: 0 1px 4px rgba(0,0,0,.35);
        }

        /* ── Chips ── */
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .chip-green { background: var(--green-dim); border: 1px solid var(--green-border); color: #6ee7b7; }
        .chip-red   { background: var(--red-dim);   border: 1px solid rgba(239,68,68,.3);  color: #fca5a5; }
        .chip-blue  { background: var(--blue-dim);  border: 1px solid rgba(59,130,246,.3); color: #93c5fd; }
        .chip-gray  { background: rgba(107,114,128,.12); border: 1px solid rgba(107,114,128,.28); color: #9ca3af; }

        /* ── Buttons ── */
        .btn-ts {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .55rem 1.1rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: .82rem;
            cursor: pointer;
            transition: opacity 160ms, transform 160ms;
            text-decoration: none;
            border: none;
        }

        .btn-ts:hover { opacity: .88; transform: translateY(-1px); }
        .btn-ts:active { transform: none; }

        .btn-green {
            background: linear-gradient(135deg,#10b981,#059669);
            color: #fff;
        }

        .btn-green:hover { color: #fff; }

        .btn-outline {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border-bright);
        }

        .btn-outline:hover { color: #fff; background: rgba(99,179,237,.08); }

        /* ── State boxes ── */
        .state-box {
            padding: 3.5rem 1.5rem;
            text-align: center;
            color: var(--text-dim);
            border-radius: 10px;
            border: 1px dashed rgba(255,255,255,.1);
            background: rgba(255,255,255,.01);
        }

        .state-icon { font-size: 2rem; display: block; margin-bottom: .75rem; }

        .state-title { font-size: .95rem; font-weight: 700; color: var(--text); margin-bottom: .35rem; }

        .state-sub { font-size: .78rem; color: var(--text-dim); }

        /* ── Spinner ── */
        .spin {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(16,185,129,.2);
            border-top-color: var(--green);
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Text helpers ── */
        .text-dim   { color: var(--text-dim); }
        .text-muted { color: var(--text-muted); }
        .text-green { color: var(--green); }
        .text-red   { color: var(--red); }
        .text-white { color: #fff; }

        /* ── Fade animation ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .fade-up { animation: fadeUp 280ms ease both; }

        /* ── Footer ── */
        .ts-footer {
            border-top: 1px solid var(--border);
            padding: 1.5rem 0 1.25rem;
            text-align: center;
            font-size: .75rem;
            color: var(--text-muted);
        }

        /* ── Footer newsletter - compact pill style ── */
        .footer-nl {
            max-width: 640px;
            margin: 0 auto 1.75rem;
            padding: 1.1rem 1.25rem;
            background: linear-gradient(135deg, rgba(16,185,129,.06), rgba(16,185,129,.01));
            border: 1px solid rgba(16,185,129,.18);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            text-align: left;
        }
        .footer-nl-text { flex: 1; min-width: 200px; }
        .footer-nl-label {
            font-size: .92rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -.01em;
            line-height: 1.3;
        }
        .footer-nl-sub {
            font-size: .72rem;
            color: var(--text-dim);
            margin-top: .2rem;
            line-height: 1.5;
        }
        .footer-nl-form {
            display: inline-flex;
            background: rgba(8,13,26,.6);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 4px;
            transition: border-color 160ms, box-shadow 160ms;
        }
        .footer-nl-form:focus-within {
            border-color: rgba(16,185,129,.4);
            box-shadow: 0 0 0 3px rgba(16,185,129,.10);
        }
        .footer-nl-input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--text);
            font-size: .82rem;
            font-family: inherit;
            padding: .5rem .75rem;
            min-width: 220px;
            width: 240px;
        }
        .footer-nl-input::placeholder { color: var(--text-dim); }
        .footer-nl-btn {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            border: none;
            cursor: pointer;
            padding: .5rem 1rem;
            border-radius: 7px;
            font-weight: 800;
            font-size: .8rem;
            font-family: inherit;
            transition: transform 140ms, box-shadow 140ms;
        }
        .footer-nl-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16,185,129,.25);
        }
        .footer-nl-btn-arrow { transition: transform 140ms; font-size: .9rem; }
        .footer-nl-btn:hover .footer-nl-btn-arrow { transform: translateX(2px); }
        .footer-nl-status {
            display: inline-block;
            padding: .55rem .9rem;
            background: rgba(16,185,129,.10);
            border: 1px solid rgba(16,185,129,.25);
            border-radius: 8px;
            font-size: .78rem;
            font-weight: 600;
            color: #6ee7b7;
        }

        @media (max-width: 600px) {
            .footer-nl { flex-direction: column; align-items: stretch; padding: 1rem; }
            .footer-nl-text { text-align: center; }
            .footer-nl-form { width: 100%; }
            .footer-nl-input { flex: 1; min-width: 0; width: auto; }
            .footer-nl-status { text-align: center; }
        }

        /* ── Mobile nav — handled by drawer in navbar.blade.php ── */
        @media (max-width: 860px) {
            .nav-toggle { display: flex; }
            .nav-links  { display: none !important; }
        }
    </style>

    @stack('styles')
</head>
<body>
<div class="page-shell">
    @include('layouts.navbar')

    {{-- Disclaimer banner --}}
    <div id="disclaimer-bar" style="background:linear-gradient(90deg,rgba(245,158,11,.13),rgba(245,158,11,.07));border-bottom:1px solid rgba(245,158,11,.25);padding:.55rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;">
        <p style="margin:0;font-size:.72rem;color:#fbbf24;line-height:1.6;flex:1;min-width:200px;">
            ⚠️ <strong>No prediction is guaranteed.</strong>
            If AI tips <strong>Over 2.5 Goals</strong>, consider playing the safer <strong>Over 1.5 Goals</strong> instead.
            Always gamble responsibly - only stake what you can afford to lose.
        </p>
        <button onclick="dismissDisclaimer()" aria-label="Dismiss"
            style="background:none;border:1px solid rgba(245,158,11,.35);color:#fbbf24;border-radius:6px;padding:3px 10px;font-size:.7rem;cursor:pointer;flex-shrink:0;white-space:nowrap;">
            Got it ✕
        </button>
    </div>
    <script>
        (function(){
            if(localStorage.getItem('ts_disclaimer_dismissed')==='1'){
                document.getElementById('disclaimer-bar').style.display='none';
            }
        })();
        function dismissDisclaimer(){
            localStorage.setItem('ts_disclaimer_dismissed','1');
            document.getElementById('disclaimer-bar').style.display='none';
        }
    </script>

    <main class="page-main">
        @yield('content')
    </main>
    <footer class="ts-footer">
        <div class="wrap">
            {{-- Footer newsletter - compact, single row, distinct design from /picks --}}
            <div class="footer-nl">
                <div class="footer-nl-text">
                    <div class="footer-nl-label">📬 Get tomorrow's 3 picks free</div>
                    <div class="footer-nl-sub">One email a day at 09:00 Lagos · unsubscribe anytime</div>
                </div>
                @if(session('newsletter_status'))
                <div class="footer-nl-status">{{ session('newsletter_status') }}</div>
                @else
                <form method="POST" action="{{ route('newsletter.subscribe') }}" class="footer-nl-form">
                    @csrf
                    <input type="hidden" name="source" value="footer">
                    {{-- Honeypot --}}
                    <div aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;">
                        <label for="nl-website-footer">Website</label>
                        <input type="text" id="nl-website-footer" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <input type="email" name="email" class="footer-nl-input"
                           placeholder="you@example.com"
                           required maxlength="200"
                           value="{{ old('email') }}"
                           aria-label="Email address">
                    <button type="submit" class="footer-nl-btn">
                        <span class="footer-nl-btn-text">Subscribe</span>
                        <span class="footer-nl-btn-arrow">→</span>
                    </button>
                </form>
                @endif
            </div>
            {{-- Social links --}}
            @if($telegramUrl || $twitterUrl)
            <div style="display:flex; justify-content:center; gap:1rem; margin-bottom:.85rem;">
                @if($telegramUrl)
                <a href="{{ $telegramUrl }}" target="_blank" rel="noopener noreferrer"
                   title="Join us on Telegram"
                   style="display:inline-flex; align-items:center; gap:.4rem; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12); color:var(--text-muted); text-decoration:none; font-size:.75rem; font-weight:600; padding:.4rem .85rem; border-radius:20px; transition:background 140ms;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    Telegram
                </a>
                @endif
                @if($twitterUrl)
                <a href="{{ $twitterUrl }}" target="_blank" rel="noopener noreferrer"
                   title="Follow us on X (Twitter)"
                   style="display:inline-flex; align-items:center; gap:.4rem; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12); color:var(--text-muted); text-decoration:none; font-size:.75rem; font-weight:600; padding:.4rem .85rem; border-radius:20px; transition:background 140ms;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    Follow on X
                </a>
                @endif
            </div>
            @endif

            <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:.5rem 1.25rem; margin-bottom:.6rem;">
                <a href="{{ route('about') }}"   style="color:var(--text-muted); text-decoration:none; font-size:.72rem;">About</a>
                <a href="{{ route('blog.index') }}" style="color:var(--text-muted); text-decoration:none; font-size:.72rem;">Blog</a>
                <a href="{{ route('privacy') }}" style="color:var(--text-muted); text-decoration:none; font-size:.72rem;">Privacy Policy</a>
                <a href="{{ route('terms') }}"   style="color:var(--text-muted); text-decoration:none; font-size:.72rem;">Terms of Service</a>
                <a href="{{ route('contact') }}" style="color:var(--text-muted); text-decoration:none; font-size:.72rem;">Contact</a>
            </div>
            <div>⚽ &copy; {{ date('Y') }} TavsScore &mdash; Real-Time Football Scores &amp; AI Predictions</div>
            <div style="font-size:.67rem; color:var(--text-muted); margin-top:.3rem;">Scores &amp; data powered by API-Football. For entertainment purposes only - not for betting.</div>
        </div>
    </footer>
</div>
@stack('scripts')

@include('layouts.telegram-popup')

{{-- Cookie consent banner (required for Google AdSense) --}}
<div id="cookie-banner" style="display:none; position:fixed; bottom:0; left:0; right:0; z-index:9999;
     background:rgba(8,13,26,.97); border-top:1px solid rgba(255,255,255,.1);
     padding:.875rem 1rem; backdrop-filter:blur(12px);">
    <div style="max-width:1020px; margin:0 auto; display:flex; align-items:center; flex-wrap:wrap; gap:.75rem; justify-content:space-between;">
        <p style="font-size:.78rem; color:var(--text-dim); margin:0; flex:1; min-width:200px; line-height:1.6;">
            We use cookies to show relevant advertising and understand how people use TavsScore.
            <a href="{{ route('privacy') }}" style="color:var(--green);">Learn more</a>
        </p>
        <div style="display:flex; gap:.5rem; flex-shrink:0;">
            <button onclick="acceptCookies()" style="background:linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; padding:.42rem .95rem; border-radius:7px; font-size:.78rem; font-weight:700; cursor:pointer;">Accept</button>
            <button onclick="declineCookies()" style="background:transparent; color:var(--text-dim); border:1px solid rgba(255,255,255,.15); padding:.42rem .95rem; border-radius:7px; font-size:.78rem; font-weight:600; cursor:pointer;">Decline</button>
        </div>
    </div>
</div>
<script>
(function() {
    var consent = localStorage.getItem('ts_cookie_consent');
    if (!consent) { document.getElementById('cookie-banner').style.display = 'block'; }
})();
function acceptCookies() {
    localStorage.setItem('ts_cookie_consent', 'accepted');
    document.getElementById('cookie-banner').style.display = 'none';
    if (typeof gtag === 'function') {
        gtag('consent', 'update', {
            'ad_storage':         'granted',
            'analytics_storage':  'granted',
            'ad_user_data':       'granted',
            'ad_personalization': 'granted',
        });
    }
}
function declineCookies() {
    localStorage.setItem('ts_cookie_consent', 'declined');
    document.getElementById('cookie-banner').style.display = 'none';
}
</script>
    {{-- OneSignal Push Notifications + PWA Service Worker --}}
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
    <script>
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        OneSignalDeferred.push(async function(OneSignal) {
            await OneSignal.init({
                appId: "c53acaeb-7e53-4028-9ccc-18d26d70652a",
                notifyButton: { enable: true },
                promptOptions: {
                    slidedown: {
                        prompts: [{
                            type: 'push',
                            autoPrompt: true,
                            text: {
                                actionMessage: 'Get daily picks and live score alerts!',
                                acceptButton: 'Allow',
                                cancelButton: 'Not now',
                            },
                            delay: { pageViews: 1, timeDelay: 3 },
                        }]
                    }
                }
            });
        });

        // OneSignal handles its own service worker — do not register a competing sw.js
    </script>
</body>
</html>
