<nav class="topnav">
    <div class="topnav-inner">
        <a href="{{ route('home.index') }}" class="brand">
            <span class="brand-icon">⚽</span>
            <span>TavsScore</span>
        </a>

        {{-- Desktop nav --}}
        <div class="nav-links" id="nav-links">
            <a href="{{ route('home.index') }}"
               class="nav-pill {{ request()->routeIs('home.index') ? 'active' : '' }}">Home</a>

            <a href="{{ route('live.index') }}"
               class="nav-pill {{ request()->routeIs('live.index') ? 'active' : '' }}">
                Live
                <span class="nav-live-badge" id="nav-live-badge">
                    <span class="live-dot"></span>
                    <span id="nav-live-num">0</span>
                </span>
            </a>

            <a href="{{ route('predictions.index') }}"
               class="nav-pill {{ request()->routeIs('predictions.*') ? 'active' : '' }}">Predictions</a>

            <a href="{{ route('tennis.index') }}"
               class="nav-pill {{ request()->routeIs('tennis.*') ? 'active' : '' }}">🎾 Tennis</a>

            <a href="{{ route('fantasy.index') }}"
               class="nav-pill {{ request()->routeIs('fantasy.index') ? 'active' : '' }}">🏆 Fantasy</a>

            {{-- Picks dropdown --}}
            <div class="nav-drop {{ request()->routeIs('picks.index','draw-picks.index','gg-picks.index','over15-picks.index','over25-picks.index','team3plus-picks.index','double-chance.index','lineup-picks.index','correct-score.index','rollover.*') ? 'nav-drop-active' : '' }}" id="drop-picks">
                <button class="nav-pill nav-drop-btn" aria-expanded="false" aria-haspopup="true">
                    ⭐ Picks
                    <svg class="nav-caret" width="8" height="5" viewBox="0 0 8 5" fill="currentColor"><path d="M0 0l4 5 4-5z"/></svg>
                </button>
                <div class="nav-drop-menu" role="menu">
                    <a href="{{ route('picks.index') }}" class="nav-drop-item {{ request()->routeIs('picks.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">⭐</span>
                        <span><span class="ndi-label">Daily Picks</span><span class="ndi-sub">Top 3 picks today</span></span>
                    </a>
                    <a href="{{ route('draw-picks.index') }}" class="nav-drop-item {{ request()->routeIs('draw-picks.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">🤝</span>
                        <span><span class="ndi-label">Draw Picks</span><span class="ndi-sub">Triple AI draw predictions</span></span>
                    </a>
                    <a href="{{ route('gg-picks.index') }}" class="nav-drop-item {{ request()->routeIs('gg-picks.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">⚽</span>
                        <span><span class="ndi-label">GG Picks</span><span class="ndi-sub">Both teams to score</span></span>
                    </a>
                    <a href="{{ route('rollover.index') }}" class="nav-drop-item {{ request()->routeIs('rollover.*') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">🔄</span>
                        <span><span class="ndi-label">Rollover</span><span class="ndi-sub">10-day compound challenge</span></span>
                    </a>
                    <a href="{{ route('lineup-picks.index') }}" class="nav-drop-item {{ request()->routeIs('lineup-picks.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">⚡</span>
                        <span><span class="ndi-label">Lineup Picks</span><span class="ndi-sub">After starting XI confirmed</span></span>
                    </a>
                    <a href="{{ route('correct-score.index') }}" class="nav-drop-item {{ request()->routeIs('correct-score.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">🎲</span>
                        <span><span class="ndi-label">Correct Score <span style="font-size:.6rem;color:#fca5a5;font-weight:800;">HIGH RISK</span></span><span class="ndi-sub">Hardest market · big odds · for fun</span></span>
                    </a>
                    <div class="nav-drop-divider"></div>
                    <a href="{{ route('over15-picks.index') }}" class="nav-drop-item {{ request()->routeIs('over15-picks.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">⚽</span>
                        <span><span class="ndi-label">Over 1.5 Goals</span><span class="ndi-sub">5 daily picks</span></span>
                    </a>
                    <a href="{{ route('over25-picks.index') }}" class="nav-drop-item {{ request()->routeIs('over25-picks.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">🔥</span>
                        <span><span class="ndi-label">Over 2.5 Goals</span><span class="ndi-sub">5 daily picks</span></span>
                    </a>
                    <a href="{{ route('team3plus-picks.index') }}" class="nav-drop-item {{ request()->routeIs('team3plus-picks.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">🚫</span>
                        <span><span class="ndi-label">Team Goals NO</span><span class="ndi-sub">2+ & 3+ NO picks</span></span>
                    </a>
                    <a href="{{ route('double-chance.index') }}" class="nav-drop-item {{ request()->routeIs('double-chance.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">🎯</span>
                        <span><span class="ndi-label">Double Chance</span><span class="ndi-sub">1X & 2X daily picks</span></span>
                    </a>
                    <a href="{{ route('goalscorer-picks.index') }}" class="nav-drop-item {{ request()->routeIs('goalscorer-picks.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">⚽</span>
                        <span><span class="ndi-label">Goalscorer Picks</span><span class="ndi-sub">Anytime scorer tips</span></span>
                    </a>
                    <a href="{{ route('corners-picks.index') }}" class="nav-drop-item {{ request()->routeIs('corners-picks.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">🚩</span>
                        <span><span class="ndi-label">Corner Picks</span><span class="ndi-sub">Safest total-corners line</span></span>
                    </a>
                </div>
            </div>

            <a href="{{ route('africa.index') }}"
               class="nav-pill {{ request()->routeIs('africa.index') ? 'active' : '' }}"
               style="{{ request()->routeIs('africa.index') ? '' : 'background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.18);color:#6ee7b7;' }}">
                🌍 Africa
            </a>

            <a href="{{ route('blog.index') }}"
               class="nav-pill {{ request()->routeIs('blog.*') ? 'active' : '' }}">
                📰 Blog
            </a>

            {{-- More dropdown --}}
            <div class="nav-drop {{ request()->routeIs('stats.index','track-record.index','results.index','daily-football-predictions.*','winners.*','hall-of-fame.*','about') ? 'nav-drop-active' : '' }}" id="drop-more">
                <button class="nav-pill nav-drop-btn" aria-expanded="false" aria-haspopup="true">
                    More
                    <svg class="nav-caret" width="8" height="5" viewBox="0 0 8 5" fill="currentColor"><path d="M0 0l4 5 4-5z"/></svg>
                </button>
                <div class="nav-drop-menu" role="menu">
                    <a href="{{ route('stats.index') }}" class="nav-drop-item {{ request()->routeIs('stats.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">📊</span>
                        <span><span class="ndi-label">Stats</span><span class="ndi-sub">AI accuracy & records</span></span>
                    </a>
                    <a href="{{ route('standings.index') }}" class="nav-drop-item {{ request()->routeIs('standings.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">🏆</span>
                        <span><span class="ndi-label">Standings</span><span class="ndi-sub">League tables & form</span></span>
                    </a>
                    <a href="{{ route('top-scorers.index') }}" class="nav-drop-item {{ request()->routeIs('top-scorers.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">⚽</span>
                        <span><span class="ndi-label">Top Scorers</span><span class="ndi-sub">Goals & assist leaders</span></span>
                    </a>
                    <a href="{{ route('track-record.index') }}" class="nav-drop-item {{ request()->routeIs('track-record.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">📈</span>
                        <span><span class="ndi-label">Track Record</span><span class="ndi-sub">Verified results over time</span></span>
                    </a>
                    <a href="{{ route('results.index') }}" class="nav-drop-item {{ request()->routeIs('results.index') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">📜</span>
                        <span><span class="ndi-label">Results</span><span class="ndi-sub">Past match outcomes</span></span>
                    </a>
                    <a href="{{ route('daily-football-predictions.index') }}" class="nav-drop-item {{ request()->routeIs('daily-football-predictions.*') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">📅</span>
                        <span><span class="ndi-label">Daily Results</span><span class="ndi-sub">Today's and yesterday's picks</span></span>
                    </a>
                    <div class="nav-drop-divider"></div>
                    <a href="{{ route('winners.index') }}" class="nav-drop-item {{ request()->routeIs('winners.*') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">🏆</span>
                        <span><span class="ndi-label">Winners</span><span class="ndi-sub">Submit your win</span></span>
                    </a>
                    <a href="{{ route('hall-of-fame.index') }}" class="nav-drop-item {{ request()->routeIs('hall-of-fame.*') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">🥇</span>
                        <span><span class="ndi-label">Hall of Fame</span><span class="ndi-sub">Top earners leaderboard</span></span>
                    </a>
                    <div class="nav-drop-divider"></div>
                    <a href="{{ route('about') }}" class="nav-drop-item {{ request()->routeIs('about') ? 'active' : '' }}" role="menuitem">
                        <span class="ndi-icon">ℹ️</span>
                        <span><span class="ndi-label">About</span><span class="ndi-sub">About TavsScore</span></span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Mobile hamburger --}}
        <button id="nav-toggle" class="nav-toggle" aria-label="Open menu" aria-expanded="false">
            <span class="ham-bar"></span>
            <span class="ham-bar"></span>
            <span class="ham-bar"></span>
        </button>
    </div>
