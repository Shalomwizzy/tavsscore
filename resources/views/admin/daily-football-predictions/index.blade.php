@extends('layouts.admin')
@section('title', 'Daily Football Predictions')
@section('page-title', 'Daily Football Predictions')

@section('content')
<div class="page-hd">
    <span class="page-hd-title">📅 Daily Football Predictions</span>
    <span style="color:var(--dim);font-size:.78rem;">{{ $meta['pretty'] }}</span>
</div>

<div class="a-card" style="margin-bottom:1rem;display:flex;gap:.55rem;align-items:center;flex-wrap:wrap;">
    <a href="{{ route('admin.daily-football-predictions.index', ['date' => $meta['previous_iso']]) }}" class="btn-a btn-gray">← Previous</a>
    <a href="{{ route('admin.daily-football-predictions.index') }}" class="btn-a {{ $meta['is_today'] ? 'btn-green' : 'btn-gray' }}">Today</a>
    <a href="{{ route('admin.daily-football-predictions.index', ['date' => $meta['yesterday_iso']]) }}" class="btn-a {{ $meta['is_yesterday'] ? 'btn-green' : 'btn-gray' }}">Yesterday</a>
    @if($meta['next_iso'])<a href="{{ route('admin.daily-football-predictions.index', ['date' => $meta['next_iso']]) }}" class="btn-a btn-gray">Next →</a>@endif
    <form method="GET" style="margin-left:auto"><input type="date" name="date" value="{{ $meta['iso'] }}" max="{{ $meta['today_iso'] }}" class="form-input" style="width:auto;padding:.42rem .55rem;" onchange="this.form.submit()"></form>
</div>

<div style="display:flex;gap:.55rem;flex-wrap:wrap;margin:0 0 1rem;">
    <span class="badge badge-gray">{{ $summary['total'] }} picks</span>
    <span class="badge badge-green">{{ $summary['won'] }} won</span>
    <span class="badge badge-red">{{ $summary['lost'] }} lost</span>
    @if($summary['pending'])<span class="badge badge-gray">{{ $summary['pending'] }} pending</span>@endif
</div>

<div class="a-card"><div style="overflow-x:auto"><table class="a-table"><thead><tr><th>Time</th><th>Match</th><th>League</th><th>Predicted Outcome</th><th>Score</th><th>Result</th></tr></thead><tbody>
@forelse($predictions as $prediction)
    @php($match = $prediction->match)
    <tr>
        <td style="color:var(--dim);white-space:nowrap;">{{ $match?->match_time?->timezone(config('app.timezone'))->format('H:i') ?? '—' }}</td>
        <td style="color:#fff;font-weight:650;">{{ $match?->home_team ?? '?' }} vs {{ $match?->away_team ?? '?' }}</td>
        <td style="color:var(--dim);">{{ \App\Support\LeagueCoverage::formatName($match?->league, $match?->league_country) }}</td>
        <td style="color:#93c5fd;font-weight:700;">{{ $prediction->predicted_outcome ?? '—' }}</td>
        <td style="font-weight:700;">{{ $match?->home_score !== null ? $match->home_score.'–'.$match->away_score : '—' }}</td>
        <td>@if($prediction->was_correct === true)<span class="badge badge-green">✓ Won</span>@elseif($prediction->was_correct === false)<span class="badge badge-red">✗ Lost</span>@else<span class="badge badge-gray">⏳ Pending</span>@endif</td>
    </tr>
@empty
    <tr><td colspan="6" style="padding:2.5rem;text-align:center;color:var(--dim);">No football predictions were generated for this date.</td></tr>
@endforelse
</tbody></table></div></div>
@endsection
