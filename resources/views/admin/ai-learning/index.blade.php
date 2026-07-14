@extends('layouts.admin')
@section('title', 'AI Learning — TavsScore Admin')

@push('styles')
<style>
    .al-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; margin-bottom:1.5rem; }
    .al-title  { font-size:1.3rem; font-weight:900; color:#fff; }
    .al-sub    { font-size:.78rem; color:var(--text-dim); margin-top:.2rem; }
    .al-ts     { font-size:.68rem; color:var(--text-dim); }

    .al-strip  { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:.75rem; margin-bottom:1.75rem; }
    .al-stat   { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1rem; }
    .al-stat-lbl  { font-size:.65rem; color:var(--text-dim); font-weight:700; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.3rem; }
    .al-stat-val  { font-size:1.45rem; font-weight:900; color:#fff; line-height:1; }
    .al-stat-sub  { font-size:.65rem; color:var(--text-dim); margin-top:.3rem; }
    .al-stat-ok   { color:#34d399; }
    .al-stat-warn { color:#fbbf24; }
    .al-stat-bad  { color:#f87171; }

    .al-section       { margin-bottom:2rem; }
    .al-section-title { font-size:.8rem; font-weight:800; color:var(--text-dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.75rem; border-bottom:1px solid var(--border); padding-bottom:.4rem; }

    .al-table { width:100%; border-collapse:collapse; font-size:.78rem; }
    .al-table th { text-align:left; font-size:.65rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; padding:.4rem .75rem; border-bottom:1px solid var(--border); }
    .al-table td { padding:.55rem .75rem; border-bottom:1px solid rgba(255,255,255,.04); color:var(--text); vertical-align:middle; }
    .al-table tr:last-child td { border-bottom:none; }
    .al-table tr:hover td { background:rgba(255,255,255,.02); }

    .al-pill { display:inline-flex; align-items:center; gap:.25rem; font-size:.62rem; font-weight:800; padding:2px 8px; border-radius:999px; }
    .al-pill-cold { background:rgba(239,68,68,.12);  border:1px solid rgba(239,68,68,.25); color:#fca5a5; }
    .al-pill-hot  { background:rgba(52,211,153,.12); border:1px solid rgba(52,211,153,.25); color:#6ee7b7; }
    .al-pill-ok   { background:rgba(99,102,241,.1);  border:1px solid rgba(99,102,241,.25); color:#c4b5fd; }
    .al-pill-warn { background:rgba(251,191,36,.1);  border:1px solid rgba(251,191,36,.25); color:#fde68a; }

    .al-bar-wrap { display:flex; align-items:center; gap:.5rem; }
    .al-bar-bg   { flex:1; height:6px; background:rgba(255,255,255,.07); border-radius:999px; overflow:hidden; min-width:60px; }
    .al-bar-fill { height:100%; border-radius:999px; }
    .al-bar-good { background:#34d399; }
    .al-bar-ok   { background:#818cf8; }
    .al-bar-warn { background:#fbbf24; }
    .al-bar-bad  { background:#f87171; }

    .al-gap-positive { color:#34d399; }
    .al-gap-negative { color:#f87171; }

    .al-recal-btn { background:rgba(99,102,241,.15); border:1px solid rgba(99,102,241,.3); color:#c4b5fd; font-size:.75rem; font-weight:700; padding:.45rem 1rem; border-radius:8px; cursor:pointer; transition:background 150ms; }
    .al-recal-btn:hover { background:rgba(99,102,241,.25); }

    .al-threshold-box { background:rgba(99,102,241,.08); border:1px solid rgba(99,102,241,.2); border-radius:10px; padding:.875rem 1rem; margin-bottom:1.75rem; font-size:.8rem; color:var(--text); line-height:1.6; }
    .al-threshold-box strong { color:#fff; }

    .al-cold-alert { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2); border-radius:10px; padding:.75rem 1rem; margin-bottom:1.75rem; font-size:.78rem; color:#fca5a5; line-height:1.6; }

    @media(max-width:640px){ .al-strip { grid-template-columns:1fr 1fr; } }
</style>
@endpush

@section('content')
<div style="max-width:900px;">

    {{-- Header --}}
    <div class="al-header">
        <div>
            <div class="al-title">🧠 AI Self-Calibration</div>
            <div class="al-sub">The system learns from its resolved picks and automatically adjusts quality thresholds.</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;">
            <form method="POST" action="{{ route('admin.ai-learning.recalibrate') }}">
                @csrf
                <button type="submit" class="al-recal-btn">↺ Force Recalibrate</button>
            </form>
            <div class="al-ts">Computed: {{ \Carbon\Carbon::parse($state['computed_at'])->diffForHumans() }}</div>
        </div>
    </div>

    @if(session('success'))
    <div style="background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);color:#6ee7b7;border-radius:8px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.78rem;">
        ✅ {{ session('success') }}
    </div>
    @endif

    {{-- Top stat strip --}}
    <div class="al-strip">
        <div class="al-stat">
            <div class="al-stat-lbl">Learned Threshold</div>
            <div class="al-stat-val">{{ $state['threshold'] }}%</div>
            <div class="al-stat-sub">min AI confidence for picks</div>
        </div>
        <div class="al-stat">
            <div class="al-stat-lbl">30-Day Accuracy</div>
            <div class="al-stat-val {{ $state['acc_30d'] !== null ? ($state['acc_30d'] >= 55 ? 'al-stat-ok' : ($state['acc_30d'] >= 45 ? 'al-stat-warn' : 'al-stat-bad')) : '' }}">
                {{ $state['acc_30d'] !== null ? $state['acc_30d'] . '%' : '—' }}
            </div>
            <div class="al-stat-sub">{{ $state['total_30d'] }} picks resolved</div>
        </div>
        <div class="al-stat">
            <div class="al-stat-lbl">90-Day Accuracy</div>
            <div class="al-stat-val {{ $state['acc_90d'] !== null ? ($state['acc_90d'] >= 55 ? 'al-stat-ok' : ($state['acc_90d'] >= 45 ? 'al-stat-warn' : 'al-stat-bad')) : '' }}">
                {{ $state['acc_90d'] !== null ? $state['acc_90d'] . '%' : '—' }}
            </div>
            <div class="al-stat-sub">{{ $state['total_90d'] }} picks resolved</div>
        </div>
        <div class="al-stat">
            <div class="al-stat-lbl">7-Day Accuracy</div>
            <div class="al-stat-val {{ $state['acc_7d'] !== null ? ($state['acc_7d'] >= 55 ? 'al-stat-ok' : ($state['acc_7d'] >= 45 ? 'al-stat-warn' : 'al-stat-bad')) : '' }}">
                {{ $state['acc_7d'] !== null ? $state['acc_7d'] . '%' : '—' }}
            </div>
            <div class="al-stat-sub">
                @if($state['trend'] === 'improving') 📈 Improving vs 90d avg
                @elseif($state['trend'] === 'declining') 📉 Declining vs 90d avg
                @else ➡️ Stable vs 90d avg
                @endif
            </div>
        </div>
        <div class="al-stat">
            <div class="al-stat-lbl">Cold Markets</div>
            <div class="al-stat-val {{ count($state['cold_markets']) > 0 ? 'al-stat-warn' : 'al-stat-ok' }}">
                {{ count($state['cold_markets']) }}
            </div>
            <div class="al-stat-sub">extra penalty applied</div>
        </div>
    </div>

    {{-- Learned threshold explanation --}}
    <div class="al-threshold-box">
        <strong>Current threshold: {{ $state['threshold'] }}%</strong> —
        {{ $state['threshold_why'] }}.
        @if($state['threshold'] > 65)
            The system raised the bar above the default 65% because lower confidence bands have not been profitable.
        @elseif($state['threshold'] < 65)
            The system found evidence that confident picks down to {{ $state['threshold'] }}% have been profitable — more picks may be selected.
        @else
            Running at the default 65% threshold — build more history for the system to learn a better value.
        @endif
    </div>

    {{-- Cold markets alert --}}
    @if(count($state['cold_markets']) > 0)
    <div class="al-cold-alert">
        ❄️ <strong>Cold market streak (last 14 days):</strong>
        {{ implode(', ', $state['cold_markets']) }}.
        These markets receive an extra 0.60× scoring penalty in daily pick selection — they will only appear if no better alternatives exist.
    </div>
    @endif

    {{-- Confidence calibration --}}
    <div class="al-section">
        <div class="al-section-title">Confidence Calibration — AI says X%, history shows Y%</div>
        <table class="al-table">
            <thead>
                <tr>
                    <th>AI Confidence Band</th>
                    <th>Picks</th>
                    <th>Actual Win%</th>
                    <th>Calibration Error</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($state['confidence_bands'] as $b)
                @php
                    $midpoint = ($b['min'] + $b['max']) / 2;
                    $gapClass = '';
                    $statusLabel = 'Insufficient data';
                    $statusPill  = 'al-pill-warn';
                    if ($b['pct'] !== null) {
                        $gapClass    = $b['gap'] >= 0 ? 'al-gap-positive' : 'al-gap-negative';
                        $statusLabel = $b['gap'] >= 0 ? 'AI underconfident ✓' : 'AI overconfident ⚠️';
                        $statusPill  = $b['gap'] >= -5 ? 'al-pill-ok' : 'al-pill-warn';
                        if ($b['pct'] < 45) { $statusLabel = 'Underperforming ✗'; $statusPill = 'al-pill-cold'; }
                    }
                    $barPct   = $b['pct'] ?? 0;
                    $barClass = $barPct >= 60 ? 'al-bar-good' : ($barPct >= 50 ? 'al-bar-ok' : ($barPct >= 40 ? 'al-bar-warn' : 'al-bar-bad'));
                @endphp
                <tr>
                    <td style="font-weight:700;color:#fff;">{{ $b['label'] }}</td>
                    <td style="color:var(--text-dim);">{{ $b['total'] }}</td>
                    <td>
                        <div class="al-bar-wrap">
                            <div class="al-bar-bg">
                                <div class="al-bar-fill {{ $barClass }}" style="width:{{ min(100, $barPct) }}%"></div>
                            </div>
                            <span style="min-width:38px;text-align:right;">
                                {{ $b['pct'] !== null ? $b['pct'] . '%' : '—' }}
                            </span>
                        </div>
                    </td>
                    <td class="{{ $gapClass }}" style="font-weight:700;">
                        @if($b['gap'] !== null)
                            {{ $b['gap'] > 0 ? '+' : '' }}{{ $b['gap'] }}pp
                        @else
                            —
                        @endif
                    </td>
                    <td><span class="al-pill {{ $statusPill }}">{{ $statusLabel }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <p style="font-size:.68rem;color:var(--text-dim);margin-top:.6rem;">
            Calibration error = Actual Win% − Midpoint of AI confidence band. Negative = AI is overconfident.
            Minimum {{ \App\Services\AdaptiveThresholdService::CACHE_HOURS }}h cache; use Force Recalibrate to update immediately.
        </p>
    </div>

    {{-- Market performance --}}
    <div class="al-section">
        <div class="al-section-title">Market Performance — last 90 days</div>
        @if(empty($state['market_perf']))
            <p style="color:var(--text-dim);font-size:.8rem;">No resolved picks yet — keep running predictions.</p>
        @else
        <table class="al-table">
            <thead>
                <tr>
                    <th>Market</th>
                    <th>Picks (90d)</th>
                    <th>Win%</th>
                    <th>Recent (14d)</th>
                    <th>System Weight</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($state['market_perf'] as $m)
                @php
                    $barPct   = $m['pct'] ?? 0;
                    $barClass = $barPct >= 60 ? 'al-bar-good' : ($barPct >= 50 ? 'al-bar-ok' : ($barPct >= 40 ? 'al-bar-warn' : 'al-bar-bad'));
                    $wLabel   = number_format($m['weight'], 2);
                    $wColor   = $m['weight'] >= 1.10 ? '#34d399' : ($m['weight'] >= 1.0 ? 'var(--text)' : '#fbbf24');
                @endphp
                <tr>
                    <td style="font-weight:700;color:#fff;max-width:200px;">{{ $m['market'] }}</td>
                    <td style="color:var(--text-dim);">{{ $m['total'] }}</td>
                    <td>
                        <div class="al-bar-wrap">
                            <div class="al-bar-bg">
                                <div class="al-bar-fill {{ $barClass }}" style="width:{{ min(100, $barPct) }}%"></div>
                            </div>
                            <span style="min-width:38px;text-align:right;">{{ $m['pct'] !== null ? $m['pct'] . '%' : '—' }}</span>
                        </div>
                    </td>
                    <td style="color:var(--text-dim);">
                        @if($m['recent_pct'] !== null) {{ $m['recent_pct'] }}% ({{ $m['recent_total'] }})
                        @else <span style="opacity:.5;">—</span>
                        @endif
                    </td>
                    <td style="font-weight:800;color:{{ $wColor }};">×{{ $wLabel }}</td>
                    <td>
                        @if($m['is_cold'])
                            <span class="al-pill al-pill-cold">❄️ Cold streak</span>
                        @elseif($m['is_hot'])
                            <span class="al-pill al-pill-hot">🔥 Hot streak</span>
                        @else
                            <span class="al-pill al-pill-ok">Steady</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- League reliability --}}
    <div class="al-section">
        <div class="al-section-title">League Reliability — last 90 days (≥5 picks)</div>
        @if(empty($state['league_perf']))
            <p style="color:var(--text-dim);font-size:.8rem;">Not enough per-league history yet.</p>
        @else
        <table class="al-table">
            <thead>
                <tr>
                    <th>League</th>
                    <th>Country</th>
                    <th>Picks</th>
                    <th>Win%</th>
                    <th>System Weight</th>
                </tr>
            </thead>
            <tbody>
                @foreach($state['league_perf'] as $l)
                @php
                    $barPct   = $l['pct'];
                    $barClass = $barPct >= 60 ? 'al-bar-good' : ($barPct >= 50 ? 'al-bar-ok' : ($barPct >= 40 ? 'al-bar-warn' : 'al-bar-bad'));
                    $wColor   = $l['weight'] >= 1.10 ? '#34d399' : ($l['weight'] >= 1.0 ? 'var(--text)' : '#fbbf24');
                @endphp
                <tr>
                    <td style="font-weight:700;color:#fff;">{{ $l['league'] }}</td>
                    <td style="color:var(--text-dim);">{{ $l['country'] }}</td>
                    <td style="color:var(--text-dim);">{{ $l['total'] }}</td>
                    <td>
                        <div class="al-bar-wrap">
                            <div class="al-bar-bg">
                                <div class="al-bar-fill {{ $barClass }}" style="width:{{ min(100, $barPct) }}%"></div>
                            </div>
                            <span style="min-width:38px;text-align:right;">{{ $l['pct'] }}%</span>
                        </div>
                    </td>
                    <td style="font-weight:800;color:{{ $wColor }};">×{{ number_format($l['weight'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ── 5-season historical backtest calibration (Phase 2/4) ────────── --}}
    @if(! empty($state['historical']))
        @php $h = $state['historical']; @endphp
        <div class="al-section" style="margin-top:2.25rem;">
            <div class="al-section-title" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
                <span>5-Season Historical Calibration — Dixon-Coles walk-forward backtest</span>
                <span style="display:inline-block; background:linear-gradient(135deg,#10b981,#3b82f6); color:#fff; font-size:.55rem; font-weight:900; padding:2px 7px; border-radius:999px; letter-spacing:.04em;">{{ $h['version'] }}</span>
            </div>

            <div class="al-threshold-box" style="background:rgba(16,185,129,.06); border-color:rgba(16,185,129,.20); margin-bottom:1rem;">
                <strong>{{ number_format($h['total_predictions']) }}</strong> held-out predictions across 5 seasons of the 9 priority European leagues.
                Overall win rate: <strong>{{ $h['overall_pct'] ?? '—' }}%</strong>.
                @if(! empty($h['advisory_threshold']))
                    Historical data suggests a threshold at <strong>{{ $h['advisory_threshold'] }}%</strong> — the lowest band where the DC model was profitable over 5 seasons.
                    The operational threshold above is set independently from live picks. If the two diverge sharply, it's worth investigating why.
                @endif
                <br><span style="color:var(--text-dim); font-size:.7rem;">Advisory only — does not adjust live pick selection. Use <code>/admin/model-metrics</code> for full drill-down.</span>
            </div>

            @foreach($h['by_market'] as $market => $bands)
                @php
                    $marketLabel = match($market) {
                        '1X2'    => 'Home / Draw / Away',
                        'over25' => 'Over 2.5 Goals',
                        'over15' => 'Over 1.5 Goals',
                        'gg'     => 'Both Teams Score',
                        default  => $market,
                    };
                @endphp
                <div style="margin-bottom:1.25rem;">
                    <div style="font-size:.72rem; font-weight:800; color:#fff; margin-bottom:.4rem;">
                        {{ $marketLabel }}
                        <span style="color:var(--text-dim); font-weight:600; font-size:.68rem;">
                            ({{ number_format(collect($bands)->sum('total')) }} predictions)
                        </span>
                    </div>
                    <table class="al-table">
                        <thead>
                            <tr>
                                <th>Stated confidence</th>
                                <th style="text-align:right;">Predictions</th>
                                <th style="text-align:right;">Wins</th>
                                <th style="text-align:right;">Actual %</th>
                                <th style="text-align:right;">Gap</th>
                                <th>Reliable?</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bands as $b)
                            <tr @if(! $b['trusted']) style="opacity:.55;" @endif>
                                <td style="font-weight:700;">{{ $b['band'] }}%</td>
                                <td style="text-align:right;">{{ number_format($b['total']) }}</td>
                                <td style="text-align:right;">{{ number_format($b['wins']) }}</td>
                                <td style="text-align:right;">{{ $b['pct'] !== null ? $b['pct'] . '%' : '—' }}</td>
                                <td style="text-align:right;" @if($b['gap'] !== null) class="{{ $b['gap'] >= 0 ? 'al-gap-positive' : 'al-gap-negative' }}" @endif>
                                    @if($b['gap'] !== null){{ $b['gap'] >= 0 ? '+' : '' }}{{ $b['gap'] }}pp @else — @endif
                                </td>
                                <td>
                                    @if($b['trusted'])
                                        <span class="al-pill al-pill-hot">✓ trusted</span>
                                    @else
                                        <span class="al-pill al-pill-warn">thin</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endif

    <p style="font-size:.68rem;color:var(--text-dim);padding-bottom:2rem;">
        Operational data (top section) covers resolved daily picks over 90 days. Historical calibration (bottom section) covers the 5-season DC backtest — deeper history, but from the model, not live picks. League and market weights are applied silently during <code>picks:select</code>. Confidence threshold adjusts daily pick selection automatically. Cache refreshes every {{ \App\Services\AdaptiveThresholdService::CACHE_HOURS }}h or on Force Recalibrate.
    </p>

</div>
@endsection
