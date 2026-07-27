<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            --sidebar: 220px;
        }

        body { font-family:'Inter',system-ui,sans-serif; font-size:14px; background:var(--bg); color:var(--text); min-height:100vh; -webkit-font-smoothing:antialiased; }

        /* Layout */
        .admin-shell { display:flex; min-height:100vh; }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar);
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            position: fixed; top:0; left:0; bottom:0;
            z-index: 100; overflow-y: auto;
        }

        .sb-brand {
            display: flex; align-items: center; gap: .55rem;
            padding: 1rem 1rem .875rem;
            border-bottom: 1px solid var(--border);
            text-decoration: none; color: #fff;
            font-weight: 800; font-size: .95rem;
        }

        .sb-brand-icon {
            width: 30px; height: 30px; border-radius: 7px;
            background: linear-gradient(135deg,#10b981,#059669);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }

        .sb-nav { padding: .75rem .5rem; flex: 1; }

        .sb-section-label {
            font-size: .63rem; font-weight: 700; color: var(--dim);
            text-transform: uppercase; letter-spacing: .07em;
            padding: .75rem .5rem .3rem;
        }

        .sb-link {
            display: flex; align-items: center; gap: .55rem;
            padding: .5rem .65rem; border-radius: 7px;
            color: var(--dim); text-decoration: none;
            font-size: .8rem; font-weight: 600;
            transition: color 150ms, background 150ms;
            margin-bottom: 2px;
        }

        .sb-link:hover { color: var(--text); background: rgba(255,255,255,.04); }
        .sb-link.active { color: #fff; background: var(--green-d); border-left: 2px solid var(--green); padding-left: calc(.65rem - 2px); }

        .sb-icon { font-size: .95rem; flex-shrink: 0; width: 18px; text-align: center; }

        /* Collapsible groups */
        .sb-group { margin-bottom: .1rem; }
        .sb-group > summary {
            list-style: none; cursor: pointer;
            display: flex; align-items: center; justify-content: space-between;
            padding: .55rem .5rem; border-radius: 7px;
            font-size: .63rem; font-weight: 700; color: var(--dim);
            text-transform: uppercase; letter-spacing: .07em; user-select: none;
        }
        .sb-group > summary::-webkit-details-marker { display: none; }
        .sb-group > summary:hover { color: var(--text); background: rgba(255,255,255,.03); }
        .sb-caret { font-size: .55rem; transition: transform .15s ease; opacity: .7; }
        .sb-group[open] > summary .sb-caret { transform: rotate(90deg); }
        .sb-group-body { padding: 0 0 .35rem; }

        .sb-footer {
            padding: .75rem 1rem;
            border-top: 1px solid var(--border);
            font-size: .72rem; color: var(--dim);
        }

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
            .sidebar { transform:translateX(-100%); transition:transform 220ms ease; }
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
            <span class="sb-brand-icon">⚽</span> TavsScore
        </a>

        @php
            $isPicks = request()->routeIs('admin.picks','admin.draw-picks.*','admin.gg-picks.*','admin.over15.*','admin.over25.*','admin.team3plus.*','admin.double-chance.*','admin.correct-score.*','admin.lineup-picks.*','admin.booking-code.*','admin.rollover.*','admin.goalscorer-picks.*');
            $isData  = request()->routeIs('admin.matches','admin.predictions','admin.api-stats.*','admin.fantasy.*');
            $isModel = request()->routeIs('admin.stats.*','admin.ai-learning.*','admin.pi-ratings.*','admin.model-metrics.*','admin.team-aliases.*');
            $isContent = request()->routeIs('admin.blog.*');
            $isEngage  = request()->routeIs('admin.newsletter.*','admin.broadcast.*','admin.winners.*');
            $isRevenue = request()->routeIs('admin.affiliate-links.*','admin.settings.*');
        @endphp
        <nav class="sb-nav">
            <div class="sb-section-label">Main</div>
            <a href="{{ route('admin.dashboard') }}" class="sb-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <span class="sb-icon">📊</span> Dashboard
            </a>
            <a href="{{ route('admin.analytics') }}" class="sb-link {{ request()->routeIs('admin.analytics') ? 'active' : '' }}">
                <span class="sb-icon">📈</span> Analytics
            </a>
            @if(config('services.ga.id'))
            <a href="https://analytics.google.com/analytics/web/" target="_blank" rel="noopener" class="sb-link">
                <span class="sb-icon">🔍</span> Google Analytics ↗
            </a>
            @endif

            {{-- Picks & Rollover --}}
            <details class="sb-group" {{ $isPicks ? 'open' : '' }}>
                <summary><span>⭐ Picks &amp; Rollover</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.picks') }}" class="sb-link {{ request()->routeIs('admin.picks') ? 'active' : '' }}"><span class="sb-icon">⭐</span> Daily Picks</a>
                    <a href="{{ route('admin.rollover.index') }}" class="sb-link {{ request()->routeIs('admin.rollover.*') ? 'active' : '' }}"><span class="sb-icon">🔄</span> Rollover</a>
                    <a href="{{ route('admin.draw-picks.index') }}" class="sb-link {{ request()->routeIs('admin.draw-picks.*') ? 'active' : '' }}"><span class="sb-icon">🤝</span> Draw Picks</a>
                    <a href="{{ route('admin.gg-picks.index') }}" class="sb-link {{ request()->routeIs('admin.gg-picks.*') ? 'active' : '' }}"><span class="sb-icon">⚽</span> GG Picks</a>
                    <a href="{{ route('admin.over15.index') }}" class="sb-link {{ request()->routeIs('admin.over15.*') ? 'active' : '' }}"><span class="sb-icon">⚽</span> Over 1.5 Picks</a>
                    <a href="{{ route('admin.over25.index') }}" class="sb-link {{ request()->routeIs('admin.over25.*') ? 'active' : '' }}"><span class="sb-icon">🔥</span> Over 2.5 Picks</a>
                    <a href="{{ route('admin.team3plus.index') }}" class="sb-link {{ request()->routeIs('admin.team3plus.*') ? 'active' : '' }}"><span class="sb-icon">🚫</span> Team 3+ NO</a>
                    <a href="{{ route('admin.double-chance.index') }}" class="sb-link {{ request()->routeIs('admin.double-chance.*') ? 'active' : '' }}"><span class="sb-icon">🎯</span> Double Chance</a>
                    <a href="{{ route('admin.goalscorer-picks.index') }}" class="sb-link {{ request()->routeIs('admin.goalscorer-picks.*') ? 'active' : '' }}"><span class="sb-icon">⚽</span> Goalscorer Picks</a>
                    <a href="{{ route('admin.correct-score.index') }}" class="sb-link {{ request()->routeIs('admin.correct-score.*') ? 'active' : '' }}"><span class="sb-icon">🎯</span> Correct Score</a>
                    <a href="{{ route('admin.lineup-picks.index') }}" class="sb-link {{ request()->routeIs('admin.lineup-picks.*') ? 'active' : '' }}"><span class="sb-icon">⚡</span> Lineup Picks</a>
                    <a href="{{ route('admin.booking-code.index') }}" class="sb-link {{ request()->routeIs('admin.booking-code.*') ? 'active' : '' }}"><span class="sb-icon">🎟️</span> Booking Code</a>
                </div>
            </details>

            {{-- Football Data --}}
            <details class="sb-group" {{ $isData ? 'open' : '' }}>
                <summary><span>⚽ Football Data</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.matches') }}" class="sb-link {{ request()->routeIs('admin.matches') ? 'active' : '' }}"><span class="sb-icon">⚽</span> Matches</a>
                    <a href="{{ route('admin.predictions') }}" class="sb-link {{ request()->routeIs('admin.predictions') ? 'active' : '' }}"><span class="sb-icon">📈</span> Predictions</a>
                    <a href="{{ route('admin.api-stats.index') }}" class="sb-link {{ request()->routeIs('admin.api-stats.*') ? 'active' : '' }}"><span class="sb-icon">📊</span> API Stats</a>
                    <a href="{{ route('admin.fantasy.index') }}" class="sb-link {{ request()->routeIs('admin.fantasy.*') ? 'active' : '' }}"><span class="sb-icon">🏆</span> Fantasy XI</a>
                </div>
            </details>

            {{-- Model & Accuracy --}}
            <details class="sb-group" {{ $isModel ? 'open' : '' }}>
                <summary><span>🧠 Model &amp; Accuracy</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.stats.index') }}" class="sb-link {{ request()->routeIs('admin.stats.*') ? 'active' : '' }}"><span class="sb-icon">📊</span> Stats</a>
                    <a href="{{ route('admin.model-metrics.index') }}" class="sb-link {{ request()->routeIs('admin.model-metrics.*') ? 'active' : '' }}"><span class="sb-icon">📊</span> Model Metrics</a>
                    <a href="{{ route('admin.ai-learning.index') }}" class="sb-link {{ request()->routeIs('admin.ai-learning.*') ? 'active' : '' }}"><span class="sb-icon">🧠</span> AI Learning</a>
                    <a href="{{ route('admin.pi-ratings.index') }}" class="sb-link {{ request()->routeIs('admin.pi-ratings.*') ? 'active' : '' }}"><span class="sb-icon">⚡</span> Pi-Ratings</a>
                    <a href="{{ route('admin.team-aliases.index') }}" class="sb-link {{ request()->routeIs('admin.team-aliases.*') ? 'active' : '' }}"><span class="sb-icon">🏷️</span> Team Aliases</a>
                </div>
            </details>

            {{-- Content --}}
            <details class="sb-group" {{ $isContent ? 'open' : '' }}>
                <summary><span>📝 Content</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.blog.index') }}" class="sb-link {{ request()->routeIs('admin.blog.index','admin.blog.edit') ? 'active' : '' }}"><span class="sb-icon">📝</span> Blog Posts</a>
                    <a href="{{ route('admin.blog.create') }}" class="sb-link {{ request()->routeIs('admin.blog.create') ? 'active' : '' }}"><span class="sb-icon">✏️</span> New Post</a>
                </div>
            </details>

            {{-- Engagement --}}
            <details class="sb-group" {{ $isEngage ? 'open' : '' }}>
                <summary><span>📢 Engagement</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.newsletter.index') }}" class="sb-link {{ request()->routeIs('admin.newsletter.*') ? 'active' : '' }}"><span class="sb-icon">📬</span> Newsletter</a>
                    <a href="{{ route('admin.broadcast.index') }}" class="sb-link {{ request()->routeIs('admin.broadcast.*') ? 'active' : '' }}"><span class="sb-icon">📢</span> Broadcast</a>
                    <a href="{{ route('admin.winners.index') }}" class="sb-link {{ request()->routeIs('admin.winners.*') ? 'active' : '' }}"><span class="sb-icon">🏆</span> Winners Wall</a>
                </div>
            </details>

            {{-- Revenue & Settings --}}
            <details class="sb-group" {{ $isRevenue ? 'open' : '' }}>
                <summary><span>💰 Revenue &amp; Settings</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('admin.affiliate-links.index') }}" class="sb-link {{ request()->routeIs('admin.affiliate-links.*') ? 'active' : '' }}"><span class="sb-icon">💰</span> Affiliate Links</a>
                    <a href="{{ route('admin.settings.index') }}" class="sb-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"><span class="sb-icon">⚙️</span> Settings</a>
                </div>
            </details>

            {{-- Public Site (collapsed) --}}
            <details class="sb-group">
                <summary><span>🌐 View Public Site</span><span class="sb-caret">▶</span></summary>
                <div class="sb-group-body">
                    <a href="{{ route('home.index') }}" target="_blank" class="sb-link"><span class="sb-icon">🌐</span> Home ↗</a>
                    <a href="{{ route('picks.index') }}" target="_blank" class="sb-link"><span class="sb-icon">↗</span> Picks</a>
                    <a href="{{ route('standings.index') }}" target="_blank" class="sb-link"><span class="sb-icon">↗</span> Standings</a>
                    <a href="{{ route('top-scorers.index') }}" target="_blank" class="sb-link"><span class="sb-icon">↗</span> Top Scorers</a>
                    <a href="{{ route('gg-picks.index') }}" target="_blank" class="sb-link"><span class="sb-icon">↗</span> GG Picks</a>
                    <a href="{{ route('rollover.index') }}" target="_blank" class="sb-link"><span class="sb-icon">↗</span> Rollover</a>
                    <a href="{{ route('blog.index') }}" target="_blank" class="sb-link"><span class="sb-icon">📰</span> Blog</a>
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
if (toggle) {
    toggle.style.display = 'block';
    toggle.addEventListener('click', function() { sidebar.classList.toggle('open'); });
}
</script>
@stack('scripts')
</body>
</html>
