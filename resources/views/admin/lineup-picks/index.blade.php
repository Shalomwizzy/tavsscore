@extends('layouts.admin')
@section('title', 'Lineup Confirmed Picks')
@section('page-title', 'Lineup Confirmed Picks')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">⚡ Lineup Confirmed Picks</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.lineup-picks.notify') }}"
              onsubmit="return confirm('Push all today\'s lineup picks to Telegram and OneSignal?');">
            @csrf
            <button type="submit" class="btn-a btn-green">📢 Push Notification</button>
        </form>
        <a href="{{ route('lineup-picks.index') }}" target="_blank" class="btn-a btn-blue">↗ View Public Page</a>
    </div>
</div>

@if(session('success'))
<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#6ee7b7;">
    ✅ {{ session('success') }}
</div>
@endif

{{-- Today --}}
<div class="a-card" style="margin-bottom:1.25rem; border-color:rgba(16,185,129,.3);">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">
            ⚡ Today's Lineup Picks
            <span style="background:rgba(16,185,129,.15);color:#6ee7b7;font-size:.65rem;padding:1px 8px;border-radius:999px;margin-left:.4rem;">{{ $todayPicks->count() }}</span>
        </span>
        <span style="font-size:.72rem; color:var(--dim);">{{ now('Africa/Lagos')->format('M d, Y') }}</span>
    </div>

    @if($todayPicks->isEmpty())
        <p style="font-size:.8rem; color:var(--dim); text-align:center; padding:1.25rem;">
            No lineup-confirmed predictions yet today. They auto-populate as clubs publish their starting XI (~75 min before kickoff).
        </p>
    @else
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>Match</th>
                    <th>League</th>
                    <th>Tip</th>
                    <th>Confidence</th>
                    <th>Top Scoreline</th>
                    <th>Kickoff (Lagos)</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($todayPicks as $pick)
                @php $m = $pick->match; $topScore = is_array($pick->likely_scores) ? ($pick->likely_scores[0] ?? null) : null; @endphp
                <tr>
                    <td>@include('admin.partials.fixture-mini', ['match' => $m])</td>
                    <td style="color:var(--dim); font-size:.74rem; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $m?->league }}</td>
                    <td style="color:#6ee7b7; font-weight:700;">{{ $pick->predicted_outcome }}</td>
                    <td style="font-weight:700; color:#fff;">{{ $pick->confidence }}%</td>
                    <td>
                        @if($topScore)
                            <span style="background:rgba(139,92,246,.15);color:#c4b5fd;border:1px solid rgba(139,92,246,.3);padding:1px 8px;border-radius:4px;font-weight:700;font-size:.74rem;">
                                {{ $topScore['score'] }} ({{ $topScore['pct'] }}%)
                            </span>
                        @else
                            <span style="color:var(--dim);">-</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); font-size:.74rem;">{{ $m?->match_time?->setTimezone('Africa/Lagos')->format('H:i') }}</td>
                    <td>
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span class="badge badge-gray">FT {{ $m->home_score }}-{{ $m->away_score }}</span>
                        @elseif($m && in_array($m->status, ['1H','HT','2H','ET','BT','P']))
                            <span class="badge" style="background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.3);">🔴 LIVE</span>
                        @else
                            <span class="badge badge-green">⚡ Lineup Set</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.picks.regenerate', $pick) }}"
                              onsubmit="return confirm('Re-run lineup AI for this match and push lineup notification?');">
                            @csrf
                            <input type="hidden" name="type" value="lineup">
                            <button type="submit" style="background:rgba(16,185,129,.2);color:#6ee7b7;border:1px solid rgba(16,185,129,.3);padding:2px 10px;border-radius:5px;font-size:.72rem;cursor:pointer;">🔄 Regen</button>
                        </form>
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
        <p style="font-size:.8rem; color:var(--dim); text-align:center; padding:1.25rem;">No historical lineup picks yet.</p>
    @else
        @foreach($history as $date => $dayPicks)
        <div style="margin-bottom:1rem;">
            <div style="padding:.35rem .25rem; border-bottom:1px solid var(--border); margin-bottom:.35rem;">
                <span style="font-weight:700; font-size:.78rem; color:var(--text);">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</span>
                <span style="font-size:.68rem; color:var(--dim); margin-left:.5rem;">({{ $dayPicks->count() }} picks)</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="a-table" style="font-size:.78rem;">
                    <tbody>
                        @foreach($dayPicks as $pick)
                        @php $m = $pick->match; @endphp
                        <tr>
                            <td>@include('admin.partials.fixture-mini', ['match' => $m])</td>
                            <td style="color:#6ee7b7; font-weight:700;">{{ $pick->predicted_outcome }}</td>
                            <td style="color:var(--dim);">{{ $pick->confidence }}%</td>
                            <td style="color:var(--dim); font-size:.72rem;">{{ $m?->match_time?->setTimezone('Africa/Lagos')->format('H:i') }}</td>
                            <td>
                                @if($pick->was_correct === true) <span class="badge badge-green">✓</span>
                                @elseif($pick->was_correct === false) <span class="badge badge-red">✗</span>
                                @else <span class="badge badge-gray">⏳</span>
                                @endif
                            </td>
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
