@extends('layouts.admin')

@section('title', 'Corner Picks')
@section('page-title', 'Corner Picks')

@push('styles')
<style>
    .corner-hero { position:relative; overflow:hidden; border:1px solid rgba(45,212,191,.24); border-radius:18px; padding:1.25rem; background:linear-gradient(125deg,#0a2427 0%,#102e35 53%,#111827 100%); margin-bottom:1rem; }
    .corner-hero:after { content:''; position:absolute; width:260px; height:260px; border:1px solid rgba(45,212,191,.15); border-radius:50%; right:-85px; top:-145px; box-shadow:0 0 0 26px rgba(45,212,191,.035),0 0 0 54px rgba(45,212,191,.025); pointer-events:none; }
    .corner-hero > * { position:relative; z-index:1; }
    .corner-title { display:flex; align-items:center; gap:.7rem; font-size:1.12rem; font-weight:900; color:#fff; letter-spacing:-.02em; }
    .corner-title i { display:grid; place-items:center; width:38px; height:38px; border-radius:12px; background:rgba(45,212,191,.15); font-style:normal; }
    .corner-sub { max-width:700px; margin:.55rem 0 0; font-size:.79rem; line-height:1.55; color:#b7d7d8; }
    .corner-datebar { display:flex; align-items:center; gap:.55rem; flex-wrap:wrap; margin-top:1.1rem; }
    .corner-datebar a, .corner-datebar button, .corner-datebar input { min-height:34px; border-radius:9px; border:1px solid rgba(148,163,184,.25); background:rgba(15,23,42,.52); color:#dffaf5; padding:.35rem .65rem; font:inherit; font-size:.74rem; text-decoration:none; }
    .corner-datebar a:hover, .corner-datebar button:hover { border-color:rgba(45,212,191,.6); background:rgba(45,212,191,.12); }
    .corner-datebar .corner-date { font-weight:800; min-width:190px; text-align:center; }
    .corner-datebar .corner-today { background:rgba(45,212,191,.15); border-color:rgba(45,212,191,.32); }
    .corner-datebar .is-disabled { opacity:.35; pointer-events:none; }
    .corner-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(265px,1fr)); gap:.85rem; }
    .corner-card { position:relative; overflow:hidden; min-width:0; border:1px solid rgba(45,212,191,.19); border-radius:15px; background:linear-gradient(145deg,rgba(16,36,44,.92),rgba(15,23,42,.92)); padding:1rem; }
    .corner-card:before { content:''; position:absolute; left:0; top:0; width:4px; height:100%; background:linear-gradient(#2dd4bf,#0ea5e9); }
    .corner-card-top { display:flex; justify-content:space-between; gap:.75rem; align-items:flex-start; }
    .corner-rank { color:#5eead4; font-size:.67rem; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .corner-market { margin:.4rem 0 .35rem; color:#fff; font-size:1.03rem; font-weight:900; letter-spacing:-.02em; }
    .corner-prob { flex-shrink:0; display:grid; place-items:center; min-width:48px; height:48px; border-radius:50%; border:2px solid rgba(45,212,191,.45); color:#5eead4; font-size:.83rem; font-weight:900; background:rgba(45,212,191,.08); }
    .corner-meta { display:flex; gap:.45rem; flex-wrap:wrap; margin-top:.8rem; padding-top:.75rem; border-top:1px solid rgba(148,163,184,.13); color:#a8c3c7; font-size:.7rem; }
    .corner-meta span { padding:.22rem .42rem; border-radius:6px; background:rgba(148,163,184,.08); }
    .corner-reasons { margin-top:.8rem; padding:.65rem .7rem; background:rgba(2,6,23,.3); border-radius:9px; }
    .corner-reasons summary { cursor:pointer; color:#99f6e4; font-size:.71rem; font-weight:800; }
    .corner-reasons ul { padding-left:1rem; margin:.5rem 0 0; display:grid; gap:.3rem; color:#aabec5; font-size:.7rem; line-height:1.42; }
    .corner-status { display:inline-flex; align-items:center; border-radius:999px; padding:.22rem .48rem; font-size:.66rem; font-weight:900; }
    .corner-status.win { background:rgba(16,185,129,.13); color:#6ee7b7; }.corner-status.loss { background:rgba(248,113,113,.13); color:#fca5a5; }.corner-status.pending { background:rgba(148,163,184,.12); color:#cbd5e1; }.corner-status.live { background:rgba(248,113,113,.15); color:#fda4af; }
    @media(max-width:600px) { .corner-hero { padding:1rem; border-radius:14px; }.corner-datebar { gap:.4rem; }.corner-datebar .corner-date { order:-1; width:100%; }.corner-datebar input { flex:1; }.corner-grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<section class="corner-hero">
    <div class="corner-title"><i>🚩</i><span>Corner Intelligence</span></div>
    <p class="corner-sub">A separate set-piece board built from each side’s completed corner history. A fixture appears only when both teams have enough evidence and one total-corners line clears the model gate.</p>

    <form class="corner-datebar" method="GET" action="{{ route('admin.corners.index') }}">
        <a href="{{ route('admin.corners.index', ['date' => $dateMeta['prev_iso']]) }}" aria-label="Previous day">← Previous</a>
        @if($dateMeta['next_iso'])
            <a href="{{ route('admin.corners.index', ['date' => $dateMeta['next_iso']]) }}" aria-label="Next day">Next →</a>
        @else
            <span class="is-disabled">Next →</span>
        @endif
        <strong class="corner-date">{{ $dateMeta['pretty'] }}</strong>
        <input type="date" name="date" value="{{ $dateMeta['iso'] }}" min="{{ $dateMeta['min_iso'] }}" max="{{ $dateMeta['max_iso'] }}" onchange="this.form.submit()">
        <button type="submit">View date</button>
        @unless($dateMeta['is_today'])<a class="corner-today" href="{{ route('admin.corners.index') }}">Today</a>@endunless
    </form>
</section>

@if(session('success'))
<div style="margin-bottom:1rem;padding:.75rem .9rem;border-radius:10px;background:rgba(16,185,129,.11);border:1px solid rgba(16,185,129,.27);font-size:.78rem;color:#a7f3d0;">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="margin-bottom:1rem;padding:.75rem .9rem;border-radius:10px;background:rgba(239,68,68,.11);border:1px solid rgba(239,68,68,.27);font-size:.78rem;color:#fecaca;">⚠️ {{ session('error') }}</div>
@endif

<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(145px,1fr));margin-bottom:1rem;">
    <div class="stat-card" style="border-color:rgba(45,212,191,.24);"><span class="stat-val" style="color:#5eead4;">{{ $cards->count() }}</span><span class="stat-lbl">Picks for this date</span></div>
    <div class="stat-card"><span class="stat-val" style="color:#6ee7b7;">{{ $correct }}</span><span class="stat-lbl">Won / resolved</span></div>
    <div class="stat-card"><span class="stat-val" style="color:#fca5a5;">{{ $settled->count() - $correct }}</span><span class="stat-lbl">Lost / resolved</span></div>
    <div class="stat-card"><span class="stat-val" style="color:#fbbf24;">{{ $readyTeams }}</span><span class="stat-lbl">Teams with 3+ samples</span></div>
</div>

<section class="a-card" style="margin-bottom:1rem;border-color:rgba(45,212,191,.22);">
    <div class="page-hd" style="margin-bottom:.9rem;gap:.75rem;">
        <div><span style="font-weight:900;color:#fff;">🚩 Corner Predictions</span><span style="display:block;font-size:.7rem;color:var(--dim);margin-top:.2rem;">{{ $statRows }} stored team-stat rows · total-corners lines only</span></div>
        @if($dateMeta['is_today'])
        <div style="display:flex;gap:.45rem;flex-wrap:wrap;justify-content:flex-end;">
            <form method="POST" action="{{ route('admin.corners.refresh') }}" onsubmit="return confirm('Re-select today’s corner picks from the statistics already stored?');">@csrf<button class="btn-a" style="background:rgba(45,212,191,.14);color:#99f6e4;border:1px solid rgba(45,212,191,.32);">↻ Select stored data</button></form>
            <form method="POST" action="{{ route('admin.corners.rebuild') }}" onsubmit="return confirm('This checks recent finished-match stats, refreshes fixtures, rebuilds prediction boards and sends only qualifying corner picks. It may use API quota. Continue?');">@csrf<button class="btn-a btn-green">↻ Pull stats + rebuild</button></form>
            <a class="btn-a btn-blue" href="{{ route('corners-picks.index') }}" target="_blank" rel="noopener">↗ Public page</a>
        </div>
        @endif
    </div>

    @if($cards->isEmpty())
        <div style="text-align:center;padding:2rem 1rem;max-width:620px;margin:auto;">
            <div style="font-size:1.7rem;">🚩</div>
            <strong style="display:block;color:#fff;margin:.45rem 0;">No qualified corner picks for this date</strong>
            <p style="font-size:.78rem;line-height:1.55;color:var(--dim);margin:0;">Each team needs at least three finished fixtures with saved corner statistics. Once both teams qualify, only an Over 8.5 to 11.5 line that clears the model gate is published. Current ready teams: <strong style="color:#fbbf24;">{{ $readyTeams }}</strong>.</p>
        </div>
    @else
        <div class="corner-grid">
            @foreach($cards as $card)
                @php
                    $match = $card['match'];
                    $status = $match?->status;
                    $isLive = in_array($status, ['1H','HT','2H','ET','BT','P','LIVE'], true);
                @endphp
                <article class="corner-card">
                    <div class="corner-card-top">
                        <div style="min-width:0;">
                            <div class="corner-rank">Signal #{{ $card['pick']->corners_rank }} · {{ \App\Support\LeagueCoverage::formatName($match?->league, $match?->league_country) }}</div>
                            <div style="margin-top:.55rem;">@include('admin.partials.fixture-mini', ['match' => $match])</div>
                            <div class="corner-market">{{ $card['label'] }}</div>
                        </div>
                        <div class="corner-prob">{{ $card['probability'] }}%</div>
                    </div>
                    <div class="corner-meta">
                        <span>🕒 {{ $match?->match_time?->timezone('Africa/Lagos')->format('H:i') ?? 'N/A' }} Lagos</span>
                        @if($card['finished'])<span>Final {{ $match?->home_score }}:{{ $match?->away_score }}</span>@endif
                        @if($card['finished'])<span class="corner-status {{ $card['result'] === true ? 'win' : ($card['result'] === false ? 'loss' : 'pending') }}">{{ $card['result'] === true ? '✓ Won' : ($card['result'] === false ? '✗ Lost' : '⏳ Awaiting corner stats') }}</span>@elseif($isLive)<span class="corner-status live">🔴 Live {{ $match?->home_score ?? 0 }}:{{ $match?->away_score ?? 0 }}</span>@else<span class="corner-status pending">⏳ Upcoming</span>@endif
                    </div>
                    <details class="corner-reasons"><summary>Why this corner line?</summary><ul>@forelse($card['reasons'] as $reason)<li>{{ $reason }}</li>@empty<li>The projected total-corners line is the strongest qualified market on this fixture’s board.</li>@endforelse</ul></details>
                </article>
            @endforeach
        </div>
    @endif
</section>

<div style="padding:.8rem .9rem;border:1px solid rgba(251,191,36,.2);border-radius:11px;background:rgba(251,191,36,.06);font-size:.73rem;line-height:1.5;color:#fde68a;">
    <strong>Data note:</strong> this page does not invent corner picks. If API-Football quota is exhausted, use the date browser to review existing results and wait for the next quota window before pulling more fixture statistics.
</div>
@endsection
