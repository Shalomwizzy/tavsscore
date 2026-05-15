@extends('layouts.app')
@section('title', 'Results | TavsScore')
@section('meta_description', 'Track record of every TavsScore daily pick over the last 14 days - match score, our prediction, and whether it was correct.')
@section('canonical', url('/results'))

@push('styles')
<style>
    .res-hero { padding: 1.5rem 0 .25rem; }
    .res-title { font-size: 1.6rem; font-weight: 800; letter-spacing: -.02em; color: #fff; margin-bottom: .35rem; }
    .res-sub   { font-size: .85rem; color: var(--text-dim); }

    .res-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:.6rem; margin: 1rem 0 1.5rem; }
    .res-tile { background:var(--card); border:1px solid var(--border); border-radius:11px; padding:.95rem 1rem; }
    .res-tile-val { font-size:1.55rem; font-weight:800; color:#fff; line-height:1; }
    .res-tile-lbl { font-size:.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; margin-top:.4rem; font-weight:700; }

    .res-day { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.1rem 1.15rem; margin-bottom:1rem; }
    .res-day-head { display:flex; align-items:baseline; justify-content:space-between; flex-wrap:wrap; gap:.5rem; margin-bottom:.85rem; padding-bottom:.7rem; border-bottom:1px solid var(--border); }
    .res-day-date { font-size:1rem; font-weight:800; color:#fff; }
    .res-day-score { font-size:.78rem; color:var(--text-dim); font-weight:700; }
    .res-day-score strong { color:var(--text); }

    .res-row { display:grid; grid-template-columns: 32px 1fr auto auto; gap:.65rem; align-items:center; padding:.55rem 0; border-bottom:1px solid rgba(255,255,255,.03); }
    .res-row:last-child { border-bottom: none; }
    .res-rank { font-weight:800; color:#fcd34d; font-size:.78rem; }
    .res-match { min-width:0; }
    .res-match-line1 { color:#fff; font-weight:700; font-size:.86rem; }
    .res-match-score { color:var(--text-dim); font-weight:600; margin-left:.35rem; font-variant-numeric:tabular-nums; }
    .res-match-line2 { color:var(--text-dim); font-size:.72rem; margin-top:2px; }
    .res-tip   { color:#93c5fd; font-weight:700; font-size:.76rem; white-space:nowrap; }
    .res-conf  { color:var(--text-dim); font-size:.72rem; }
    .res-badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:999px; font-size:.7rem; font-weight:800; letter-spacing:.02em; white-space:nowrap; }
    .res-win  { background:rgba(16,185,129,.13); border:1px solid rgba(16,185,129,.28); color:#6ee7b7; }
    .res-loss { background:rgba(239,68,68,.1);   border:1px solid rgba(239,68,68,.28);  color:#fca5a5; }
    .res-pend { background:rgba(107,114,128,.12); border:1px solid rgba(107,114,128,.28); color:#9ca3af; }

    .res-empty { padding:2.5rem 1rem; text-align:center; color:var(--text-dim); border:1px dashed rgba(255,255,255,.1); border-radius:12px; }

    @media (max-width:560px) {
        .res-summary { grid-template-columns: 1fr 1fr; }
        .res-row { grid-template-columns: 28px 1fr auto; }
        .res-tip, .res-conf { font-size: .72rem; }
        .res-conf { display:none; }
    }
</style>
@endpush

@section('content')
<div class="wrap">
    <header class="res-hero">
        <h1 class="res-title">📜 Results Archive</h1>
        <p class="res-sub">Every daily pick from the last 14 days - verified, with the actual match score next to our prediction.</p>
    </header>

    <div class="res-summary">
        <div class="res-tile">
            <div class="res-tile-val" style="color:#fcd34d;">
                @if($summary['accuracy'] !== null){{ $summary['accuracy'] }}%@else-@endif
            </div>
            <div class="res-tile-lbl">⭐ 14-day accuracy</div>
        </div>
        <div class="res-tile">
            <div class="res-tile-val" style="color:#6ee7b7;">{{ $summary['correct'] }}</div>
            <div class="res-tile-lbl">✓ Correct picks</div>
        </div>
        <div class="res-tile">
            <div class="res-tile-val" style="color:#fca5a5;">{{ $summary['total'] - $summary['correct'] }}</div>
            <div class="res-tile-lbl">✗ Missed picks</div>
        </div>
    </div>

    <div class="ts-tabs mb-4" style="max-width:380px; margin-bottom:1rem;">
        <a href="{{ route('picks.index') }}" class="ts-tab">⭐ Today's Picks</a>
        <a href="{{ route('stats.index') }}" class="ts-tab">📊 Stats</a>
        <span class="ts-tab active">📜 Results</span>
    </div>

    @if($byDay->isEmpty())
        <div class="res-empty">
            <div style="font-size:2rem; margin-bottom:.5rem;">📭</div>
            <div style="color:#fff; font-weight:700; margin-bottom:.4rem;">No results yet</div>
            <div style="font-size:.78rem;">Come back after the first day of resolved picks.</div>
        </div>
    @else
        @foreach($byDay as $date => $day)
        <article class="res-day">
            <div class="res-day-head">
                <div class="res-day-date">
                    {{ \Carbon\Carbon::parse($date)->format('l, M j') }}
                </div>
                <div class="res-day-score">
                    @if($day['total'] > 0)
                        <strong>{{ $day['correct'] }}/{{ $day['total'] }}</strong> correct
                        @if($day['pending'] > 0) · {{ $day['pending'] }} pending @endif
                    @elseif($day['pending'] > 0)
                        {{ $day['pending'] }} pending
                    @endif
                </div>
            </div>

            @foreach($day['picks'] as $pick)
            <div class="res-row">
                <div class="res-rank">#{{ $pick['rank'] }}</div>
                <div class="res-match">
                    <div class="res-match-line1">
                        {{ $pick['home'] }} <span class="res-match-score">{{ $pick['home_score'] ?? '–' }}–{{ $pick['away_score'] ?? '–' }}</span> {{ $pick['away'] }}
                    </div>
                    <div class="res-match-line2">{{ $pick['league'] }} · KO {{ $pick['time'] }}</div>
                </div>
                <div>
                    <div class="res-tip">{{ $pick['outcome'] }}</div>
                    <div class="res-conf">{{ $pick['confidence'] }}% conf.</div>
                </div>
                <div>
                    @if($pick['was_correct'] === true)
                        <span class="res-badge res-win">✓ Won</span>
                    @elseif($pick['was_correct'] === false)
                        <span class="res-badge res-loss">✗ Lost</span>
                    @else
                        <span class="res-badge res-pend">⏳ Pending</span>
                    @endif
                </div>
            </div>
            @endforeach
        </article>
        @endforeach
    @endif

    <div style="height:2rem"></div>
</div>
@endsection
