<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>@yield('title', 'Admin') | TavsScore Admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #080d1a;
            --surface: #0e1525;
            --card:    #131d30;
            --border:  rgba(255,255,255,0.07);
            --text:    #e2e8f0;
            --dim:     #64748b;
            --green:   #10b981;
            --green-d: rgba(16,185,129,0.12);
            --green-b: rgba(16,185,129,0.28);
            --red:     #ef4444;
            --red-d:   rgba(239,68,68,0.12);
            --yellow:  #f59e0b;
            --blue:    #3b82f6;
            --blue-d:  rgba(59,130,246,0.12);
            --sidebar: 268px;
        }

        body { font-family:'Inter',system-ui,sans-serif; font-size:14px; background:var(--bg); color:var(--text); min-height:100vh; -webkit-font-smoothing:antialiased; }
        a, a:visited, a:hover { text-decoration:none; }
        a, a:visited { text-decoration:none !important; }

        /* Layout */
        .admin-shell { display:flex; min-height:100vh; }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar);
            background:
                radial-gradient(circle at 14% -6%, rgba(16,185,129,.15), transparent 30%),
                linear-gradient(180deg, #111b2d 0%, #0b1220 48%, #080d1a 100%);
            border-right: 1px solid rgba(148,163,184,.13);
            display: flex; flex-direction: column;
            position: fixed; top:0; left:0; bottom:0;
            z-index: 100; overflow-y: auto;
            box-shadow: 18px 0 48px rgba(0,0,0,.16);
            scrollbar-width: thin;
            scrollbar-color: rgba(148,163,184,.25) transparent;
        }
        .sidebar::-webkit-scrollbar { width:5px; }
        .sidebar::-webkit-scrollbar-thumb { background:rgba(148,163,184,.24); border-radius:99px; }

        .sb-brand {
            display: flex; align-items: center; gap: .7rem;
            padding: 1.15rem 1rem 1rem;
            border-bottom: 1px solid rgba(148,163,184,.12);
            text-decoration: none; color: #fff;
            transition:background 180ms ease;
        }
        .sb-brand:hover { background:rgba(255,255,255,.025); }

        .sb-brand-icon {
            width: 36px; height: 36px; border-radius: 11px;
            background: linear-gradient(135deg,#22c55e,#059669 58%,#047857);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
            box-shadow:0 8px 20px rgba(16,185,129,.24);
        }
        .sb-brand-copy { display:grid; gap:2px; min-width:0; }
        .sb-brand-copy strong { font-size:.91rem; letter-spacing:-.02em; }
        .sb-brand-copy small { color:#94a3b8; font-size:.6rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase; }
        .sb-brand-arrow { margin-left:auto; color:#64748b; font-size:.8rem; }

        .sb-nav { padding: .8rem .7rem 1rem; flex: 1; }

        .sb-section-label {
            font-size: .6rem; font-weight: 800; color: #64748b;
            text-transform: uppercase; letter-spacing: .11em;
            padding: .55rem .5rem .38rem;
        }

        .sb-link {
            display: flex; align-items: center; gap: .62rem;
            padding: .57rem .65rem; border-radius: 9px;
            color: #9aa9bd; text-decoration: none;
            font-size: .76rem; font-weight: 650;
            transition: color 160ms, background 160ms, transform 160ms, box-shadow 160ms;
            margin-bottom: 3px;
        }

        .sb-link:hover { color:#fff; background:rgba(255,255,255,.055); transform:translateX(2px); }
        .sb-link.active { color:#ecfdf5; background:linear-gradient(90deg,rgba(16,185,129,.21),rgba(16,185,129,.07)); box-shadow:inset 2px 0 0 #34d399, 0 5px 16px rgba(0,0,0,.1); }

        .sb-icon { display:grid;place-items:center;font-size:.84rem;flex-shrink:0;width:25px;height:25px;text-align:center;border-radius:7px;background:rgba(148,163,184,.09); }
        .sb-link:hover .sb-icon, .sb-link.active .sb-icon { background:rgba(255,255,255,.11); }
        .sb-spotlight { display:block; margin:.15rem 0 .85rem; padding:.78rem; border:1px solid rgba(16,185,129,.28); border-radius:12px; text-decoration:none; background:linear-gradient(135deg,rgba(16,185,129,.16),rgba(14,116,144,.1)); box-shadow:inset 0 1px 0 rgba(255,255,255,.04); transition:transform 160ms, border-color 160ms; }
        .sb-spotlight:hover { transform:translateY(-1px); border-color:rgba(52,211,153,.55); }
        .sb-spotlight.active { border-color:rgba(52,211,153,.65); box-shadow:0 8px 24px rgba(16,185,129,.13), inset 0 1px 0 rgba(255,255,255,.06); }
        .sb-spotlight-kicker { color:#86efac; font-size:.58rem; font-weight:900; text-transform:uppercase; letter-spacing:.1em; }
        .sb-spotlight-title { color:#fff; font-size:.78rem; font-weight:800; margin-top:.22rem; display:flex; align-items:center; justify-content:space-between; }
        .sb-spotlight-sub { color:#a7f3d0; font-size:.63rem; line-height:1.35; margin-top:.25rem; opacity:.78; }

        /* Collapsible groups */
        .sb-group { margin:.28rem 0; border:1px solid transparent; border-radius:10px; }
        .sb-group[open] { background:rgba(255,255,255,.018); border-color:rgba(148,163,184,.08); }
        .sb-group > summary {
            list-style: none; cursor: pointer;
            display: flex; align-items: center; justify-content: space-between;
            padding:.65rem .65rem; border-radius:9px;
            font-size:.68rem; font-weight:800; color:#8fa0b6;
            letter-spacing:.015em; user-select:none;
        }
        .sb-group > summary::-webkit-details-marker { display: none; }
        .sb-group > summary:hover { color:#fff; background:rgba(255,255,255,.035); }
        .sb-caret { font-size:.58rem; transition:transform .15s ease; opacity:.75; }
        .sb-group[open] > summary .sb-caret { transform: rotate(90deg); }
        .sb-group-body { padding:0 .32rem .38rem; }
        .sb-group-body .sb-link { font-size:.735rem; }

        .sb-footer {
            padding:.8rem .7rem;
            border-top:1px solid rgba(148,163,184,.12);
            font-size:.72rem; color:var(--dim);
        }
        .sb-footer form { border:1px solid rgba(148,163,184,.12); border-radius:9px; background:rgba(255,255,255,.025); padding:.1rem; }
        .sb-footer button { border-radius:7px; padding:.52rem .55rem !important; transition:background 160ms,color 160ms; }
        .sb-footer button:hover { background:rgba(239,68,68,.1) !important; color:#fca5a5 !important; }

        /* Main */
        .admin-main {
            margin-left: var(--sidebar);
            flex: 1;
            display: flex; flex-direction: column;
        }

        .admin-topbar {
            height: 52px;
            background: rgba(8,13,26,.94);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky; top: 0; z-index: 50;
        }

        .topbar-title { font-weight: 700; font-size: .88rem; color: #fff; }

        .topbar-user { display:flex; align-items:center; gap:.65rem; }

        .user-avatar {
            width: 30px; height: 30px; border-radius: 50%;
            background: var(--green-d); border: 1px solid var(--green-b);
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700; color: #6ee7b7;
        }

        .user-name { font-size: .78rem; font-weight: 600; color: var(--text); }

        .admin-content { padding: 1.5rem; flex: 1; }

        /* Cards */
        .a-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
        }

        /* Stat tiles */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .65rem; margin-bottom: 1.5rem; }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem;
        }

        .stat-val { font-size: 1.75rem; font-weight: 800; color: #fff; line-height: 1; display:block; }
        .stat-lbl { font-size: .68rem; color: var(--dim); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-top: 4px; display:block; }

        /* Tables */
        .a-table { width:100%; border-collapse:collapse; }
        .a-table th { font-size:.7rem; font-weight:700; color:var(--dim); text-transform:uppercase; letter-spacing:.04em; padding:.55rem .75rem; text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
        .a-table td { padding:.6rem .75rem; font-size:.8rem; border-bottom:1px solid rgba(255,255,255,.04); vertical-align:middle; }
        .a-table tr:last-child td { border-bottom:none; }
        .a-table tr:hover td { background:rgba(255,255,255,.02); }

        /* Badges */
        .badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; font-size:.68rem; font-weight:700; }
        .badge-green { background:var(--green-d); border:1px solid var(--green-b); color:#6ee7b7; }
        .badge-gray  { background:rgba(107,114,128,.12); border:1px solid rgba(107,114,128,.25); color:#9ca3af; }
        .badge-red   { background:var(--red-d); border:1px solid rgba(239,68,68,.3); color:#fca5a5; }
        .badge-blue  { background:var(--blue-d); border:1px solid rgba(59,130,246,.3); color:#93c5fd; }

        /* Buttons */
        .btn-a {
            display:inline-flex; align-items:center; gap:.4rem;
            padding:.42rem .9rem; border-radius:7px;
            font-size:.78rem; font-weight:700; cursor:pointer;
            text-decoration:none; font-family:inherit; transition:opacity 160ms, transform 160ms;
            border:none;
        }
        .btn-a:hover { opacity:.88; transform:translateY(-1px); }
        .btn-green { background:linear-gradient(135deg,#10b981,#059669); color:#fff; }
        .btn-green:hover { color:#fff; }
        .btn-blue  { background:var(--blue-d); border:1px solid rgba(59,130,246,.3); color:#93c5fd; }
        .btn-red   { background:var(--red-d); border:1px solid rgba(239,68,68,.3); color:#fca5a5; }
        .btn-gray  { background:rgba(255,255,255,.06); border:1px solid var(--border); color:var(--text); }

        /* Forms */
        .form-group { margin-bottom:1.1rem; }
        .form-label { display:block; font-size:.78rem; font-weight:600; color:var(--text); margin-bottom:.4rem; }
        .form-hint  { font-size:.68rem; color:var(--dim); margin-top:.25rem; }

        .form-input, .form-select, .form-textarea {
            width:100%; background:var(--surface); border:1px solid var(--border);
            border-radius:7px; color:var(--text); padding:.5rem .75rem;
            font-size:.82rem; font-family:inherit; outline:none;
            transition:border-color 160ms;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color:rgba(16,185,129,.4);
            box-shadow:0 0 0 3px rgba(16,185,129,.08);
        }
        .form-textarea { resize:vertical; min-height:200px; line-height:1.6; }

        /* Alert */
        .alert { padding:.75rem 1rem; border-radius:8px; font-size:.8rem; font-weight:600; margin-bottom:1rem; }
        .alert-green { background:var(--green-d); border:1px solid var(--green-b); color:#6ee7b7; }
        .alert-red   { background:var(--red-d); border:1px solid rgba(239,68,68,.3); color:#fca5a5; }

        /* Page header */
        .page-hd { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; margin-bottom:1.25rem; }
        .page-hd-title { font-size:1.15rem; font-weight:800; color:#fff; letter-spacing:-.02em; }

        /* Pagination */
        .pagination { display:flex; gap:.35rem; flex-wrap:wrap; margin-top:1rem; }
        .pagination .page-item .page-link {
            display:inline-flex; align-items:center; justify-content:center;
            width:32px; height:32px; border-radius:6px; font-size:.75rem; font-weight:600;
            background:var(--card); border:1px solid var(--border); color:var(--dim);
            text-decoration:none; transition:all 150ms;
        }
        .pagination .page-item.active .page-link { background:var(--green-d); border-color:var(--green-b); color:#6ee7b7; }
        .pagination .page-item .page-link:hover { color:var(--text); border-color:rgba(99,179,237,.25); }

        /* Mobile */
        @media (max-width:768px) {
            .sidebar { transform:translateX(-100%); transition:transform 220ms ease; box-shadow:18px 0 48px rgba(0,0,0,.38); }
            .sidebar.open { transform:translateX(0); }
            .admin-main { margin-left:0; }
            .admin-topbar { padding:0 1rem; }
            .admin-content { padding:1rem; }
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="admin-shell">
    {{-- Sidebar --}}
    <aside class="sidebar" id="admin-sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sb-brand">
            <span class="sb-brand-icon">⚽</span>
            <span class="sb-brand-copy"><strong>TavsScore</strong><small>Admin Command Centre</small></span>
            <span class="sb-brand-arrow">↗</span>
        </a>

        @php
            $isMainPicks = request()->routeIs('admin.picks','admin.draw-picks.*','admin.gg-picks.*','admin.double-chance.*');
            $isGoalMarkets = request()->routeIs('admin.over15.*','admin.over25.*','admin.under35.*','admin.under45.*','admin.team3plus.*');
            $isHandicapMarkets = request()->routeIs('admin.handicap.*','admin.european-handicap.*');
            $isSpecialistPicks = request()->routeIs('admin.correct-score.*','admin.lineup-picks.*','admin.goalscorer-picks.*','admin.corners.*');
            $isRollover = request()->routeIs('admin.booking-code.*','admin.rollover.*');
            $isData  = request()->routeIs('admin.matches','admin.predictions','admin.daily-football-predictions.*','admin.api-stats.*','admin.fantasy.*');
            $isModel = request()->routeIs('admin.stats.*','admin.ai-learning.*','admin.shalom-ai.*','admin.pi-ratings.*','admin.model-metrics.*','admin.team-aliases.*');
            $isContent = request()->routeIs('admin.blog.*');
            $isEngage  = request()->routeIs('admin.newsletter.*','admin.broadcast.*','admin.winners.*');
            $isRevenue = request()->routeIs('admin.affiliate-links.*','admin.settings.*');
            $isHomepageMedia = request()->routeIs('admin.homepage-media.*');
        @endphp
        <nav class="sb-nav">
            <div class="sb-section-label">Main</div>
            <a href="{{ route('admin.dashboard') }}" class="sb-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <span class="sb-icon">📊</span> Dashboard
            </a>
            <a href="{{ route('admin.analytics') }}" class="sb-link {{ request()->routeIs('admin.analytics') ? 'active' : '' }}">
                <span class="sb-icon">📈</span> Analytics
            </a>
            <a href="{{ route('admin.homepage-media.index') }}" class="sb-spotlight {{ $isHomepageMedia ? 'active' : '' }}">
                <div class="sb-spotlight-kicker">Content studio</div>
                <div class="sb-spotlight-title"><span>🖼️ Homepage Images</span><span>→</span></div>
                <div class="sb-spotlight-sub">Upload and preview your homepage visuals.</div>
            </a>
            @if(config('services.ga.id'))
            <a href="https://analytics.google.com/analytics/web/" target="_blank" rel="noopener" class="sb-link">
                <span class="sb-icon">🔍</span> Google Analytics ↗
            </a>
            @endif

            {{-- Picks --}}
            <div class="sb-section-label">Pick Centre</div>
            <details class="sb-group" data-admin-group="main-picks" {{ $isMainPicks ? 'open' : '' }}>
                <summary><span>⭐ Main Picks</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.picks') }}" class="sb-link {{ request()->routeIs('admin.picks') ? 'active' : '' }}"><span class="sb-icon">⭐</span> Daily Picks</a>
                    <a href="{{ route('admin.draw-picks.index') }}" class="sb-link {{ request()->routeIs('admin.draw-picks.*') ? 'active' : '' }}"><span class="sb-icon">🤝</span> Draw Picks</a>
                    <a href="{{ route('admin.gg-picks.index') }}" class="sb-link {{ request()->routeIs('admin.gg-picks.*') ? 'active' : '' }}"><span class="sb-icon">⚽</span> GG Picks</a>
                    <a href="{{ route('admin.double-chance.index') }}" class="sb-link {{ request()->routeIs('admin.double-chance.*') ? 'active' : '' }}"><span class="sb-icon">🎯</span> Double Chance</a>
                </div>
            </details>
            <details class="sb-group" data-admin-group="goal-markets" {{ $isGoalMarkets ? 'open' : '' }}>
                <summary><span>📈 Goal Markets</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.over15.index') }}" class="sb-link {{ request()->routeIs('admin.over15.*') ? 'active' : '' }}"><span class="sb-icon">⚽</span> Over 1.5 Picks</a>
                    <a href="{{ route('admin.over25.index') }}" class="sb-link {{ request()->routeIs('admin.over25.*') ? 'active' : '' }}"><span class="sb-icon">🔥</span> Over 2.5 Picks</a>
                    <a href="{{ route('admin.under35.index') }}" class="sb-link {{ request()->routeIs('admin.under35.*') ? 'active' : '' }}"><span class="sb-icon">🧊</span> Under 3.5 Picks</a>
                    <a href="{{ route('admin.under45.index') }}" class="sb-link {{ request()->routeIs('admin.under45.*') ? 'active' : '' }}"><span class="sb-icon">🛟</span> Under 4.5 Picks</a>
                    <a href="{{ route('admin.team3plus.index') }}" class="sb-link {{ request()->routeIs('admin.team3plus.*') ? 'active' : '' }}"><span class="sb-icon">🚫</span> Team 3+ NO</a>
                </div>
            </details>
            <details class="sb-group" data-admin-group="handicap-markets" {{ $isHandicapMarkets ? 'open' : '' }}>
                <summary><span>🛡️ Handicap Markets</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.handicap.index') }}" class="sb-link {{ request()->routeIs('admin.handicap.*') ? 'active' : '' }}"><span class="sb-icon">🛡️</span> Handicap Picks</a>
                    <a href="{{ route('admin.european-handicap.index') }}" class="sb-link {{ request()->routeIs('admin.european-handicap.*') ? 'active' : '' }}"><span class="sb-icon">🏁</span> European Handicap</a>
                </div>
            </details>
            <details class="sb-group" data-admin-group="specialist-picks" {{ $isSpecialistPicks ? 'open' : '' }}>
                <summary><span>🎯 Specialist Picks</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.goalscorer-picks.index') }}" class="sb-link {{ request()->routeIs('admin.goalscorer-picks.*') ? 'active' : '' }}"><span class="sb-icon">⚽</span> Goalscorer Picks</a>
                    <a href="{{ route('admin.correct-score.index') }}" class="sb-link {{ request()->routeIs('admin.correct-score.*') ? 'active' : '' }}"><span class="sb-icon">🎯</span> Correct Score</a>
                    <a href="{{ route('admin.lineup-picks.index') }}" class="sb-link {{ request()->routeIs('admin.lineup-picks.*') ? 'active' : '' }}"><span class="sb-icon">⚡</span> Lineup Picks</a>
                    <a href="{{ route('admin.corners.index') }}" class="sb-link {{ request()->routeIs('admin.corners.*') ? 'active' : '' }}"><span class="sb-icon">🚩</span> Corner Picks</a>
                </div>
            </details>
            <details class="sb-group" data-admin-group="rollover" {{ $isRollover ? 'open' : '' }}>
                <summary><span>🔄 Rollover &amp; Booking</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.rollover.index') }}" class="sb-link {{ request()->routeIs('admin.rollover.*') ? 'active' : '' }}"><span class="sb-icon">🔄</span> Rollover Challenge</a>
                    <a href="{{ route('admin.booking-code.index') }}" class="sb-link {{ request()->routeIs('admin.booking-code.*') ? 'active' : '' }}"><span class="sb-icon">🎟️</span> Booking Code</a>
                    <a href="{{ route('admin.high-risk.index') }}" class="sb-link {{ request()->routeIs('admin.high-risk.*') ? 'active' : '' }}"><span class="sb-icon">🎲</span> High Risk</a>
                </div>
            </details>

            {{-- Football Data --}}
            <details class="sb-group" data-admin-group="football-data" {{ $isData ? 'open' : '' }}>
                <summary><span>⚽ Football Data</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.matches') }}" class="sb-link {{ request()->routeIs('admin.matches') ? 'active' : '' }}"><span class="sb-icon">⚽</span> Matches</a>
                    <a href="{{ route('admin.predictions') }}" class="sb-link {{ request()->routeIs('admin.predictions') ? 'active' : '' }}"><span class="sb-icon">📈</span> Predictions</a>
                    <a href="{{ route('admin.daily-football-predictions.index') }}" class="sb-link {{ request()->routeIs('admin.daily-football-predictions.*') ? 'active' : '' }}"><span class="sb-icon">📅</span> Daily Results</a>
                    <a href="{{ route('admin.api-stats.index') }}" class="sb-link {{ request()->routeIs('admin.api-stats.*') ? 'active' : '' }}"><span class="sb-icon">📊</span> API Stats</a>
                    <a href="{{ route('admin.fantasy.index') }}" class="sb-link {{ request()->routeIs('admin.fantasy.*') ? 'active' : '' }}"><span class="sb-icon">🏆</span> Fantasy XI</a>
                </div>
            </details>

            <details class="sb-group" data-admin-group="tennis-data" {{ request()->routeIs('admin.tennis.*') ? 'open' : '' }}>
                <summary><span>🎾 Tennis Data</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.tennis.index') }}" class="sb-link {{ request()->routeIs('admin.tennis.*') ? 'active' : '' }}"><span class="sb-icon">🎾</span> Tennis Predictions</a>
                    <a href="{{ route('admin.tennis.media') }}" class="sb-link {{ request()->routeIs('admin.tennis.media') ? 'active' : '' }}"><span class="sb-icon">🖼️</span> Tennis Page Image</a>
                </div>
            </details>

            {{-- Model & Accuracy --}}
            <details class="sb-group" data-admin-group="model-accuracy" {{ $isModel ? 'open' : '' }}>
                <summary><span>🧠 Model &amp; Accuracy</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.stats.index') }}" class="sb-link {{ request()->routeIs('admin.stats.*') ? 'active' : '' }}"><span class="sb-icon">📊</span> Stats</a>
                    <a href="{{ route('admin.model-metrics.index') }}" class="sb-link {{ request()->routeIs('admin.model-metrics.*') ? 'active' : '' }}"><span class="sb-icon">📊</span> Model Metrics</a>
                    <a href="{{ route('admin.ai-learning.index') }}" class="sb-link {{ request()->routeIs('admin.ai-learning.*') ? 'active' : '' }}"><span class="sb-icon">🧠</span> AI Learning</a>
                    <a href="{{ route('admin.shalom-ai.index') }}" class="sb-link {{ request()->routeIs('admin.shalom-ai.*') ? 'active' : '' }}"><span class="sb-icon">✦</span> Shalom AI Lab</a>
                    <a href="{{ route('admin.pi-ratings.index') }}" class="sb-link {{ request()->routeIs('admin.pi-ratings.*') ? 'active' : '' }}"><span class="sb-icon">⚡</span> Pi-Ratings</a>
                    <a href="{{ route('admin.team-aliases.index') }}" class="sb-link {{ request()->routeIs('admin.team-aliases.*') ? 'active' : '' }}"><span class="sb-icon">🏷️</span> Team Aliases</a>
                </div>
            </details>

            {{-- Content --}}
            <details class="sb-group" data-admin-group="content" {{ $isContent ? 'open' : '' }}>
                <summary><span>📝 Content</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.blog.index') }}" class="sb-link {{ request()->routeIs('admin.blog.index','admin.blog.edit') ? 'active' : '' }}"><span class="sb-icon">📝</span> Blog Posts</a>
                    <a href="{{ route('admin.blog.create') }}" class="sb-link {{ request()->routeIs('admin.blog.create') ? 'active' : '' }}"><span class="sb-icon">✏️</span> New Post</a>
                </div>
            </details>

            {{-- Engagement --}}
            <details class="sb-group" data-admin-group="engagement" {{ $isEngage ? 'open' : '' }}>
                <summary><span>📢 Engagement</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.newsletter.index') }}" class="sb-link {{ request()->routeIs('admin.newsletter.*') ? 'active' : '' }}"><span class="sb-icon">📬</span> Newsletter</a>
                    <a href="{{ route('admin.broadcast.index') }}" class="sb-link {{ request()->routeIs('admin.broadcast.*') ? 'active' : '' }}"><span class="sb-icon">📢</span> Broadcast</a>
                    <a href="{{ route('admin.winners.index') }}" class="sb-link {{ request()->routeIs('admin.winners.*') ? 'active' : '' }}"><span class="sb-icon">🏆</span> Winners Wall</a>
                </div>
            </details>

            {{-- Revenue & Settings --}}
            <details class="sb-group" data-admin-group="revenue-settings" {{ $isRevenue ? 'open' : '' }}>
                <summary><span>💰 Revenue &amp; Settings</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.affiliate-links.index') }}" class="sb-link {{ request()->routeIs('admin.affiliate-links.*') ? 'active' : '' }}"><span class="sb-icon">💰</span> Affiliate Links</a>
                    <a href="{{ route('admin.settings.index') }}" class="sb-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"><span class="sb-icon">⚙️</span> Settings</a>
                </div>
            </details>

            {{-- Public Site (collapsed) --}}
            <details class="sb-group" data-admin-group="public-site">
                <summary><span>🌐 View Public Site</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('home.index') }}" target="_blank" class="sb-link"><span class="sb-icon">🌐</span> Home ↗</a>
                    <a href="{{ route('picks.index') }}" target="_blank" class="sb-link"><span class="sb-icon">↗</span> Picks</a>
                    <a href="{{ route('standings.index') }}" target="_blank" class="sb-link"><span class="sb-icon">↗</span> Standings</a>
                    <a href="{{ route('top-scorers.index') }}" target="_blank" class="sb-link"><span class="sb-icon">↗</span> Top Scorers</a>
                    <a href="{{ route('gg-picks.index') }}" target="_blank" class="sb-link"><span class="sb-icon">↗</span> GG Picks</a>
                    <a href="{{ route('rollover.index') }}" target="_blank" class="sb-link"><span class="sb-icon">↗</span> Rollover</a>
                    <a href="{{ route('blog.index') }}" target="_blank" class="sb-link"><span class="sb-icon">📰</span> Football News</a>
                </div>
            </details>
        </nav>

        <div class="sb-footer">
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" style="background:none;border:none;color:var(--dim);cursor:pointer;font-size:.72rem;font-weight:600;padding:0;width:100%;text-align:left;">
                    🚪 Sign out
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <div class="admin-main">
        <header class="admin-topbar">
            <div style="display:flex;align-items:center;gap:.75rem">
                <button id="sb-toggle" style="display:none;background:none;border:none;color:var(--text);cursor:pointer;font-size:1.1rem;" aria-label="Menu">☰</button>
                <span class="topbar-title">@yield('page-title', 'Dashboard')</span>
            </div>
            <div class="topbar-user">
                <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
                <span class="user-name">{{ auth()->user()->name }}</span>
            </div>
        </header>

        <main class="admin-content">
            @if(session('success'))
                <div class="alert alert-green">✓ {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-red">✗ {{ session('error') }}</div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
var toggle  = document.getElementById('sb-toggle');
var sidebar = document.getElementById('admin-sidebar');
var compactAdmin = window.matchMedia('(max-width: 768px)');

function syncAdminMenu() {
    if (!toggle || !sidebar) return;
    toggle.style.display = compactAdmin.matches ? 'block' : 'none';
    if (!compactAdmin.matches) sidebar.classList.remove('open');
}

if (toggle && sidebar) {
    toggle.addEventListener('click', function() { sidebar.classList.toggle('open'); });
    compactAdmin.addEventListener('change', syncAdminMenu);
    syncAdminMenu();
}

// Keep the admin navigation exactly where the admin left it when a new page
// loads. Server-side route checks still force the active section open.
if (sidebar) {
    (function () {
        var storageKey = 'tavsscore.admin.sidebar-state.v1';
        var state = { groups: {}, scrollTop: 0 };

        try {
            var saved = window.localStorage.getItem(storageKey);
            if (saved) state = Object.assign(state, JSON.parse(saved));
        } catch (error) {}

        var groups = sidebar.querySelectorAll('details[data-admin-group]');
        groups.forEach(function (group) {
            var name = group.getAttribute('data-admin-group');
            if (Object.prototype.hasOwnProperty.call(state.groups, name)) {
                group.open = Boolean(state.groups[name]);
            }
            if (group.querySelector('.sb-link.active')) group.open = true;

            group.addEventListener('toggle', function () {
                state.groups[name] = group.open;
                saveState();
            });
        });

        function saveState() {
            state.scrollTop = sidebar.scrollTop;
            try { window.localStorage.setItem(storageKey, JSON.stringify(state)); } catch (error) {}
        }

        sidebar.addEventListener('scroll', saveState, { passive: true });
        sidebar.querySelectorAll('.sb-link').forEach(function (link) {
            link.addEventListener('click', saveState);
        });
        window.addEventListener('pagehide', saveState);
        window.requestAnimationFrame(function () { sidebar.scrollTop = Number(state.scrollTop) || 0; });
    }());
}
</script>
@stack('scripts')
</body>
</html>
