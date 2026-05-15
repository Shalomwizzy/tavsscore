@extends('layouts.app')

@section('title', 'Prediction Accuracy & Track Record | TavsScore')
@section('meta_description', 'Full transparency: see exactly how accurate TavsScore football predictions are. Win rates by outcome type, by league, and weekly trend.')
@section('og_title', 'TavsScore Prediction Accuracy - Full Track Record')

@push('styles')
<style>
    .stats-hero {
        padding: 3.5rem 0 2.5rem;
        background:
            radial-gradient(ellipse 70% 50% at 50% -10%, rgba(16,185,129,.1), transparent),
            radial-gradient(ellipse 50% 40% at 90% 80%, rgba(59,130,246,.06), transparent);
        border-bottom: 1px solid var(--border);
    }

    .stats-eyebrow {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: 3px 12px; border-radius: 999px;
        background: var(--green-dim); border: 1px solid var(--green-border);
        color: #6ee7b7; font-size: .72rem; font-weight: 700;
        letter-spacing: .05em; text-transform: uppercase; margin-bottom: 1rem;
    }

    .stats-title { font-size: clamp(1.8rem,5vw,3rem); font-weight:900; color:#fff; letter-spacing:-.03em; line-height:1.1; margin-bottom:.75rem; }
    .stats-subtitle { font-size:.95rem; color:var(--text-dim); line-height:1.7; max-width:520px; }

    /* ── Period tabs ── */
    .period-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:2rem; }
    .period-tab {
        padding:.38rem .9rem; border-radius:999px; font-size:.78rem; font-weight:700;
        border:1px solid var(--border); background:var(--card); color:var(--text-dim);
        cursor:pointer; transition:all 150ms;
    }
    .period-tab.active, .period-tab:hover { background:var(--green-dim); border-color:var(--green-border); color:#6ee7b7; }

    /* ── Big number tiles ── */
    .big-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; margin-bottom:2rem; }
    .big-tile {
        background:var(--card); border:1px solid var(--border); border-radius:14px;
        padding:1.4rem; text-align:center;
    }
    .big-tile.highlight { background:linear-gradient(135deg,rgba(16,185,129,.1),rgba(16,185,129,.04)); border-color:rgba(16,185,129,.25); }
    .big-num  { font-size:2.8rem; font-weight:900; line-height:1; margin-bottom:.3rem; }
    .big-lbl  { font-size:.72rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; }
    .pct-good  { color:#6ee7b7; }
    .pct-ok    { color:#fcd34d; }
    .pct-low   { color:#fca5a5; }
    .pct-none  { color:var(--text-dim); }

    /* ── Section ── */
    .s-section { padding:2.5rem 0; border-top:1px solid var(--border); }
    .s-grid2   { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-top:1.5rem; }

    /* ── Outcome cards ── */
    .outcome-card {
        background:var(--card); border:1px solid var(--border); border-radius:12px;
        padding:1.1rem 1.25rem; display:flex; align-items:center; justify-content:space-between; gap:1rem;
    }
    .outcome-name { font-size:.88rem; font-weight:700; color:#fff; margin-bottom:.2rem; }
    .outcome-sub  { font-size:.72rem; color:var(--text-dim); }
    .outcome-right { text-align:right; flex-shrink:0; }
    .outcome-pct  { font-size:1.6rem; font-weight:900; line-height:1; }
    .outcome-raw  { font-size:.68rem; color:var(--text-dim); margin-top:.15rem; }

    .mini-bar-wrap { height:5px; background:var(--surface); border-radius:999px; overflow:hidden; margin-top:.5rem; }
    .mini-bar      { height:100%; border-radius:999px; background:linear-gradient(90deg,#10b981,#06d6a0); }

    /* ── Weekly chart ── */
    .week-chart { display:flex; align-items:flex-end; gap:.5rem; height:140px; margin-top:1.5rem; }
    .week-col   { flex:1; display:flex; flex-direction:column; align-items:center; gap:.35rem; }
    .week-bar-wrap { flex:1; width:100%; display:flex; align-items:flex-end; }
    .week-bar   {
        width:100%; border-radius:6px 6px 0 0;
        background:linear-gradient(180deg,#10b981,#059669);
        transition:height 500ms ease; min-height:3px;
        position:relative; cursor:default;
    }
    .week-bar.no-data { background:var(--surface); border:1px solid var(--border); border-radius:6px; min-height:20px; }
    .week-pct  { font-size:.65rem; font-weight:800; color:#6ee7b7; }
    .week-lbl  { font-size:.6rem; color:var(--text-dim); white-space:nowrap; }
    .week-total{ font-size:.6rem; color:var(--text-dim); }

    /* ── League table ── */
    .league-table { width:100%; border-collapse:collapse; margin-top:1.25rem; }
    .league-table th { font-size:.68rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.04em; padding:.5rem .75rem; text-align:left; border-bottom:1px solid var(--border); }
    .league-table td { padding:.55rem .75rem; font-size:.8rem; border-bottom:1px solid rgba(255,255,255,.04); }
    .league-table tr:last-child td { border-bottom:none; }
    .league-table tr:hover td { background:rgba(255,255,255,.02); }

    .pct-pill {
        display:inline-flex; align-items:center; justify-content:center;
        padding:2px 9px; border-radius:999px; font-size:.72rem; font-weight:800;
        min-width:48px;
    }
    .pill-good { background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.25); color:#6ee7b7; }
    .pill-ok   { background:rgba(245,158,11,.1);  border:1px solid rgba(245,158,11,.2);  color:#fcd34d; }
    .pill-low  { background:rgba(239,68,68,.1);   border:1px solid rgba(239,68,68,.2);   color:#fca5a5; }

    /* ── Result log ── */
    .result-log { display:flex; flex-direction:column; gap:.5rem; margin-top:1.25rem; }
    .log-row {
        display:flex; align-items:center; gap:.75rem;
        padding:.6rem .875rem; border-radius:9px;
        background:var(--card); border:1px solid var(--border);
    }
    .log-row.log-win  { border-color:rgba(16,185,129,.25); }
    .log-row.log-loss { border-color:rgba(239,68,68,.2); }
    .log-badge { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:800; flex-shrink:0; }
    .log-badge-win  { background:rgba(16,185,129,.15); color:#6ee7b7; }
    .log-badge-loss { background:rgba(239,68,68,.12);  color:#fca5a5; }
    .log-match { flex:1; font-size:.8rem; font-weight:600; color:#fff; }
    .log-pick  { font-size:.72rem; color:var(--text-dim); }
    .log-league{ font-size:.68rem; color:var(--text-dim); }
    .log-date  { font-size:.68rem; color:var(--text-dim); white-space:nowrap; }

    /* ── Empty ── */
    .stats-empty { text-align:center; padding:4rem 1rem; }
    .stats-empty-icon  { font-size:3rem; margin-bottom:1rem; }
    .stats-empty-title { font-size:1.1rem; font-weight:800; color:#fff; margin-bottom:.5rem; }
    .stats-empty-desc  { font-size:.85rem; color:var(--text-dim); line-height:1.7; }

    /* Per-market category cards */
    .cat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:.75rem; }
    .cat-card { background:var(--card); border:1px solid var(--border); border-radius:11px; padding:.95rem 1rem; }
    .cat-name { font-size:.74rem; color:var(--text-dim); font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.4rem; }
    .cat-pct  { font-size:1.55rem; font-weight:900; line-height:1; }
    .cat-counts { font-size:.7rem; color:var(--text-dim); margin-top:.4rem; font-weight:700; }
    .cat-bar  { height:6px; background:rgba(255,255,255,.06); border-radius:999px; margin-top:.5rem; overflow:hidden; }
    .cat-bar-fill { height:100%; border-radius:999px; transition:width 400ms; }

    /* Calibration table */
    .cal-table { width:100%; border-collapse:collapse; margin-top:.75rem; }
    .cal-table th { font-size:.68rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.04em; padding:.5rem .75rem; text-align:left; border-bottom:1px solid var(--border); }
    .cal-table td { padding:.6rem .75rem; font-size:.82rem; border-bottom:1px solid rgba(255,255,255,.04); }
    .cal-table tr:last-child td { border-bottom:none; }

    @media(max-width:640px) {
        .big-stats { grid-template-columns:1fr 1fr; }
        .s-grid2   { grid-template-columns:1fr; }
        .week-lbl  { display:none; }
        .cal-table th:nth-child(2), .cal-table td:nth-child(2),
        .cal-table th:last-child, .cal-table td:last-child { display:none; }
    }
</style>
@endpush

@section('content')

{{-- ── Hero ── --}}
<section class="stats-hero">
    <div class="wrap">
        <div class="stats-eyebrow">📊 Full Transparency</div>
        <h1 class="stats-title">Our Prediction Track Record</h1>
        <p class="stats-subtitle">
            Every result, published openly. No cherry-picking, no hidden losses.
            This is exactly how our AI has performed on its daily picks.
        </p>
    </div>
</section>

<div class="wrap" style="padding-top:2.5rem; padding-bottom:4rem;">

@if($total === 0)
<div class="stats-empty">
    <div class="stats-empty-icon">📈</div>
    <div class="stats-empty-title">Building our track record</div>
    <p class="stats-empty-desc">
        Results will appear here once today's daily picks are resolved.<br>
        Check back after matches finish - the system updates automatically every 5 minutes.
    </p>
    <a href="{{ route('picks.index') }}" class="btn-ts btn-green" style="display:inline-flex; margin-top:1.25rem;">View Today's Picks →</a>
</div>
@else

{{-- ── Period tabs ── --}}
<div class="period-tabs">
    <button class="period-tab active" data-period="7d">Last 7 days</button>
    <button class="period-tab" data-period="30d">Last 30 days</button>
    <button class="period-tab" data-period="all">All time</button>
</div>

{{-- ── Big stat tiles ── --}}
@foreach(['7d' => 'Last 7 Days', '30d' => 'Last 30 Days', 'all' => 'All Time'] as $key => $label)
@php
    $ps  = $periodStats[$key];
    $pct = $ps['pct'];
    $cls = $pct === null ? 'pct-none' : ($pct >= 60 ? 'pct-good' : ($pct >= 45 ? 'pct-ok' : 'pct-low'));
@endphp
<div class="period-block" data-period="{{ $key }}" style="{{ $key !== '7d' ? 'display:none' : '' }}">
    <div class="big-stats">
        <div class="big-tile highlight">
            <div class="big-num {{ $cls }}">{{ $pct !== null ? $pct.'%' : '-' }}</div>
            <div class="big-lbl">Accuracy</div>
        </div>
        <div class="big-tile">
            <div class="big-num" style="color:#6ee7b7;">{{ $ps['correct'] }}</div>
            <div class="big-lbl">✓ Correct</div>
        </div>
        <div class="big-tile">
            <div class="big-num" style="color:#fca5a5;">{{ $ps['wrong'] }}</div>
            <div class="big-lbl">✗ Incorrect</div>
        </div>
    </div>
</div>
@endforeach

{{-- ── By outcome type ── --}}
<section class="s-section">
    <div class="section-label">Breakdown</div>
    <h2 class="section-title" style="font-size:1.2rem;">Accuracy by prediction type</h2>
    <div class="s-grid2">
        @foreach($byOutcome as $outcome => $stat)
        @php
            $pct = $stat['pct'];
            $cls = $pct === null ? 'pct-none' : ($pct >= 60 ? 'pct-good' : ($pct >= 45 ? 'pct-ok' : 'pct-low'));
            $pillCls = $pct === null ? '' : ($pct >= 60 ? 'pill-good' : ($pct >= 45 ? 'pill-ok' : 'pill-low'));
            $emoji = match($outcome) {
                'Home Win'         => '🏠',
                'Away Win'         => '✈️',
                'Draw'             => '🤝',
                'Over 2.5 Goals'   => '⚽',
                'Under 2.5 Goals'  => '🔒',
                'Both Teams Score' => '🎯',
                default            => '📊',
            };
        @endphp
        <div class="outcome-card">
            <div>
                <div class="outcome-name">{{ $emoji }} {{ $outcome }}</div>
                <div class="outcome-sub">{{ $stat['correct'] }} correct / {{ $stat['total'] }} predictions</div>
                <div class="mini-bar-wrap" style="width:140px;">
                    <div class="mini-bar" style="width:{{ min(100, $pct ?? 0) }}%"></div>
                </div>
            </div>
            <div class="outcome-right">
                <div class="outcome-pct {{ $cls }}">{{ $pct !== null ? $pct.'%' : '-' }}</div>
                <div class="outcome-raw">{{ $stat['correct'] }}/{{ $stat['total'] }}</div>
            </div>
        </div>
        @endforeach
    </div>
</section>

{{-- ── Weekly chart ── --}}
<section class="s-section">
    <div class="section-label">Trend</div>
    <h2 class="section-title" style="font-size:1.2rem;">Weekly accuracy - last 8 weeks</h2>
    <div class="week-chart">
        @php $maxPct = $weekly->max('pct') ?: 100; @endphp
        @foreach($weekly as $week)
        @php
            $barH    = $week['pct'] !== null ? round($week['pct'] / 100 * 100) : 0;
            $noData  = $week['total'] === 0;
            $wCls    = $week['pct'] >= 60 ? 'pct-good' : ($week['pct'] >= 45 ? 'pct-ok' : ($week['pct'] !== null ? 'pct-low' : 'pct-none'));
        @endphp
        <div class="week-col">
            <span class="week-pct {{ $wCls }}">{{ $week['pct'] !== null ? $week['pct'].'%' : '-' }}</span>
            <div class="week-bar-wrap">
                <div class="week-bar {{ $noData ? 'no-data' : '' }}"
                     style="height:{{ $noData ? '20px' : $barH.'%' }}"
                     title="{{ $week['label'] }}: {{ $week['correct'] }}/{{ $week['total'] }}"></div>
            </div>
            <span class="week-lbl">{{ $week['label'] }}</span>
            <span class="week-total">{{ $week['total'] > 0 ? $week['total'].' picks' : 'no data' }}</span>
        </div>
        @endforeach
    </div>
</section>

{{-- ── Per-market accuracy ── --}}
@if($byCategory->isNotEmpty())
<section class="s-section">
    <div class="section-label">Honesty Check</div>
    <h2 class="section-title" style="font-size:1.2rem;">Which markets actually hit?</h2>
    <p style="font-size:.82rem; color:var(--text-dim); line-height:1.6; margin-bottom:1rem;">
        Tracking accuracy by bet type - so you know which markets to trust.
        We never hide misses.
    </p>
    <div class="cat-grid">
        @foreach($byCategory as $category => $stat)
        @php
            $pct = $stat['pct'];
            $cls = $pct === null ? 'pct-none' : ($pct >= 60 ? 'pct-good' : ($pct >= 45 ? 'pct-ok' : 'pct-low'));
        @endphp
        <div class="cat-card">
            <div class="cat-name">{{ $category }}</div>
            <div class="cat-pct {{ $cls }}">{{ $pct !== null ? $pct.'%' : '-' }}</div>
            <div class="cat-counts">
                <span style="color:#6ee7b7;">{{ $stat['correct'] }} won</span>
                · <span style="color:#fca5a5;">{{ $stat['wrong'] }} lost</span>
            </div>
            <div class="cat-bar"><div class="cat-bar-fill" style="width:{{ $pct ?? 0 }}%; background:{{ $pct >= 60 ? '#10b981' : ($pct >= 45 ? '#f59e0b' : '#ef4444') }};"></div></div>
        </div>
        @endforeach
    </div>
</section>
@endif

{{-- ── Confidence calibration ── --}}
@php $hasCalib = $calibration->some(fn($b) => $b['total'] > 0); @endphp
@if($hasCalib)
<section class="s-section">
    <div class="section-label">Calibration</div>
    <h2 class="section-title" style="font-size:1.2rem;">Does AI confidence match reality?</h2>
    <p style="font-size:.82rem; color:var(--text-dim); line-height:1.6; margin-bottom:1rem;">
        When the AI says "80% confident", does it actually win 80% of the time?
        A tight match means our model is well-calibrated.
    </p>
    <table class="cal-table">
        <thead>
            <tr>
                <th>Stated confidence</th>
                <th style="text-align:right">Picks</th>
                <th style="text-align:right">Won</th>
                <th style="text-align:right">Actual hit rate</th>
                <th style="text-align:right">Bar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($calibration as $bucket)
            @php
                $pct = $bucket['pct'];
                $cls = $pct === null ? 'pct-none' : ($pct >= 60 ? 'pct-good' : ($pct >= 45 ? 'pct-ok' : 'pct-low'));
            @endphp
            <tr>
                <td style="font-weight:700; color:#fff;">{{ $bucket['label'] }}</td>
                <td style="text-align:right; color:var(--text-dim); font-variant-numeric:tabular-nums;">{{ $bucket['total'] }}</td>
                <td style="text-align:right; color:#6ee7b7; font-variant-numeric:tabular-nums;">{{ $bucket['correct'] }}</td>
                <td style="text-align:right; font-weight:800; font-variant-numeric:tabular-nums;" class="{{ $cls }}">
                    {{ $pct !== null ? $pct.'%' : '-' }}
                </td>
                <td style="width:160px;">
                    <div class="cat-bar" style="margin:0;"><div class="cat-bar-fill" style="width:{{ $pct ?? 0 }}%; background:{{ $pct >= 60 ? '#10b981' : ($pct >= 45 ? '#f59e0b' : '#ef4444') }};"></div></div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</section>
@endif

{{-- ── By league ── --}}
@if($byLeague->isNotEmpty())
<section class="s-section">
    <div class="section-label">By Competition</div>
    <h2 class="section-title" style="font-size:1.2rem;">Accuracy by league</h2>
    <table class="league-table">
        <thead>
            <tr>
                <th>League</th>
                <th style="text-align:right">Correct</th>
                <th style="text-align:right">Total</th>
                <th style="text-align:right">Accuracy</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byLeague as $league => $stat)
            @php
                $pct     = $stat['pct'];
                $pillCls = $pct === null ? '' : ($pct >= 60 ? 'pill-good' : ($pct >= 45 ? 'pill-ok' : 'pill-low'));
            @endphp
            <tr>
                <td style="color:#fff; font-weight:600;">{{ $league }}</td>
                <td style="text-align:right; color:#6ee7b7; font-weight:700;">{{ $stat['correct'] }}</td>
                <td style="text-align:right; color:var(--text-dim);">{{ $stat['total'] }}</td>
                <td style="text-align:right;">
                    <span class="pct-pill {{ $pillCls }}">{{ $pct !== null ? $pct.'%' : '-' }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</section>
@endif

{{-- ── Recent result log ── --}}
<section class="s-section">
    <div class="section-label">History</div>
    <h2 class="section-title" style="font-size:1.2rem;">Last 15 resolved picks</h2>
    <div class="result-log">
        @foreach($recentLog as $log)
        <div class="log-row {{ $log['correct'] ? 'log-win' : 'log-loss' }}">
            <div class="log-badge {{ $log['correct'] ? 'log-badge-win' : 'log-badge-loss' }}">
                {{ $log['correct'] ? '✓' : '✗' }}
            </div>
            <div style="flex:1; min-width:0;">
                <div class="log-match">{{ $log['match'] }}</div>
                <div class="log-pick">{{ $log['outcome'] }} · <span class="log-league">{{ $log['league'] }}</span></div>
            </div>
            <div class="log-date">{{ $log['date'] }}</div>
        </div>
        @endforeach
    </div>
</section>

{{-- ── Disclaimer ── --}}
<div style="padding:1.25rem 1.5rem; background:var(--card); border:1px solid var(--border); border-radius:12px; font-size:.78rem; color:var(--text-dim); line-height:1.75; margin-top:2rem;">
    <strong style="color:var(--text);">About these stats:</strong>
    Accuracy is measured on our daily picks only - the 3 highest-confidence predictions each day.
    Past performance is not a guarantee of future results.
    These predictions are for entertainment purposes only.
</div>

@endif
</div>

@push('scripts')
<script>
(function() {
    var tabs   = document.querySelectorAll('.period-tab');
    var blocks = document.querySelectorAll('.period-block');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var p = tab.dataset.period;
            tabs.forEach(function(t)   { t.classList.toggle('active', t.dataset.period === p); });
            blocks.forEach(function(b) { b.style.display = b.dataset.period === p ? '' : 'none'; });
        });
    });
}());
</script>
@endpush

@endsection
