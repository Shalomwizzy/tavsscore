@extends('layouts.app')

@section('title', 'TavsScore | Live Football Scores & Predictions')

@push('styles')
<style>
    /* ── Hero ── */
    .hero {
        padding: 4.5rem 0 3rem;
        background:
            radial-gradient(ellipse 80% 50% at 50% -5%, rgba(16,185,129,.14), transparent),
            radial-gradient(ellipse 60% 40% at 80% 80%,  rgba(59,130,246,.07), transparent);
    }

    .hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: 3px 12px;
        border-radius: 999px;
        background: var(--green-dim);
        border: 1px solid var(--green-border);
        color: #6ee7b7;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        margin-bottom: 1.25rem;
    }

    .hero-title {
        font-size: clamp(2.1rem, 6vw, 4.2rem);
        font-weight: 900;
        line-height: 1.06;
        letter-spacing: -.03em;
        color: #fff;
        margin-bottom: 1.1rem;
    }

    .hero-title .grad {
        background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .hero-desc {
        font-size: 1rem;
        color: var(--text-dim);
        max-width: 500px;
        line-height: 1.75;
        margin-bottom: 1.75rem;
    }

    .hero-ctas {
        display: flex;
        flex-wrap: wrap;
        gap: .65rem;
        margin-bottom: 2.75rem;
    }

    /* ── Stats bar ── */
    .stats-bar {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: .6rem;
    }

    .stat-tile {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: .75rem;
    }

    .stat-ico {
        width: 38px;
        height: 38px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .ico-red  { background: var(--red-dim); }
    .ico-blue { background: var(--blue-dim); }
    .ico-gray { background: rgba(107,114,128,.12); }
    .ico-green{ background: var(--green-dim); }

    .stat-val {
        font-size: 1.45rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
        display: block;
    }

    .stat-lbl {
        font-size: .68rem;
        color: var(--text-dim);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-top: 3px;
        display: block;
    }

    /* ── Features ── */
    .features {
        padding: 3rem 0 4rem;
    }

    .section-label {
        font-size: .72rem;
        font-weight: 700;
        color: var(--green);
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: .6rem;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #fff;
        letter-spacing: -.02em;
        margin-bottom: .6rem;
    }

    .section-desc {
        font-size: .88rem;
        color: var(--text-dim);
        max-width: 480px;
        line-height: 1.7;
        margin-bottom: 2rem;
    }

    .feature-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .8rem;
    }

    .feature-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.4rem;
        transition: border-color 200ms, transform 200ms;
    }

    .feature-card:hover {
        border-color: rgba(16,185,129,.25);
        transform: translateY(-2px);
    }

    .feat-emoji {
        font-size: 1.7rem;
        display: block;
        margin-bottom: .75rem;
    }

    .feat-title {
        font-size: .9rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: .4rem;
    }

    .feat-desc {
        font-size: .78rem;
        color: var(--text-dim);
        line-height: 1.65;
    }

    /* ── Competitions highlight ── */
    .leagues-strip {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        margin-top: 2rem;
    }

    .league-pill {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: 5px 11px;
        border-radius: 999px;
        background: var(--surface);
        border: 1px solid var(--border);
        font-size: .75rem;
        font-weight: 600;
        color: var(--text-dim);
        transition: color 160ms, border-color 160ms;
    }

    .league-pill:hover {
        color: var(--text);
        border-color: rgba(99,179,237,.25);
    }

    @media (max-width: 768px) {
        .stats-bar { grid-template-columns: repeat(2, 1fr); }
        .feature-grid { grid-template-columns: 1fr 1fr; }
        .hero { padding: 3rem 0 2.5rem; }
    }

    @media (max-width: 500px) {
        .feature-grid { grid-template-columns: 1fr; }
    }

    /* African spotlight */
    .afr-grid {
        display:grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap:.65rem;
    }
    .afr-card {
        display:block; text-decoration:none;
        background:linear-gradient(135deg, rgba(16,185,129,.06), rgba(16,185,129,.02));
        border:1px solid rgba(16,185,129,.18); border-radius:11px;
        padding:.85rem .95rem;
        transition:transform 160ms, border-color 160ms, background 160ms;
    }
    .afr-card:hover { transform:translateY(-2px); border-color:rgba(16,185,129,.4); background:linear-gradient(135deg, rgba(16,185,129,.10), rgba(16,185,129,.04)); }
    .afr-league { display:flex; align-items:center; gap:.45rem; font-size:.7rem; font-weight:700; color:#6ee7b7; margin-bottom:.45rem; }
    .afr-flag   { font-size:1rem; }
    .afr-teams  { display:flex; align-items:center; color:#fff; font-weight:700; font-size:.85rem; line-height:1.3; }
    .afr-teams > div:first-child, .afr-teams > div:last-child { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .afr-teams > div:last-child { text-align:right; }
    .afr-meta   { font-size:.7rem; color:var(--text-dim); margin-top:.55rem; display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
    .afr-tip    { display:inline-block; padding:1px 7px; border-radius:999px; background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.25); color:#fcd34d; font-weight:700; font-size:.65rem; margin-left:auto; }
</style>
@endpush

@section('content')

{{-- ── Hero ── --}}
<section class="hero">
    <div class="wrap">
        <div class="hero-eyebrow">
            <span class="live-dot"></span>
            Live Football Platform
        </div>

        <h1 class="hero-title">
            The Smartest Way<br>
            to Follow <span class="grad">Football</span>
        </h1>

        <p class="hero-desc">
            Live scores from the Premier League, Champions League, La Liga, Serie A,
            Bundesliga, and 20+ competitions updated in real time.
        </p>

        <div class="hero-ctas">
            <a href="{{ route('live.index') }}" class="btn-ts btn-green">
                ⚡ View Live Scores
            </a>
            <a href="{{ route('predictions.index') }}" class="btn-ts btn-outline">
                📊 Match Predictions
            </a>
        </div>

        {{-- Live stats ──────────────────────────────────── --}}
        <div class="stats-bar">
            <div class="stat-tile">
                <div class="stat-ico ico-red">🔴</div>
                <div>
                    <span class="stat-val" id="h-live">–</span>
                    <span class="stat-lbl">Live now</span>
                </div>
            </div>
            <div class="stat-tile">
                <div class="stat-ico ico-blue">📅</div>
                <div>
                    <span class="stat-val" id="h-today">–</span>
                    <span class="stat-lbl">Upcoming today</span>
                </div>
            </div>
            <div class="stat-tile">
                <div class="stat-ico ico-gray">✅</div>
                <div>
                    <span class="stat-val" id="h-finished">–</span>
                    <span class="stat-lbl">Finished today</span>
                </div>
            </div>
            <div class="stat-tile">
                <div class="stat-ico ico-green">📊</div>
                <div>
                    <span class="stat-val" id="h-preds">–</span>
                    <span class="stat-lbl">Predictions</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Features ── --}}
<section class="features">
    <div class="wrap">
        <div class="section-label">What we offer</div>
        <h2 class="section-title">Everything you need</h2>
        <p class="section-desc">
            From live match updates to statistical AI-powered predictions —
            all in one place, always fast.
        </p>

        <div class="feature-grid">
            <div class="feature-card">
                <span class="feat-emoji">📺</span>
                <div class="feat-title">Live Scores</div>
                <p class="feat-desc">
                    Real-time scores with automatic 30-second refresh.
                    Grouped by competition, filtered by league, with live minute indicators.
                </p>
            </div>
            <div class="feature-card">
                <span class="feat-emoji">📊</span>
                <div class="feat-title">Match Predictions</div>
                <p class="feat-desc">
                    Win / Draw / Loss probabilities built from attack strength
                    and defensive weakness across recent form.
                </p>
            </div>
            <div class="feature-card">
                <span class="feat-emoji">🤖</span>
                <div class="feat-title">AI Analysis</div>
                <p class="feat-desc">
                    Every prediction comes with a concise AI-generated
                    explanation of the statistical model output.
                </p>
            </div>
        </div>

        {{-- Daily Picks teaser --}}
        <div style="margin-top:2.5rem; background:linear-gradient(135deg,rgba(245,158,11,.1),rgba(245,158,11,.04)); border:1px solid rgba(245,158,11,.25); border-radius:14px; padding:1.5rem 1.75rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
            <div>
                <div style="font-size:.68rem; font-weight:700; color:rgba(252,211,77,.7); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem;">New — Updated Daily</div>
                <div style="font-size:1.1rem; font-weight:800; color:#fff; margin-bottom:.35rem;">⭐ Today's 3 Best Picks</div>
                <div style="font-size:.82rem; color:var(--text-dim); line-height:1.6; max-width:380px;">
                    Our AI selects the 3 most confident predictions from all of today's matches free, transparent, no sign-up.
                </div>
            </div>
            <a href="{{ route('picks.index') }}" style="display:inline-flex; align-items:center; gap:.5rem; padding:.65rem 1.4rem; background:rgba(245,158,11,.18); border:1px solid rgba(245,158,11,.35); border-radius:9px; font-size:.85rem; font-weight:800; color:#fcd34d; text-decoration:none; white-space:nowrap; transition:opacity 160ms;" onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                View Today's Picks →
            </a>
        </div>

        {{-- ── African Football Spotlight ── --}}
        @if($africanMatches->isNotEmpty())
        <div style="margin-top:2.5rem;">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; margin-bottom:.85rem;">
                <div>
                    <div style="font-size:.68rem; font-weight:800; color:rgba(110,231,183,.7); text-transform:uppercase; letter-spacing:.06em;">🌍 African Football</div>
                    <h3 style="font-size:1.15rem; font-weight:800; color:#fff; margin-top:.2rem;">Coverage no other site has — predictions in Pidgin & Swahili</h3>
                </div>
                <a href="{{ route('predictions.index') }}" style="font-size:.78rem; color:var(--text-dim); text-decoration:none; font-weight:700;">All predictions →</a>
            </div>

            <div class="afr-grid">
                @foreach($africanMatches as $am)
                @php
                    $flag = ['Nigeria'=>'🇳🇬','South Africa'=>'🇿🇦','Ghana'=>'🇬🇭','Egypt'=>'🇪🇬','Morocco'=>'🇲🇦',
                             'Tunisia'=>'🇹🇳','Algeria'=>'🇩🇿','Kenya'=>'🇰🇪','Tanzania'=>'🇹🇿','Senegal'=>'🇸🇳',
                             'Ivory Coast'=>'🇨🇮','Cameroon'=>'🇨🇲','Zambia'=>'🇿🇲','Uganda'=>'🇺🇬','Ethiopia'=>'🇪🇹','Sudan'=>'🇸🇩'][$am->league_country] ?? '🌍';
                    $isLive  = in_array($am->status, ['1H','2H','HT','ET','BT','P','LIVE']);
                    $isFt    = in_array($am->status, ['FT','AET','PEN']);
                    $hasPred = $am->prediction && $am->prediction->predicted_outcome;
                    $linkUrl = $hasPred ? route('predictions.show', $am->slug) : route('live.index');
                @endphp
                <a href="{{ $linkUrl }}" class="afr-card">
                    <div class="afr-league">
                        <span class="afr-flag">{{ $flag }}</span>
                        <span>{{ \App\Support\LeagueCoverage::formatName($am->league, $am->league_country) }}</span>
                    </div>
                    <div class="afr-teams">
                        <div>{{ $am->home_team }}</div>
                        <div style="color:var(--text-muted); font-weight:600; padding:0 .25rem;">vs</div>
                        <div>{{ $am->away_team }}</div>
                    </div>
                    <div class="afr-meta">
                        @if($isLive)
                            <span style="color:#ef4444;">● LIVE {{ $am->elapsed }}'</span>
                            <strong style="margin-left:.4rem; color:#fff;">{{ $am->home_score ?? 0 }}–{{ $am->away_score ?? 0 }}</strong>
                        @elseif($isFt)
                            <strong style="color:#fff;">{{ $am->home_score }}–{{ $am->away_score }}</strong>
                            <span style="color:var(--text-dim);"> FT</span>
                        @else
                            KO {{ $am->match_time?->format('H:i') }} · {{ $am->match_time?->format('M j') }}
                        @endif
                        @if($hasPred)
                            <span class="afr-tip">📊 {{ $am->prediction->predicted_outcome }}</span>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.85rem; align-items:center;">
                <span style="font-size:.7rem; color:var(--text-dim);">Now covering:</span>
                <span class="league-pill">🇳🇬 NPFL</span>
                <span class="league-pill">🇿🇦 PSL</span>
                <span class="league-pill">🇬🇭 Ghana Premier</span>
                <span class="league-pill">🇪🇬 Egyptian Premier</span>
                <span class="league-pill">🇲🇦 Botola Pro</span>
                <span class="league-pill">🇰🇪 FKF Premier</span>
                <span class="league-pill">🌍 CAF Champions League</span>
                <span class="league-pill">🌍 AFCON</span>
            </div>
        </div>
        @endif

        {{-- Competitions strip --}}
        <div class="section-label" style="margin-top:2.5rem;">Competitions covered</div>
        <div class="leagues-strip">
            <span class="league-pill">🏴󠁧󠁢󠁥󠁮󠁧󠁿 Premier League</span>
            <span class="league-pill">🌍 Champions League</span>
            <span class="league-pill">🇪🇸 La Liga</span>
            <span class="league-pill">🇩🇪 Bundesliga</span>
            <span class="league-pill">🇮🇹 Serie A</span>
            <span class="league-pill">🇫🇷 Ligue 1</span>
            <span class="league-pill">🌍 Europa League</span>
            <span class="league-pill">🌍 Conference League</span>
            <span class="league-pill">🏴󠁧󠁢󠁥󠁮󠁧󠁿 Championship</span>
            <span class="league-pill">🇳🇱 Eredivisie</span>
            <span class="league-pill">🇵🇹 Primeira Liga</span>
            <span class="league-pill">+ more</span>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
Promise.all([
    fetch('/api/matches/live',     { headers: { Accept: 'application/json' } }).then(r => r.json()),
    fetch('/api/matches/today',    { headers: { Accept: 'application/json' } }).then(r => r.json()),
    fetch('/api/matches/finished', { headers: { Accept: 'application/json' } }).then(r => r.json()),
    fetch('/api/predictions',      { headers: { Accept: 'application/json' } }).then(r => r.json()),
]).then(function ([live, today, finished, preds]) {
    document.getElementById('h-live').textContent     = Array.isArray(live.data)     ? live.data.length     : 0;
    document.getElementById('h-today').textContent    = Array.isArray(today.data)    ? today.data.length    : 0;
    document.getElementById('h-finished').textContent = Array.isArray(finished.data) ? finished.data.length : 0;
    document.getElementById('h-preds').textContent    = Array.isArray(preds.data)    ? preds.data.length    : 0;
}).catch(function () {
    ['h-live','h-today','h-finished','h-preds'].forEach(function (id) {
        document.getElementById(id).textContent = '0';
    });
});
</script>
@endpush
