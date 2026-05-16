@extends('layouts.admin')
@section('title', 'Team 3+ Goals Admin')
@section('page-title', 'Team 3+ Goals')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">🎯 Team 3+ Goals Picks</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.team3plus.refresh') }}"
              onsubmit="return confirm('Re-select today\'s Team 3+ picks? This overwrites the current selection.');">
            @csrf
            <button type="submit" class="btn-a" style="background:linear-gradient(135deg,#7f1d1d,#991b1b);color:#fca5a5;">🎯 Re-select Today</button>
        </form>
        <a href="{{ route('team3plus.index') }}" target="_blank" class="btn-a btn-blue">↗ View Public Page</a>
    </div>
</div>

@if(session('success'))
<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#6ee7b7;">
    ✅ {{ session('success') }}
</div>
@endif

<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); margin-bottom:1.25rem;">
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(220,38,38,.10),rgba(220,38,38,.04));border-color:rgba(220,38,38,.25);">
        <span class="stat-val" style="color:#fca5a5;">@if($accuracy !== null){{ $accuracy }}%@else—@endif</span>
        <span class="stat-lbl">🎯 All-time accuracy</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#6ee7b7;">{{ $correct }}</span>
        <span class="stat-lbl">✓ Total correct</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#fca5a5;">{{ $total - $correct }}</span>
        <span class="stat-lbl">✗ Total wrong</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ $total }}</span>
        <span class="stat-lbl">🎯 Picks resolved</span>
    </div>
</div>

<div class="a-card" style="margin-bottom:1.25rem; border-color:rgba(220,38,38,.25);">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">
            🎯 Today's Team 3+ Picks
            <span style="background:rgba(220,38,38,.15);color:#fca5a5;font-size:.65rem;padding:1px 8px;border-radius:999px;margin-left:.4rem;">{{ $todayPicks->count() }}/5</span>
        </span>
        <span style="font-size:.72rem; color:var(--dim);">{{ now('Africa/Lagos')->format('M d, Y') }}</span>
    </div>

    @if($todayPicks->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">
            No Team 3+ picks selected today.<br>
            <span style="font-size:.75rem;">Click "Re-select Today" above, or wait for the 05:00 cron.</span>
        </div>
    @else
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Match</th>
                    <th>League</th>
                    <th>NO Team</th>
                    <th>Their 3+ Prob</th>
                    <th>Other 3+ Prob</th>
                    <th>Kickoff (Lagos)</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach($todayPicks as $pick)
                @php
                    $m = $pick->match;
                    $label = $pick->team3plus_label ?? 'Home';
                    $noTeam = $label === 'Home' ? ($m?->home_team ?? '?') : ($m?->away_team ?? '?');
                    $noProb = $label === 'Home' ? $pick->home_3plus_prob : $pick->away_3plus_prob;
                    $otherProb = $label === 'Home' ? $pick->away_3plus_prob : $pick->home_3plus_prob;
                @endphp
                <tr>
                    <td style="font-weight:800; color:#fca5a5;">🚫 #{{ $pick->team3plus_rank }}</td>
                    <td style="color:#fff; font-weight:600; white-space:nowrap;">
                        {{ $m?->home_team ?? '?' }} vs {{ $m?->away_team ?? '?' }}
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}–{{ $m->away_score }})</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); font-size:.74rem; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        {{ \App\Support\LeagueCoverage::formatName($m?->league, $m?->league_country) }}
                    </td>
                    <td style="font-weight:700; color:#fca5a5; white-space:nowrap;">
                        🚫 {{ $noTeam }} (NO)
                    </td>
                    <td style="font-weight:700; color:#6ee7b7;">{{ $noProb ? round($noProb) . '%' : '-' }}</td>
                    <td style="font-weight:700; color:var(--dim);">{{ $otherProb ? round($otherProb) . '%' : '-' }}</td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                        {{ $m?->match_time?->setTimezone('Africa/Lagos')->format('H:i') ?? '-' }}
                    </td>
                    <td>
                        @if($pick->team3plus_notified && $m && in_array($m->status, ['FT','AET','PEN']))
                            @php $won = $label === 'Home' ? (int)$m->home_score < 3 : (int)$m->away_score < 3; @endphp
                            @if($won) <span class="badge badge-green">✓ Won</span>
                            @else <span class="badge badge-red">✗ Lost</span> @endif
                        @elseif($m && in_array($m->status, ['1H','HT','2H','ET','BT','P','LIVE']))
                            <span class="badge" style="background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.3);">🔴 LIVE</span>
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

<div class="a-card">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">📜 History</span>
        <a href="{{ route('stats.index') }}" target="_blank" style="font-size:.72rem; color:var(--dim); text-decoration:none;">Full stats →</a>
    </div>
    @if($history->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">No previous Team 3+ picks recorded yet.</div>
    @else
        @foreach($history as $date => $dayPicks)
        <div style="margin-bottom:1rem;">
            <div style="display:flex; align-items:center; gap:.5rem; padding:.4rem .25rem; border-bottom:1px solid var(--border); margin-bottom:.4rem;">
                <span style="font-weight:700; font-size:.78rem; color:var(--text);">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="a-table" style="font-size:.78rem;">
                    <tbody>
                        @foreach($dayPicks as $pick)
                        @php
                            $m = $pick->match;
                            $label = $pick->team3plus_label ?? 'Home';
                            $noTeam = $label === 'Home' ? ($m?->home_team ?? '?') : ($m?->away_team ?? '?');
                        @endphp
                        <tr>
                            <td style="width:50px; font-weight:700; color:#fca5a5;">#{{ $pick->team3plus_rank }}</td>
                            <td style="color:#fff; font-weight:600; white-space:nowrap;">
                                {{ $m?->home_team ?? '?' }} vs {{ $m?->away_team ?? '?' }}
                                @if($m && in_array($m->status, ['FT','AET','PEN']))
                                    <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}–{{ $m->away_score }})</span>
                                @endif
                            </td>
                            <td style="color:#fca5a5; font-weight:700;">🚫 {{ $noTeam }} NO</td>
                            <td style="text-align:right;">
                                @if($pick->team3plus_notified && $m && in_array($m->status, ['FT','AET','PEN']))
                                    @php $w = $label === 'Home' ? (int)$m->home_score < 3 : (int)$m->away_score < 3; @endphp
                                    @if($w) <span class="badge badge-green">✓</span>
                                    @else <span class="badge badge-red">✗</span> @endif
                                @else <span class="badge badge-gray">⏳</span> @endif
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

<div style="margin-top:1.25rem; padding:.875rem 1rem; border-radius:8px; background:rgba(220,38,38,.07); border:1px solid rgba(220,38,38,.18); font-size:.74rem; color:#fca5a5;">
    <strong>ℹ️ How Team 3+ NO picks work:</strong>
    Auto-select at <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">05:00</code> via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">picks:select</code>.
    We pick the team with the <strong>lowest 3+ probability</strong> (≤ 8%) and predict NO on them.
    A pick wins when <strong>that team scores fewer than 3 goals</strong>.
    Outcomes resolve via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">predictions:check-outcomes</code>:
    Home label = home_score &lt; 3 wins, Away label = away_score &lt; 3 wins.
</div>

@endsection
