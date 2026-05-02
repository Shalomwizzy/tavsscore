@extends('layouts.admin')
@section('title', 'Analytics')
@section('page-title', 'Site Analytics')

@push('styles')
<style>
    .an-bars { display:grid; grid-template-columns: repeat(14, 1fr); gap:.3rem; align-items:end; height:140px; padding:.85rem .5rem 0; background:var(--card); border:1px solid var(--border); border-radius:10px; }
    .an-bar  { background:linear-gradient(180deg,#10b981,#059669); border-radius:3px 3px 0 0; min-height:2px; position:relative; }
    .an-bar:hover { background:#34d399; }
    .an-bar-label { position:absolute; bottom:-18px; left:50%; transform:translateX(-50%); font-size:.58rem; color:var(--dim); white-space:nowrap; }

    .an-grid { display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-top:1.25rem; }
    @media (max-width:760px) { .an-grid { grid-template-columns: 1fr; } }

    .an-list-row { display:flex; align-items:center; justify-content:space-between; padding:.5rem 0; border-bottom:1px solid rgba(255,255,255,.04); font-size:.78rem; gap:.6rem; }
    .an-list-row:last-child { border-bottom:none; }
    .an-list-row .an-path { color:#fff; font-weight:600; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; }
    .an-list-row .an-num  { color:var(--dim); font-weight:700; font-variant-numeric:tabular-nums; flex-shrink:0; }
</style>
@endpush

@section('content')

<div class="page-hd">
    <span class="page-hd-title">📊 Site Analytics</span>
    @if(config('services.ga.id'))
    <a href="https://analytics.google.com/analytics/web/" target="_blank" rel="noopener" class="btn-a btn-blue">↗ Open Google Analytics</a>
    @endif
</div>

{{-- Summary tiles --}}
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));">
    <div class="stat-card">
        <span class="stat-val" style="color:#6ee7b7;">{{ number_format($summary['today_visits']) }}</span>
        <span class="stat-lbl">📈 Visits today</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ number_format($summary['today_uniques']) }}</span>
        <span class="stat-lbl">👤 Unique today</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ number_format($summary['week_visits']) }}</span>
        <span class="stat-lbl">📅 Visits last 7d</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ number_format($summary['month_visits']) }}</span>
        <span class="stat-lbl">🗓 Visits last 30d</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#fcd34d;">{{ number_format($summary['bot_today']) }}</span>
        <span class="stat-lbl">🤖 Bot hits today</span>
    </div>
</div>

{{-- Daily trend --}}
<div class="a-card">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">📊 Last 14 days</span>
        <span style="font-size:.7rem; color:var(--dim);">Hover bars for hits / uniques</span>
    </div>
    @php $maxHits = max($daily->max('hits'), 1); @endphp
    <div class="an-bars">
        @foreach($daily as $d)
        <div class="an-bar"
             style="height:{{ max(2, round($d['hits'] / $maxHits * 100)) }}%"
             title="{{ $d['label'] }}: {{ $d['hits'] }} hits, {{ $d['uniques'] }} uniques">
            <span class="an-bar-label">{{ $loop->iteration % 2 ? $d['label'] : '' }}</span>
        </div>
        @endforeach
    </div>
    <div style="height:1.25rem;"></div>
</div>

<div class="an-grid">
    {{-- Top pages --}}
    <div class="a-card">
        <div class="page-hd" style="margin-bottom:.875rem;">
            <span style="font-weight:700; font-size:.9rem; color:#fff;">🔥 Top Pages (last 7d)</span>
        </div>
        @forelse($topPages as $row)
        <div class="an-list-row">
            <a href="{{ $row->path }}" target="_blank" rel="noopener" class="an-path" style="text-decoration:none;">{{ $row->path }}</a>
            <span class="an-num">{{ number_format($row->hits) }}<span style="color:var(--dim); font-weight:600; margin-left:.4rem;">/ {{ number_format($row->uniques) }}</span></span>
        </div>
        @empty
        <div style="padding:1.5rem; text-align:center; color:var(--dim); font-size:.8rem;">No data yet — visit the public site to populate this.</div>
        @endforelse
    </div>

    {{-- Top referrers --}}
    <div class="a-card">
        <div class="page-hd" style="margin-bottom:.875rem;">
            <span style="font-weight:700; font-size:.9rem; color:#fff;">🔗 Top Referrers</span>
        </div>
        @forelse($referrers as $row)
        <div class="an-list-row">
            <span class="an-path">{{ \Illuminate\Support\Str::limit(parse_url($row->referer, PHP_URL_HOST) ?? $row->referer, 50) }}</span>
            <span class="an-num">{{ number_format($row->hits) }}</span>
        </div>
        @empty
        <div style="padding:1.5rem; text-align:center; color:var(--dim); font-size:.8rem;">No external referrers yet.</div>
        @endforelse
    </div>

    {{-- Countries (only meaningful if Cloudflare is in front) --}}
    @if($countries->isNotEmpty())
    <div class="a-card">
        <div class="page-hd" style="margin-bottom:.875rem;">
            <span style="font-weight:700; font-size:.9rem; color:#fff;">🌍 Countries (last 7d)</span>
        </div>
        @foreach($countries as $row)
        <div class="an-list-row">
            <span class="an-path">{{ $row->country }}</span>
            <span class="an-num">{{ number_format($row->hits) }} / {{ number_format($row->uniques) }}</span>
        </div>
        @endforeach
    </div>
    @endif
</div>

<div style="margin-top:1.25rem; padding:.875rem 1rem; border-radius:8px; background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); font-size:.74rem; color:#93c5fd;">
    <strong>ℹ️ Privacy-friendly tracking:</strong>
    We never store raw IP addresses. Each visit is hashed with the app key,
    the User-Agent string is stored to detect bots, and country comes from Cloudflare's <code>CF-IPCountry</code> header if available.
    @if(config('services.ga.id'))
    <strong>Google Analytics</strong> ({{ config('services.ga.id') }}) is also enabled — open the GA dashboard above for richer behavioural reports.
    @else
    Set <code>GA_MEASUREMENT_ID</code> in <code>.env</code> to also enable Google Analytics.
    @endif
</div>

@endsection
