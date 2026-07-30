@extends('layouts.admin')

@section('title', 'Daily Football Predictions')
@section('page-title', 'Daily Football Predictions')

@push('styles')
<style>
    .daily-desk { max-width: 1180px; }
    .daily-desk-hero { position:relative; overflow:hidden; display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:1.2rem; border:1px solid rgba(16,185,129,.25); border-radius:16px; background:radial-gradient(circle at 88% 8%,rgba(16,185,129,.18),transparent 28%),linear-gradient(125deg,#102238,#101928); }
    .daily-desk-hero::after { content:'RESULTS'; position:absolute; right:-.2rem; bottom:-1.4rem; color:rgba(255,255,255,.035); font-size:5.2rem; font-weight:950; letter-spacing:-.09em; pointer-events:none; }
    .daily-desk-hero > * { position:relative; z-index:1; }.daily-desk-kicker { color:#86efac; font-size:.6rem; font-weight:900; letter-spacing:.12em; text-transform:uppercase; }.daily-desk-hero h1 { margin:.38rem 0 .28rem; color:#fff; font-size:1.35rem; letter-spacing:-.04em; }.daily-desk-hero p { max-width:620px; margin:0; color:#cbd5e1; font-size:.72rem; line-height:1.55; }
    .daily-desk-date { flex-shrink:0; padding:.48rem .58rem; border:1px solid rgba(255,255,255,.12); border-radius:9px; background:rgba(2,6,23,.25); color:#fff; font-size:.68rem; font-weight:800; }
    .daily-desk-controls { display:flex; align-items:center; flex-wrap:wrap; gap:.45rem; margin: .85rem 0; }.daily-desk-controls form { margin-left:auto; }.daily-desk-input { width:auto; padding:.42rem .52rem; }
    .daily-desk-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.55rem; margin-bottom:.85rem; }.daily-desk-stat { padding:.75rem .8rem; border:1px solid var(--border); border-radius:11px; background:var(--card); }.daily-desk-stat b { display:block; color:#fff; font-size:1.05rem; }.daily-desk-stat span { display:block; margin-top:.2rem; color:var(--dim); font-size:.58rem; font-weight:850; letter-spacing:.075em; text-transform:uppercase; }
    .daily-desk-board { overflow:hidden; border:1px solid var(--border); border-radius:14px; background:var(--card); }.daily-desk-board-head { display:flex; align-items:center; justify-content:space-between; gap:.7rem; padding:.8rem .9rem; border-bottom:1px solid var(--border); }.daily-desk-board-head h2 { margin:0; color:#fff; font-size:.83rem; }.daily-desk-board-head span { color:var(--dim); font-size:.65rem; }
    .daily-desk-row { display:grid; grid-template-columns:65px minmax(230px,1fr) minmax(130px,.55fr) 84px 78px; gap:.7rem; align-items:center; padding:.68rem .9rem; border-bottom:1px solid rgba(255,255,255,.05); }.daily-desk-row:last-child { border-bottom:0; }.daily-desk-time { color:var(--dim); font-size:.68rem; font-weight:750; }.daily-desk-league { margin-bottom:.16rem; color:var(--dim); font-size:.6rem; }.daily-desk-market { color:#bfdbfe; font-size:.71rem; font-weight:800; }.daily-desk-score { color:#e2e8f0; font-size:.72rem; font-weight:800; }.daily-desk-empty { padding:2.3rem 1rem; color:var(--dim); font-size:.75rem; text-align:center; }
    @media(max-width:760px) { .daily-desk-hero { display:block; }.daily-desk-date { display:inline-block; margin-top:.75rem; }.daily-desk-controls form { margin-left:0; width:100%; }.daily-desk-input { width:100%; }.daily-desk-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }.daily-desk-row { grid-template-columns:48px minmax(0,1fr) auto; gap:.45rem; padding:.65rem; }.daily-desk-market { grid-column:2; }.daily-desk-score { display:none; }.daily-desk-row > .badge { grid-column:3; grid-row:1 / span 2; } }
</style>
@endpush

@section('content')
<div class="daily-desk">
    <section class="daily-desk-hero">
        <div><div class="daily-desk-kicker">Prediction ledger · full transparency</div><h1>Daily Football Predictions</h1><p>Every generated football prediction for the selected date—shown whether it won, lost or is still waiting on a verified final score.</p></div>
        <div class="daily-desk-date">{{ $meta['pretty'] }}</div>
    </section>

    <nav class="daily-desk-controls" aria-label="Prediction date">
        <a href="{{ route('admin.daily-football-predictions.index', ['date' => $meta['previous_iso']]) }}" class="btn-a btn-gray">← Previous</a>
        <a href="{{ route('admin.daily-football-predictions.index') }}" class="btn-a {{ $meta['is_today'] ? 'btn-green' : 'btn-gray' }}">Today</a>
        <a href="{{ route('admin.daily-football-predictions.index', ['date' => $meta['yesterday_iso']]) }}" class="btn-a {{ $meta['is_yesterday'] ? 'btn-green' : 'btn-gray' }}">Yesterday</a>
        @if($meta['next_iso'])<a href="{{ route('admin.daily-football-predictions.index', ['date' => $meta['next_iso']]) }}" class="btn-a btn-gray">Next →</a>@endif
        <form method="GET"><input type="date" name="date" value="{{ $meta['iso'] }}" max="{{ $meta['today_iso'] }}" class="form-input daily-desk-input" onchange="this.form.submit()"></form>
    </nav>

    <section class="daily-desk-stats" aria-label="Daily result summary">
        <article class="daily-desk-stat"><b>{{ $summary['total'] }}</b><span>Predictions</span></article>
        <article class="daily-desk-stat"><b style="color:#6ee7b7">{{ $summary['won'] }}</b><span>Won</span></article>
        <article class="daily-desk-stat"><b style="color:#fca5a5">{{ $summary['lost'] }}</b><span>Lost</span></article>
        <article class="daily-desk-stat"><b>{{ $summary['pending'] }}</b><span>Pending result</span></article>
    </section>

    <section class="daily-desk-board">
        <div class="daily-desk-board-head"><h2>Prediction board</h2><span>{{ $meta['iso'] }}</span></div>
        @forelse($predictions as $prediction)
            @php($match = $prediction->match)
            <article class="daily-desk-row">
                <div class="daily-desk-time">{{ $match?->match_time?->timezone(config('app.timezone'))->format('H:i') ?? 'N/A' }}</div>
                <div><div class="daily-desk-league">{{ \App\Support\LeagueCoverage::formatName($match?->league, $match?->league_country) }}</div>@include('admin.partials.fixture-mini', ['match' => $match])</div>
                <div class="daily-desk-market">{{ $prediction->predicted_outcome ?? 'N/A' }}</div>
                <div class="daily-desk-score">{{ $match?->home_score !== null ? $match->home_score.':'.$match->away_score : '—' }}</div>
                @if($prediction->was_correct === true)<span class="badge badge-green">✓ Won</span>@elseif($prediction->was_correct === false)<span class="badge badge-red">✗ Lost</span>@else<span class="badge badge-gray">⏳ Pending</span>@endif
            </article>
        @empty
            <div class="daily-desk-empty">No football predictions were generated for this date.</div>
        @endforelse
    </section>
</div>
@endsection
