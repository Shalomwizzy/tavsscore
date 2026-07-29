@extends('layouts.app')

@section('title', 'Corner Picks — Total Corners Predictions | TavsScore')
@section('meta_description', "Today's safest total-corners picks — the corner line each match is most likely to clear, from our Poisson corners model on team corner averages.")
@section('og_title', 'Corner Picks | TavsScore')

@push('styles')
<style>
    .cn-wrap { padding: 2.5rem 0 4rem; }
    .cn-title { font-size: clamp(1.6rem,4vw,2.4rem); font-weight:900; color:#fff; letter-spacing:-.02em; }
    .cn-sub   { font-size:.9rem; color:var(--text-dim); margin:.35rem 0 1.25rem; max-width:44rem; line-height:1.6; }
    .cn-acc   { display:inline-flex; gap:1.25rem; background:var(--card); border:1px solid var(--border); border-radius:12px; padding:.7rem 1.1rem; margin-bottom:1.25rem; }
    .cn-acc b { color:#fff; font-size:1.1rem; }
    .cn-acc span { font-size:.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; }
    .cn-grid  { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:.9rem; }
    .cn-card  { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1rem 1.1rem; }
    .cn-card.result-win  { border-color:rgba(16,185,129,.4); }
    .cn-card.result-loss { border-color:rgba(239,68,68,.35); }
    .cn-league { font-size:.72rem; color:var(--text-dim); font-weight:700; }
    .cn-teams  { font-weight:800; color:#fff; font-size:.98rem; margin:.3rem 0 .6rem; }
    .cn-pick   { display:flex; align-items:center; justify-content:space-between; gap:.5rem; border-top:1px solid var(--border); padding-top:.7rem; }
    .cn-market { color:#6ee7b7; font-weight:800; font-size:.9rem; }
    .cn-prob   { color:#fcd34d; font-weight:800; font-size:.95rem; }
    .cn-badge  { font-size:.68rem; font-weight:800; padding:2px 8px; border-radius:999px; }
    .cn-win    { background:rgba(16,185,129,.15); color:#6ee7b7; }
    .cn-loss   { background:rgba(239,68,68,.12); color:#fca5a5; }
    .cn-empty  { padding:3rem; text-align:center; color:var(--text-dim); background:var(--card); border:1px solid var(--border); border-radius:14px; }
</style>
@endpush

@section('content')
<div class="wrap cn-wrap">
    <h1 class="cn-title">🚩 Corner Picks</h1>
    <p class="cn-sub">The total-corners line each match is most likely to clear — our safest corners call per game, from a Poisson model on each team's corner averages. Graded from official post-match stats.</p>

    @if($accuracy['pct'] !== null)
    <div class="cn-acc">
        <div><b>{{ $accuracy['pct'] }}%</b><br><span>7-day hit rate</span></div>
        <div><b>{{ $accuracy['correct'] }}/{{ $accuracy['total'] }}</b><br><span>Correct</span></div>
    </div>
    @endif

    @include('partials.date-nav')

    @if($formatted->isEmpty())
        <div class="cn-empty">
            <div style="font-size:2rem;">🚩</div>
            <div style="font-weight:800; color:#fff; margin:.5rem 0;">No corner picks for this date</div>
            <p style="font-size:.85rem;">Corner picks need each team's corner stats. They appear once fixtures with enough corner history are scheduled.</p>
        </div>
    @else
    <div class="cn-grid">
        @foreach($formatted as $pick)
        @php
            $cls = $pick['was_correct'] === true ? 'result-win' : ($pick['was_correct'] === false ? 'result-loss' : '');
        @endphp
        <div class="cn-card {{ $cls }}">
            <div class="cn-league">{{ $pick['match']['league'] }} · {{ $pick['match']['time'] }}</div>
            @include('partials.fixture-showcase', ['predictionId' => $pick['id'], 'accent' => '#6ee7b7', 'compact' => true])
            <div class="cn-pick">
                <span class="cn-market">{{ $pick['market'] }}</span>
                <span>
                    @if($pick['prob'] !== null)<span class="cn-prob">{{ $pick['prob'] }}%</span>@endif
                    @if($pick['was_correct'] === true)<span class="cn-badge cn-win">✓ Won @if($pick['live_score']) {{ $pick['live_score'] }}@endif</span>
                    @elseif($pick['was_correct'] === false)<span class="cn-badge cn-loss">✗ Lost @if($pick['live_score']) {{ $pick['live_score'] }}@endif</span>
                    @endif
                </span>
            </div>
            @include('partials.match-intelligence', ['predictionId' => $pick['id'], 'accent' => '#6ee7b7'])
        </div>
        @endforeach
    </div>
    @endif

    <p style="font-size:.72rem; color:var(--text-dim); margin-top:1.5rem;">⚠️ For insight and entertainment. Corner outcomes are graded from official post-match statistics once available.</p>
</div>
@endsection
