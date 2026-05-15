@extends('layouts.admin')
@section('title', 'Correct Score Predictions')
@section('page-title', 'Correct Score Predictions')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">🎯 Correct Score Predictions</span>
    <a href="{{ route('correct-score.index') }}" target="_blank" class="btn-a btn-blue">↗ View Public Page</a>
</div>

{{-- Today --}}
<div class="a-card" style="margin-bottom:1.25rem; border-color:rgba(139,92,246,.3);">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">
            🎯 Today's Correct Score Predictions
            <span style="background:rgba(139,92,246,.15);color:#c4b5fd;font-size:.65rem;padding:1px 8px;border-radius:999px;margin-left:.4rem;">{{ $todayPredictions->count() }}</span>
        </span>
        <span style="font-size:.72rem; color:var(--dim);">{{ now('Africa/Lagos')->format('M d, Y') }}</span>
    </div>

    @if($todayPredictions->isEmpty())
        <p style="font-size:.8rem; color:var(--dim); text-align:center; padding:1.25rem;">
            No correct score predictions today yet. They auto-generate when predictions run.
        </p>
    @else
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>Match</th>
                    <th>League</th>
                    <th>AI Tip</th>
                    <th>Conf</th>
                    <th>Top Scorelines</th>
                    <th>Kickoff (Lagos)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($todayPredictions as $pred)
                @php $m = $pred->match; $scores = $pred->likely_scores ?? []; @endphp
                <tr>
                    <td style="color:#fff; font-weight:600; white-space:nowrap;">{{ $m?->home_team }} vs {{ $m?->away_team }}</td>
                    <td style="color:var(--dim); font-size:.74rem; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $m?->league }}</td>
                    <td style="color:#6ee7b7; font-weight:700;">{{ $pred->predicted_outcome ?? '-' }}</td>
                    <td style="font-weight:700; color:#fff;">{{ $pred->confidence ?? '-' }}%</td>
                    <td>
                        <div style="display:flex; gap:.3rem; flex-wrap:wrap;">
                            @foreach(array_slice($scores, 0, 4) as $i => $s)
                            <span style="background:rgba(139,92,246,.15);color:#c4b5fd;border:1px solid rgba(139,92,246,.3);padding:1px 7px;border-radius:4px;font-weight:700;font-size:.72rem;{{ $i === 0 ? 'border-color:rgba(139,92,246,.5);' : '' }}">
                                {{ $s['score'] }} <span style="opacity:.7;">({{ $s['pct'] }}%)</span>
                            </span>
                            @endforeach
                        </div>
                    </td>
                    <td style="color:var(--dim); font-size:.74rem;">{{ $m?->match_time?->setTimezone('Africa/Lagos')->format('H:i') }}</td>
                    <td>
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span class="badge badge-gray">FT {{ $m->home_score }}-{{ $m->away_score }}</span>
                        @elseif($m && in_array($m->status, ['1H','HT','2H','ET','BT','P']))
                            <span class="badge" style="background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.3);">🔴 LIVE</span>
                        @else
                            <span class="badge badge-green">Upcoming</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- History --}}
<div class="a-card">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">📜 History</span>
    </div>

    @if($history->isEmpty())
        <p style="font-size:.8rem; color:var(--dim); text-align:center; padding:1.25rem;">No historical correct score predictions yet.</p>
    @else
        @foreach($history as $date => $dayPreds)
        <div style="margin-bottom:1rem;">
            <div style="padding:.35rem .25rem; border-bottom:1px solid var(--border); margin-bottom:.35rem;">
                <span style="font-weight:700; font-size:.78rem; color:var(--text);">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</span>
                <span style="font-size:.68rem; color:var(--dim); margin-left:.5rem;">({{ $dayPreds->count() }} predictions)</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="a-table" style="font-size:.78rem;">
                    <tbody>
                        @foreach($dayPreds as $pred)
                        @php $m = $pred->match; $scores = $pred->likely_scores ?? []; @endphp
                        <tr>
                            <td style="color:#fff; font-weight:600; white-space:nowrap;">
                                {{ $m?->home_team }} vs {{ $m?->away_team }}
                                @if($m && in_array($m->status, ['FT','AET','PEN']))
                                    <span style="color:var(--dim); font-size:.7rem;">({{ $m->home_score }}-{{ $m->away_score }})</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex; gap:.25rem; flex-wrap:wrap;">
                                    @foreach(array_slice($scores, 0, 3) as $s)
                                    <span style="background:rgba(139,92,246,.1);color:#c4b5fd;border:1px solid rgba(139,92,246,.25);padding:1px 6px;border-radius:3px;font-weight:700;font-size:.7rem;">
                                        {{ $s['score'] }}
                                    </span>
                                    @endforeach
                                </div>
                            </td>
                            <td style="color:#6ee7b7; font-weight:700;">{{ $pred->predicted_outcome ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    @endif
</div>

@endsection
