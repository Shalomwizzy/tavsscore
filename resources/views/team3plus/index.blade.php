@extends('layouts.app')
@section('title', "A Team to Score 2+ or 3+ Goals: YES Picks | Daily AI Predictions | TavsScore")
@section('meta_description', 'Free daily AI YES picks for the "A Team to Score 2+" and "A Team to Score 3+" markets. Our Poisson model finds the team most likely to score big every day.')
@section('og_title', "Team 2+ or 3+ Goals YES Picks: Daily AI Predictions")
@section('og_description', 'AI predictions for "A Team to Score 2+" and "A Team to Score 3+" YES markets. We identify the team our model is most confident will hit the goal threshold. Free from TavsScore.')
@section('og_image', asset('images/og-team3plus.jpg'))
@section('canonical', url('/team-3-plus'))

@push('styles')
<style>
    .picks-hero { padding:3.5rem 0 2.5rem; background: radial-gradient(ellipse 70% 60% at 50% -10%, rgba(245,158,11,.10), transparent), radial-gradient(ellipse 50% 40% at 90% 80%, rgba(16,185,129,.07), transparent); border-bottom:1px solid var(--border); }
    .picks-eyebrow { display:inline-flex; align-items:center; gap:.45rem; padding:3px 12px; border-radius:999px; background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.28); color:#fcd34d; font-size:.72rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; margin-bottom:1.1rem; }
    .picks-title { font-size:clamp(1.8rem,5vw,2.8rem); font-weight:900; color:#fff; letter-spacing:-.03em; line-height:1.1; margin-bottom:.75rem; }
    .picks-title .accent { color:#f59e0b; }
    .picks-subtitle { font-size:.95rem; color:var(--text-dim); line-height:1.7; max-width:520px; margin-bottom:1.5rem; }
    .picks-meta { display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; }
    .picks-badge { display:inline-flex; align-items:center; gap:.4rem; padding:4px 12px; border-radius:999px; font-size:.73rem; font-weight:700; }
    .badge-amber { background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.25); color:#fcd34d; }
    .badge-free  { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.2); color:#6ee7b7; }
    .accuracy-strip { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1.25rem 1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:2rem; }
    .accuracy-pct { font-size:2.5rem; font-weight:900; line-height:1; }
    .accuracy-pct.good { color:#6ee7b7; } .accuracy-pct.ok { color:#fcd34d; } .accuracy-pct.low { color:#fca5a5; }
    .accuracy-label { font-size:.78rem; color:var(--text-dim); line-height:1.6; }
    .accuracy-label strong { color:var(--text); display:block; font-size:.85rem; }
    .picks-section { padding:2.5rem 0 4rem; }
    .picks-grid { display:flex; flex-direction:column; gap:1.5rem; }
    .pick-card { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; transition:border-color 200ms, transform 200ms; }
    .pick-card:hover { border-color:rgba(245,158,11,.3); transform:translateY(-2px); }
    .pick-card.result-win  { border-color:rgba(16,185,129,.35); }
    .pick-card.result-loss { border-color:rgba(239,68,68,.3); }
    .pick-card-top { padding:1.5rem 1.75rem 0; }
    .pick-card-rank { display:inline-flex; align-items:center; gap:.4rem; padding:3px 10px; border-radius:999px; font-size:.7rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; margin-bottom:1rem; }
    .rank-1     { background:rgba(245,158,11,.15); border:1px solid rgba(245,158,11,.3); color:#fcd34d; }
    .rank-other { background:rgba(107,114,128,.1);  border:1px solid rgba(107,114,128,.2); color:#9ca3af; }
    .pick-league-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:.6rem; }
    .pick-league-name { font-size:.73rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; }
    .pick-time { font-size:.73rem; font-weight:700; color:var(--text-dim); }
    .pick-match-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
    .pick-team { font-size:1.2rem; font-weight:800; color:#fff; line-height:1.2; flex:1; }
    .pick-team.away { text-align:right; }
    .pick-team.yes-team { color:#f59e0b; }
    .pick-vs { font-size:.78rem; font-weight:800; color:var(--text-dim); padding:0 .5rem; flex-shrink:0; }
    .pick-callout { background:linear-gradient(135deg,rgba(245,158,11,.12) 0%,rgba(245,158,11,.06) 100%); border:1px solid rgba(245,158,11,.25); border-radius:10px; padding:1.1rem 1.4rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
    .pick-callout.callout-win  { background:linear-gradient(135deg,rgba(16,185,129,.12),rgba(16,185,129,.05)); border-color:rgba(16,185,129,.3); }
    .pick-callout.callout-loss { background:linear-gradient(135deg,rgba(239,68,68,.1),rgba(239,68,68,.04)); border-color:rgba(239,68,68,.25); }
    .pick-callout-label { font-size:.68rem; font-weight:700; color:rgba(252,211,77,.6); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.25rem; }
    .pick-callout-value { font-size:1.1rem; font-weight:900; color:#fcd34d; letter-spacing:-.01em; }
    .callout-win .pick-callout-label  { color:rgba(110,231,183,.6); } .callout-win .pick-callout-value  { color:#6ee7b7; }
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
    .hiw-num { width:28px; height:28px; border-radius:50%; background:rgba(245,158,11,.15); border:1px solid rgba(245,158,11,.3); color:#fcd34d; font-size:.75rem; font-weight:900; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
    .hiw-text { font-size:.85rem; color:var(--text-dim); line-height:1.65; }
    .hiw-text strong { color:var(--text); }
    .market-info { background:rgba(245,158,11,.07); border:1px solid rgba(245,158,11,.18); border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:2rem; }
    .market-info p { font-size:.85rem; color:var(--text-dim); line-height:1.7; margin:0; }
    .market-info strong { color:#fcd34d; }
    .market-tag { display:inline-flex; align-items:center; gap:.3rem; padding:2px 9px; border-radius:999px; font-size:.67rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; border:1px solid; }
    .tag-3plus { background:rgba(245,158,11,.1); border-color:rgba(245,158,11,.3); color:#fcd34d; }
    .tag-2plus { background:rgba(16,185,129,.1); border-color:rgba(16,185,129,.3); color:#6ee7b7; }
</style>
@endpush

@section('content')

<section class="picks-hero">
    <div class="wrap">
        <div class="picks-eyebrow">⚽ Team Goals YES Picks</div>
        <h1 class="picks-title">A Team to Score <span class="accent">2+ or 3+</span> Goals: YES</h1>
        <p class="picks-subtitle">
            We predict <strong style="color:var(--text);">YES</strong> on the team our model is most confident will reach the goal threshold.
            Each day the AI identifies the most likely scoring threat between the <strong style="color:var(--text);">2+ Goals</strong> and <strong style="color:var(--text);">3+ Goals</strong> markets.
            The highlighted team is the one we back to score big.
        </p>
        <div class="picks-meta">
            <span class="picks-badge badge-amber">⚽ High-Scoring Pick</span>
            <span class="picks-badge badge-free">🔓 Free Forever</span>
        </div>
    </div>
</section>

<section class="picks-section">
    <div class="wrap">

        @include('partials.date-nav')

        <div class="market-info">
            <p>
                <strong>What is this market?</strong> "A Team to Score 2 or More Goals" and "A Team to Score 3 or More Goals" are YES/NO markets available at Bet365, 1xBet, SportyBet and most bookmakers under <em>Match Goals → A Team to Score 2+/3+</em>. <strong>We predict YES</strong> — backing the team our Poisson model says is most likely to reach the threshold. The AI picks the market (2+ or 3+) where our confidence is highest.
            </p>
        </div>

        @if($accuracy['total'] > 0)
        <div class="accuracy-strip">
            <div style="display:flex; align-items:center; gap:1.1rem;">
                @php $pct = $accuracy['pct']; $cls = $pct >= 75 ? 'good' : ($pct >= 60 ? 'ok' : 'low'); @endphp
                <div class="accuracy-pct {{ $cls }}">{{ $pct }}%</div>
                <div class="accuracy-label">
                    <strong>7-Day Team Goals YES Accuracy</strong>
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
                $isHome       = str_starts_with($pick['label'], 'Home');
                $market       = $pick['market'] ?? '3+';
                $tagClass     = $market === '3+' ? 'tag-3plus' : 'tag-2plus';
                $winDesc      = $market === '3+' ? 'scores 3 or more goals' : 'scores 2 or more goals';
            @endphp
            <div class="pick-card {{ $resultClass }}">
                <div class="pick-card-top">

                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem;">
                        <span class="pick-card-rank {{ $rankClass }}">{{ $rankLabel }}</span>
                        <span class="market-tag {{ $tagClass }}">⚽ YES: {{ $pick['team_name'] }} {{ $market }} Goals</span>
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

                    {{-- The YES team is highlighted in amber --}}
                    <div class="pick-match-row">
                        <div class="pick-team {{ $isHome ? 'yes-team' : '' }}">{{ $pick['match']['home'] }}</div>
                        <div class="pick-vs">vs</div>
                        <div class="pick-team away {{ !$isHome ? 'yes-team' : '' }}">{{ $pick['match']['away'] }}</div>
                    </div>

                    <div class="pick-callout {{ $calloutClass }}">
                        <div>
                            <div class="pick-callout-label">Our prediction</div>
                            <div class="pick-callout-value">⚽ {{ $pick['team_name'] }}: {{ $market }} Goals: YES</div>
                        </div>
                        <div style="text-align:right;">
                            @if($pick['was_correct'] === true)
                                <span class="result-badge result-win-badge">✅ Won</span>
                            @elseif($pick['was_correct'] === false)
                                <span class="result-badge result-loss-badge">❌ Lost</span>
                            @else
                                <div style="font-size:.7rem; color:rgba(252,211,77,.6); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.2rem;">{{ $market }} Probability</div>
                                <div style="font-size:1.4rem; font-weight:900; color:#fcd34d;">{{ $pick['prob'] }}%</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Per-team probability bars — use market-appropriate probs --}}
                @php
                    $homeProb = $market === '2+' ? $pick['home_2plus'] : $pick['home_3plus'];
                    $awayProb = $market === '2+' ? $pick['away_2plus'] : $pick['away_3plus'];
                @endphp
                <div style="padding:0 1.75rem; margin-bottom:1.25rem;">
                    <div style="font-size:.68rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.5rem;">{{ $market }} Goals probability per team (higher = better for YES)</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:.75rem;">
                        <div>
                            <div style="font-size:.7rem; color:var(--text-dim); margin-bottom:.3rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ Str::limit($pick['match']['home'], 12) }} {{ $market }}</div>
                            <div class="prob-bar-outer"><div class="prob-bar-fill" style="width:{{ min($homeProb, 100) }}%; background:{{ $isHome ? '#f59e0b' : '#6b7280' }};"></div></div>
                            <div style="font-size:.72rem; font-weight:800; color:{{ $isHome ? '#fcd34d' : '#9ca3af' }};">{{ $homeProb }}%</div>
                        </div>
                        <div>
                            <div style="font-size:.7rem; color:var(--text-dim); margin-bottom:.3rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ Str::limit($pick['match']['away'], 12) }} {{ $market }}</div>
                            <div class="prob-bar-outer"><div class="prob-bar-fill" style="width:{{ min($awayProb, 100) }}%; background:{{ !$isHome ? '#f59e0b' : '#6b7280' }};"></div></div>
                            <div style="font-size:.72rem; font-weight:800; color:{{ !$isHome ? '#fcd34d' : '#9ca3af' }};">{{ $awayProb }}%</div>
                        </div>
                        <div>
                            <div style="font-size:.7rem; color:var(--text-dim); margin-bottom:.3rem;">Over 2.5</div>
                            <div class="prob-bar-outer"><div class="prob-bar-fill" style="width:{{ $pick['over25_prob'] }}%; background:#6366f1;"></div></div>
                            <div style="font-size:.72rem; font-weight:800; color:#a5b4fc;">{{ $pick['over25_prob'] }}%</div>
                        </div>
                    </div>
                </div>

                @if($pick['is_ai'] && $pick['analysis'])
                <div class="pick-analysis-section">
                    <div class="pick-analysis-header">AI Analysis</div>
                    <div class="pick-analysis-text">{{ Str::limit($pick['analysis'], 280) }}</div>
                </div>
                @endif

                <div class="pick-card-footer">
                    <span style="font-size:.7rem; color:var(--text-dim);">⚽ {{ $pick['team_name'] }} {{ $market }} Goals: YES — we win if {{ $pick['team_name'] }} {{ $winDesc }}</span>
                </div>
            </div>
            @empty
            <div class="empty-state">
                <div style="font-size:2.5rem; margin-bottom:1rem;">⚽</div>
                <h3>No Team Goals YES Picks Yet Today</h3>
                <p>We require a team's 3+ goal probability to be at least 28% before publishing a YES pick. Not every day qualifies. Check back after 06:00 Lagos time.</p>
            </div>
            @endforelse
        </div>

        <div class="how-it-works">
            <div class="hiw-title">How Team Goals YES Picks Work</div>
            <div class="hiw-steps">
                <div class="hiw-step">
                    <div class="hiw-num">1</div>
                    <div class="hiw-text"><strong>Our Poisson model calculates each team's goal probability for the 2+ and 3+ markets.</strong> Using attacking xG vs defensive weakness, we compute the exact probability of each team scoring 2 or more, or 3 or more goals in today's matches.</div>
                </div>
                <div class="hiw-step">
                    <div class="hiw-num">2</div>
                    <div class="hiw-text"><strong>We pick the team most likely to hit the threshold.</strong> If a team has ≥ 28% chance of scoring 3+, we back YES on the 3+ market. If 3+ doesn't qualify but 2+ probability is ≥ 55%, we take YES on 2+. The AI always picks the highest-confidence market.</div>
                </div>
                <div class="hiw-step">
                    <div class="hiw-num">3</div>
                    <div class="hiw-text"><strong>We win when the named team reaches the goal threshold.</strong> For a 3+ YES: team scores 3 or more goals. For a 2+ YES: team scores 2 or more goals. Available at Bet365, 1xBet, SportyBet under <em>Match Goals → A Team to Score 2+/3+</em>.</div>
                </div>
            </div>
        </div>

    </div>
</section>

<div class="wrap" style="padding-bottom:2rem;">
    @include('partials.affiliate-strip')
    <div style="text-align:center; font-size:.7rem; color:var(--text-dim); line-height:1.75; padding:.875rem 1rem; border-top:1px solid var(--border); margin-top:2rem;">
        🔞 <strong style="color:var(--text-dim);">18+ only.</strong>
        Team goals YES picks are for <strong style="color:var(--text-dim);">entertainment purposes only</strong> and do not constitute financial or betting advice.
        Please gamble responsibly.
    </div>
</div>

@endsection
