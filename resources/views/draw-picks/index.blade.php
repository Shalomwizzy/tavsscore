@extends('layouts.app')
@section('title', "Today's Draw Picks – Triple AI Verified | TavsScore")
@section('meta_description', 'Free daily draw predictions where all three independent AI engines agree on a draw. Only the strongest draw picks make the cut — triple-validated, published every morning.')
@section('og_title', "Today's Draw Picks — Triple AI Agreed")
@section('og_description', 'Three independent AI engines must all predict a draw for a pick to appear here. Free daily draw predictions from TavsScore.')
@section('og_image', asset('images/og-draw-picks.jpg'))
@section('canonical', url('/draw-picks'))

@push('styles')
<style>
    .picks-hero {
        padding: 3.5rem 0 2.5rem;
        background:
            radial-gradient(ellipse 70% 60% at 50% -10%, rgba(245,158,11,.10), transparent),
            radial-gradient(ellipse 50% 40% at 90% 80%, rgba(16,185,129,.07), transparent);
        border-bottom: 1px solid var(--border);
    }
    .picks-eyebrow {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: 3px 12px; border-radius: 999px;
        background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.28);
        color: #fcd34d; font-size: .72rem; font-weight: 700;
        letter-spacing: .05em; text-transform: uppercase; margin-bottom: 1.1rem;
    }
    .picks-title { font-size: clamp(1.8rem, 5vw, 2.8rem); font-weight: 900; color: #fff; letter-spacing: -.03em; line-height: 1.1; margin-bottom: .75rem; }
    .picks-title .accent { color: #fcd34d; }
    .picks-subtitle { font-size: .95rem; color: var(--text-dim); line-height: 1.7; max-width: 520px; margin-bottom: 1.5rem; }
    .picks-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
    .picks-badge { display: inline-flex; align-items: center; gap: .4rem; padding: 4px 12px; border-radius: 999px; font-size: .73rem; font-weight: 700; }
    .badge-source { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.2); color: #6ee7b7; }
    .badge-free   { background: rgba(59,130,246,.1);  border: 1px solid rgba(59,130,246,.25); color: #93c5fd; }

    .accuracy-strip {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 14px; padding: 1.25rem 1.5rem;
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;
    }
    .accuracy-pct { font-size: 2.5rem; font-weight: 900; line-height: 1; }
    .accuracy-pct.good { color: #6ee7b7; }
    .accuracy-pct.ok   { color: #fcd34d; }
    .accuracy-pct.low  { color: #fca5a5; }
    .accuracy-pct.none { color: var(--text-dim); }
    .accuracy-label { font-size: .78rem; color: var(--text-dim); line-height: 1.6; }
    .accuracy-label strong { color: var(--text); display: block; font-size: .85rem; }

    .picks-section { padding: 2.5rem 0 4rem; }
    .picks-grid    { display: flex; flex-direction: column; gap: 1.5rem; }

    .pick-card {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 16px; overflow: hidden;
        transition: border-color 200ms, transform 200ms;
    }
    .pick-card:hover { border-color: rgba(245,158,11,.3); transform: translateY(-2px); }
    .pick-card.result-win  { border-color: rgba(16,185,129,.35); }
    .pick-card.result-loss { border-color: rgba(239,68,68,.3); }

    .pick-card-top { padding: 1.5rem 1.75rem 0; }

    .pick-card-rank {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: 3px 10px; border-radius: 999px; font-size: .7rem;
        font-weight: 800; letter-spacing: .04em; text-transform: uppercase;
        margin-bottom: 1.1rem;
    }
    .rank-1 { background: rgba(245,158,11,.15); border: 1px solid rgba(245,158,11,.3);  color: #fcd34d; }
    .rank-2 { background: rgba(148,163,184,.1); border: 1px solid rgba(148,163,184,.2); color: #94a3b8; }
    .rank-3 { background: rgba(180,120,60,.12); border: 1px solid rgba(180,120,60,.25); color: #c4895a; }
    .rank-other { background: rgba(107,114,128,.1); border: 1px solid rgba(107,114,128,.2); color: #9ca3af; }

    .pick-league-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: .6rem; }
    .pick-league-name { font-size: .73rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: .05em; }
    .pick-time        { font-size: .73rem; font-weight: 700; color: var(--text-dim); }

    .pick-match-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
    .pick-team      { font-size: 1.2rem; font-weight: 800; color: #fff; line-height: 1.2; flex: 1; }
    .pick-team.away { text-align: right; }
    .pick-vs        { font-size: .78rem; font-weight: 800; color: var(--text-dim); padding: 0 .5rem; flex-shrink: 0; }

    .pick-callout {
        background: linear-gradient(135deg, rgba(245,158,11,.12) 0%, rgba(245,158,11,.06) 100%);
        border: 1px solid rgba(245,158,11,.25); border-radius: 10px;
        padding: 1.1rem 1.4rem;
        display: flex; align-items: center; justify-content: space-between;
        gap: 1rem; margin-bottom: 1.25rem;
    }
    .pick-callout.callout-win  { background: linear-gradient(135deg, rgba(16,185,129,.12), rgba(16,185,129,.05)); border-color: rgba(16,185,129,.3); }
    .pick-callout.callout-loss { background: linear-gradient(135deg, rgba(239,68,68,.1), rgba(239,68,68,.04)); border-color: rgba(239,68,68,.25); }

    .pick-callout-label { font-size: .68rem; font-weight: 700; color: rgba(252,211,77,.6); text-transform: uppercase; letter-spacing: .06em; margin-bottom: .25rem; }
    .pick-callout-value { font-size: 1.25rem; font-weight: 900; color: #fcd34d; letter-spacing: -.01em; }
    .callout-win  .pick-callout-label { color: rgba(110,231,183,.6); }
    .callout-win  .pick-callout-value { color: #6ee7b7; }
    .callout-loss .pick-callout-label { color: rgba(252,165,165,.6); }
    .callout-loss .pick-callout-value { color: #fca5a5; }

    .result-badge { display: inline-flex; align-items: center; gap: .4rem; padding: 6px 14px; border-radius: 999px; font-size: .8rem; font-weight: 800; flex-shrink: 0; }
    .result-win-badge     { background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.3); color: #6ee7b7; }
    .result-loss-badge    { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.25); color: #fca5a5; }
    .result-pending-badge { background: rgba(107,114,128,.12); border: 1px solid rgba(107,114,128,.2); color: #64748b; }

    .prob-section { padding: 0 1.75rem; margin-bottom: 1.25rem; }
    .prob-bar-outer { height: 8px; border-radius: 999px; background: var(--surface); border: 1px solid var(--border); overflow: hidden; display: flex; margin-bottom: .45rem; }
    .prob-seg-home { background: #10b981; }
    .prob-seg-draw { background: #f59e0b; }
    .prob-seg-away { background: #3b82f6; }
    .prob-labels   { display: flex; justify-content: space-between; font-size: .68rem; font-weight: 700; color: var(--text-dim); }
    .prob-labels span:nth-child(1) { color: #6ee7b7; }
    .prob-labels span:nth-child(2) { color: #fcd34d; }
    .prob-labels span:nth-child(3) { color: #93c5fd; text-align: right; }

    .pick-analysis-section { padding: 0 1.75rem; margin-bottom: 1.25rem; }
    .pick-analysis-header  { font-size: .68rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: .06em; margin-bottom: .6rem; }
    .pick-analysis-text    { font-size: .85rem; color: var(--text-dim); line-height: 1.75; }

    .pick-ai-badge {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: 3px 10px; border-radius: 999px; font-size: .67rem; font-weight: 700;
        background: rgba(16,185,129,.09); border: 1px solid rgba(16,185,129,.2); color: #6ee7b7;
        margin-bottom: .75rem; text-transform: uppercase; letter-spacing: .04em;
    }

    .pick-card-footer {
        padding: .9rem 1.75rem; border-top: 1px solid var(--border);
        display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
    }

    .empty-state { text-align: center; padding: 4rem 1rem; }
    .empty-state h3 { font-size: 1.1rem; font-weight: 800; color: var(--text); margin-bottom: .5rem; }
    .empty-state p  { font-size: .88rem; color: var(--text-dim); line-height: 1.7; max-width: 380px; margin: 0 auto; }

    .how-it-works {
        background: var(--card); border: 1px solid var(--border); border-radius: 16px;
        padding: 2rem; margin-top: 2.5rem;
    }
    .hiw-title { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: 1.25rem; }
    .hiw-steps { display: flex; flex-direction: column; gap: 1rem; }
    .hiw-step  { display: flex; gap: 1rem; align-items: flex-start; }
    .hiw-num   { width: 28px; height: 28px; border-radius: 50%; background: rgba(245,158,11,.15); border: 1px solid rgba(245,158,11,.3); color: #fcd34d; font-size: .75rem; font-weight: 900; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
    .hiw-text  { font-size: .85rem; color: var(--text-dim); line-height: 1.65; }
    .hiw-text strong { color: var(--text); }
</style>
@endpush

@section('content')

{{-- Hero --}}
<section class="picks-hero">
    <div class="wrap">
        <div class="picks-eyebrow">🤝 Draw Picks</div>
        <h1 class="picks-title">Today's <span class="accent">Draw Picks</span></h1>
        <p class="picks-subtitle">
            Three independent AI engines each analyse the match data separately. A pick only appears here when <strong style="color:var(--text);">all three independently predict a draw</strong> — no groupthink, strict triple agreement.
        </p>
        <div class="picks-meta">
            <span class="picks-badge badge-source">🤖 Triple AI Verified</span>
            <span class="picks-badge badge-free">🔓 Free Forever</span>
        </div>
    </div>
</section>

{{-- Main --}}
<section class="picks-section">
    <div class="wrap">

        {{-- Accuracy strip --}}
        @if($accuracy['total'] > 0)
        <div class="accuracy-strip">
            <div style="display:flex; align-items:center; gap:1.1rem;">
                @php
                    $pct = $accuracy['pct'];
                    $cls = $pct >= 55 ? 'good' : ($pct >= 45 ? 'ok' : 'low');
                @endphp
                <div class="accuracy-pct {{ $cls }}">{{ $pct }}%</div>
                <div>
                    <div class="accuracy-label">
                        <strong>7-Day Draw Accuracy</strong>
                        {{ $accuracy['correct'] }}/{{ $accuracy['total'] }} picks correct
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Picks grid --}}
        <div class="picks-grid">
            @forelse($formatted as $pick)
            @php
                $resultClass = $pick['was_correct'] === true ? 'result-win' : ($pick['was_correct'] === false ? 'result-loss' : '');
                $calloutClass = $pick['was_correct'] === true ? 'callout-win' : ($pick['was_correct'] === false ? 'callout-loss' : '');
                $rankClass = match($pick['rank']) { 1 => 'rank-1', 2 => 'rank-2', 3 => 'rank-3', default => 'rank-other' };
                $rankLabel = match($pick['rank']) { 1 => '👑 Best Draw Pick', 2 => '⚖️ Draw #2', 3 => '⚖️ Draw #3', default => '⚖️ Draw #' . $pick['rank'] };
                $tips = $pick['tips'] ?? [];
                $geminiAgrees = $tips[0]['gemini_agrees'] ?? null;
            @endphp
            <div class="pick-card {{ $resultClass }}">
                <div class="pick-card-top">

                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem;">
                        <span class="pick-card-rank {{ $rankClass }}">{{ $rankLabel }}</span>
                        @if($pick['is_ai'])
                        <span class="pick-ai-badge">✅ Triple AI Agreement</span>
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

                    <div class="pick-match-row">
                        <div class="pick-team">{{ $pick['match']['home'] }}</div>
                        <div class="pick-vs">vs</div>
                        <div class="pick-team away">{{ $pick['match']['away'] }}</div>
                    </div>

                    <div class="pick-callout {{ $calloutClass }}">
                        <div>
                            <div class="pick-callout-label">Our prediction</div>
                            <div class="pick-callout-value">🤝 Match Ends in a Draw</div>
                        </div>
                        <div style="text-align:right;">
                            @if($pick['was_correct'] === true)
                                <span class="result-badge result-win-badge">✅ Won</span>
                            @elseif($pick['was_correct'] === false)
                                <span class="result-badge result-loss-badge">❌ Lost</span>
                            @else
                                @if($pick['confidence_pct'])
                                <div style="font-size:.7rem; color:rgba(252,211,77,.6); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.2rem;">Confidence</div>
                                <div style="font-size:1.4rem; font-weight:900; color:#fcd34d;">{{ $pick['confidence_pct'] }}%</div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Probability bar --}}
                @if($pick['hw'] || $pick['d'] || $pick['aw'])
                <div class="prob-section">
                    <div class="prob-bar-outer">
                        <div class="prob-seg-home" style="width:{{ $pick['hw'] }}%;"></div>
                        <div class="prob-seg-draw" style="width:{{ $pick['d'] }}%;"></div>
                        <div class="prob-seg-away" style="width:{{ $pick['aw'] }}%;"></div>
                    </div>
                    <div class="prob-labels">
                        <span>H {{ round($pick['hw']) }}%</span>
                        <span>D {{ round($pick['d']) }}%</span>
                        <span>A {{ round($pick['aw']) }}%</span>
                    </div>
                </div>
                @endif

                {{-- AI Analysis --}}
                @if($pick['is_ai'] && $pick['analysis'])
                <div class="pick-analysis-section">
                    <div class="pick-analysis-header">AI Analysis</div>
                    <div class="pick-analysis-text">
                        {{ Str::limit($pick['analysis'], 320) }}
                    </div>
                </div>
                @endif

                <div class="pick-card-footer">
                    @if($geminiAgrees === true)
                    <span style="font-size:.7rem; color:#6ee7b7; font-weight:700;">✅ AI #1 · AI #2 · AI #3 — all agreed</span>
                    @endif
                    @if($pick['confidence_pct'] && $pick['was_correct'] === null)
                    <span style="font-size:.7rem; color:var(--text-dim); margin-left:auto;">{{ $pick['confidence_pct'] }}% confidence</span>
                    @endif
                </div>
            </div>
            @empty
            <div class="empty-state">
                <div style="font-size:2.5rem; margin-bottom:1rem;">🤝</div>
                <h3>No Draw Picks Yet Today</h3>
                <p>Our three AI engines analyse every match independently. Draw picks only appear when all three reach the same conclusion — that standard takes time to meet. Check back later today.</p>
            </div>
            @endforelse
        </div>

        {{-- How it works --}}
        <div class="how-it-works">
            <div class="hiw-title">How Draw Picks Work</div>
            <div class="hiw-steps">
                <div class="hiw-step">
                    <div class="hiw-num">1</div>
                    <div class="hiw-text"><strong>Three AIs analyse independently</strong> — each engine receives only raw match data and stats. They never see each other's output.</div>
                </div>
                <div class="hiw-step">
                    <div class="hiw-num">2</div>
                    <div class="hiw-text"><strong>All three must predict a draw</strong> — if even one AI disagrees or returns below 60% confidence, the match is excluded entirely.</div>
                </div>
                <div class="hiw-step">
                    <div class="hiw-num">3</div>
                    <div class="hiw-text"><strong>Top 5 published at 08:00 Lagos time</strong> — sorted by consensus confidence. Full track record visible on the <a href="{{ route('stats.index') }}" style="color:var(--green);">stats page</a>.</div>
                </div>
            </div>
        </div>

    </div>
</section>

<div class="wrap" style="padding-bottom:2rem;">
    <div style="text-align:center; font-size:.7rem; color:var(--text-dim); line-height:1.75; padding:.875rem 1rem; border-top:1px solid var(--border); margin-top:2rem;">
        🔞 <strong style="color:var(--text-dim);">18+ only.</strong>
        Draw Picks are for <strong style="color:var(--text-dim);">entertainment purposes only</strong> and do not constitute financial or betting advice.
        Triple-AI agreement increases signal strength but does not guarantee any outcome.
        Please gamble responsibly — never stake money you cannot afford to lose.
    </div>
</div>

@endsection
