<nav class="topnav">
    <div class="topnav-inner">
        <a href="{{ route('home.index') }}" class="brand">
            <span class="brand-icon">⚽</span>
            <span>TavsScore</span>
        </a>

        <button id="nav-toggle" class="nav-toggle" aria-label="Menu" aria-expanded="false">
            <svg width="16" height="14" viewBox="0 0 16 14" fill="currentColor">
                <rect y="0"  width="16" height="2" rx="1"/>
                <rect y="6"  width="16" height="2" rx="1"/>
                <rect y="12" width="16" height="2" rx="1"/>
            </svg>
        </button>

        <div class="nav-links" id="nav-links">
            <a href="{{ route('home.index') }}"
               class="nav-pill {{ request()->routeIs('home.index') ? 'active' : '' }}">
                Home
            </a>
            <a href="{{ route('live.index') }}"
               class="nav-pill {{ request()->routeIs('live.index') ? 'active' : '' }}">
                Live
                <span class="nav-live-badge" id="nav-live-badge">
                    <span class="live-dot"></span>
                    <span id="nav-live-num">0</span>
                </span>
            </a>
            <a href="{{ route('predictions.index') }}"
               class="nav-pill {{ request()->routeIs('predictions.index') ? 'active' : '' }}">
                Predictions
            </a>
            <a href="{{ route('picks.index') }}"
               class="nav-pill {{ request()->routeIs('picks.index') ? 'active' : '' }}"
               style="{{ request()->routeIs('picks.index') ? '' : 'background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.2);color:#fcd34d;' }}">
                ⭐ Daily Picks
            </a>
            <a href="{{ route('stats.index') }}"
               class="nav-pill {{ request()->routeIs('stats.index') ? 'active' : '' }}">
                📊 Stats
            </a>
            <a href="{{ route('results.index') }}"
               class="nav-pill {{ request()->routeIs('results.index') ? 'active' : '' }}">
                📜 Results
            </a>
            <a href="{{ route('africa.index') }}"
               class="nav-pill {{ request()->routeIs('africa.index') ? 'active' : '' }}"
               style="{{ request()->routeIs('africa.index') ? '' : 'background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.18);color:#6ee7b7;' }}">
                🌍 Africa
            </a>
            <a href="{{ route('blog.index') }}"
               class="nav-pill {{ request()->routeIs('blog.*') ? 'active' : '' }}">
                Blog
            </a>
            <a href="{{ route('about') }}"
               class="nav-pill {{ request()->routeIs('about') ? 'active' : '' }}">
                About
            </a>
        </div>
    </div>
</nav>

<script>
(function () {
    var toggle = document.getElementById('nav-toggle');
    var links  = document.getElementById('nav-links');

    toggle.addEventListener('click', function () {
        var open = links.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open);
    });

    // Show live count badge
    fetch('/api/matches/live', { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var n = Array.isArray(d.data) ? d.data.length : 0;
            if (n > 0) {
                var badge = document.getElementById('nav-live-badge');
                var num   = document.getElementById('nav-live-num');
                num.textContent = n;
                badge.classList.add('visible');
            }
        })
        .catch(function () {});
}());
</script>