</nav>

{{-- Mobile drawer overlay --}}
<div id="drawer-overlay" class="drawer-overlay" aria-hidden="true"></div>

{{-- Mobile drawer --}}
<aside id="mobile-drawer" class="mobile-drawer" aria-label="Navigation menu" role="dialog" aria-modal="true">
    <div class="drawer-header">
        <a href="{{ route('home.index') }}" class="brand" onclick="closeDrawer()">
            <span class="brand-icon">⚽</span>
            <span>TavsScore</span>
        </a>
        <button id="drawer-close" class="drawer-close-btn" aria-label="Close menu">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="currentColor">
                <path d="M1 1l16 16M17 1L1 17" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" fill="none"/>
            </svg>
        </button>
    </div>

    <nav class="drawer-nav">
        {{-- Main --}}
        <div class="drawer-section-label">Main</div>

        <a href="{{ route('home.index') }}" class="drawer-item {{ request()->routeIs('home.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🏠</span>
            <span class="di-text">Home</span>
        </a>
        <a href="{{ route('live.index') }}" class="drawer-item {{ request()->routeIs('live.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🔴</span>
            <span class="di-text">Live Scores</span>
            <span class="drawer-live-badge" id="drawer-live-badge" style="display:none;">
                <span class="live-dot"></span>
                <span id="drawer-live-num">0</span>
            </span>
        </a>
        <a href="{{ route('predictions.index') }}" class="drawer-item {{ request()->routeIs('predictions.*') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🤖</span>
            <span class="di-text">Predictions</span>
        </a>
        <a href="{{ route('tennis.index') }}" class="drawer-item {{ request()->routeIs('tennis.*') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🎾</span>
            <span class="di-text">Tennis Predictions</span>
        </a>
        <a href="{{ route('fantasy.index') }}" class="drawer-item {{ request()->routeIs('fantasy.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🏆</span>
            <span class="di-text">Fantasy Best XI</span>
        </a>
        <a href="{{ route('africa.index') }}" class="drawer-item {{ request()->routeIs('africa.index') ? 'active' : '' }}" onclick="closeDrawer()" style="{{ request()->routeIs('africa.index') ? '' : 'color:#6ee7b7;' }}">
            <span class="di-icon">🌍</span>
            <span class="di-text">Africa</span>
        </a>

        {{-- Picks --}}
        <div class="drawer-section-label" style="margin-top:.75rem;">Picks</div>

        <a href="{{ route('picks.index') }}" class="drawer-item {{ request()->routeIs('picks.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">⭐</span>
            <span class="di-text">
                Daily Picks
                <small>Top 3 picks today</small>
            </span>
        </a>
        <a href="{{ route('draw-picks.index') }}" class="drawer-item {{ request()->routeIs('draw-picks.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🤝</span>
            <span class="di-text">
                Draw Picks
                <small>Triple AI draw predictions</small>
            </span>
        </a>
        <a href="{{ route('gg-picks.index') }}" class="drawer-item {{ request()->routeIs('gg-picks.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">⚽</span>
            <span class="di-text">
                GG Picks
                <small>Both teams to score</small>
            </span>
        </a>
        <a href="{{ route('rollover.index') }}" class="drawer-item {{ request()->routeIs('rollover.*') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🔄</span>
            <span class="di-text">
                Rollover
                <small>10-day challenge</small>
            </span>
        </a>
        <a href="{{ route('lineup-picks.index') }}" class="drawer-item {{ request()->routeIs('lineup-picks.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">⚡</span>
            <span class="di-text">
                Lineup Picks
                <small>After team sheets drop</small>
            </span>
        </a>
        <a href="{{ route('correct-score.index') }}" class="drawer-item {{ request()->routeIs('correct-score.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🎲</span>
            <span class="di-text">
                Correct Score <small style="color:#fca5a5;font-weight:800;">HIGH RISK</small>
                <small>Hardest market · big odds · for fun</small>
            </span>
        </a>
        <a href="{{ route('over15-picks.index') }}" class="drawer-item {{ request()->routeIs('over15-picks.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">⚽</span>
            <span class="di-text">
                Over 1.5 Goals
                <small>5 daily picks</small>
            </span>
        </a>
        <a href="{{ route('over25-picks.index') }}" class="drawer-item {{ request()->routeIs('over25-picks.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🔥</span>
            <span class="di-text">
                Over 2.5 Goals
                <small>5 daily picks</small>
            </span>
        </a>
        <a href="{{ route('team3plus-picks.index') }}" class="drawer-item {{ request()->routeIs('team3plus-picks.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🚫</span>
            <span class="di-text">
                Team Goals NO
                <small>2+ & 3+ NO picks</small>
            </span>
        </a>
        <a href="{{ route('double-chance.index') }}" class="drawer-item {{ request()->routeIs('double-chance.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🎯</span>
            <span class="di-text">
                Double Chance
                <small>1X & 2X daily picks</small>
            </span>
        </a>
        <a href="{{ route('goalscorer-picks.index') }}" class="drawer-item {{ request()->routeIs('goalscorer-picks.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">⚽</span>
            <span class="di-text">
                Goalscorer Picks
                <small>Anytime scorer tips</small>
            </span>
        </a>
        <a href="{{ route('corners-picks.index') }}" class="drawer-item {{ request()->routeIs('corners-picks.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🚩</span>
            <span class="di-text">
                Corner Picks
                <small>Safest total-corners line</small>
            </span>
        </a>

        {{-- Stats --}}
        <div class="drawer-section-label" style="margin-top:.75rem;">Track Record</div>

        <a href="{{ route('stats.index') }}" class="drawer-item {{ request()->routeIs('stats.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">📊</span>
            <span class="di-text">Stats</span>
        </a>
        <a href="{{ route('standings.index') }}" class="drawer-item {{ request()->routeIs('standings.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🏆</span>
            <span class="di-text">Standings</span>
        </a>
        <a href="{{ route('top-scorers.index') }}" class="drawer-item {{ request()->routeIs('top-scorers.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">⚽</span>
            <span class="di-text">Top Scorers</span>
        </a>
        <a href="{{ route('track-record.index') }}" class="drawer-item {{ request()->routeIs('track-record.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">📈</span>
            <span class="di-text">Track Record</span>
        </a>
        <a href="{{ route('results.index') }}" class="drawer-item {{ request()->routeIs('results.index') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">📜</span>
            <span class="di-text">Results</span>
        </a>
        <a href="{{ route('daily-football-predictions.index') }}" class="drawer-item {{ request()->routeIs('daily-football-predictions.*') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">📅</span>
            <span class="di-text">Daily Results</span>
        </a>
        <a href="{{ route('winners.index') }}" class="drawer-item {{ request()->routeIs('winners.*') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🏆</span>
            <span class="di-text">Winners</span>
        </a>
        <a href="{{ route('hall-of-fame.index') }}" class="drawer-item {{ request()->routeIs('hall-of-fame.*') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">🥇</span>
            <span class="di-text">Hall of Fame</span>
        </a>

        {{-- More --}}
        <div class="drawer-section-label" style="margin-top:.75rem;">More</div>

        <a href="{{ route('blog.index') }}" class="drawer-item {{ request()->routeIs('blog.*') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">📝</span>
            <span class="di-text">Blog</span>
        </a>
        <a href="{{ route('about') }}" class="drawer-item {{ request()->routeIs('about') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">ℹ️</span>
            <span class="di-text">About</span>
        </a>
        <a href="{{ route('contact') }}" class="drawer-item {{ request()->routeIs('contact') ? 'active' : '' }}" onclick="closeDrawer()">
            <span class="di-icon">✉️</span>
            <span class="di-text">Contact</span>
        </a>

        @if($telegramUrl)
        <div class="drawer-section-label" style="margin-top:.75rem;">Community</div>
        <a href="{{ $telegramUrl }}" target="_blank" rel="noopener noreferrer" class="drawer-item drawer-telegram">
            <span class="di-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#2aabee"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
            </span>
            <span class="di-text">Join Telegram Channel</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left:auto; opacity:.4;"><path d="M7 17L17 7M17 7H7M17 7v10"/></svg>
        </a>
        @endif
    </nav>
