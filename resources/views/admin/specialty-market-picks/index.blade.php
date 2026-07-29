@extends('layouts.admin')

@section('title', $config['title'] . ' Admin')
@section('page-title', $config['title'])

@section('content')
<div class="page-hd">
    <span class="page-hd-title">{{ $config['icon'] }} {{ $config['title'] }} Picks</span>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.' . $config['admin_route'] . '.rebuild') }}" onsubmit="return confirm('Pull latest fixtures, rebuild model boards, then select and send only 90%+ {{ $config['title'] }} picks?');">
            @csrf
            <button class="btn-a btn-green">↻ Pull data + rebuild</button>
        </form>
        <form method="POST" action="{{ route('admin.' . $config['admin_route'] . '.refresh') }}" onsubmit="return confirm('Re-select stored {{ $config['title'] }} data and notify qualified picks?');">
            @csrf
            <button class="btn-a" style="background:linear-gradient(135deg,#164e63,#0e7490);color:#a5f3fc;">↻ Re-select Today</button>
        </form>
        <a class="btn-a btn-blue" href="{{ route($config['route']) }}" target="_blank" rel="noopener">↗ View Public Page</a>
    </div>
</div>

<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:1.25rem;">
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(103,232,249,.1),rgba(59,130,246,.04));border-color:rgba(103,232,249,.25);"><span class="stat-val" style="color:#67e8f9;">{{ $todayCards->count() }}</span><span class="stat-lbl">Today's 90% signals</span></div>
    <div class="stat-card"><span class="stat-val" style="color:#6ee7b7;">{{ $correct }}</span><span class="stat-lbl">✓ Total correct</span></div>
    <div class="stat-card"><span class="stat-val" style="color:#fca5a5;">{{ $total - $correct }}</span><span class="stat-lbl">✗ Total wrong</span></div>
    <div class="stat-card"><span class="stat-val">{{ $total ? round($correct / $total * 100, 1).'%' : '—' }}</span><span class="stat-lbl">Historical accuracy</span></div>
</div>

<section class="a-card" style="margin-bottom:1.25rem;border-color:rgba(103,232,249,.24);">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700;font-size:.9rem;color:#fff;">{{ $config['icon'] }} Today's {{ $config['title'] }} Picks <span style="background:rgba(103,232,249,.15);color:#67e8f9;font-size:.65rem;padding:1px 8px;border-radius:999px;margin-left:.4rem;">{{ $todayCards->count() }}</span></span>
        <span style="font-size:.72rem;color:var(--dim);">{{ now('Africa/Lagos')->format('M d, Y') }}</span>
    </div>

    @if($todayCards->isEmpty())
        <div style="text-align:center;padding:1.75rem;color:var(--dim);font-size:.85rem;">No {{ $config['title'] }} picks qualified today.<br><span style="font-size:.75rem;">The exact market must reach the 90% confidence gate before it is published.</span></div>
    @else
        <div style="overflow-x:auto;"><table class="a-table" style="min-width:850px;"><thead><tr><th># Rank</th><th>Match</th><th>League</th><th>Market</th><th>Probability</th><th>Kickoff (Lagos)</th><th>Reason</th><th>Status</th></tr></thead><tbody>
        @foreach($todayCards as $card)
            @php
                $pick = $card['pick'];
                $match = $pick->match;
                $finished = $match && in_array($match->status, ['FT', 'AET', 'PEN'], true) && $match->home_score !== null;
                $result = $finished ? \App\Support\PickHelpers::resolveForMatch($match, $card['label']) : null;
            @endphp
            <tr>
                <td style="font-weight:800;color:#67e8f9;">#{{ $pick->{$config['rank']} }}</td>
                <td>@include('admin.partials.fixture-mini', ['match' => $match]) @if($finished)<span style="color:var(--dim);font-size:.72rem;margin-left:.35rem;">({{ $match->home_score }}–{{ $match->away_score }})</span>@endif</td>
                <td style="color:var(--dim);font-size:.74rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ \App\Support\LeagueCoverage::formatName($match?->league, $match?->league_country) }}</td>
                <td style="color:#a5f3fc;font-weight:700;white-space:nowrap;">{{ $card['label'] }} @if($card['european_start'])<small style="color:var(--dim);">({{ $card['european_start'] }} · {{ $card['european_selection'] }})</small>@endif</td>
                <td style="color:#67e8f9;font-weight:800;">{{ $card['probability'] }}%</td>
                <td style="color:var(--dim);font-size:.74rem;white-space:nowrap;">{{ $match?->match_time?->timezone('Africa/Lagos')->format('H:i') ?? '—' }}</td>
                <td style="min-width:170px;"><details><summary style="cursor:pointer;color:#a5f3fc;font-size:.72rem;font-weight:700;">View model reason</summary><div style="margin-top:.45rem;display:grid;gap:.35rem;">@forelse($card['reasons'] as $reason)<span style="font-size:.7rem;line-height:1.4;color:var(--dim);">• {{ $reason }}</span>@empty<span style="font-size:.7rem;color:var(--dim);">Exact market reached the 90% confidence gate.</span>@endforelse</div></details></td>
                <td>@if($finished)<span class="badge {{ $result ? 'badge-green' : 'badge-red' }}">{{ $result ? '✓ Won' : '✗ Lost' }}</span>@elseif($match && in_array($match->status, ['1H','HT','2H','ET','BT','P','LIVE'], true))<span class="badge badge-red">🔴 Live</span>@else<span class="badge badge-gray">⏳ Pending</span>@endif</td>
            </tr>
        @endforeach
        </tbody></table></div>
    @endif
