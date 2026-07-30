@extends('layouts.admin')
@section('title', 'Prediction Stats')
@section('page-title', 'Prediction Stats')

@push('styles')
<style>
    .proof-hero { position:relative; overflow:hidden; display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:1.15rem; margin-bottom:1rem; border:1px solid rgba(16,185,129,.24); border-radius:15px; background:radial-gradient(circle at 88% 5%,rgba(16,185,129,.18),transparent 28%),linear-gradient(125deg,#102238,#101928); }
    .proof-hero::after { content:'PROOF'; position:absolute; right:-.2rem; bottom:-1.3rem; color:rgba(255,255,255,.035); font-size:5rem; font-weight:950; letter-spacing:-.09em; pointer-events:none; }.proof-hero > * { position:relative; z-index:1; }.proof-kicker { color:#86efac; font-size:.59rem; font-weight:900; letter-spacing:.12em; text-transform:uppercase; }.proof-hero h1 { margin:.38rem 0 .26rem; color:#fff; font-size:1.3rem; letter-spacing:-.04em; }.proof-hero p { max-width:620px; margin:0; color:#cbd5e1; font-size:.72rem; line-height:1.55; }
    @media(max-width:620px) { .proof-hero { display:block; }.proof-hero .btn-a { margin-top:.75rem; } }
</style>
@endpush

@section('content')

<section class="proof-hero">
    <div><div class="proof-kicker">More &amp; proof · performance control room</div><h1>Prediction statistics</h1><p>Review the full resolved sample, market accuracy and league performance before deciding whether a prediction board should keep its current publication standard.</p></div>
    <a href="{{ route('stats.index') }}" target="_blank" class="btn-a btn-blue">↗ View Public Page</a>
</section>

{{-- Overall accuracy --}}
<div class="stat-grid" style="margin-bottom:1.25rem;">
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(16,185,129,.1),rgba(16,185,129,.04));border-color:rgba(16,185,129,.25);">
        <span class="stat-val" style="color:#6ee7b7;">{{ $overall !== null ? $overall . '%' : '-' }}</span>
        <span class="stat-lbl">⭐ Daily accuracy</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#6ee7b7;">{{ $correct }}</span>
        <span class="stat-lbl">✓ Correct picks</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#fca5a5;">{{ $total - $correct }}</span>
        <span class="stat-lbl">✗ Incorrect picks</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ $total }}</span>
        <span class="stat-lbl">🎯 Total resolved</span>
    </div>
</div>

{{-- Draw & GG pick suites --}}
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); margin-bottom:1.25rem;">
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(245,158,11,.10),rgba(245,158,11,.04));border-color:rgba(245,158,11,.25);">
        <span class="stat-val" style="color:#fcd34d;">{{ $drawStat['pct'] !== null ? $drawStat['pct'].'%' : 'N/A' }}</span>
        <span class="stat-lbl">🤝 Draw Picks accuracy</span>
    </div>
    <div class="stat-card" style="background:rgba(245,158,11,.04);">
        <span class="stat-val" style="color:#6ee7b7;">{{ $drawStat['correct'] }}</span>
        <span class="stat-lbl">✓ Draw correct</span>
    </div>
    <div class="stat-card" style="background:rgba(245,158,11,.04);">
        <span class="stat-val">{{ $drawStat['total'] }}</span>
        <span class="stat-lbl">🎯 Draw resolved</span>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(16,185,129,.10),rgba(16,185,129,.04));border-color:rgba(16,185,129,.25);">
        <span class="stat-val" style="color:#6ee7b7;">{{ $ggStat['pct'] !== null ? $ggStat['pct'].'%' : 'N/A' }}</span>
        <span class="stat-lbl">⚽ GG Picks accuracy</span>
    </div>
    <div class="stat-card" style="background:rgba(16,185,129,.04);">
        <span class="stat-val" style="color:#6ee7b7;">{{ $ggStat['correct'] }}</span>
        <span class="stat-lbl">✓ GG correct</span>
    </div>
    <div class="stat-card" style="background:rgba(16,185,129,.04);">
        <span class="stat-val">{{ $ggStat['total'] }}</span>
        <span class="stat-lbl">🎯 GG resolved</span>
    </div>
</div>

{{-- By outcome --}}
<div class="a-card" style="margin-bottom:1.25rem;">
    <div style="font-weight:700; font-size:.9rem; color:#fff; margin-bottom:.875rem;">By Outcome Type</div>
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>Market</th>
                    <th>Correct</th>
                    <th>Total</th>
                    <th>Accuracy</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byOutcome as $outcome => $data)
                @php $acc = $data['total'] > 0 ? round($data['correct'] / $data['total'] * 100, 1) : 0; @endphp
                <tr>
                    <td style="color:#fff; font-weight:600;">{{ $outcome }}</td>
                    <td style="color:#6ee7b7; font-weight:700;">{{ $data['correct'] }}</td>
                    <td>{{ $data['total'] }}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:.5rem;">
                            <div style="flex:1; height:4px; background:rgba(255,255,255,.08); border-radius:2px; max-width:80px;">
                                <div style="height:100%; width:{{ $acc }}%; background:{{ $acc >= 60 ? '#10b981' : ($acc >= 45 ? '#f59e0b' : '#ef4444') }}; border-radius:2px;"></div>
                            </div>
                            <span style="font-weight:700; color:{{ $acc >= 60 ? '#6ee7b7' : ($acc >= 45 ? '#fcd34d' : '#fca5a5') }};">{{ $acc }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- By league --}}
@if(! empty($byLeague))
<div class="a-card" style="margin-bottom:1.25rem;">
    <div style="font-weight:700; font-size:.9rem; color:#fff; margin-bottom:.875rem;">By League (top 15)</div>
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>League</th>
                    <th>Correct</th>
                    <th>Total</th>
                    <th>Accuracy</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byLeague->take(15) as $league => $data)
                @php $acc = $data['total'] > 0 ? round($data['correct'] / $data['total'] * 100, 1) : 0; @endphp
                <tr>
                    <td style="color:#fff;">{{ $league }}</td>
                    <td style="color:#6ee7b7; font-weight:700;">{{ $data['correct'] }}</td>
                    <td>{{ $data['total'] }}</td>
                    <td style="font-weight:700; color:{{ $acc >= 60 ? '#6ee7b7' : ($acc >= 45 ? '#fcd34d' : '#fca5a5') }};">{{ $acc }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Weekly trend --}}
@if(isset($weekly) && $weekly->isNotEmpty())
<div class="a-card">
    <div style="font-weight:700; font-size:.9rem; color:#fff; margin-bottom:.875rem;">Weekly Trend (last 8 weeks)</div>
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>Week</th>
                    <th>Correct</th>
                    <th>Total</th>
                    <th>Accuracy</th>
                </tr>
            </thead>
            <tbody>
                @foreach($weekly as $week)
                @php $acc = $week['total'] > 0 ? round($week['correct'] / $week['total'] * 100, 1) : 0; @endphp
                <tr>
                    <td style="color:var(--dim);">{{ $week['label'] }}</td>
                    <td style="color:#6ee7b7; font-weight:700;">{{ $week['correct'] }}</td>
                    <td>{{ $week['total'] }}</td>
                    <td style="font-weight:700; color:{{ $acc >= 60 ? '#6ee7b7' : ($acc >= 45 ? '#fcd34d' : '#fca5a5') }};">{{ $acc }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