</aside>

<style>
/* ── Desktop dropdown styles ─────────────────────────────── */
.nav-drop { position: relative; }

.nav-drop-btn {
    background: none;
    border-color: transparent;
    cursor: pointer;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
}
.nav-drop-btn:hover { background: rgba(255,255,255,.05); color: var(--text); }

.nav-drop-active .nav-drop-btn {
    color: #fff;
    background: var(--green-dim);
    border-color: var(--green-border);
}

.nav-caret { opacity: .5; transition: transform 160ms; flex-shrink: 0; }
.nav-drop.open .nav-caret { transform: rotate(180deg); }

.nav-drop-menu {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    left: 50%;
    transform: translateX(-50%);
    min-width: 220px;
    max-height: calc(100vh - 90px);
    overflow-y: auto;
    background: #0e1525;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 12px;
    padding: .4rem;
    box-shadow: 0 16px 40px rgba(0,0,0,.5);
    z-index: 300;
}
.nav-drop.open .nav-drop-menu { display: block; }

.nav-drop-item {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: .55rem .7rem;
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-dim);
    transition: background 130ms, color 130ms;
    white-space: nowrap;
}
.nav-drop-item:hover, .nav-drop-item.active {
    background: rgba(255,255,255,.06);
    color: #fff;
}
.ndi-icon  { font-size: 1rem; width: 22px; text-align: center; flex-shrink: 0; }
.ndi-label { display: block; font-size: .8rem; font-weight: 700; color: inherit; }
.ndi-sub   { display: block; font-size: .66rem; color: var(--text-dim); margin-top: 1px; }
.nav-drop-item:hover .ndi-sub, .nav-drop-item.active .ndi-sub { color: rgba(255,255,255,.5); }

