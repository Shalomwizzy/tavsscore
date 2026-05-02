@extends('layouts.admin')
@section('title', 'Daily Picks')
@section('page-title', 'Daily Picks')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">⭐ Daily Picks</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.picks.recheck') }}">
            @csrf
            <button type="submit" class="btn-a btn-gray" title="Re-check the last 7 days of picks against current scores">🔄 Re-check Outcomes</button>
        </form>
        <form method="POST" action="{{ route('admin.picks.refresh') }}"
              onsubmit="return confirm('Re-select today\'s 3 picks? This overwrites the current selection.');">
            @csrf
            <button type="submit" class="btn-a" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">⭐ Re-select Today</button>
        </form>
        <a href="{{ route('picks.index') }}" target="_blank" class="btn-a btn-blue">↗ View Public Page</a>
    </div>
</div>

{{-- Summary strip --}}
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); margin-bottom:1.25rem;">
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(245,158,11,.10),rgba(245,158,11,.04));border-color:rgba(245,158,11,.25);">
        <span class="stat-val" style="color:#fcd34d;">
            @if($accuracy !== null){{ $accuracy }}%@else—@endif
        </span>
        <span class="stat-lbl">⭐ All-time accuracy</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#6ee7b7;">{{ $correctAll }}</span>
        <span class="stat-lbl">✓ Total correct</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#fca5a5;">{{ $totalAll - $correctAll }}</span>
        <span class="stat-lbl">✗ Total wrong</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ $totalAll }}</span>
        <span class="stat-lbl">🎯 Picks resolved</span>
    </div>
</div>

{{-- Today --}}
<div class="a-card" style="margin-bottom:1.25rem;">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">📌 Today's Picks ({{ now()->format('M d, Y') }})</span>
        <span style="font-size:.72rem; color:var(--dim);">{{ $today->count() }}/3 selected</span>
    </div>

    @if($today->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">
            No picks selected yet today.
            <br>
            <span style="font-size:.75rem;">Click "Re-select Today" above to generate now, or wait for the daily cron at 09:00.</span>
        </div>
    @else
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Match</th>
                    <th>League</th>
                    <th>Tip</th>
                    <th>Confidence</th>
                    <th>Kickoff</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach($today as $pick)
                @php $m = $pick->match; @endphp
                <tr>
                    <td style="font-weight:800; color:#fcd34d;">
                        {{ $pick->pick_rank === 1 ? '👑' : '⭐' }} #{{ $pick->pick_rank }}
                    </td>
                    <td style="color:#fff; font-weight:600; white-space:nowrap;">
                        {{ $m?->home_team ?? '?' }} vs {{ $m?->away_team ?? '?' }}
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}–{{ $m->away_score }})</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        {{ \App\Support\LeagueCoverage::formatName($m?->league, $m?->league_country) }}
                    </td>
                    <td style="color:#93c5fd; font-weight:700; white-space:nowrap;">{{ $pick->predicted_outcome ?? '—' }}</td>
                    <td style="font-weight:700; color:#6ee7b7;">{{ $pick->predicted_outcome ? number_format(\App\Support\PickHelpers::confidencePct($pick), 0) : '—' }}%</td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                        {{ $m?->match_time?->format('H:i') ?? '—' }}
                    </td>
                    <td>
                        @if($pick->was_correct === true)
                            <span class="badge badge-green">✓ Correct</span>
                        @elseif($pick->was_correct === false)
                            <span class="badge badge-red">✗ Wrong</span>
                        @else
                            <span class="badge badge-gray">⏳ Pending</span>
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
        <span style="font-weight:700; font-size:.9rem; color:#fff;">📜 History (last 30 picks)</span>
        <a href="{{ route('stats.index') }}" target="_blank" style="font-size:.72rem; color:var(--dim); text-decoration:none;">Full stats →</a>
    </div>

    @if($history->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">
            No previous picks recorded yet.
        </div>
    @else
        @foreach($history as $date => $dayPicks)
        @php
            $dayResolved = $dayPicks->whereNotNull('was_correct');
            $dayCorrect  = $dayResolved->where('was_correct', true)->count();
            $dayTotal    = $dayResolved->count();
        @endphp
        <div style="margin-bottom:1rem;">
            <div style="display:flex; align-items:center; gap:.5rem; padding:.4rem .25rem; border-bottom:1px solid var(--border); margin-bottom:.4rem;">
                <span style="font-weight:700; font-size:.78rem; color:var(--text);">
                    {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}
                </span>
                @if($dayTotal > 0)
                    <span style="font-size:.7rem; color:var(--dim);">
                        ({{ $dayCorrect }}/{{ $dayTotal }} correct)
                    </span>
                @endif
            </div>
            <div style="overflow-x:auto;">
                <table class="a-table" style="font-size:.78rem;">
                    <tbody>
                        @foreach($dayPicks as $pick)
                        @php $m = $pick->match; @endphp
                        <tr>
                            <td style="width:60px; font-weight:700; color:#fcd34d;">#{{ $pick->pick_rank }}</td>
                            <td style="color:#fff; font-weight:600; white-space:nowrap;">
                                {{ $m?->home_team ?? '?' }} vs {{ $m?->away_team ?? '?' }}
                                @if($m && in_array($m->status, ['FT','AET','PEN']))
                                    <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}–{{ $m->away_score }})</span>
                                @endif
                            </td>
                            <td style="color:#93c5fd; font-weight:700; white-space:nowrap;">{{ $pick->predicted_outcome ?? '—' }}</td>
                            <td style="color:var(--dim);">{{ $pick->predicted_outcome ? number_format(\App\Support\PickHelpers::confidencePct($pick), 0) : '—' }}%</td>
                            <td style="text-align:right;">
                                @if($pick->was_correct === true)
                                    <span class="badge badge-green">✓</span>
                                @elseif($pick->was_correct === false)
                                    <span class="badge badge-red">✗</span>
                                @else
                                    <span class="badge badge-gray">⏳</span>
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

<div style="margin-top:1.25rem; padding:.875rem 1rem; border-radius:8px; background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); font-size:.74rem; color:#93c5fd;">
    <strong>ℹ️ How picks work:</strong>
    Picks auto-select daily at <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">09:00</code> via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">picks:select</code>.
    Outcomes auto-resolve every 5 minutes via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">predictions:check-outcomes</code>.
    Re-selecting today overwrites existing picks. Re-checking outcomes re-evaluates results from the last 7 days against current scores.
</div>

@endsection
