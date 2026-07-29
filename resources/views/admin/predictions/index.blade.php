@extends('layouts.admin')
@section('title', 'Predictions')
@section('page-title', 'Predictions')

@push('styles')
<style>
    .pred-row { cursor:pointer; transition:background 140ms; }
    .pred-row:hover td { background:rgba(255,255,255,.03) !important; }
    .pred-row .expander {
        width:18px; display:inline-flex; justify-content:center;
        color:var(--dim); transition:transform 200ms; font-size:.7rem;
    }
    .pred-row.is-open .expander { transform:rotate(90deg); color:#6ee7b7; }
    .pred-detail-row td { padding:0 !important; background:rgba(0,0,0,.18); border-top:1px solid var(--border); }
    .pred-detail-inner {
        padding:1rem 1.1rem; display:grid;
        grid-template-columns: 1fr 1fr 1fr; gap:1rem;
    }
    .pred-detail-block { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:.75rem .85rem; min-width:0; }
    .pred-detail-label { font-size:.66rem; font-weight:800; color:var(--dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; display:flex; align-items:center; gap:.35rem; }
    .pred-detail-text  { font-size:.78rem; color:var(--text); line-height:1.6; white-space:pre-wrap; word-break:break-word; }
    .pred-detail-empty { font-size:.74rem; color:var(--dim); font-style:italic; }
    .lang-flag { font-size:.85rem; }
    .control-hero { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; padding:1.2rem; border:1px solid rgba(16,185,129,.24); border-radius:12px; background:radial-gradient(circle at 80% 0%,rgba(16,185,129,.15),transparent 34%),linear-gradient(135deg,#101b2d,#0a111e); }
    .control-kicker { color:#6ee7b7; font-size:.66rem; font-weight:900; text-transform:uppercase; letter-spacing:.09em; }
    .control-title { color:#fff; font-weight:900; font-size:1.4rem; letter-spacing:-.03em; margin:.3rem 0; }
    .control-sub { color:var(--dim); font-size:.75rem; max-width:560px; line-height:1.5; }
    .control-actions { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .control-date { display:flex; gap:.4rem; align-items:center; flex-wrap:wrap; margin-bottom:1rem; }
    .control-date .btn-a { padding:.38rem .65rem; font-size:.72rem; }
    .metric-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:.65rem; margin-bottom:1rem; }
    .metric-card { padding:.8rem .85rem; background:var(--card); border:1px solid var(--border); border-radius:10px; }
    .metric-value { color:#fff; font-size:1.3rem; font-weight:900; letter-spacing:-.03em; }
    .metric-label { color:var(--dim); font-size:.63rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; margin-top:.3rem; }
    .admin-match-cell { display:flex; align-items:center; gap:.55rem; min-width:180px; }
    .admin-club-marks { width:27px; flex-shrink:0; position:relative; height:31px; }
    .admin-club-mark { position:absolute; width:21px; height:21px; display:grid; place-items:center; border-radius:50%; color:#fff; font-size:.52rem; font-weight:900; background:linear-gradient(135deg,#285d92,#0b2545); border:1px solid rgba(147,197,253,.35); }
    .admin-club-mark:last-child { right:0; bottom:0; background:linear-gradient(135deg,#1c785d,#093a31); border-color:rgba(110,231,183,.3); }
    .admin-match-teams { min-width:0; }
    .admin-match-teams strong { display:block; color:#fff; font-size:.76rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .admin-match-teams span { display:block; color:var(--dim); font-size:.65rem; margin-top:.12rem; }
    @media (max-width:850px) { .metric-grid { grid-template-columns:repeat(3,1fr); } }
    @media (max-width:900px) { .pred-detail-inner { grid-template-columns: 1fr; } }
    @media (max-width:520px) { .metric-grid { grid-template-columns:repeat(2,1fr); } }
</style>
@endpush

@section('content')

<section class="control-hero">
    <div>
        <div class="control-kicker">TavsScore operations</div>
        <div class="control-title">Prediction Control Centre</div>
        <div class="control-sub">Monitor every football prediction for the selected date, check verified outcomes, and generate a fresh model run when new match data arrives.</div>
    </div>
    <div class="control-actions">
        <a href="{{ route('admin.daily-football-predictions.index', ['date' => $dateMeta['iso']]) }}" class="btn-a btn-gray">📅 Daily Results</a>
        <form method="POST" action="{{ route('admin.predictions.rebuild') }}" onsubmit="return confirm('Pull latest fixtures and rebuild every football prediction board?');">@csrf<button type="submit" class="btn-a btn-green">↻ Pull data + rebuild all</button></form>
        <form method="POST" action="{{ route('admin.predictions.generate') }}">@csrf<button type="submit" class="btn-a btn-green">✦ Generate Predictions</button></form>
    </div>
</section>

<div class="control-date">
    <a href="{{ route('admin.predictions', ['date' => $dateMeta['previous_iso']]) }}" class="btn-a btn-gray">← Previous</a>
    <a href="{{ route('admin.predictions') }}" class="btn-a {{ $dateMeta['is_today'] ? 'btn-green' : 'btn-gray' }}">Today</a>
    <a href="{{ route('admin.predictions', ['date' => $dateMeta['yesterday_iso']]) }}" class="btn-a {{ $dateMeta['iso'] === $dateMeta['yesterday_iso'] ? 'btn-green' : 'btn-gray' }}">Yesterday</a>
    @if($dateMeta['next_iso'])<a href="{{ route('admin.predictions', ['date' => $dateMeta['next_iso']]) }}" class="btn-a btn-gray">Next →</a>@endif
    <form method="GET" style="margin-left:auto"><input type="date" name="date" value="{{ $dateMeta['iso'] }}" max="{{ $dateMeta['today_iso'] }}" class="form-input" style="width:auto;padding:.38rem .5rem;" onchange="this.form.submit()"></form>
</div>

<div class="metric-grid">
    <div class="metric-card"><div class="metric-value">{{ $metrics['total'] }}</div><div class="metric-label">Predictions</div></div>
    <div class="metric-card"><div class="metric-value" style="color:#6ee7b7">{{ $metrics['won'] }}</div><div class="metric-label">Won</div></div>
    <div class="metric-card"><div class="metric-value" style="color:#fca5a5">{{ $metrics['lost'] }}</div><div class="metric-label">Lost</div></div>
    <div class="metric-card"><div class="metric-value" style="color:#fcd34d">{{ $metrics['pending'] }}</div><div class="metric-label">Pending</div></div>
    <div class="metric-card"><div class="metric-value" style="color:#93c5fd">{{ $metrics['accuracy'] !== null ? $metrics['accuracy'].'%' : '—' }}</div><div class="metric-label">Accuracy</div></div>
</div>

<div class="a-card">
    <div style="overflow-x:auto">
        <table class="a-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Match</th>
                    <th>League</th>
                    <th>Home Win</th>
                    <th>Draw</th>
                    <th>Away Win</th>
                    <th>Over 2.5</th>
                    <th>BTTS</th>
                    <th>Verdict</th>
                    <th>Result</th>
                    <th>Generated</th>
                </tr>
            </thead>
            <tbody>
                @forelse($predictions as $pred)
                @php
                    $m = $pred->match;
                    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(3)->map(fn ($part) => mb_substr($part, 0, 1))->join('');
                @endphp
                <tr class="pred-row" data-pred="{{ $pred->id }}">
                    <td><span class="expander">▶</span></td>
                    <td>
                        @if($pred->is_daily_pick)<span title="Daily Pick #{{ $pred->pick_rank }}" style="display:inline-block;margin-right:.25rem;vertical-align:middle;">{{ $pred->pick_rank === 1 ? '👑' : '⭐' }}</span>@endif
                        @include('admin.partials.fixture-mini', ['match' => $m])
                    </td>
                    <td style="color:var(--dim); max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ \App\Support\LeagueCoverage::formatName($m?->league, $m?->league_country) }}</td>
                    <td style="color:#6ee7b7; font-weight:700; font-variant-numeric:tabular-nums;">{{ number_format($pred->home_win_prob, 1) }}%</td>
                    <td style="color:#fcd34d; font-weight:700; font-variant-numeric:tabular-nums;">{{ number_format($pred->draw_prob, 1) }}%</td>
                    <td style="color:#fca5a5; font-weight:700; font-variant-numeric:tabular-nums;">{{ number_format($pred->away_win_prob, 1) }}%</td>
                    <td style="font-weight:700; font-variant-numeric:tabular-nums; color:{{ $pred->over_25_prob >= 60 ? '#6ee7b7' : ($pred->over_25_prob <= 40 ? '#fca5a5' : '#fcd34d') }}">
                        {{ number_format($pred->over_25_prob ?? 0, 1) }}%
                    </td>
                    <td style="font-weight:700; font-variant-numeric:tabular-nums; color:{{ ($pred->btts_prob ?? 0) >= 60 ? '#6ee7b7' : (($pred->btts_prob ?? 0) <= 40 ? '#fca5a5' : '#fcd34d') }}">
                        {{ number_format($pred->btts_prob ?? 0, 1) }}%
                    </td>
                    <td style="color:#93c5fd; font-size:.75rem; font-weight:700; white-space:nowrap;">
                        {{ $pred->predicted_outcome ?? '-' }}
                    </td>
                    <td>
                        @if($pred->was_correct === true)
                            <span class="badge badge-green">✓ Won</span>
                        @elseif($pred->was_correct === false)
                            <span class="badge badge-red">✗ Lost</span>
                        @else
                            <span style="color:var(--dim); font-size:.72rem;">-</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); font-size:.72rem; white-space:nowrap;">{{ $pred->created_at->format('M d H:i') }}</td>
                </tr>
                <tr class="pred-detail-row" id="detail-{{ $pred->id }}" style="display:none;">
                    <td colspan="11">
                        <div class="pred-detail-inner">
                            <div class="pred-detail-block">
                                <div class="pred-detail-label"><span class="lang-flag">🇬🇧</span> English Analysis</div>
                                @if(!blank($pred->analysis) && $pred->analysis !== \App\Services\GroqService::FALLBACK_ANALYSIS)
                                    <div class="pred-detail-text">{{ $pred->analysis }}</div>
                                @else
                                    <div class="pred-detail-empty">No analysis (statistical model only or generation pending).</div>
                                @endif
                            </div>
                            <div class="pred-detail-block">
                                <div class="pred-detail-label"><span class="lang-flag">🇳🇬</span> Pidgin</div>
                                @if(!blank($pred->analysis_pidgin))
                                    <div class="pred-detail-text">{{ $pred->analysis_pidgin }}</div>
                                @else
                                    <div class="pred-detail-empty">Not yet translated. Cached on first user click on /picks.</div>
                                @endif
                            </div>
                            <div class="pred-detail-block">
                                <div class="pred-detail-label"><span class="lang-flag">🇰🇪</span> Swahili</div>
                                @if(!blank($pred->analysis_swahili))
                                    <div class="pred-detail-text">{{ $pred->analysis_swahili }}</div>
                                @else
                                    <div class="pred-detail-empty">Not yet translated. Cached on first user click on /picks.</div>
                                @endif
                            </div>
                            <div class="pred-detail-block" style="grid-column:1/-1;">
                                <div class="pred-detail-label">🎯 Market Board @if(!empty($pred->market_board))({{ count($pred->market_board) }} markets)@endif</div>
                                @if(!empty($pred->market_board))
                                    <div style="max-height:300px; overflow-y:auto;">
                                    @foreach(\App\Support\MarketCategories::group($pred->market_board) as $category => $markets)
                                        <div style="font-size:.66rem; font-weight:800; color:var(--dim); text-transform:uppercase; letter-spacing:.05em; margin:.5rem 0 .2rem;">{{ $category }}</div>
                                        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:.1rem .9rem;">
                                            @foreach($markets as $label => $prob)
                                            <div style="display:flex; justify-content:space-between; font-size:.74rem; border-bottom:1px solid rgba(255,255,255,.04); padding:.15rem 0;">
                                                <span style="color:var(--text);">{{ $label }}</span>
                                                <span style="color:#6ee7b7; font-weight:700;">{{ number_format($prob,1) }}%</span>
                                            </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                    </div>
                                @else
                                    <div class="pred-detail-empty">No market board yet — regenerates on the next predict:matches run.</div>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" style="color:var(--dim); text-align:center; padding:2.5rem;">
                        No predictions yet. First fetch matches, then click "Generate Predictions".
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($predictions->hasPages())
    @include('admin.partials.pagination', ['paginator' => $predictions])
    @endif
</div>

@push('scripts')
<script>
document.querySelectorAll('.pred-row').forEach(function (row) {
    row.addEventListener('click', function () {
        var id = row.getAttribute('data-pred');
        var detail = document.getElementById('detail-' + id);
        if (!detail) return;
        var open = detail.style.display !== 'none';
        detail.style.display = open ? 'none' : 'table-row';
        row.classList.toggle('is-open', !open);
    });
});
</script>
@endpush

@endsection