</section>

<section class="a-card">
    <div class="page-hd" style="margin-bottom:.875rem;"><span style="font-weight:700;font-size:.9rem;color:#fff;">📜 {{ $config['title'] }} History</span><span style="font-size:.72rem;color:var(--dim);">Last {{ $history->count() }} published picks</span></div>
    @if($history->isEmpty())
        <div style="text-align:center;padding:1.75rem;color:var(--dim);font-size:.85rem;">No previous {{ $config['title'] }} picks recorded yet.</div>
    @else
        <div style="overflow-x:auto;"><table class="a-table" style="min-width:680px;"><thead><tr><th>Date</th><th>Match</th><th>Market</th><th>Score</th><th>Status</th></tr></thead><tbody>
        @foreach($history as $pick)
            @php
                $match = $pick->match;
                $label = $config['market'] ?? $pick->{$config['label_field']};
                $finished = $match && in_array($match->status, ['FT', 'AET', 'PEN'], true) && $match->home_score !== null;
                $result = $finished ? \App\Support\PickHelpers::resolveForMatch($match, $label) : null;
            @endphp
            <tr><td style="color:var(--dim);font-size:.74rem;white-space:nowrap;">{{ $match?->match_time?->timezone('Africa/Lagos')->format('d M Y') ?? '—' }}</td><td>@include('admin.partials.fixture-mini', ['match' => $match])</td><td style="color:#a5f3fc;font-weight:700;">{{ $label }}</td><td style="color:var(--dim);">@if($finished){{ $match->home_score }}–{{ $match->away_score }}@else—@endif</td><td>@if($finished)<span class="badge {{ $result ? 'badge-green' : 'badge-red' }}">{{ $result ? '✓ Won' : '✗ Lost' }}</span>@else<span class="badge badge-gray">⏳ Pending</span>@endif</td></tr>
        @endforeach
        </tbody></table></div>
    @endif
</section>

<div style="margin-top:1.25rem;padding:.875rem 1rem;border-radius:8px;background:rgba(103,232,249,.07);border:1px solid rgba(103,232,249,.18);font-size:.74rem;color:#a5f3fc;">
    <strong>ℹ️ Quality gate:</strong> this page only publishes an exact {{ $config['title'] }} market when it reaches at least 90% probability after the latest model-board rebuild. No fixture is forced into the list.
</div>
@endsection
