@extends('layouts.app')

@section('title', 'Goalscorer Picks, Anytime Scorer Tips | TavsScore')
@section('meta_description', "Today's most likely goalscorers, anytime scorer probabilities from each player's scoring rate and the opponent's defence.")
@section('og_title', 'Goalscorer Picks | TavsScore')

@push('styles')
<style>
    .gs-wrap { padding: 2.5rem 0 4rem; }
    .gs-title { font-size: clamp(1.6rem,4vw,2.4rem); font-weight:900; color:#fff; letter-spacing:-.02em; }
    .gs-sub   { font-size:.9rem; color:var(--text-dim); margin:.35rem 0 1.5rem; max-width:44rem; line-height:1.6; }
    .gs-grid  { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:.9rem; }
    .gs-card  { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1rem 1.1rem; }
    .gs-top   { display:flex; align-items:center; gap:.75rem; margin-bottom:.75rem; }
    .gs-photo { width:44px; height:44px; border-radius:50%; object-fit:cover; background:#1a2230; flex-shrink:0; }
    .gs-name  { font-weight:800; color:#fff; font-size:.95rem; }
    .gs-team  { font-size:.74rem; color:var(--text-dim); }
    .gs-prob  { display:flex; align-items:baseline; gap:.4rem; margin-bottom:.5rem; }
    .gs-prob-val { font-size:1.6rem; font-weight:900; color:#6ee7b7; }
    .gs-prob-lbl { font-size:.72rem; color:var(--text-dim); }
    .gs-meta  { font-size:.74rem; color:var(--text-dim); line-height:1.6; }
    .gs-meta strong { color:var(--text); }
    .gs-two   { display:inline-block; margin-top:.5rem; font-size:.7rem; padding:2px 8px; border-radius:999px; background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.28); color:#fcd34d; }
    .gs-empty { text-align:center; padding:4rem 1rem; color:var(--text-dim); }
    .gs-empty-icon { font-size:2.5rem; margin-bottom:.75rem; }
    .gs-note { font-size:.75rem; color:var(--text-dim); background:var(--card); border:1px solid var(--border); border-radius:10px; padding:.7rem .9rem; margin-bottom:1.5rem; }
</style>
@endpush

@section('content')
<div class="wrap gs-wrap">
    <h1 class="gs-title">⚽ Goalscorer Picks</h1>
    <p class="gs-sub">Today's most likely anytime goalscorers, ranked by each player's scoring rate against the opponent's defence. {{ $date }}.</p>

    <div class="gs-note">
        These are <strong>anytime scorer</strong> probabilities. Even elite strikers score in only ~50 to 60% of games, so these are higher-odds, higher-reward picks, not safe bankers. Bet responsibly.
    </div>

    @if($picks->isEmpty())
        <div class="gs-empty">
            <div class="gs-empty-icon">⚽</div>
            <p>No goalscorer picks available yet today. These appear once player stats are loaded for today's covered fixtures.</p>
        </div>
    @else
    <div class="gs-grid">
        @foreach($picks as $p)
        <div class="gs-card">
            <div class="gs-top">
                @if($p['photo'])<img src="{{ $p['photo'] }}" alt="" class="gs-photo" loading="lazy">@endif
                <div>
                    <div class="gs-name">{{ $p['player'] }}</div>
                    <div class="gs-team">{{ $p['team'] }} · {{ $p['season'] ?? '' }}</div>
                </div>
            </div>
            <div class="gs-prob">
                <span class="gs-prob-val">{{ number_format($p['probability'],0) }}%</span>
                <span class="gs-prob-lbl">to score anytime</span>
            </div>
            <div class="gs-meta">
                vs <strong>{{ $p['opponent'] }}</strong> · {{ $p['kickoff'] }}<br>
                {{ $p['goals'] }} goals in {{ $p['apps'] }} apps this season
            </div>
            @if(!empty($p['two_plus']) && $p['two_plus'] >= 8)
                <span class="gs-two">2+ goals: {{ number_format($p['two_plus'],0) }}%</span>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
