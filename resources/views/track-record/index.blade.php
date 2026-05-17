@extends('layouts.app')

@section('title', 'AI Track Record | TavsScore: Verified Football Prediction Accuracy')
@section('meta_description', 'See exactly how our AI football predictions perform over time. Real verified results, month by month. No cherry-picking. The complete track record.')
@section('og_image', asset('images/og-track-record.jpg'))

@push('styles')
<style>
    .tr-hero { padding: 2.5rem 0 2rem; border-bottom: 1px solid var(--border); margin-bottom: 2rem; }
    .tr-badge { display:inline-flex; align-items:center; gap:.4rem; background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.25); color:#6ee7b7; font-size:.68rem; font-weight:800; padding:3px 10px; border-radius:999px; letter-spacing:.04em; margin-bottom:.75rem; }
    .tr-title { font-size:1.6rem; font-weight:900; color:#fff; letter-spacing:-.02em; margin-bottom:.5rem; }
    .tr-sub   { font-size:.83rem; color:var(--text-dim); line-height:1.65; max-width:560px; }

    .tr-stats { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:.75rem; margin-bottom:2rem; }
    .tr-stat  { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1rem 1.1rem; }
    .tr-stat-lbl { font-size:.63rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.3rem; }
    .tr-stat-val { font-size:1.5rem; font-weight:900; color:#fff; line-height:1; }
    .tr-stat-sub { font-size:.65rem; color:var(--text-dim); margin-top:.3rem; }
    .tr-stat-green  { color:#34d399; }
    .tr-stat-amber  { color:#fbbf24; }
    .tr-stat-red    { color:#f87171; }

    .tr-section       { margin-bottom:2.5rem; }
    .tr-section-title { font-size:.77rem; font-weight:800; color:var(--text-dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; }
    .tr-section-title::after { content:''; flex:1; height:1px; background:var(--border); }

    /* Monthly timeline */
    .tr-timeline { display:flex; flex-direction:column; gap:.5rem; }
    .tr-month-row { display:grid; grid-template-columns:80px 1fr 52px 80px; align-items:center; gap:.75rem; }
    .tr-month-lbl { font-size:.72rem; color:var(--text-dim); font-weight:600; text-align:right; }
    .tr-bar-bg    { height:22px; background:rgba(255,255,255,.05); border-radius:6px; overflow:hidden; position:relative; }
    .tr-bar-fill  { height:100%; border-radius:6px; transition:width .4s; display:flex; align-items:center; padding:0 .5rem; }
    .tr-bar-green { background:linear-gradient(90deg,rgba(16,185,129,.7),rgba(16,185,129,.4)); }
    .tr-bar-amber { background:linear-gradient(90deg,rgba(251,191,36,.7),rgba(251,191,36,.4)); }
    .tr-bar-red   { background:linear-gradient(90deg,rgba(239,68,68,.7),rgba(239,68,68,.4)); }
    .tr-bar-gray  { background:rgba(255,255,255,.08); }
    .tr-bar-pct   { font-size:.67rem; font-weight:800; color:#fff; white-space:nowrap; }
    .tr-month-pct { font-size:.75rem; font-weight:800; text-align:right; }
    .tr-month-cnt { font-size:.65rem; color:var(--text-dim); }

    /* Improvement card */
    .tr-improve { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1.5rem; margin-bottom:2rem; }
    .tr-improve-row { display:grid; grid-template-columns:1fr auto 1fr; gap:1rem; align-items:center; }
    .tr-improve-box { text-align:center; padding:1rem; background:rgba(255,255,255,.03); border-radius:10px; }
    .tr-improve-label { font-size:.65rem; color:var(--text-dim); font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.4rem; }
    .tr-improve-pct   { font-size:2rem; font-weight:900; line-height:1; }
    .tr-improve-sub   { font-size:.65rem; color:var(--text-dim); margin-top:.3rem; }
    .tr-improve-arrow { font-size:1.6rem; text-align:center; }
    .tr-improve-delta { text-align:center; margin-top:.75rem; font-size:.8rem; font-weight:700; }

    /* Calibration */
    .tr-calib-grid { display:flex; flex-direction:column; gap:.6rem; }
    .tr-calib-row  { display:grid; grid-template-columns:90px 1fr 52px 90px; align-items:center; gap:.75rem; }
    .tr-calib-lbl  { font-size:.7rem; color:var(--text-dim); font-weight:600; text-align:right; }
    .tr-calib-pct  { font-size:.72rem; font-weight:800; text-align:right; }
    .tr-calib-gap  { font-size:.65rem; text-align:right; }
    .tr-gap-pos    { color:#34d399; }
    .tr-gap-neg    { color:#f87171; }
    .tr-gap-neutral{ color:var(--text-dim); }

    /* Snapshot timeline */
    .tr-snap-timeline { display:flex; flex-direction:column; gap:0; position:relative; }
    .tr-snap-timeline::before { content:''; position:absolute; left:119px; top:12px; bottom:12px; width:2px; background:var(--border); }
    .tr-snap-row  { display:grid; grid-template-columns:110px 20px 1fr; gap:.75rem; align-items:start; padding:.6rem 0; }
    .tr-snap-period { font-size:.72rem; color:var(--text-dim); font-weight:700; text-align:right; padding-top:.15rem; }
    .tr-snap-dot  { width:12px; height:12px; border-radius:50%; background:#818cf8; border:2px solid var(--card-bg, #0f1117); margin-top:.2rem; flex-shrink:0; position:relative; z-index:1; }
    .tr-snap-content { background:var(--card); border:1px solid var(--border); border-radius:10px; padding:.75rem 1rem; }
    .tr-snap-pills  { display:flex; flex-wrap:wrap; gap:.35rem; }
    .tr-snap-pill   { font-size:.62rem; font-weight:700; padding:2px 8px; border-radius:999px; border:1px solid; }
    .tr-snap-thresh { background:rgba(99,102,241,.1);  border-color:rgba(99,102,241,.3); color:#c4b5fd; }
    .tr-snap-acc    { background:rgba(16,185,129,.1);  border-color:rgba(16,185,129,.3); color:#6ee7b7; }
    .tr-snap-calib  { background:rgba(251,191,36,.1);  border-color:rgba(251,191,36,.3); color:#fde68a; }
    .tr-snap-cold   { background:rgba(239,68,68,.1);   border-color:rgba(239,68,68,.3); color:#fca5a5; }

    .tr-empty { text-align:center; padding:3rem 1rem; background:var(--card); border:1px dashed var(--border); border-radius:12px; }
    .tr-empty-icon { font-size:2rem; margin-bottom:.5rem; }
    .tr-empty-msg  { font-size:.82rem; color:var(--text-dim); max-width:360px; margin:0 auto; line-height:1.6; }

    .tr-disclaimer { font-size:.7rem; color:var(--text-dim); line-height:1.7; padding:1rem; background:rgba(255,255,255,.02); border:1px solid var(--border); border-radius:8px; margin-bottom:2rem; }

    @media(max-width:600px) {
        .tr-month-row  { grid-template-columns:60px 1fr 42px 50px; }
        .tr-calib-row  { grid-template-columns:70px 1fr 42px 60px; }
        .tr-improve-row { grid-template-columns:1fr auto 1fr; gap:.5rem; }
        .tr-improve-pct { font-size:1.5rem; }
        .tr-snap-timeline::before { display:none; }
        .tr-snap-row { grid-template-columns:1fr; }
        .tr-snap-period { text-align:left; }
        .tr-snap-dot { display:none; }
    }
</style>
@endpush

@section('content')
<div class="wrap">

    {{-- Hero --}}
    <div class="tr-hero">
        <div class="tr-badge">📈 VERIFIED TRACK RECORD</div>
        <h1 class="tr-title">Our AI Picks: The Real Results</h1>
        <p class="tr-sub">
            No cherry-picking. No spin. Every daily pick we've ever made is tracked, resolved, and shown below exactly as it happened.
            The system learns from every result to get stricter and sharper over time.
        </p>
    </div>

    @if($total === 0)
    <div class="tr-empty">
        <div class="tr-empty-icon">⏳</div>
        <p class="tr-empty-msg">The track record builds up as picks are resolved. Check back after a few weeks of predictions.</p>
    </div>
    @else

    {{-- Stat strip --}}
    <div class="tr-stats">
        <div class="tr-stat">
            <div class="tr-stat-lbl">Total Picks Made</div>
            <div class="tr-stat-val">{{ number_format($total) }}</div>
            <div class="tr-stat-sub">all time, verified</div>
        </div>
        <div class="tr-stat">
            <div class="tr-stat-lbl">Overall Accuracy</div>
            <div class="tr-stat-val {{ $overall !== null ? ($overall >= 55 ? 'tr-stat-green' : ($overall >= 45 ? 'tr-stat-amber' : 'tr-stat-red')) : '' }}">
                {{ $overall !== null ? $overall . '%' : '—' }}
            </div>
            <div class="tr-stat-sub">{{ $correct }} correct of {{ $total }}</div>
        </div>
        @if($bestMonth && $bestMonth['pct'] !== null)
        <div class="tr-stat">
            <div class="tr-stat-lbl">Best Month</div>
            <div class="tr-stat-val tr-stat-green">{{ $bestMonth['pct'] }}%</div>
            <div class="tr-stat-sub">{{ $bestMonth['label'] }} · {{ $bestMonth['total'] }} picks</div>
        </div>
        @endif
        <div class="tr-stat">
            <div class="tr-stat-lbl">Current Streak</div>
            <div class="tr-stat-val {{ $streak['type'] === 'win' ? 'tr-stat-green' : ($streak['type'] === 'loss' ? 'tr-stat-red' : '') }}">
                @if($streak['count'] > 0)
                    {{ $streak['type'] === 'win' ? '🔥' : '❄️' }} {{ $streak['count'] }}
                @else N/A @endif
            </div>
            <div class="tr-stat-sub">
                @if($streak['count'] > 0) {{ $streak['type'] === 'win' ? 'correct in a row' : 'wrong in a row' }}
                @else no streak yet @endif
            </div>
        </div>
    </div>

    {{-- Improvement story --}}
    @if($improvement)
    <div class="tr-section">
        <div class="tr-section-title">📊 Early Days vs Now</div>
        <div class="tr-improve">
            <div class="tr-improve-row">
                <div class="tr-improve-box">
                    <div class="tr-improve-label">First 30 days</div>
                    <div class="tr-improve-pct {{ $improvement['is_better'] ? 'tr-stat-amber' : 'tr-stat-green' }}">
                        {{ $improvement['early_pct'] }}%
                    </div>
                    <div class="tr-improve-sub">{{ $improvement['early_picks'] }} picks</div>
                </div>
                <div class="tr-improve-arrow">→</div>
                <div class="tr-improve-box">
                    <div class="tr-improve-label">Last 30 days</div>
                    <div class="tr-improve-pct {{ $improvement['is_better'] ? 'tr-stat-green' : 'tr-stat-red' }}">
                        {{ $improvement['recent_pct'] }}%
                    </div>
                    <div class="tr-improve-sub">{{ $improvement['recent_picks'] }} picks</div>
                </div>
            </div>
            <div class="tr-improve-delta">
                @if($improvement['delta'] > 0)
                    <span style="color:#34d399;">↑ +{{ $improvement['delta'] }}pp improvement</span>
                    . The AI has learned from {{ $total }} resolved picks and is getting sharper.
                @elseif($improvement['delta'] < 0)
                    <span style="color:#f87171;">↓ {{ $improvement['delta'] }}pp</span>
                    . A tough recent stretch; the system is adjusting its thresholds automatically.
                @else
                    <span style="color:var(--text-dim);">Stable accuracy</span>. Consistent performance across both periods.
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Monthly timeline --}}
    <div class="tr-section">
        <div class="tr-section-title">📅 Month-by-Month</div>
        @if($monthly->isEmpty())
        <p style="color:var(--text-dim);font-size:.8rem;">Not enough data yet.</p>
        @else
        <div class="tr-timeline">
            @foreach($monthly as $m)
            @php
                $pct      = $m['pct'] ?? 0;
                $barClass = $pct >= 60 ? 'tr-bar-green' : ($pct >= 50 ? 'tr-bar-amber' : ($pct > 0 ? 'tr-bar-red' : 'tr-bar-gray'));
                $pctClass = $pct >= 60 ? 'tr-stat-green' : ($pct >= 50 ? 'tr-stat-amber' : 'tr-stat-red');
            @endphp
            <div class="tr-month-row">
                <div class="tr-month-lbl">{{ $m['label'] }}</div>
                <div class="tr-bar-bg">
                    <div class="tr-bar-fill {{ $barClass }}" style="width:{{ min(100, $pct) }}%">
                        @if($pct >= 20)
                        <span class="tr-bar-pct">{{ $pct }}%</span>
                        @endif
                    </div>
                </div>
                <div class="tr-month-pct {{ $m['pct'] !== null ? $pctClass : '' }}">
                    {{ $m['pct'] !== null ? $m['pct'] . '%' : '—' }}
                </div>
                <div class="tr-month-cnt">{{ $m['total'] }} pick{{ $m['total'] !== 1 ? 's' : '' }}</div>
            </div>
            @endforeach
        </div>
        <p style="font-size:.67rem;color:var(--text-dim);margin-top:.75rem;">Green = ≥ 60% · Amber = 50–59% · Red = &lt; 50% · Minimum 5 picks to qualify as representative.</p>
        @endif
    </div>

    {{-- Confidence calibration --}}
    <div class="tr-section">
        <div class="tr-section-title">🎯 Does Our Confidence Match Reality?</div>
        <p style="font-size:.78rem;color:var(--text-dim);margin-bottom:1rem;line-height:1.6;">
            When our AI says 80% confident, does it actually win 80% of the time? The gap between stated and actual is the calibration error.
            A smaller gap means the AI is being more honest about what it knows.
        </p>
        <div class="tr-calib-grid">
            @foreach($calibration as $b)
            @php
                $barPct   = $b['pct'] ?? 0;
                $barClass = $barPct >= 60 ? 'tr-bar-green' : ($barPct >= 50 ? 'tr-bar-amber' : ($barPct > 0 ? 'tr-bar-red' : 'tr-bar-gray'));
                $gapClass = 'tr-gap-neutral';
                if ($b['gap'] !== null) {
                    $gapClass = $b['gap'] >= -5 ? 'tr-gap-pos' : 'tr-gap-neg';
                }
            @endphp
            <div class="tr-calib-row">
                <div class="tr-calib-lbl">{{ $b['label'] }}</div>
                <div class="tr-bar-bg">
                    <div class="tr-bar-fill {{ $barClass }}" style="width:{{ min(100, $barPct) }}%"></div>
                </div>
                <div class="tr-calib-pct">{{ $b['pct'] !== null ? $b['pct'] . '%' : '—' }}</div>
                <div class="tr-calib-gap {{ $gapClass }}">
                    @if($b['gap'] !== null)
                        {{ $b['gap'] > 0 ? '+' : '' }}{{ $b['gap'] }}pp gap
                        @if(!$b['trusted'])<span style="opacity:.5;"> (low n)</span>@endif
                    @else
                        <span style="opacity:.5;">insufficient data</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        <p style="font-size:.67rem;color:var(--text-dim);margin-top:.75rem;">
            Gap = Actual win% − AI confidence midpoint. Negative means AI overstated confidence. Trend toward 0 = system improving its self-awareness.
        </p>
    </div>

    {{-- System evolution timeline (snapshots) --}}
    <div class="tr-section">
        <div class="tr-section-title">🧠 How the System Has Evolved</div>
        @if($snapshots->isEmpty())
        <div class="tr-empty" style="padding:2rem 1rem;">
            <div class="tr-empty-icon">🌱</div>
            <p class="tr-empty-msg">
                Monthly evolution snapshots start recording from now. After a few months this section will show exactly how the AI's confidence threshold and calibration accuracy have changed, concrete proof of the system learning.
            </p>
        </div>
        @else
        <p style="font-size:.78rem;color:var(--text-dim);margin-bottom:1rem;line-height:1.6;">
            Every month the system saves a snapshot of its current state. A rising threshold means the AI got stricter. A calibration error trending toward zero means it's getting more honest.
        </p>
        <div class="tr-snap-timeline">
            @foreach($snapshots as $snap)
            @php
                $errStr  = $snap->calibration_error_avg !== null ? round($snap->calibration_error_avg, 1) . 'pp' : '—';
                $errSign = (float)($snap->calibration_error_avg ?? 0) >= 0 ? '+' : '';
            @endphp
            <div class="tr-snap-row">
                <div class="tr-snap-period">{{ \Carbon\Carbon::createFromFormat('Y-m', $snap->period_label)->format('M Y') }}</div>
                <div><div class="tr-snap-dot"></div></div>
                <div class="tr-snap-content">
                    <div class="tr-snap-pills">
                        <span class="tr-snap-pill tr-snap-thresh">Min threshold: {{ $snap->threshold }}%</span>
                        @if($snap->acc_pct !== null)
                        <span class="tr-snap-pill tr-snap-acc">30d accuracy: {{ $snap->acc_pct }}%</span>
                        @endif
                        @if($snap->calibration_error_avg !== null)
                        <span class="tr-snap-pill tr-snap-calib">Calib error: {{ $errSign }}{{ $errStr }}</span>
                        @endif
                        @if($snap->cold_markets_count > 0)
                        <span class="tr-snap-pill tr-snap-cold">{{ $snap->cold_markets_count }} cold market{{ $snap->cold_markets_count !== 1 ? 's' : '' }}</span>
                        @endif
                        @if($snap->total_picks > 0)
                        <span class="tr-snap-pill" style="background:rgba(255,255,255,.04);border-color:var(--border);color:var(--text-dim);">{{ $snap->total_picks }} picks that month</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Disclaimer --}}
    <div class="tr-disclaimer">
        🔞 18+ only · Past performance does not guarantee future results. Football is inherently unpredictable and even the best AI models will lose picks.
        This track record is published for transparency. Always treat predictions as informed opinions, not certainties.
        Never stake money you cannot afford to lose. <a href="{{ route('terms') }}" style="color:var(--text-dim);">Terms</a>
    </div>

    @endif {{-- end $total > 0 --}}

</div>
@endsection