.nav-drop-divider {
    height: 1px;
    background: rgba(255,255,255,.07);
    margin: .3rem .4rem;
}

/* ── Hamburger bars ──────────────────────────────────────── */
.nav-toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    background: none;
    border: none;
    cursor: pointer;
    padding: .4rem;
    border-radius: 6px;
    transition: background 130ms;
}
.nav-toggle:hover { background: rgba(255,255,255,.07); }
.ham-bar {
    display: block;
    width: 22px;
    height: 2px;
    background: var(--text);
    border-radius: 2px;
    transition: transform 200ms, opacity 200ms;
}

/* ── Drawer overlay ──────────────────────────────────────── */
.drawer-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    backdrop-filter: blur(3px);
    z-index: 9000;
}
.drawer-overlay.active { display: block; }

/* ── Mobile drawer ───────────────────────────────────────── */
.mobile-drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: min(320px, 88vw);
    background: #0b1220;
    border-left: 1px solid rgba(255,255,255,.09);
    z-index: 9100;
    transform: translateX(100%);
    transition: transform 280ms cubic-bezier(.4,0,.2,1);
    overflow-y: auto;
    overscroll-behavior: contain;
    padding-bottom: 2rem;
}
.mobile-drawer.open { transform: translateX(0); }

.drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1rem .75rem;
    border-bottom: 1px solid rgba(255,255,255,.08);
    position: sticky;
    top: 0;
    background: #0b1220;
    z-index: 1;
}

