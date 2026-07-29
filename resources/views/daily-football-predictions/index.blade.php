@extends('layouts.app')
@section('title', 'Daily Football Predictions | TavsScore')
@section('meta_description', 'Daily TavsScore football picks with the predicted outcome and verified win or loss result.')

@push('styles')
<style>
    .dfp-head { padding:1.5rem 0 1rem; }
    .dfp-title { color:#fff; font-size:1.55rem; font-weight:800; margin:0 0 .3rem; }
    .dfp-sub { color:var(--text-dim); font-size:.84rem; margin:0; }
    .dfp-date { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; margin:1rem 0; }
    .dfp-date a, .dfp-date input { border:1px solid var(--border); background:var(--card); color:var(--text); border-radius:8px; padding:.48rem .7rem; font:inherit; font-size:.76rem; text-decoration:none; }
    .dfp-date a:hover, .dfp-date a.active { border-color:rgba(16,185,129,.55); color:#6ee7b7; background:rgba(16,185,129,.08); }
    .dfp-day { color:#fcd34d; font-size:.78rem; font-weight:800; margin-left:.1rem; }
    .dfp-summary { display:flex; gap:.55rem; flex-wrap:wrap; margin:0 0 1rem; }
    .dfp-stat { background:var(--card); border:1px solid var(--border); border-radius:8px; padding:.48rem .7rem; font-size:.73rem; color:var(--text-dim); }
    .dfp-stat strong { color:#fff; margin-right:.2rem; }
    .dfp-card { overflow:hidden; border:1px solid var(--border); border-radius:12px; background:var(--card); }
    .dfp-table { width:100%; border-collapse:collapse; }
    .dfp-table th { padding:.68rem .8rem; font-size:.66rem; color:var(--text-dim); text-align:left; text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid var(--border); }
    .dfp-table td { padding:.78rem .8rem; border-bottom:1px solid rgba(255,255,255,.05); vertical-align:middle; }
    .dfp-table tr:last-child td { border-bottom:0; }
    .dfp-match { color:#fff; font-weight:750; font-size:.84rem; }
    .dfp-meta { color:var(--text-dim); font-size:.69rem; margin-top:.2rem; }
    .dfp-outcome { color:#93c5fd; font-size:.78rem; font-weight:750; }
    .dfp-badge { display:inline-flex; padding:.28rem .55rem; border-radius:999px; font-size:.68rem; font-weight:800; white-space:nowrap; }
    .dfp-win { color:#6ee7b7; background:rgba(16,185,129,.13); border:1px solid rgba(16,185,129,.3); }
    .dfp-loss { color:#fca5a5; background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.3); }
    .dfp-pending { color:#d1d5db; background:rgba(107,114,128,.14); border:1px solid rgba(107,114,128,.35); }
    .dfp-empty { padding:2.5rem 1rem; color:var(--text-dim); text-align:center; }
    @media(max-width:620px) { .dfp-table th:nth-child(1), .dfp-table td:nth-child(1) { display:none; } .dfp-table th, .dfp-table td { padding:.72rem .55rem; } .dfp-outcome { font-size:.72rem; } }
</style>
@endpush

@section('content')
<div class="wrap">
    <header class="dfp-head">
        <h1 class="dfp-title">📅 Daily Football Predictions</h1>
        <p class="dfp-sub">Every football prediction generated for the selected date, with its predicted outcome and verified result.</p>
    </header>

    <nav class="dfp-date" aria-label="Prediction date">
        <a href="{{ route('daily-football-predictions.index', ['date' => $meta['previous_iso']]) }}">← Previous</a>
        <a href="{{ route('daily-football-predictions.index') }}" class="{{ $meta['is_today'] ? 'active' : '' }}">Today</a>
        <a href="{{ route('daily-football-predictions.index', ['date' => $meta['yesterday_iso']]) }}" class="{{ $meta['is_yesterday'] ? 'active' : '' }}">Yesterday</a>
        @if($meta['next_iso'])<a href="{{ route('daily-football-predictions.index', ['date' => $meta['next_iso']]) }}">Next →</a>@endif
        <form method="GET"><input type="date" name="date" value="{{ $meta['iso'] }}" max="{{ $meta['today_iso'] }}" onchange="this.form.submit()"></form>
        <span class="dfp-day">{{ $meta['pretty'] }}</span>
    </nav>

    <div class="dfp-summary">
        <span class="dfp-stat"><strong>{{ $summary['total'] }}</strong> picks</span>
        <span class="dfp-stat"><strong style="color:#6ee7b7">{{ $summary['won'] }}</strong> won</span>
        <span class="dfp-stat"><strong style="color:#fca5a5">{{ $summary['lost'] }}</strong> lost</span>
        @if($summary['pending'])<span class="dfp-stat"><strong>{{ $summary['pending'] }}</strong> pending</span>@endif
    </div>

    <div class="dfp-card">
        @if($predictions->isEmpty())
            <div class="dfp-empty">No football predictions were generated for this date.</div>
        @else
            <table class="dfp-table"><thead><tr><th>Time</th><th>Match</th><th>Predicted outcome</th><th>Result</th></tr></thead><tbody>
            @foreach($predictions as $prediction)
                @php($match = $prediction->match)
                <tr>
                    <td style="color:var(--text-dim);font-size:.75rem;white-space:nowrap;">{{ $match?->match_time?->timezone(config('app.timezone'))->format('H:i') ?? '—' }}</td>
                    <td style="min-width:235px;">
                        <div class="dfp-meta">{{ \App\Support\LeagueCoverage::formatName($match?->league, $match?->league_country) }}</div>
                        @include('partials.fixture-showcase', ['match' => $match, 'accent' => '#93c5fd', 'compact' => true])
                    </td>
                    <td><span class="dfp-outcome">{{ $prediction->predicted_outcome ?? '—' }}</span></td>
                    <td>@if($prediction->was_correct === true)<span class="dfp-badge dfp-win">✓ Won</span>@elseif($prediction->was_correct === false)<span class="dfp-badge dfp-loss">✗ Lost</span>@else<span class="dfp-badge dfp-pending">⏳ Pending</span>@endif</td>
                </tr>
            @endforeach
            </tbody></table>
        @endif
    </div>
</div>
@endsection
