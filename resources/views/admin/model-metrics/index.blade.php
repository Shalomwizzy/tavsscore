@extends('layouts.admin')
@section('title', 'Model Metrics — TavsScore Admin')
@section('page-title', 'Model Metrics')

@push('styles')
<style>
    .mm-note   { font-size:.78rem; color:var(--text-dim); background:rgba(99,102,241,.06); border:1px solid rgba(99,102,241,.18); border-radius:10px; padding:.7rem .9rem; margin-bottom:1.25rem; line-height:1.55; }
    .mm-note strong { color:#fff; }

    .mm-filters { display:flex; flex-wrap:wrap; gap:.75rem; align-items:end; margin-bottom:1.5rem; padding:.8rem .9rem; background:var(--card); border:1px solid var(--border); border-radius:10px; }
    .mm-filters label { display:flex; flex-direction:column; gap:.25rem; font-size:.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; font-weight:700; }
    .mm-filters select, .mm-filters input { background:rgba(255,255,255,.04); border:1px solid var(--border); color:var(--text); font-size:.78rem; padding:.35rem .5rem; border-radius:6px; }
    .mm-filters button { background:rgba(99,102,241,.18); border:1px solid rgba(99,102,241,.3); color:#c4b5fd; font-size:.75rem; font-weight:700; padding:.4rem .9rem; border-radius:8px; cursor:pointer; }

    .mm-strip  { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:.75rem; margin-bottom:1.5rem; }
    .mm-stat   { background:var(--card); border:1px solid var(--border); border-radius:10px; padding:.85rem 1rem; }
    .mm-stat-lbl { font-size:.62rem; color:var(--text-dim); font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
    .mm-stat-val { font-size:1.25rem; font-weight:900; color:#fff; margin-top:.25rem; }
    .mm-stat-sub { font-size:.62rem; color:var(--text-dim); margin-top:.2rem; }

    .mm-section-title { font-size:.8rem; font-weight:800; color:var(--text-dim); text-transform:uppercase; letter-spacing:.06em; margin:1.5rem 0 .5rem; border-bottom:1px solid var(--border); padding-bottom:.35rem; }

    .mm-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .mm-table { width:100%; min-width:640px; border-collapse:collapse; font-size:.78rem; }
    .mm-table th { text-align:left; font-size:.62rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; padding:.4rem .6rem; border-bottom:1px solid var(--border); }
    .mm-table td { padding:.45rem .6rem; border-bottom:1px solid rgba(255,255,255,.04); color:var(--text); font-variant-numeric:tabular-nums; }
    .mm-table td.num { text-align:right; }
    .mm-table tr:hover td { background:rgba(255,255,255,.02); }
    .mm-tag { display:inline-block; font-size:.6rem; font-weight:800; padding:1px 6px; border-radius:999px; background:rgba(255,255,255,.06); color:#c7d2fe; }
    .mm-diag-ok   { color:#6ee7b7; }
    .mm-diag-warn { color:#fde68a; }
    .mm-diag-bad  { color:#fca5a5; }
    .mm-empty { color:var(--text-dim); font-size:.78rem; padding:.75rem; }

    @media (max-width:640px) {
        .mm-filters { flex-direction:column; align-items:stretch; }
        .mm-filters label { width:100%; }
        .mm-filters select { width:100%; }
        .mm-strip { grid-template-columns:1fr 1fr; }
    }
</style>
@endpush

@php
    /**
     * Colour Brier: lower = better. Naive baselines depend on market;
     * these thresholds are rough guides for a 3-way 1X2 (Brier .22 = coin flip-ish).
     */
    $brierClass = fn (?float $b) => $b === null ? '' : ($b < 0.20 ? 'mm-diag-ok' : ($b < 0.24 ? 'mm-diag-warn' : 'mm-diag-bad'));
    // Delta vs market: negative = beats the market (good). Positive = loses to it.
    $deltaClass = fn (?float $d) => $d === null ? '' : ($d < -0.005 ? 'mm-diag-ok' : ($d < 0.005 ? 'mm-diag-warn' : 'mm-diag-bad'));
    $fmt = fn (?float $v, int $dp = 3) => $v === null ? '—' : number_format($v, $dp);
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v * 100, 1) . '%';
    $signed = fn (?float $v, int $dp = 4) => $v === null ? '—' : ($v >= 0 ? '+' : '') . number_format($v, $dp);
@endphp

@section('content')

<div class="mm-note">
    <strong>Measurement layer — Phase 1.</strong>
    Metrics computed from <code>prediction_logs</code>. Baseline
    <strong>{{ \App\Services\PredictionLogger::VERSION_BASELINE }}</strong> reflects the current pipeline:
    1X2 probabilities come from Groq (LLM); Over 2.5 / BTTS are 50/50 blends of Groq and the internal Poisson;
    Over 1.5 / Over 3.5 are pure Poisson. New engines land here for ship-gate comparison.
    Comparisons must stay like-for-like — pre-lineup vs pre-lineup, never across stages.
</div>

<form method="GET" class="mm-filters">
    <label>
        Stage
        <select name="stage">
            <option value="pre_lineup"  {{ $stage === 'pre_lineup'  ? 'selected' : '' }}>Pre-lineup</option>
            <option value="post_lineup" {{ $stage === 'post_lineup' ? 'selected' : '' }}>Post-lineup</option>
        </select>
    </label>
    <label>
        Backfilled rows
        <select name="include_backfill">
            <option value="1" {{ $includeBackfill ? 'selected' : '' }}>Include (default)</option>
            <option value="0" {{ ! $includeBackfill ? 'selected' : '' }}>Exclude (clean baseline)</option>
        </select>
    </label>
    <label>
        Calibration for version
        <select name="bucket_version">
            <option value="">—</option>
            @foreach($versions as $v)
                <option value="{{ $v }}" {{ $bucketVersion === $v ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
    </label>
    <label>
        Market
        <select name="bucket_market">
            <option value="">—</option>
            @foreach($markets as $m)
                <option value="{{ $m }}" {{ $bucketMarket === $m ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
        </select>
    </label>
    <button type="submit">Apply</button>
</form>

<div class="mm-section-title">Overview by model version</div>
@if(empty($overview))
    <div class="mm-empty">No prediction_logs rows for the selected filters yet. Run <code>php artisan predictions:seed-logs</code> to backfill.</div>
@else
<div class="mm-scroll"><table class="mm-table">
    <thead>
        <tr>
            <th>Model version</th>
            <th class="num">Logged</th>
            <th class="num">Settled</th>
            <th class="num">Pending</th>
            <th class="num">Void</th>
        </tr>
    </thead>
    <tbody>
    @foreach($overview as $row)
        <tr>
            <td><span class="mm-tag">{{ $row->model_version }}</span></td>
            <td class="num">{{ number_format($row->total) }}</td>
            <td class="num">{{ number_format($row->settled_n) }}</td>
            <td class="num">{{ number_format($row->pending_n) }}</td>
            <td class="num">{{ number_format($row->void_n) }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endif

<div class="mm-section-title">By market — Brier is primary (lower is better). Δ vs market: negative = beats the bookmaker consensus</div>
@if(empty($byMarket))
    <div class="mm-empty">No settled logs yet.</div>
@else
<div class="mm-scroll"><table class="mm-table">
    <thead>
        <tr>
            <th>Version</th>
            <th>Market</th>
            <th class="num">n</th>
            <th class="num">Hit rate</th>
            <th class="num">Brier</th>
            <th class="num">Log-loss</th>
            <th class="num">Avg stated</th>
            <th class="num">Δ vs market</th>
            <th class="num">Paired n</th>
        </tr>
    </thead>
    <tbody>
    @foreach($byMarket as $row)
        @php $hitRate = $row->settled_n > 0 ? $row->wins / $row->settled_n : null; @endphp
        <tr>
            <td><span class="mm-tag">{{ $row->model_version }}</span></td>
            <td>{{ $row->market }}</td>
            <td class="num">{{ number_format($row->settled_n) }}</td>
            <td class="num">{{ $pct($hitRate) }}</td>
            <td class="num {{ $brierClass($row->brier) }}">{{ $fmt($row->brier) }}</td>
            <td class="num">{{ $fmt($row->log_loss) }}</td>
            <td class="num">{{ $pct($row->avg_stated_prob) }}</td>
            <td class="num {{ $deltaClass($row->delta_vs_market ?? null) }}">{{ $signed($row->delta_vs_market ?? null) }}</td>
            <td class="num">{{ number_format($row->paired_n ?? 0) }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endif

<div class="mm-section-title">By league (min 20 settled) — Δ vs market identifies where edge is possible</div>
@if(empty($byLeague))
    <div class="mm-empty">No league has 20+ settled logs yet.</div>
@else
<div class="mm-scroll"><table class="mm-table">
    <thead>
        <tr>
            <th>Version</th>
            <th>League ID</th>
            <th class="num">n</th>
            <th class="num">Hit rate</th>
            <th class="num">Brier</th>
            <th class="num">Δ vs market</th>
            <th class="num">Paired n</th>
        </tr>
    </thead>
    <tbody>
    @foreach($byLeague as $row)
        @php $hitRate = $row->settled_n > 0 ? $row->wins / $row->settled_n : null; @endphp
        <tr>
            <td><span class="mm-tag">{{ $row->model_version }}</span></td>
            <td>{{ $row->league_id ?? '—' }}</td>
            <td class="num">{{ number_format($row->settled_n) }}</td>
            <td class="num">{{ $pct($hitRate) }}</td>
            <td class="num {{ $brierClass($row->brier) }}">{{ $fmt($row->brier) }}</td>
            <td class="num {{ $deltaClass($row->delta_vs_market ?? null) }}">{{ $signed($row->delta_vs_market ?? null) }}</td>
            <td class="num">{{ number_format($row->paired_n ?? 0) }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endif

@if(!empty($calibration))
<div class="mm-section-title">Calibration — {{ $bucketVersion }} / {{ $bucketMarket }}</div>
<div class="mm-scroll"><table class="mm-table">
    <thead>
        <tr>
            <th>Bucket</th>
            <th class="num">n</th>
            <th class="num">Avg stated prob</th>
            <th class="num">Realized frequency</th>
            <th class="num">Gap (realized − stated)</th>
        </tr>
    </thead>
    <tbody>
    @foreach($calibration as $b)
        @php
            $lo  = ((int) $b->bucket) * 10;
            $hi  = $lo + 10;
            $gap = $b->realized - $b->stated;
            $gapClass = abs($gap) < 0.03 ? 'mm-diag-ok' : (abs($gap) < 0.07 ? 'mm-diag-warn' : 'mm-diag-bad');
        @endphp
        <tr>
            <td>{{ $lo }}–{{ $hi }}%</td>
            <td class="num">{{ number_format($b->n) }}</td>
            <td class="num">{{ $pct($b->stated) }}</td>
            <td class="num">{{ $pct($b->realized) }}</td>
            <td class="num {{ $gapClass }}">{{ ($gap >= 0 ? '+' : '') . number_format($gap * 100, 1) }}pp</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endif

@endsection