.drawer-close-btn {
    background: rgba(255,255,255,.07);
    border: none;
    border-radius: 8px;
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--text-dim);
    transition: background 130ms;
}
.drawer-close-btn:hover { background: rgba(255,255,255,.12); }

.drawer-nav { padding: .75rem .75rem 0; }

.drawer-section-label {
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: rgba(255,255,255,.3);
    padding: 0 .5rem .4rem;
}

.drawer-item {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .7rem .75rem;
    border-radius: 10px;
    text-decoration: none;
    color: rgba(255,255,255,.7);
    font-size: .88rem;
    font-weight: 500;
    margin-bottom: .15rem;
    transition: background 130ms, color 130ms;
}
.drawer-item:hover  { background: rgba(255,255,255,.06); color: #fff; }
.drawer-item.active { background: var(--green-dim); color: #fff; border: 1px solid var(--green-border); }

.di-icon {
    font-size: 1.1rem;
    width: 28px;
    text-align: center;
    flex-shrink: 0;
    line-height: 1;
}
.di-text {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
}
.di-text small {
    font-size: .67rem;
    color: rgba(255,255,255,.38);
    font-weight: 400;
    margin-top: 1px;
}
.drawer-item.active .di-text small { color: rgba(255,255,255,.55); }

.drawer-telegram {
    background: rgba(42,171,238,.08);
    border: 1px solid rgba(42,171,238,.2);
    color: #7dd3fc;
}
.drawer-telegram:hover {
    background: rgba(42,171,238,.14);
    color: #bae6fd;
}

.drawer-live-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: rgba(239,68,68,.15);
    border: 1px solid rgba(239,68,68,.3);
    border-radius: 20px;
    padding: 1px 7px;
    font-size: .65rem;
    font-weight: 700;
    color: #f87171;
    margin-left: auto;
}

/* ── Show on mobile only ─────────────────────────────────── */
@media (max-width: 960px) {
    .nav-toggle { display: flex; }
    .nav-links  { display: none !important; }
}
</style>

<script>
(function () {
    var toggle  = document.getElementById('nav-toggle');
    var drawer  = document.getElementById('mobile-drawer');
    var overlay = document.getElementById('drawer-overlay');
    var closeBtn= document.getElementById('drawer-close');

    function openDrawer() {
        drawer.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        toggle.setAttribute('aria-expanded', 'true');
    }

    window.closeDrawer = function () {
        drawer.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', openDrawer);
    closeBtn.addEventListener('click', closeDrawer);
    overlay.addEventListener('click', closeDrawer);

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDrawer();
    });

    // Desktop dropdowns
    document.querySelectorAll('.nav-drop').forEach(function (drop) {
        var btn = drop.querySelector('.nav-drop-btn');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = drop.classList.toggle('open');
            btn.setAttribute('aria-expanded', isOpen);
            document.querySelectorAll('.nav-drop').forEach(function (other) {
                if (other !== drop) {
                    other.classList.remove('open');
                    var ob = other.querySelector('.nav-drop-btn');
                    if (ob) ob.setAttribute('aria-expanded', 'false');
                }
            });
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.nav-drop.open').forEach(function (drop) {
            drop.classList.remove('open');
            var ob = drop.querySelector('.nav-drop-btn');
            if (ob) ob.setAttribute('aria-expanded', 'false');
        });
    });

    // Live badge (both topnav + drawer)
    fetch('/api/matches/live', { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var n = Array.isArray(d.data) ? d.data.length : 0;
            if (n > 0) {
                var badge = document.getElementById('nav-live-badge');
                var num   = document.getElementById('nav-live-num');
                if (badge) { num.textContent = n; badge.classList.add('visible'); }

                var dbadge = document.getElementById('drawer-live-badge');
                var dnum   = document.getElementById('drawer-live-num');
                if (dbadge) { dnum.textContent = n; dbadge.style.display = 'inline-flex'; }
            }
        })
        .catch(function () {});
}());
</script>
