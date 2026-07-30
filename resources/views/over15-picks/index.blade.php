@extends('layouts.app')
@section('title', "Today's Over 1.5 Goals Picks, AI Powered | TavsScore")
@section('meta_description', 'Free daily Over 1.5 Goals predictions backed by Poisson probability modelling. Only matches with 82%+ likelihood of 2 or more goals make the cut. 5 picks daily.')
@section('og_title', "Today's Over 1.5 Goals Picks")
@section('og_description', 'AI-powered Over 1.5 Goals picks. Only the highest-probability goal-heavy matches make the daily cut. 5 picks every morning.')
@section('og_image', asset('images/og-over15.jpg'))
@section('canonical', url('/over-1-5'))

@push('styles')
<style>
    .picks-hero { padding:3.5rem 0 2.5rem; background: radial-gradient(ellipse 70% 60% at 50% -10%, rgba(59,130,246,.10), transparent), radial-gradient(ellipse 50% 40% at 90% 80%, rgba(16,185,129,.07), transparent); border-bottom:1px solid var(--border); }
    .picks-eyebrow { display:inline-flex; align-items:center; gap:.45rem; padding:3px 12px; border-radius:999px; background:rgba(59,130,246,.12); border:1px solid rgba(59,130,246,.28); color:#93c5fd; font-size:.72rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; margin-bottom:1.1rem; }
    .picks-title { font-size:clamp(1.8rem,5vw,2.8rem); font-weight:900; color:#fff; letter-spacing:-.03em; line-height:1.1; margin-bottom:.75rem; }
    .picks-title .accent { color:#60a5fa; }
    .picks-subtitle { font-size:.95rem; color:var(--text-dim); line-height:1.7; max-width:520px; margin-bottom:1.5rem; }
    .picks-meta { display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; }
    .picks-badge { display:inline-flex; align-items:center; gap:.4rem; padding:4px 12px; border-radius:999px; font-size:.73rem; font-weight:700; }
    .badge-blue  { background:rgba(59,130,246,.1); border:1px solid rgba(59,130,246,.25); color:#93c5fd; }
    .badge-free  { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.2); color:#6ee7b7; }
    .accuracy-strip { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1.25rem 1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:2rem; }
    .accuracy-pct { font-size:2.5rem; font-weight:900; line-height:1; }
    .accuracy-pct.good { color:#6ee7b7; } .accuracy-pct.ok { color:#fcd34d; } .accuracy-pct.low { color:#fca5a5; }
    .accuracy-label { font-size:.78rem; color:var(--text-dim); line-height:1.6; }
    .accuracy-label strong { color:var(--text); display:block; font-size:.85rem; }
    .picks-section { padding:2.5rem 0 4rem; }
    .picks-grid { display:flex; flex-direction:column; gap:1.5rem; }
    .pick-card { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; transition:border-color 200ms, transform 200ms; }
    .pick-card:hover { border-color:rgba(59,130,246,.35); transform:translateY(-2px); }
    .pick-card.result-win  { border-color:rgba(16,185,129,.35); }
    .pick-card.result-loss { border-color:rgba(239,68,68,.3); }
    .pick-card-top { padding:1.5rem 1.75rem 0; }
    .pick-card-rank { display:inline-flex; align-items:center; gap:.4rem; padding:3px 10px; border-radius:999px; font-size:.7rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; margin-bottom:1.1rem; }
    .rank-1 { background:rgba(59,130,246,.15); border:1px solid rgba(59,130,246,.3); color:#93c5fd; }
    .rank-other { background:rgba(107,114,128,.1); border:1px solid rgba(107,114,128,.2); color:#9ca3af; }
    .pick-league-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:.6rem; }
    .pick-league-name { font-size:.73rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; }
    .pick-time { font-size:.73rem; font-weight:700; color:var(--text-dim); }
    .pick-match-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
    .pick-team { font-size:1.2rem; font-weight:800; color:#fff; line-height:1.2; flex:1; }
    .pick-team.away { text-align:right; }
    .pick-vs { font-size:.78rem; font-weight:800; color:var(--text-dim); padding:0 .5rem; flex-shrink:0; }
    .pick-callout { background:linear-gradient(135deg, rgba(59,130,246,.12) 0%, rgba(59,130,246,.06) 100%); border:1px solid rgba(59,130,246,.25); border-radius:10px; padding:1.1rem 1.4rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
    .pick-callout.callout-win  { background:linear-gradient(135deg,rgba(16,185,129,.12),rgba(16,185,129,.05)); border-color:rgba(16,185,129,.3); }
    .pick-callout.callout-loss { background:linear-gradient(135deg,rgba(239,68,68,.1),rgba(239,68,68,.04)); border-color:rgba(239,68,68,.25); }
    .pick-callout-label { font-size:.68rem; font-weight:700; color:rgba(147,197,253,.6); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.25rem; }
    .pick-callout-value { font-size:1.25rem; font-weight:900; color:#93c5fd; letter-spacing:-.01em; }
    .callout-win .pick-callout-label { color:rgba(110,231,183,.6); } .callout-win .pick-callout-value { color:#6ee7b7; }
    .callout-loss .pick-callout-label { color:rgba(252,165,165,.6); } .callout-loss .pick-callout-value { color:#fca5a5; }
    .result-badge { display:inline-flex; align-items:center; gap:.4rem; padding:6px 14px; border-radius:999px; font-size:.8rem; font-weight:800; flex-shrink:0; }
    .result-win-badge  { background:rgba(16,185,129,.15); border:1px solid rgba(16,185,129,.3); color:#6ee7b7; }
    .result-loss-badge { background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.25); color:#fca5a5; }
    .prob-bar-outer { height:8px; border-radius:999px; background:var(--surface); border:1px solid var(--border); overflow:hidden; margin-bottom:.45rem; }
    .prob-bar-fill { height:100%; border-radius:999px; }
    .pick-analysis-section { padding:0 1.75rem; margin-bottom:1.25rem; }
    .pick-analysis-header { font-size:.68rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.6rem; }
    .pick-analysis-text { font-size:.85rem; color:var(--text-dim); line-height:1.75; }
    .pick-card-footer { padding:.9rem 1.75rem; border-top:1px solid var(--border); display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
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
    .prob-bars-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(85px,1fr)); gap:.75rem; }
    @media(max-width:480px) {
        .pick-callout { flex-wrap:wrap; }
        .pick-team { font-size:1rem; }
    }
</style>
@endpush

@section('content')

<section class="picks-hero">
    <div class="wrap">
        <div class="picks-eyebrow">⚽ Over 1.5 Goals</div>
        <h1 class="picks-title">Today's <span class="accent">Over 1.5</span> Picks</h1>
        <p class="picks-subtitle">
            Matches where our Poisson model gives <strong style="color:var(--text);">82% or higher probability</strong> of seeing 2 or more goals. Five picks every morning, sorted by confidence.
        </p>
        <div class="picks-meta">
            <span class="picks-badge badge-blue">📊 Poisson Modelled</span>
            <span class="picks-badge badge-free">🔓 Free Forever</span>
        </div>
    </div>
</section>

<section class="picks-section">
    <div class="wrap">

        @include('partials.date-nav')

        @if($accuracy['total'] > 0)
        <div class="accuracy-strip">
            <div style="display:flex; align-items:center; gap:1.1rem;">
                @php $pct = $accuracy['pct']; $cls = $pct >= 75 ? 'good' : ($pct >= 60 ? 'ok' : 'low'); @endphp
                <div class="accuracy-pct {{ $cls }}">{{ $pct }}%</div>
                <div class="accuracy-label">
                    <strong>7-Day Over 1.5 Accuracy</strong>
                    {{ $accuracy['correct'] }}/{{ $accuracy['total'] }} picks landed
                </div>
            </div>
        </div>
        @endif

        <div class="picks-grid">
            @forelse($formatted as $pick)
            @php
                $resultClass  = $pick['was_correct'] === true ? 'result-win' : ($pick['was_correct'] === false ? 'result-loss' : '');
                $calloutClass = $pick['was_correct'] === true ? 'callout-win' : ($pick['was_correct'] === false ? 'callout-loss' : '');
                $rankClass    = $pick['rank'] == 1 ? 'rank-1' : 'rank-other';
                $rankLabel    = $pick['rank'] == 1 ? '👑 Best Pick' : '⚽ Pick #' . $pick['rank'];
            @endphp
            <div class="pick-card {{ $resultClass }}">
                <div class="pick-card-top">

                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem;">
                        <span class="pick-card-rank {{ $rankClass }}">{{ $rankLabel }}</span>
                        @if($pick['is_ai'])
                        <span style="display:inline-flex; align-items:center; gap:.35rem; padding:3px 10px; border-radius:999px; font-size:.67rem; font-weight:700; background:rgba(59,130,246,.09); border:1px solid rgba(59,130,246,.2); color:#93c5fd; text-transform:uppercase; letter-spacing:.04em;">✅ AI Analysed</span>
                        @endif
                    </div>

                    <div class="pick-league-row">
                        <span class="pick-league-name">{{ $pick['match']['league'] ?? '' }}</span>
                        <span class="pick-time">
                            @if($pick['live_score'] !== null)
                                @php $st = $pick['match']['status']; @endphp
                                @if(in_array($st, ['1H','2H','ET','P','LIVE']))
                                    <span style="color:#f87171; font-weight:800;">LIVE {{ $pick['live_score'] }}</span>
                                @elseif($st === 'HT')
                                    <span style="color:#fcd34d;">HT {{ $pick['live_score'] }}</span>
                                @else
                                    <span style="color:var(--text-dim);">FT {{ $pick['live_score'] }}</span>
                                @endif
                            @else
                                {{ $pick['match']['time'] ?? '' }}
                            @endif
                        </span>
                    </div>

                    @include('partials.fixture-showcase', ['predictionId' => $pick['id'], 'accent' => '#60a5fa'])

                    <div class="pick-callout {{ $calloutClass }}">
                        <div>
                            <div class="pick-callout-label">Our prediction</div>
                            <div class="pick-callout-value">⚽ Over 1.5 Goals: YES</div>
                        </div>
                        <div style="text-align:right;">
                            @if($pick['was_correct'] === true)
                                <span class="result-badge result-win-badge">✅ Won</span>
                            @elseif($pick['was_correct'] === false)
                                <span class="result-badge result-loss-badge">❌ Lost</span>
                            @else
                                <div style="font-size:.7rem; color:rgba(147,197,253,.6); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.2rem;">Probability</div>
                                <div style="font-size:1.4rem; font-weight:900; color:#93c5fd;">{{ $pick['prob'] }}%</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Probability bar --}}
                <div style="padding:0 1.75rem; margin-bottom:1.25rem;">
                    <div style="font-size:.68rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.5rem;">Goals probability breakdown</div>
                    <div class="prob-bars-grid">
                        <div>
                            <div style="font-size:.7rem; color:var(--text-dim); margin-bottom:.3rem;">Over 1.5</div>
                            <div class="prob-bar-outer"><div class="prob-bar-fill" style="width:{{ $pick['prob'] }}%; background:#3b82f6;"></div></div>
                            <div style="font-size:.72rem; font-weight:800; color:#93c5fd;">{{ $pick['prob'] }}%</div>
                        </div>
                        <div>
                            <div style="font-size:.7rem; color:var(--text-dim); margin-bottom:.3rem;">Over 2.5</div>
                            <div class="prob-bar-outer"><div class="prob-bar-fill" style="width:{{ $pick['over25_prob'] }}%; background:#f59e0b;"></div></div>
                            <div style="font-size:.72rem; font-weight:800; color:#fcd34d;">{{ $pick['over25_prob'] }}%</div>
                        </div>
                        <div>
                            <div style="font-size:.7rem; color:var(--text-dim); margin-bottom:.3rem;">BTTS</div>
                            <div class="prob-bar-outer"><div class="prob-bar-fill" style="width:{{ $pick['btts_prob'] }}%; background:#10b981;"></div></div>
                            <div style="font-size:.72rem; font-weight:800; color:#6ee7b7;">{{ $pick['btts_prob'] }}%</div>
                        </div>
                    </div>
                </div>

                @if($pick['is_ai'] && $pick['analysis'])
                <div class="pick-analysis-section">
                    <div class="pick-analysis-header">AI Analysis</div>
                    <div class="pick-analysis-text">{{ Str::limit($pick['analysis'], 280) }}</div>
                </div>
                @endif

                <div style="padding:0 1.75rem; margin-bottom:1.25rem;">@include('partials.match-intelligence', ['predictionId' => $pick['id'], 'accent' => '#60a5fa'])</div>

                <div class="pick-card-footer">
                    <span style="font-size:.7rem; color:var(--text-dim);">📊 Poisson model: {{ $pick['prob'] }}% chance of 2+ goals</span>
                </div>
            </div>
            @empty
            @if($dateMeta['is_today'] && ($offWindow['reason'] ?? null) === 'off_window')
            @include('partials.off-season-empty', ['resumeDate' => $offWindow['resume_date'] ?? null])
            @else
            <div class="empty-state">
                <div style="font-size:2.5rem; margin-bottom:1rem;">⚽</div>
                <h3>No Over 1.5 Picks Yet Today</h3>
                <p>We only publish matches where our Poisson model gives 82%+ probability of 2 or more goals. Check back after 08:00 Lagos time.</p>
            </div>
            @endif
            @endforelse
        </div>

        <div class="how-it-works">
            <div class="hiw-title">How Over 1.5 Picks Work</div>
            <div class="hiw-steps">
                <div class="hiw-step">
                    <div class="hiw-num">1</div>
                    <div class="hiw-text"><strong>Poisson distribution modelling</strong>: we compute expected goals (xG) for both teams from recent form, attack/defense strength, and head-to-head data.</div>
                </div>
                <div class="hiw-step">
                    <div class="hiw-num">2</div>
                    <div class="hiw-text"><strong>82% probability minimum</strong>: only matches where the model gives 82% or higher chance of 2+ goals make the cut. This filters out uncertain fixtures.</div>
                </div>
                <div class="hiw-step">
                    <div class="hiw-num">3</div>
                    <div class="hiw-text"><strong>5 picks published at 08:00 Lagos</strong>, sorted by probability, highest first. Track our record on the <a href="{{ route('stats.index') }}" style="color:#60a5fa;">stats page</a>.</div>
                </div>
            </div>
        </div>

    </div>
</section>

<div class="wrap" style="padding-bottom:2rem;">
    <div style="text-align:center; font-size:.7rem; color:var(--text-dim); line-height:1.75; padding:.875rem 1rem; border-top:1px solid var(--border); margin-top:2rem;">
        🔞 <strong style="color:var(--text-dim);">18+ only.</strong>
        Over 1.5 Goals picks are for <strong style="color:var(--text-dim);">entertainment purposes only</strong> and do not constitute financial or betting advice.
        High probability does not guarantee any outcome. Please gamble responsibly.
    </div>
</div>

@endsection
