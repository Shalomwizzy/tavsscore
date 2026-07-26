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
    @media (max-width:900px) { .pred-detail-inner { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

<div class="page-hd">
    <span class="page-hd-title">📊 Predictions</span>
    <form method="POST" action="{{ route('admin.predictions.generate') }}">
        @csrf
        <button type="submit" class="btn-a btn-green">🔄 Generate Predictions</button>
    </form>
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
                @php $m = $pred->match; @endphp
                <tr class="pred-row" data-pred="{{ $pred->id }}">
                    <td><span class="expander">▶</span></td>
                    <td style="color:#fff; font-weight:600; white-space:nowrap;">
                        @if($pred->is_daily_pick)
                            <span title="Daily Pick #{{ $pred->pick_rank }}" style="margin-right:.25rem;">{{ $pred->pick_rank === 1 ? '👑' : '⭐' }}</span>
                        @endif
                        {{ $m?->home_team ?? '?' }} vs {{ $m?->away_team ?? '?' }}
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
                                    @php $board = $pred->market_board; arsort($board); @endphp
                                    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:.15rem .9rem; max-height:260px; overflow-y:auto;">
                                        @foreach($board as $label => $prob)
                                        <div style="display:flex; justify-content:space-between; font-size:.74rem; border-bottom:1px solid rgba(255,255,255,.04); padding:.15rem 0;">
                                            <span style="color:var(--text);">{{ $label }}</span>
                                            <span style="color:#6ee7b7; font-weight:700;">{{ number_format($prob,1) }}%</span>
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
