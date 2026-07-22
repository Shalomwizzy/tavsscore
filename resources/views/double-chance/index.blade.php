@extends('layouts.app')
@section('title', "Double Chance Picks 1X & 2X | Daily AI Picks | TavsScore")
@section('meta_description', 'Free daily AI Double Chance picks — 1X (Home Win or Draw) and 2X (Away Win or Draw). Our model picks the 5 safest double chance bets every day.')
@section('og_title', "Double Chance Picks: 1X & 2X Daily AI Predictions")
@section('og_description', 'AI double chance picks for the 1X and 2X markets. We find the 5 matches where our model is most confident either the home side won\'t lose (1X) or the away side won\'t lose (2X).')
@section('og_image', asset('images/og-default.jpg'))
@section('canonical', url('/double-chance'))

@push('styles')
<style>
    .picks-hero { padding:3.5rem 0 2.5rem; background: radial-gradient(ellipse 70% 60% at 50% -10%, rgba(59,130,246,.10), transparent), radial-gradient(ellipse 50% 40% at 90% 80%, rgba(16,185,129,.07), transparent); border-bottom:1px solid var(--border); }
    .picks-eyebrow { display:inline-flex; align-items:center; gap:.45rem; padding:3px 12px; border-radius:999px; background:rgba(59,130,246,.12); border:1px solid rgba(59,130,246,.28); color:#93c5fd; font-size:.72rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; margin-bottom:1.1rem; }
    .picks-title { font-size:clamp(1.8rem,5vw,2.8rem); font-weight:900; color:#fff; letter-spacing:-.03em; line-height:1.1; margin-bottom:.75rem; }
    .picks-title .accent { color:#3b82f6; }
    .picks-subtitle { font-size:.95rem; color:var(--text-dim); line-height:1.7; max-width:520px; margin-bottom:1.5rem; }
    .picks-meta { display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; }
    .picks-badge { display:inline-flex; align-items:center; gap:.4rem; padding:4px 12px; border-radius:999px; font-size:.73rem; font-weight:700; }
    .badge-blue { background:rgba(59,130,246,.1); border:1px solid rgba(59,130,246,.25); color:#93c5fd; }
    .badge-free { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.2); color:#6ee7b7; }
    .accuracy-strip { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1.25rem 1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:2rem; }
    .accuracy-pct { font-size:2.5rem; font-weight:900; line-height:1; }
    .accuracy-pct.good { color:#6ee7b7; } .accuracy-pct.ok { color:#fcd34d; } .accuracy-pct.low { color:#fca5a5; }
    .accuracy-label { font-size:.78rem; color:var(--text-dim); line-height:1.6; }
    .accuracy-label strong { color:var(--text); display:block; font-size:.85rem; }
    .picks-section { padding:2.5rem 0 4rem; }
    .picks-grid { display:flex; flex-direction:column; gap:1.5rem; }
    .pick-card { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; transition:border-color 200ms, transform 200ms; }
    .pick-card:hover { border-color:rgba(59,130,246,.3); transform:translateY(-2px); }
    .pick-card.result-win  { border-color:rgba(16,185,129,.35); }
    .pick-card.result-loss { border-color:rgba(239,68,68,.3); }
    .pick-card-top { padding:1.5rem 1.75rem 0; }
    .pick-card-rank { display:inline-flex; align-items:center; gap:.4rem; padding:3px 10px; border-radius:999px; font-size:.7rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; margin-bottom:1rem; }
    .rank-1 { background:rgba(59,130,246,.15); border:1px solid rgba(59,130,246,.3); color:#93c5fd; }
    .rank-other { background:rgba(107,114,128,.1); border:1px solid rgba(107,114,128,.2); color:#9ca3af; }
    .pick-league-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:.6rem; }
    .pick-league-name { font-size:.73rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; }
    .pick-time { font-size:.73rem; font-weight:700; color:var(--text-dim); }
    .pick-match-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
    .pick-team { font-size:1.2rem; font-weight:800; color:#fff; line-height:1.2; flex:1; }
    .pick-team.away { text-align:right; }
    .pick-vs { font-size:.78rem; font-weight:800; color:var(--text-dim); padding:0 .5rem; flex-shrink:0; }
    .pick-callout { background:linear-gradient(135deg,rgba(59,130,246,.12) 0%,rgba(59,130,246,.06) 100%); border:1px solid rgba(59,130,246,.25); border-radius:10px; padding:1.1rem 1.4rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
    .pick-callout.callout-win  { background:linear-gradient(135deg,rgba(16,185,129,.12),rgba(16,185,129,.05)); border-color:rgba(16,185,129,.3); }
    .pick-callout.callout-loss { background:linear-gradient(135deg,rgba(239,68,68,.1),rgba(239,68,68,.04)); border-color:rgba(239,68,68,.25); }
    .pick-callout-label { font-size:.68rem; font-weight:700; color:rgba(147,197,253,.6); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.25rem; }
    .pick-callout-value { font-size:1.1rem; font-weight:900; color:#93c5fd; letter-spacing:-.01em; }
    .callout-win .pick-callout-label { color:rgba(110,231,183,.6); } .callout-win .pick-callout-value { color:#6ee7b7; }
    .callout-loss .pick-callout-label { color:rgba(252,165,165,.6); } .callout-loss .pick-callout-value { color:#fca5a5; }
    .result-badge { display:inline-flex; align-items:center; gap:.4rem; padding:6px 14px; border-radius:999px; font-size:.8rem; font-weight:800; flex-shrink:0; }
    .result-win-badge  { background:rgba(16,185,129,.15); border:1px solid rgba(16,185,129,.3); color:#6ee7b7; }
    .result-loss-badge { background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.25); color:#fca5a5; }
    .prob-row { display:flex; gap:1rem; margin-bottom:1.25rem; padding:0 1.75rem; }
    .prob-box { flex:1; background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:.75rem 1rem; }
    .prob-box.active { border-color:rgba(59,130,246,.35); background:rgba(59,130,246,.06); }
    .prob-box-label { font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:var(--text-dim); margin-bottom:.2rem; }
    .prob-box-val { font-size:1.1rem; font-weight:900; color:#fff; }
    .prob-box.active .prob-box-val { color:#93c5fd; }
    .pick-card-footer { padding:.9rem 1.75rem; border-top:1px solid var(--border); display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
    .footer-tag { font-size:.72rem; font-weight:700; color:var(--text-dim); }
    .dc-tag { display:inline-flex; align-items:center; gap:.3rem; padding:2px 9px; border-radius:999px; font-size:.67rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; border:1px solid; }
    .tag-1x { background:rgba(59,130,246,.1); border-color:rgba(59,130,246,.3); color:#93c5fd; }
    .tag-2x { background:rgba(16,185,129,.1); border-color:rgba(16,185,129,.3); color:#6ee7b7; }
    .empty-state { text-align:center; padding:4rem 1rem; }
    .empty-state h3 { font-size:1.1rem; font-weight:800; color:var(--text); margin-bottom:.5rem; }
    .empty-state p  { font-size:.88rem; color:var(--text-dim); line-height:1.7; max-width:380px; margin:0 auto; }
    .how-it-works { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:2rem; margin-top:2.5rem; }
    .hiw-title { font-size:1rem; font-weight:800; color:#fff; margin-bottom:1.25rem; }
    .hiw-steps { display:flex; flex-direction:column; gap:1rem; }
    .hiw-step { display:flex; gap:1rem; align-items:flex-start; }
    .hiw-num { width:28px; height:28px; border-radius:50%; background:rgba(59,130,246,.15); border:1px solid rgba(59,130,246,.3); color:#93c5fd; font-size:.75rem; font-weight:900; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
    .hiw-text { font-size:.85rem; color:var(--text-dim); line-height:1.65; }
    .hiw-text strong { color:var(--text); }
    .live-score { font-size:.85rem; font-weight:800; color:#fcd34d; padding:2px 10px; background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.25); border-radius:999px; }
    .live-dot { width:7px; height:7px; border-radius:50%; background:#ef4444; animation:blink 1s infinite; display:inline-block; margin-right:3px; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    @media(max-width:480px) {
        .pick-callout { flex-wrap:wrap; }
        .pick-team { font-size:1rem; }
        .prob-row { flex-wrap:wrap; }
        .prob-box { min-width:120px; }
    }
</style>
@endpush

@section('content')

<section class="picks-hero">
    <div class="wrap">
        <div class="picks-eyebrow">🎯 AI Double Chance Picks</div>
        <h1 class="picks-title">Double Chance: <span class="accent">1X & 2X</span></h1>
        <p class="picks-subtitle">Our AI finds the 5 safest Double Chance bets daily — matches where a team is very unlikely to lose. <strong>1X</strong> = Home Win or Draw. <strong>2X</strong> = Away Win or Draw.</p>
        <div class="picks-meta">
            <span class="picks-badge badge-blue">5 Picks Daily</span>
            <span class="picks-badge badge-blue">AI Powered</span>
            <span class="picks-badge badge-free">Free</span>
        </div>
    </div>
</section>

<section class="picks-section">
    <div class="wrap">

        @include('partials.date-nav')

        {{-- Accuracy strip --}}
        @if($accuracy['total'] > 0)
        <div class="accuracy-strip">
            <div>
                @php
                    $pct = $accuracy['pct'];
                    $cls = $pct >= 70 ? 'good' : ($pct >= 55 ? 'ok' : 'low');
                @endphp
                <div class="accuracy-pct {{ $cls }}">{{ $pct }}%</div>
                <div class="accuracy-label">
                    <strong>7-Day Accuracy</strong>
                    {{ $accuracy['correct'] }} wins from {{ $accuracy['total'] }} resolved picks
                </div>
            </div>
            <div style="font-size:.78rem;color:var(--text-dim);max-width:220px;text-align:right;line-height:1.6;">
                Based on notified picks settled in the last 7 days
            </div>
        </div>
        @endif

        {{-- Picks grid --}}
        @if($formatted->isEmpty() && $dateMeta['is_today'] && ($offWindow['reason'] ?? null) === 'off_window')
        @include('partials.off-season-empty', ['resumeDate' => $offWindow['resume_date'] ?? null])
        @elseif($formatted->isEmpty())
        <div class="empty-state">
            <h3>No Double Chance Picks Yet</h3>
            <p>Today's picks are generated when predictions load. Check back shortly or refresh the page.</p>
        </div>
        @else
        <div class="picks-grid">
            @foreach($formatted as $pick)
            @php
                $label    = $pick['label'];       // '1X' or '2X'
                $won      = $pick['was_correct'];
                $isFt     = in_array($pick['match']['status'], ['FT','AET','PEN']);
                $isLive   = in_array($pick['match']['status'], ['1H','2H','HT','ET','BT','P','LIVE']);
                $cardCls  = $isFt ? ($won ? 'result-win' : 'result-loss') : '';
                $tagCls   = $label === '1X' ? 'tag-1x' : 'tag-2x';
                $winDesc  = $label === '1X' ? 'Home Win or Draw' : 'Away Win or Draw';
                $elapsed  = $pick['match']['elapsed'];
            @endphp
            <div class="pick-card {{ $cardCls }}">
                <div class="pick-card-top">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                        <span class="pick-card-rank {{ $pick['rank'] == 1 ? 'rank-1' : 'rank-other' }}">
                            {{ $pick['rank'] == 1 ? '👑 Top Pick' : '#' . $pick['rank'] }}
                        </span>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <span class="dc-tag {{ $tagCls }}">{{ $label }}: {{ $winDesc }}</span>
                            @if($isLive)
                                <span class="live-score"><span class="live-dot"></span>LIVE {{ $pick['live_score'] ?? '' }} {{ $elapsed ? $elapsed . "'" : '' }}</span>
                            @elseif($isFt && $pick['live_score'])
                                <span class="live-score" style="color:#9ca3af;background:rgba(107,114,128,.1);border-color:rgba(107,114,128,.2);">FT {{ $pick['live_score'] }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="pick-league-row">
                        <span class="pick-league-name">{{ $pick['match']['league'] ?: 'Football' }}</span>
                        <span class="pick-time">{{ $pick['match']['time'] }}</span>
                    </div>

                    <div class="pick-match-row">
                        <div class="pick-team">{{ $pick['match']['home'] }}</div>
                        <div class="pick-vs">vs</div>
                        <div class="pick-team away">{{ $pick['match']['away'] }}</div>
                    </div>

                    @php
                        $calloutCls = $isFt ? ($won ? 'callout-win' : 'callout-loss') : '';
                    @endphp
                    <div class="pick-callout {{ $calloutCls }}">
                        <div>
                            <div class="pick-callout-label">Our Pick</div>
                            <div class="pick-callout-value">
                                {{ $label }} — {{ $winDesc }}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            @if($isFt)
                                @if($won)
                                    <span class="result-badge result-win-badge">✅ Won</span>
                                @else
                                    <span class="result-badge result-loss-badge">❌ Lost</span>
                                @endif
                            @else
                                <div style="font-size:1.4rem;font-weight:900;color:#93c5fd;line-height:1;">{{ $pick['prob'] }}%</div>
                                <div style="font-size:.68rem;color:var(--text-dim);margin-top:.15rem;">Model confidence</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- 1X / 2X probability boxes --}}
                <div class="prob-row">
                    <div class="prob-box {{ $label === '1X' ? 'active' : '' }}">
                        <div class="prob-box-label">1X (Home/Draw)</div>
                        <div class="prob-box-val">{{ $pick['dc1x'] }}%</div>
                    </div>
                    <div class="prob-box {{ $label === '2X' ? 'active' : '' }}">
                        <div class="prob-box-label">2X (Away/Draw)</div>
                        <div class="prob-box-val">{{ $pick['dc2x'] }}%</div>
                    </div>
                </div>

                <div class="pick-card-footer">
                    <span class="footer-tag">We win if: <strong style="color:var(--text);">{{ $winDesc }}</strong></span>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- How it works --}}
        <div class="how-it-works">
            <div class="hiw-title">How Double Chance Works</div>
            <div class="hiw-steps">
                <div class="hiw-step">
                    <div class="hiw-num">1</div>
                    <div class="hiw-text"><strong>Two markets, two outcomes each.</strong> <strong>1X</strong> covers Home Win or Draw — you lose only if the away team wins. <strong>2X</strong> covers Away Win or Draw — you lose only if the home team wins.</div>
                </div>
                <div class="hiw-step">
                    <div class="hiw-num">2</div>
                    <div class="hiw-text"><strong>Poisson probability engine.</strong> Our model simulates every possible scoreline and combines the matching probabilities. A 1X of 80% means our model puts only a 20% chance on the away team winning outright.</div>
                </div>
                <div class="hiw-step">
                    <div class="hiw-num">3</div>
                    <div class="hiw-text"><strong>Top 5 selected daily.</strong> From all today's matches we pick the 5 with the highest Double Chance confidence (≥ 72%), prioritising top European leagues for quality.</div>
                </div>
                <div class="hiw-step">
                    <div class="hiw-num">4</div>
                    <div class="hiw-text"><strong>Outcomes resolved automatically.</strong> Once a match ends we check the result and update the win/loss status in real time.</div>
                </div>
            </div>
        </div>

    </div>
</section>

@endsection
