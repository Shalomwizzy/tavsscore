@extends('layouts.admin')
@section('title', 'Double Chance Admin')
@section('page-title', 'Double Chance Picks')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">🎯 Double Chance Picks (1X / 2X)</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.double-chance.refresh') }}"
              onsubmit="return confirm('Re-select today\'s Double Chance picks? This overwrites the current selection.');">
            @csrf
            <button type="submit" class="btn-a" style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);color:#93c5fd;">🎯 Re-select &amp; Notify</button>
        </form>
        <form method="POST" action="{{ route('admin.double-chance.notify') }}"
              onsubmit="return confirm('Send Double Chance notification to Telegram and push now?');">
            @csrf
            <button type="submit" class="btn-a" style="background:linear-gradient(135deg,#064e3b,#065f46);color:#6ee7b7;">📣 Send Notification</button>
        </form>
        <a href="{{ route('double-chance.index') }}" target="_blank" class="btn-a btn-blue">↗ View Public Page</a>
    </div>
</div>

@if(session('success'))
<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#6ee7b7;">
    ✅ {{ session('success') }}
</div>
@endif

<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); margin-bottom:1.25rem;">
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(59,130,246,.10),rgba(59,130,246,.04));border-color:rgba(59,130,246,.25);">
        <span class="stat-val" style="color:#93c5fd;">@if($accuracy !== null){{ $accuracy }}%@else—@endif</span>
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

<div class="a-card" style="margin-bottom:1.25rem; border-color:rgba(59,130,246,.25);">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">
            🎯 Today's Double Chance Picks
            <span style="background:rgba(59,130,246,.15);color:#93c5fd;font-size:.65rem;padding:1px 8px;border-radius:999px;margin-left:.4rem;">{{ $todayPicks->count() }}/5</span>
        </span>
        <span style="font-size:.72rem; color:var(--dim);">{{ now('Africa/Lagos')->format('M d, Y') }}</span>
    </div>

    @if($todayPicks->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">
            No Double Chance picks selected today.<br>
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
                    <th>Pick</th>
                    <th>1X Prob</th>
                    <th>2X Prob</th>
                    <th>Kickoff (Lagos)</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach($todayPicks as $pick)
                @php
                    $m     = $pick->match;
                    $label = $pick->double_chance_label ?? '1X';
                    $dc1x  = round((float)$pick->home_win_prob + (float)$pick->draw_prob, 1);
                    $dc2x  = round((float)$pick->away_win_prob + (float)$pick->draw_prob, 1);
                    $desc  = $label === '1X' ? 'Home Win or Draw' : 'Away Win or Draw';
                    $home  = (int)($m?->home_score ?? 0);
                    $away  = (int)($m?->away_score ?? 0);
                    $won   = $label === '1X' ? $home >= $away : $away >= $home;
                @endphp
                <tr>
                    <td style="font-weight:800; color:#93c5fd;">🎯 #{{ $pick->double_chance_rank }}</td>
                    <td>
                        @include('admin.partials.fixture-mini', ['match' => $m])
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}–{{ $m->away_score }})</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); font-size:.74rem; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        {{ \App\Support\LeagueCoverage::formatName($m?->league, $m?->league_country) }}
                    </td>
                    <td style="font-weight:700; color:#93c5fd; white-space:nowrap;">
                        {{ $label }} — {{ $desc }}
                    </td>
                    <td style="font-weight:700; color:{{ $label === '1X' ? '#93c5fd' : 'var(--dim)' }};">{{ $dc1x }}%</td>
                    <td style="font-weight:700; color:{{ $label === '2X' ? '#93c5fd' : 'var(--dim)' }};">{{ $dc2x }}%</td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                        {{ $m?->match_time?->setTimezone('Africa/Lagos')->format('H:i') ?? '-' }}
                    </td>
                    <td>
                        @if($pick->double_chance_notified && $m && in_array($m->status, ['FT','AET','PEN']))
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
    </div>
    @if($history->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">No previous Double Chance picks recorded yet.</div>
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
                            $m     = $pick->match;
                            $label = $pick->double_chance_label ?? '1X';
                            $home  = (int)($m?->home_score ?? 0);
                            $away  = (int)($m?->away_score ?? 0);
                            $w     = $label === '1X' ? $home >= $away : $away >= $home;
                        @endphp
                        <tr>
                            <td style="width:50px; font-weight:700; color:#93c5fd;">#{{ $pick->double_chance_rank }}</td>
                            <td>
                                @include('admin.partials.fixture-mini', ['match' => $m])
                                @if($m && in_array($m->status, ['FT','AET','PEN']))
                                    <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}–{{ $m->away_score }})</span>
                                @endif
                            </td>
                            <td style="color:#93c5fd; font-weight:700;">{{ $label }}</td>
                            <td style="text-align:right;">
                                @if($pick->double_chance_notified && $m && in_array($m->status, ['FT','AET','PEN']))
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

<div style="margin-top:1.25rem; padding:.875rem 1rem; border-radius:8px; background:rgba(59,130,246,.07); border:1px solid rgba(59,130,246,.18); font-size:.74rem; color:#93c5fd;">
    <strong>ℹ️ How Double Chance picks work:</strong>
    Auto-select at <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">03:00</code> via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">picks:select</code>, notification at <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">04:30</code>.
    Primary: best DC probability ≥ 72% (up to 5 picks). Fallback: top 3 with ≥ 60% DC probability.
    <strong>1X wins</strong> when home_score ≥ away_score. <strong>2X wins</strong> when away_score ≥ home_score.
    Use <strong>Re-select &amp; Notify</strong> to force-pick and immediately send, or <strong>Send Notification</strong> to resend current picks.
</div>

@endsection
