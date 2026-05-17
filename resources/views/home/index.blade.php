@extends('layouts.app')

@section('title', 'TavsScore | Live Football Scores & AI Predictions')

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
    display: inline-flex; align-items: center; gap: .45rem;
    padding: 3px 12px; border-radius: 999px;
    background: var(--green-dim); border: 1px solid var(--green-border);
    color: #6ee7b7; font-size: .72rem; font-weight: 700;
    letter-spacing: .05em; text-transform: uppercase; margin-bottom: 1.25rem;
}
.hero-title {
    font-size: clamp(2.1rem, 6vw, 4.2rem); font-weight: 900;
    line-height: 1.06; letter-spacing: -.03em; color: #fff; margin-bottom: 1.1rem;
}
.hero-title .grad {
    background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.hero-desc { font-size: 1rem; color: var(--text-dim); max-width: 500px; line-height: 1.75; margin-bottom: 1.75rem; }
.hero-ctas { display: flex; flex-wrap: wrap; gap: .65rem; margin-bottom: 2.75rem; }

/* ── Stats bar ── */
.stats-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: .6rem; }
.stat-tile {
    background: var(--card); border: 1px solid var(--border); border-radius: 12px;
    padding: 1rem; display: flex; align-items: center; gap: .75rem;
    text-decoration: none; transition: border-color 160ms, transform 160ms;
}
.stat-tile:hover { transform: translateY(-2px); }
.stat-ico {
    width: 38px; height: 38px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.ico-red   { background: var(--red-dim); }
.ico-blue  { background: var(--blue-dim); }
.ico-gray  { background: rgba(107,114,128,.12); }
.ico-green { background: var(--green-dim); }
.stat-val  { font-size: 1.45rem; font-weight: 800; color: #fff; line-height: 1; display: block; }
.stat-lbl  { font-size: .68rem; color: var(--text-dim); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-top: 3px; display: block; }

/* ── Section headings ── */
.section-label {
    font-size: .72rem; font-weight: 700; color: var(--green);
    text-transform: uppercase; letter-spacing: .08em; margin-bottom: .5rem;
}
.section-title  { font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -.02em; margin-bottom: .5rem; }
.section-desc   { font-size: .88rem; color: var(--text-dim); max-width: 540px; line-height: 1.7; margin-bottom: 2rem; }

/* ── Nav-section feature cards ── */
.nsc-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .85rem;
}
.nsc-card {
    display: flex; flex-direction: column;
    background: var(--card); border: 1px solid var(--border); border-radius: 16px;
    overflow: hidden; text-decoration: none;
    transition: border-color 200ms, transform 200ms;
}
.nsc-card:hover { transform: translateY(-3px); border-color: rgba(255,255,255,.12); }
.nsc-header {
    padding: 1.1rem 1.2rem .9rem;
    display: flex; align-items: center; justify-content: space-between;
}
.nsc-emoji { font-size: 1.8rem; line-height: 1; }
.nsc-live-tag {
    font-size: .6rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
    padding: 2px 8px; border-radius: 999px;
}
.nsc-body { padding: .1rem 1.2rem 1.2rem; flex: 1; display: flex; flex-direction: column; }
.nsc-title { font-size: .95rem; font-weight: 800; color: #fff; margin-bottom: .45rem; }
.nsc-desc  { font-size: .78rem; color: var(--text-dim); line-height: 1.68; flex: 1; }
.nsc-cta   { font-size: .75rem; font-weight: 700; margin-top: .85rem; }

/* ── Live Picks section ── */
.picks-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .85rem; align-items: start; }

/* ── African spotlight ── */
.afr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: .65rem; }
.afr-card {
    display: block; text-decoration: none;
    background: linear-gradient(135deg, rgba(16,185,129,.06), rgba(16,185,129,.02));
    border: 1px solid rgba(16,185,129,.18); border-radius: 11px;
    padding: .85rem .95rem;
    transition: transform 160ms, border-color 160ms, background 160ms;
}
.afr-card:hover { transform: translateY(-2px); border-color: rgba(16,185,129,.4); background: linear-gradient(135deg, rgba(16,185,129,.10), rgba(16,185,129,.04)); }
.afr-league { display: flex; align-items: center; gap: .45rem; font-size: .7rem; font-weight: 700; color: #6ee7b7; margin-bottom: .45rem; }
.afr-teams  { display: flex; align-items: center; color: #fff; font-weight: 700; font-size: .85rem; line-height: 1.3; }
.afr-teams > div:first-child, .afr-teams > div:last-child { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.afr-teams > div:last-child { text-align: right; }
.afr-meta   { font-size: .7rem; color: var(--text-dim); margin-top: .55rem; display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.afr-tip    { display: inline-block; padding: 1px 7px; border-radius: 999px; background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.25); color: #fcd34d; font-weight: 700; font-size: .65rem; margin-left: auto; }

/* ── League pills ── */
.leagues-strip { display: flex; flex-wrap: wrap; gap: .45rem; margin-top: 1.25rem; }
.league-pill {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: 5px 11px; border-radius: 999px;
    background: var(--surface); border: 1px solid var(--border);
    font-size: .75rem; font-weight: 600; color: var(--text-dim);
    transition: color 160ms, border-color 160ms;
}
.league-pill:hover { color: var(--text); border-color: rgba(99,179,237,.25); }

/* ── Community cards ── */
.community-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .85rem; }

@media (max-width: 860px) {
    .stats-bar       { grid-template-columns: repeat(2, 1fr); }
    .nsc-grid        { grid-template-columns: 1fr 1fr; }
    .picks-grid      { grid-template-columns: 1fr; }
    .community-grid  { grid-template-columns: 1fr 1fr; }
    .hero            { padding: 3rem 0 2.5rem; }
}
@media (max-width: 540px) {
    .nsc-grid       { grid-template-columns: 1fr; }
    .community-grid { grid-template-columns: 1fr; }
    .install-notif-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush

@section('content')

{{-- ══════════════════════════════════════════
     HERO
══════════════════════════════════════════ --}}
<section class="hero">
    <div class="wrap">
        <div class="hero-eyebrow">
            <span class="live-dot"></span>
            Live Football Platform
        </div>
        <h1 class="hero-title">
            The Smartest Way<br>to Follow <span class="grad">Football</span>
        </h1>
        <p class="hero-desc">
            Live scores, triple-AI match predictions, daily picks, rollover challenges,
            and full African football coverage all updated in real time.
        </p>
        <div class="hero-ctas">
            <a href="{{ route('live.index') }}"        class="btn-ts btn-green">⚡ View Live Scores</a>
            <a href="{{ route('predictions.index') }}" class="btn-ts btn-outline">📊 Match Predictions</a>
        </div>

        {{-- Live stats bar — all tiles are clickable links ── --}}
        <div class="stats-bar">
            <a href="{{ route('live.index') }}" class="stat-tile" style="border-color:transparent;" onmouseover="this.style.borderColor='rgba(239,68,68,.3)'" onmouseout="this.style.borderColor='transparent'">
                <div class="stat-ico ico-red">🔴</div>
                <div><span class="stat-val" id="h-live">–</span><span class="stat-lbl">Live now</span></div>
            </a>
            <a href="{{ route('predictions.index') }}" class="stat-tile" style="border-color:transparent;" onmouseover="this.style.borderColor='rgba(59,130,246,.3)'" onmouseout="this.style.borderColor='transparent'">
                <div class="stat-ico ico-blue">📅</div>
                <div><span class="stat-val" id="h-today">–</span><span class="stat-lbl">Upcoming today</span></div>
            </a>
            <a href="{{ route('results.index') }}" class="stat-tile" style="border-color:transparent;" onmouseover="this.style.borderColor='rgba(107,114,128,.3)'" onmouseout="this.style.borderColor='transparent'">
                <div class="stat-ico ico-gray">✅</div>
                <div><span class="stat-val" id="h-finished">–</span><span class="stat-lbl">Finished today</span></div>
            </a>
            <a href="{{ route('picks.index') }}" class="stat-tile" style="border-color:transparent;" onmouseover="this.style.borderColor='rgba(16,185,129,.3)'" onmouseout="this.style.borderColor='transparent'">
                <div class="stat-ico ico-green">⭐</div>
                <div><span class="stat-val">{{ $todayPickCount ?: '–' }}</span><span class="stat-lbl">Picks today</span></div>
            </a>
        </div>
    </div>
</section>


{{-- ══════════════════════════════════════════
     PLATFORM SECTIONS — one card per nav item
══════════════════════════════════════════ --}}
<section style="padding: 3rem 0 0;">
    <div class="wrap">
        <div class="section-label">Everything on TavsScore</div>
        <h2 class="section-title">Every section, explained</h2>
        <p class="section-desc">
            Click any section below to go directly to that page. Here's exactly what each one does.
        </p>

        <div class="nsc-grid">

            {{-- ── 1. LIVE SCORES ── --}}
            <a href="{{ route('live.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(239,68,68,.12), rgba(239,68,68,.04));">
                    <span class="nsc-emoji">🔴</span>
                    <span class="nsc-live-tag" style="background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.3); color:#fca5a5;">
                        <span class="live-dot" style="width:6px;height:6px;margin-right:4px;"></span>Live
                    </span>
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Live Scores</div>
                    <p class="nsc-desc">
                        Real-time match scores from 25+ competitions updated every 30 seconds no refresh needed.
                        See live minute-by-minute updates, goals, cards, and substitutions as they happen,
                        grouped by league so you never lose track of your match.
                    </p>
                    <div class="nsc-cta" style="color:#fca5a5;">Watch Live Scores →</div>
                </div>
            </a>

            {{-- ── 2. PREDICTIONS ── --}}
            <a href="{{ route('predictions.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(59,130,246,.12), rgba(59,130,246,.04));">
                    <span class="nsc-emoji">📊</span>
                    <span class="nsc-live-tag" style="background:rgba(59,130,246,.12); border:1px solid rgba(59,130,246,.25); color:#93c5fd;" id="preds-badge">Loading…</span>
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Match Predictions</div>
                    <p class="nsc-desc">
                        Our triple-AI engine runs three completely independent analyses on every upcoming match: team form, head-to-head records,
                        injury reports, confirmed lineups, and real-time news. All three must reach the same conclusion before a prediction
                        is published, with a confidence score and full reasoning you can read in plain English.
                    </p>
                    <div class="nsc-cta" style="color:#93c5fd;">See All Predictions →</div>
                </div>
            </a>

            {{-- ── 3. DAILY PICKS ── --}}
            <a href="{{ route('picks.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(245,158,11,.12), rgba(245,158,11,.04));">
                    <span class="nsc-emoji">⭐</span>
                    @if($todayPickCount > 0)
                    <span class="nsc-live-tag" style="background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.3); color:#fcd34d;">{{ $todayPickCount }} today</span>
                    @endif
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Daily Picks</div>
                    <p class="nsc-desc">
                        Every morning the AI curates the top 3 best-value bets from all of today's fixtures,
                        ranked #1 to #3 by confidence. Pick #1 is the single strongest selection of the day.
                        Each pick includes the full AI analysis exactly why it chose that market and what the data says.
                    </p>
                    @if($topPick)
                    <div style="background:rgba(245,158,11,.07); border:1px solid rgba(245,158,11,.18); border-radius:9px; padding:.55rem .75rem; margin-top:.65rem; font-size:.78rem;">
                        <div style="color:#fbbf24; font-weight:700; font-size:.65rem; text-transform:uppercase; margin-bottom:.25rem;">Today's #1 Pick</div>
                        <div style="color:#fff; font-weight:700; line-height:1.3;">{{ $topPick->match?->home_team }} vs {{ $topPick->match?->away_team }}</div>
                        <div style="color:#fcd34d; font-weight:800; margin-top:.2rem;">{{ $topPick->predicted_outcome }}
                            @if($topPick->confidence)<span style="color:var(--text-dim); font-weight:600;"> · {{ $topPick->confidence }}% conf.</span>@endif
                        </div>
                    </div>
                    @endif
                    <div class="nsc-cta" style="color:#fcd34d;">View Today's Picks →</div>
                </div>
            </a>

            {{-- ── 4. DRAW PICKS ── --}}
            <a href="{{ route('draw-picks.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(245,158,11,.12), rgba(245,158,11,.04));">
                    <span class="nsc-emoji">🤝</span>
                    <span class="nsc-live-tag" style="background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.3); color:#fcd34d;">Triple AI</span>
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Draw Picks</div>
                    <p class="nsc-desc">
                        Draws are the hardest market to get right, so we hold them to a higher standard.
                        A draw pick only appears here when all three independent AI engines separately analyse the match
                        and all three reach the same conclusion: it will end level. Triple agreement, no exceptions.
                    </p>
                    <div class="nsc-cta" style="color:#fcd34d;">View Draw Picks →</div>
                </div>
            </a>

            {{-- ── 5. GG PICKS ── --}}
            <a href="{{ route('gg-picks.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(16,185,129,.12), rgba(59,130,246,.06));">
                    <span class="nsc-emoji">⚽</span>
                    <span class="nsc-live-tag" style="background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.3); color:#6ee7b7;">Triple AI</span>
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">GG Picks</div>
                    <p class="nsc-desc">
                        Both teams to score is one of the most popular markets in African betting.
                        Our GG picks are only published when all three AI engines independently predict
                        that both sides will get on the scoresheet. High conviction, nothing speculative.
                    </p>
                    <div class="nsc-cta" style="color:#6ee7b7;">View GG Picks →</div>
                </div>
            </a>

            {{-- ── 6. ROLLOVER CHALLENGE ── --}}
            <a href="{{ route('rollover.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(99,102,241,.12), rgba(99,102,241,.04));">
                    <span class="nsc-emoji">🔄</span>
                    @if($rollover)
                    <span class="nsc-live-tag" style="background:rgba(99,102,241,.12); border:1px solid rgba(99,102,241,.3); color:#a5b4fc;">Day {{ $rolloverDay }}/10</span>
                    @else
                    <span class="nsc-live-tag" style="background:rgba(99,102,241,.08); border:1px solid rgba(99,102,241,.2); color:#818cf8;">Active</span>
                    @endif
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Rollover Challenge</div>
                    <p class="nsc-desc">
                        The ultimate 10-day AI betting challenge. We start with a fixed stake and attempt to grow it
                        every single day for 10 consecutive days each day's profit rolling into the next bet.
                        Three independent AI engines analyse every match separately and must all agree on the same pick before it's placed. One loss resets everything.
                    </p>
                    @if($rollover)
                    <div style="margin-top:.6rem;">
                        <div style="display:flex; justify-content:space-between; font-size:.72rem; margin-bottom:.3rem;">
                            <span style="color:var(--text-dim);">Started: ₦{{ number_format($rollover->initial_stake, 0) }}</span>
                            <span style="color:#a5b4fc; font-weight:800;">Now: ₦{{ number_format($rolloverBalance, 0) }}</span>
                        </div>
                        @php $pct = $rolloverDay > 0 ? round($rolloverDay / 10 * 100) : 0; @endphp
                        <div style="height:5px; background:rgba(99,102,241,.15); border-radius:999px; overflow:hidden;">
                            <div style="height:100%; width:{{ $pct }}%; background:linear-gradient(90deg,#6366f1,#a5b4fc); border-radius:999px;"></div>
                        </div>
                        <div style="font-size:.63rem; color:var(--text-dim); margin-top:.25rem;">{{ $pct }}% of challenge complete</div>
                    </div>
                    @endif
                    <div class="nsc-cta" style="color:#a5b4fc;">Follow the Challenge →</div>
                </div>
            </a>

            {{-- ── 5. LINEUP PICKS ── --}}
            <a href="{{ route('lineup-picks.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(16,185,129,.12), rgba(16,185,129,.04));">
                    <span class="nsc-emoji">⚡</span>
                    @if($lineupPickCount > 0)
                    <span class="nsc-live-tag" style="background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.3); color:#6ee7b7;">{{ $lineupPickCount }} confirmed</span>
                    @else
                    <span class="nsc-live-tag" style="background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.18); color:#34d399;">Updated daily</span>
                    @endif
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Lineup Picks</div>
                    <p class="nsc-desc">
                        Standard predictions are made hours before kick-off. Lineup Picks are different: they're generated
                        only <strong style="color:#fff;">after official starting XIs are confirmed</strong>, typically 60 minutes before the match.
                        This means the AI knows exactly who's playing, who's missing, and adjusts its pick accordingly.
                    </p>
                    <div class="nsc-cta" style="color:#6ee7b7;">View Lineup Picks →</div>
                </div>
            </a>

            {{-- ── 6. CORRECT SCORE ── --}}
            <a href="{{ route('correct-score.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(139,92,246,.12), rgba(139,92,246,.04));">
                    <span class="nsc-emoji">🎯</span>
                    @if($correctScoreCount > 0)
                    <span class="nsc-live-tag" style="background:rgba(139,92,246,.12); border:1px solid rgba(139,92,246,.3); color:#c4b5fd;">{{ $correctScoreCount }} today</span>
                    @else
                    <span class="nsc-live-tag" style="background:rgba(139,92,246,.08); border:1px solid rgba(139,92,246,.2); color:#a78bfa;">Daily</span>
                    @endif
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Correct Score</div>
                    <p class="nsc-desc">
                        Using Poisson distribution mathematics, we calculate the probability of every possible scoreline
                        for each match and surface the top 3 most likely exact scores.
                        Instead of just "home win"  we tell you it's most likely to end 2–1, with the probability percentage.
                        Perfect for exact score and scorecast markets.
                    </p>
                    <div class="nsc-cta" style="color:#c4b5fd;">See Score Predictions →</div>
                </div>
            </a>

            {{-- ── 7. AFRICAN FOOTBALL ── --}}
            <a href="{{ route('africa.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(16,185,129,.1), rgba(251,191,36,.06));">
                    <span class="nsc-emoji">🌍</span>
                    <span class="nsc-live-tag" style="background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.3); color:#6ee7b7;">10+ leagues</span>
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">African Football</div>
                    <p class="nsc-desc">
                        The only predictions platform with dedicated African football coverage.
                        We follow the NPFL, PSL, Ghana Premier League, Egyptian Premier, CAF Champions League,
                        AFCON, Botola Pro, FKF Premier, and more with AI match analysis written in
                        <strong style="color:#fff;">Pidgin English and Swahili</strong> for local fans.
                    </p>
                    <div class="nsc-cta" style="color:#6ee7b7;">Explore African Football →</div>
                </div>
            </a>

            {{-- ── 8. STATS ── --}}
            <a href="{{ route('stats.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(59,130,246,.12), rgba(99,102,241,.06));">
                    <span class="nsc-emoji">📈</span>
                    @if($overallAcc !== null)
                    <span class="nsc-live-tag" style="background:rgba(59,130,246,.12); border:1px solid rgba(59,130,246,.25); color:#93c5fd;">{{ $overallAcc }}% accuracy</span>
                    @endif
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">AI Stats & Accuracy</div>
                    <p class="nsc-desc">
                        Full transparency into our AI's prediction performance. See the all-time accuracy rate,
                        last-7-day rolling performance, breakdown by market type (e.g. 1X2, BTTS, Over 2.5),
                        and a complete timeline of every pick with its result. We show both the wins and the losses.
                    </p>
                    @if($overallAcc !== null || $last7Acc !== null)
                    <div style="display:flex; gap:.5rem; margin-top:.6rem;">
                        @if($overallAcc !== null)
                        <div style="flex:1; background:rgba(59,130,246,.07); border:1px solid rgba(59,130,246,.15); border-radius:8px; padding:.5rem; text-align:center;">
                            <div style="font-size:1.2rem; font-weight:900; color:{{ $overallAcc >= 60 ? '#6ee7b7' : ($overallAcc >= 45 ? '#fcd34d' : '#fca5a5') }};">{{ $overallAcc }}%</div>
                            <div style="font-size:.6rem; color:var(--text-dim); font-weight:700;">All-time</div>
                        </div>
                        @endif
                        @if($last7Acc !== null)
                        <div style="flex:1; background:rgba(59,130,246,.07); border:1px solid rgba(59,130,246,.15); border-radius:8px; padding:.5rem; text-align:center;">
                            <div style="font-size:1.2rem; font-weight:900; color:{{ $last7Acc >= 60 ? '#6ee7b7' : ($last7Acc >= 45 ? '#fcd34d' : '#fca5a5') }};">{{ $last7Acc }}%</div>
                            <div style="font-size:.6rem; color:var(--text-dim); font-weight:700;">Last 7 days</div>
                        </div>
                        @endif
                    </div>
                    @endif
                    <div class="nsc-cta" style="color:#93c5fd;">View Full Stats →</div>
                </div>
            </a>

            {{-- ── 9. RESULTS ── --}}
            <a href="{{ route('results.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(107,114,128,.12), rgba(75,85,99,.06));">
                    <span class="nsc-emoji">📜</span>
                    @if($recentResults->isNotEmpty())
                    <div style="display:flex; gap:3px;">
                        @foreach($recentResults->take(5) as $r)
                        <span style="width:14px; height:14px; border-radius:3px; display:inline-flex; align-items:center; justify-content:center; font-size:.5rem; font-weight:900; background:{{ $r->was_correct ? 'rgba(16,185,129,.25)' : 'rgba(239,68,68,.2)' }}; color:{{ $r->was_correct ? '#6ee7b7' : '#fca5a5' }};">{{ $r->was_correct ? 'W' : 'L' }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Past Results</div>
                    <p class="nsc-desc">
                        A complete archive of every prediction we've made and how it ended.
                        Browse by date or league see the AI's predicted market, the actual final score,
                        and whether the pick was correct. No cherry-picking, no hidden losses. Full history, always.
                    </p>
                    @if($recentResults->isNotEmpty())
                    <div style="margin-top:.6rem;">
                        <div style="font-size:.62rem; color:var(--text-dim); font-weight:700; margin-bottom:.3rem;">RECENT PICKS</div>
                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                            @foreach($recentResults as $r)
                            <span title="{{ $r->was_correct ? 'Win' : 'Loss' }}" style="width:22px; height:22px; border-radius:5px; display:inline-flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:800; background:{{ $r->was_correct ? 'rgba(16,185,129,.2)' : 'rgba(239,68,68,.18)' }}; color:{{ $r->was_correct ? '#6ee7b7' : '#fca5a5' }};">{{ $r->was_correct ? 'W' : 'L' }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    <div class="nsc-cta" style="color:#9ca3af;">Browse All Results →</div>
                </div>
            </a>

            {{-- ── 10. WINNERS ── --}}
            <a href="{{ route('winners.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(251,191,36,.12), rgba(245,158,11,.04));">
                    <span class="nsc-emoji">🏆</span>
                    <span class="nsc-live-tag" style="background:rgba(251,191,36,.12); border:1px solid rgba(251,191,36,.3); color:#fcd34d;">Submit your win</span>
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Winners</div>
                    <p class="nsc-desc">
                        Won money using one of our picks? We want to celebrate with you.
                        Upload a screenshot of your winning bet slip, add your story, and get featured on the platform.
                        Real wins from real people every submission is reviewed before going live.
                    </p>
                    <div class="nsc-cta" style="color:#fcd34d;">Submit Your Win →</div>
                </div>
            </a>

            {{-- ── 11. HALL OF FAME ── --}}
            <a href="{{ route('hall-of-fame.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(251,191,36,.1), rgba(245,158,11,.06));">
                    <span class="nsc-emoji">🥇</span>
                    <span class="nsc-live-tag" style="background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.25); color:#fbbf24;">Leaderboard</span>
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Hall of Fame</div>
                    <p class="nsc-desc">
                        The leaderboard of top earners ranked by their single biggest verified win.
                        Every entry is a real bettor who used TavsScore picks and posted their winning slip.
                        Can you make it to the top? Submit a win on the Winners page to claim your spot.
                    </p>
                    <div class="nsc-cta" style="color:#fbbf24;">See the Leaderboard →</div>
                </div>
            </a>

            {{-- ── 12. BLOG ── --}}
            <a href="{{ route('blog.index') }}" class="nsc-card">
                <div class="nsc-header" style="background: linear-gradient(135deg, rgba(16,185,129,.08), rgba(59,130,246,.06));">
                    <span class="nsc-emoji">📝</span>
                    <span class="nsc-live-tag" style="background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.22); color:#6ee7b7;">Analysis</span>
                </div>
                <div class="nsc-body">
                    <div class="nsc-title">Blog</div>
                    <p class="nsc-desc">
                        In-depth football analysis articles, match previews, tactical breakdowns, and betting market guides
                        written by the TavsScore team with AI assistance. Learn how our prediction models work,
                        which markets have the best value, and get expert insight on the biggest fixtures.
                    </p>
                    <div class="nsc-cta" style="color:#6ee7b7;">Read the Blog →</div>
                </div>
            </a>

        </div>
    </div>
</section>


{{-- ══════════════════════════════════════════
     TODAY'S LIVE PICKS — detailed data cards
══════════════════════════════════════════ --}}
<section style="padding: 3rem 0 0;">
    <div class="wrap">
        <div class="section-label">Updated live</div>
        <h2 class="section-title">Today's picks at a glance</h2>
        <p class="section-desc">Real data from our AI engine refreshed throughout the day.</p>

        <div class="picks-grid">

            {{-- Today's Top Pick --}}
            <a href="{{ route('picks.index') }}" style="display:block; text-decoration:none; background:var(--card); border:1px solid rgba(245,158,11,.25); border-radius:16px; padding:1.25rem; transition:transform 160ms,border-color 160ms;" onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='rgba(245,158,11,.5)'" onmouseout="this.style.transform='none';this.style.borderColor='rgba(245,158,11,.25)'">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.85rem;">
                    <span style="font-size:.65rem; font-weight:800; color:#fbbf24; text-transform:uppercase; letter-spacing:.06em;">⭐ Today's Top Pick</span>
                    @if($todayPickCount > 0)
                    <span style="font-size:.65rem; background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.25); color:#fcd34d; border-radius:999px; padding:2px 8px; font-weight:700;">{{ $todayPickCount }} pick{{ $todayPickCount > 1 ? 's' : '' }}</span>
                    @endif
                </div>
                @if($topPick)
                @php
                    $isAi  = !blank($topPick->analysis) && $topPick->analysis !== 'Prediction pending';
                    $isLive = in_array($topPick->match?->status, ['1H','2H','HT','ET','BT','P','LIVE']);
                    $isFt   = in_array($topPick->match?->status, ['FT','AET','PEN']);
                @endphp
                <div style="font-size:.95rem; font-weight:800; color:#fff; margin-bottom:.2rem; line-height:1.3;">
                    {{ $topPick->match?->home_team }} <span style="color:var(--text-dim); font-size:.8rem; font-weight:500;">vs</span> {{ $topPick->match?->away_team }}
                </div>
                <div style="font-size:.7rem; color:var(--text-dim); margin-bottom:.75rem;">
                    {{ \App\Support\LeagueCoverage::formatName($topPick->match?->league, $topPick->match?->league_country) }}
                    · KO {{ $topPick->match?->match_time?->format('H:i') }}
                </div>
                <div style="display:inline-flex; align-items:center; gap:.5rem; background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.2); border-radius:8px; padding:.5rem .75rem; margin-bottom:.75rem;">
                    <span style="font-size:.9rem; font-weight:800; color:#fcd34d;">{{ $topPick->predicted_outcome }}</span>
                    @if($topPick->confidence)<span style="font-size:.7rem; color:#fbbf24; font-weight:700;">{{ $topPick->confidence }}% confidence</span>@endif
                </div>
                @if($isLive)
                <div style="font-size:.72rem; color:#ef4444; font-weight:700; margin-bottom:.4rem;">● LIVE {{ $topPick->match->elapsed }}'  {{ $topPick->match->home_score }}–{{ $topPick->match->away_score }}</div>
                @elseif($isFt)
                @php $wc = $topPick->was_correct; @endphp
                <div style="font-size:.72rem; font-weight:700; margin-bottom:.4rem; color:{{ $wc === true ? '#6ee7b7' : ($wc === false ? '#fca5a5' : '#94a3b8') }};">
                    {{ $wc === true ? '✅ Correct' : ($wc === false ? '❌ Incorrect' : '') }} · FT: {{ $topPick->match->home_score }}–{{ $topPick->match->away_score }}
                </div>
                @endif
                @if($isAi)
                <span style="font-size:.63rem; color:#6ee7b7; background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.2); border-radius:999px; padding:2px 7px;">🤖 AI Verified</span>
                @endif
                @else
                <div style="padding:.75rem 0; color:var(--text-dim); font-size:.82rem; line-height:1.6;">
                    Picks are published every morning before 9am.<br>Check back shortly.
                </div>
                @endif
                <div style="margin-top:.9rem; font-size:.73rem; font-weight:700; color:#fbbf24;">View all today's picks →</div>
            </a>

            {{-- Rollover Challenge --}}
            <a href="{{ route('rollover.index') }}" style="display:block; text-decoration:none; background:var(--card); border:1px solid rgba(99,102,241,.25); border-radius:16px; padding:1.25rem; transition:transform 160ms,border-color 160ms;" onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='rgba(99,102,241,.5)'" onmouseout="this.style.transform='none';this.style.borderColor='rgba(99,102,241,.25)'">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.85rem;">
                    <span style="font-size:.65rem; font-weight:800; color:#a5b4fc; text-transform:uppercase; letter-spacing:.06em;">🔄 Rollover Challenge</span>
                    @if($rollover)
                    <span style="font-size:.65rem; background:rgba(99,102,241,.12); border:1px solid rgba(99,102,241,.25); color:#a5b4fc; border-radius:999px; padding:2px 8px; font-weight:700;">Day {{ $rolloverDay }} of 10</span>
                    @endif
                </div>
                @if($rollover)
                <div style="margin-bottom:.65rem;">
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:.25rem;">
                        <span style="font-size:.7rem; color:var(--text-dim);">Opening stake</span>
                        <span style="font-size:.78rem; color:#fff; font-weight:700;">₦{{ number_format($rollover->initial_stake, 0) }}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:.5rem;">
                        <span style="font-size:.7rem; color:var(--text-dim);">Current balance</span>
                        <span style="font-size:1.1rem; color:#a5b4fc; font-weight:900;">₦{{ number_format($rolloverBalance, 0) }}</span>
                    </div>
                    @php $pct = $rolloverDay > 0 ? round($rolloverDay / 10 * 100) : 0; @endphp
                    <div style="height:5px; background:rgba(99,102,241,.15); border-radius:999px; overflow:hidden;">
                        <div style="height:100%; width:{{ $pct }}%; background:linear-gradient(90deg,#6366f1,#a5b4fc); border-radius:999px;"></div>
                    </div>
                    <div style="font-size:.63rem; color:var(--text-dim); margin-top:.3rem;">{{ $pct }}% of the challenge complete</div>
                </div>
                @if($rolloverPick)
                <div style="background:rgba(99,102,241,.08); border:1px solid rgba(99,102,241,.2); border-radius:8px; padding:.55rem .7rem; margin-bottom:.5rem;">
                    <div style="font-size:.63rem; color:#a5b4fc; font-weight:800; text-transform:uppercase; margin-bottom:.25rem;">Today's Rollover Pick</div>
                    <div style="font-size:.82rem; font-weight:700; color:#fff;">{{ $rolloverPick->groq_verdict }}</div>
                    <div style="font-size:.7rem; color:var(--text-dim); margin-top:.15rem;">Odds: {{ $rolloverPick->implied_odds }} · Stake: ₦{{ number_format($rolloverPick->stake_amount, 0) }}</div>
                    @if($rolloverPick->both_agree)<div style="font-size:.63rem; color:#6ee7b7; margin-top:.3rem;">✓ All 3 AI engines agree on this pick</div>@endif
                </div>
                @else
                <div style="font-size:.78rem; color:var(--text-dim); padding:.4rem 0;">Today's pick is selected at 9:30am each morning.</div>
                @endif
                @else
                <div style="padding:.5rem 0; color:var(--text-dim); font-size:.82rem; line-height:1.6;">
                    No active challenge running right now.<br>A new one starts automatically when the last ends.
                </div>
                @endif
                <div style="margin-top:.75rem; font-size:.73rem; font-weight:700; color:#a5b4fc;">Follow the full challenge →</div>
            </a>

            {{-- AI Track Record --}}
            <a href="{{ route('stats.index') }}" style="display:block; text-decoration:none; background:var(--card); border:1px solid rgba(59,130,246,.25); border-radius:16px; padding:1.25rem; transition:transform 160ms,border-color 160ms;" onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='rgba(59,130,246,.5)'" onmouseout="this.style.transform='none';this.style.borderColor='rgba(59,130,246,.25)'">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.85rem;">
                    <span style="font-size:.65rem; font-weight:800; color:#93c5fd; text-transform:uppercase; letter-spacing:.06em;">📈 AI Track Record</span>
                    @if($totalResolved > 0)
                    <span style="font-size:.65rem; background:rgba(59,130,246,.1); border:1px solid rgba(59,130,246,.2); color:#93c5fd; border-radius:999px; padding:2px 8px; font-weight:700;">{{ $totalResolved }} picks graded</span>
                    @endif
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.85rem;">
                    <div style="background:rgba(59,130,246,.06); border:1px solid rgba(59,130,246,.12); border-radius:10px; padding:.65rem; text-align:center;">
                        <div style="font-size:1.5rem; font-weight:900; color:{{ ($overallAcc ?? 0) >= 60 ? '#6ee7b7' : (($overallAcc ?? 0) >= 45 ? '#fcd34d' : '#fca5a5') }};">{{ $overallAcc !== null ? $overallAcc.'%' : '—' }}</div>
                        <div style="font-size:.62rem; color:var(--text-dim); margin-top:2px; font-weight:700;">All-time accuracy</div>
                    </div>
                    <div style="background:rgba(59,130,246,.06); border:1px solid rgba(59,130,246,.12); border-radius:10px; padding:.65rem; text-align:center;">
                        <div style="font-size:1.5rem; font-weight:900; color:{{ ($last7Acc ?? 0) >= 60 ? '#6ee7b7' : (($last7Acc ?? 0) >= 45 ? '#fcd34d' : '#fca5a5') }};">{{ $last7Acc !== null ? $last7Acc.'%' : '—' }}</div>
                        <div style="font-size:.62rem; color:var(--text-dim); margin-top:2px; font-weight:700;">Last 7 days</div>
                    </div>
                </div>
                @if($recentResults->isNotEmpty())
                <div style="margin-bottom:.6rem;">
                    <div style="font-size:.62rem; color:var(--text-dim); margin-bottom:.35rem; font-weight:700;">LAST 10 RESULTS</div>
                    <div style="display:flex; gap:4px; flex-wrap:wrap;">
                        @foreach($recentResults as $r)
                        <span style="width:22px; height:22px; border-radius:5px; display:inline-flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:800; background:{{ $r->was_correct ? 'rgba(16,185,129,.2)' : 'rgba(239,68,68,.18)' }}; color:{{ $r->was_correct ? '#6ee7b7' : '#fca5a5' }};">{{ $r->was_correct ? 'W' : 'L' }}</span>
                        @endforeach
                    </div>
                </div>
                @endif
                @if($streak >= 2)
                <div style="font-size:.72rem; color:{{ $streakType ? '#6ee7b7' : '#fca5a5' }}; font-weight:700;">
                    {{ $streakType ? '🔥 ' . $streak . '-pick winning streak' : '❄️ ' . $streak . '-pick losing run' }}
                </div>
                @endif
                <div style="margin-top:.75rem; font-size:.73rem; font-weight:700; color:#93c5fd;">Full stats & breakdown →</div>
            </a>

        </div>
    </div>
</section>


{{-- ══════════════════════════════════════════
     AFRICAN FOOTBALL SPOTLIGHT
══════════════════════════════════════════ --}}
@if($africanMatches->isNotEmpty())
<section style="padding: 3rem 0 0;">
    <div class="wrap">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem;">
            <div>
                <div class="section-label" style="margin-bottom:.3rem;">🌍 African Football</div>
                <h2 class="section-title" style="margin-bottom:.3rem;">Coverage no other site has</h2>
                <p style="font-size:.82rem; color:var(--text-dim); margin:0;">Predictions in Pidgin English & Swahili. Live + upcoming African fixtures.</p>
            </div>
            <a href="{{ route('africa.index') }}" class="btn-ts btn-outline" style="font-size:.8rem;">Explore African Football →</a>
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
                $linkUrl = $hasPred ? route('predictions.show', $am->slug) : route('africa.index');
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
            <span style="font-size:.7rem; color:var(--text-dim);">Leagues covered:</span>
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
</section>
@endif


{{-- ══════════════════════════════════════════
     COMPETITIONS COVERED
══════════════════════════════════════════ --}}
<section style="padding: 3rem 0 4rem;">
    <div class="wrap">
        <div class="section-label">Global coverage</div>
        <h2 class="section-title">25+ competitions tracked live</h2>
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
            <span class="league-pill">🇹🇷 Süper Lig</span>
            <span class="league-pill">🇳🇬 NPFL</span>
            <span class="league-pill">🇿🇦 PSL</span>
            <span class="league-pill">🌍 CAF CL</span>
            <span class="league-pill">+ more</span>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════
     INSTALL APP + NOTIFICATIONS CTA
══════════════════════════════════════════ --}}
<section style="padding: 3rem 0 4rem;">
    <div class="wrap">
        <div class="install-notif-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; max-width:860px; margin:0 auto;">

            {{-- Install card --}}
            <div style="background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(16,185,129,.02)); border:1px solid rgba(16,185,129,.2); border-radius:18px; padding:1.75rem;">
                <div style="font-size:2rem; margin-bottom:.75rem;">📲</div>
                <h3 style="font-size:1.1rem; font-weight:900; color:#fff; letter-spacing:-.02em; margin-bottom:.5rem;">Add to Your Home Screen</h3>
                <p style="font-size:.85rem; color:var(--text-dim); line-height:1.7; margin-bottom:1.1rem;">
                    TavsScore works like a native app. Add it to your phone's home screen for one-tap access to live scores and daily picks. No app store, no installs.
                </p>
                <div style="font-size:.8rem; color:var(--text-dim); margin-bottom:.6rem; font-weight:700;">How to install:</div>
                <div style="font-size:.8rem; color:var(--text-dim); line-height:1.7; margin-bottom:.5rem;">
                    <strong style="color:var(--text);">iPhone (Safari):</strong> Tap the Share button ⬆️ → "Add to Home Screen" → Add
                </div>
                <div style="font-size:.8rem; color:var(--text-dim); line-height:1.7; margin-bottom:1.1rem;">
                    <strong style="color:var(--text);">Android (Chrome):</strong> Tap the ⋮ menu → "Add to Home screen" → Install
                </div>
                <button onclick="openInstallGuide()" style="display:inline-flex; align-items:center; gap:.4rem; padding:.5rem 1.1rem; border-radius:9px; background:rgba(16,185,129,.15); border:1px solid rgba(16,185,129,.3); color:#6ee7b7; font-size:.82rem; font-weight:700; cursor:pointer;">
                    Full install guide →
                </button>
            </div>

            {{-- Notifications card --}}
            <div style="background:linear-gradient(135deg,rgba(59,130,246,.08),rgba(59,130,246,.02)); border:1px solid rgba(59,130,246,.2); border-radius:18px; padding:1.75rem;">
                <div style="font-size:2rem; margin-bottom:.75rem;">🔔</div>
                <h3 style="font-size:1.1rem; font-weight:900; color:#fff; letter-spacing:-.02em; margin-bottom:.5rem;">Get Free Pick Alerts</h3>
                <p style="font-size:.85rem; color:var(--text-dim); line-height:1.7; margin-bottom:1.1rem;">
                    Never miss a pick. Subscribe to push notifications and we'll send you today's AI picks at 08:00 Lagos every morning, plus live goal alerts and outcome results.
                </p>
                <div style="font-size:.8rem; color:var(--text-dim); line-height:1.7; margin-bottom:.5rem;">
                    <strong style="color:var(--text);">What you get:</strong>
                </div>
                <ul style="font-size:.8rem; color:var(--text-dim); line-height:1.8; margin:0 0 1.1rem; padding-left:1.25rem;">
                    <li>🎯 Daily picks at 08:00 every morning</li>
                    <li>⚽ Goal alerts for live matches</li>
                    <li>✅ Outcome notifications when picks settle</li>
                    <li>🔄 Rollover pick updates</li>
                </ul>
                <button onclick="requestPushPermission()" style="display:inline-flex; align-items:center; gap:.4rem; padding:.5rem 1.1rem; border-radius:9px; background:rgba(59,130,246,.15); border:1px solid rgba(59,130,246,.3); color:#93c5fd; font-size:.82rem; font-weight:700; cursor:pointer;">
                    🔔 Enable notifications →
                </button>
                <div style="font-size:.72rem; color:var(--text-dim); margin-top:.65rem;">
                    Already subscribed? The bell icon 🔔 in the bottom-right lets you manage preferences.
                </div>
            </div>

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
    var predCount = Array.isArray(preds.data) ? preds.data.length : 0;
    var badge = document.getElementById('preds-badge');
    if (badge) badge.textContent = predCount + ' today';
}).catch(function () {
    ['h-live','h-today','h-finished'].forEach(function (id) {
        document.getElementById(id).textContent = '0';
    });
    var badge = document.getElementById('preds-badge');
    if (badge) badge.textContent = '—';
});
</script>
@endpush
