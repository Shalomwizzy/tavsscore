@extends('layouts.admin')
@section('title', 'Over 1.5 Picks Admin')
@section('page-title', 'Over 1.5 Picks')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">⚽ Over 1.5 Picks Admin</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.over15.rebuild') }}" onsubmit="return confirm('Pull latest fixtures, rebuild model boards, then select and send only qualifying Over 1.5 picks?');">@csrf<button type="submit" class="btn-a btn-green">↻ Pull data + rebuild</button></form>
        <form method="POST" action="{{ route('admin.over15.refresh') }}"
              onsubmit="return confirm('Re-select today\'s Over 1.5 picks? This overwrites the current selection.');">
            @csrf
            <button type="submit" class="btn-a" style="background:linear-gradient(135deg,#1e3a5f,#1e40af);color:#93c5fd;">⚽ Re-select Today</button>
        </form>
        <a href="{{ route('over15-picks.index') }}" target="_blank" class="btn-a btn-blue">↗ View Public Page</a>
    </div>
</div>

@if(session('success'))
<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#6ee7b7;">
    ✅ {{ session('success') }}
</div>
@endif

{{-- Stats strip --}}
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); margin-bottom:1.25rem;">
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(147,197,253,.10),rgba(147,197,253,.04));border-color:rgba(147,197,253,.25);">
        <span class="stat-val" style="color:#93c5fd;">@if($accuracy !== null){{ $accuracy }}%@else N/A @endif</span>
        <span class="stat-lbl">⚽ All-time accuracy</span>
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

{{-- Today --}}
<div class="a-card" style="margin-bottom:1.25rem; border-color:rgba(147,197,253,.25);">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">
            ⚽ Today's Over 1.5 Picks
            <span style="background:rgba(147,197,253,.15);color:#93c5fd;font-size:.65rem;padding:1px 8px;border-radius:999px;margin-left:.4rem;">{{ $todayPicks->count() }}/5</span>
        </span>
        <span style="font-size:.72rem; color:var(--dim);">{{ now('Africa/Lagos')->format('M d, Y') }}</span>
    </div>

    @if($todayPicks->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">
            No Over 1.5 picks selected today.<br>
            <span style="font-size:.75rem;">Click "Re-select Today" above, or wait for the 06:00 cron.</span>
        </div>
    @else
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>#rank</th>
                    <th>Match</th>
                    <th>League</th>
                    <th>O1.5 Prob</th>
                    <th>Kickoff (Lagos)</th>
                    <th>AI</th>
                    <th>Result</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($todayPicks as $pick)
                @php $m = $pick->match; $tips = is_array($pick->tips) ? $pick->tips : []; @endphp
                <tr>
                    <td style="font-weight:800; color:#93c5fd;">⚽ #{{ $pick->over15_rank }}</td>
                    <td>
                        @include('admin.partials.fixture-mini', ['match' => $m])
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}:{{ $m->away_score }})</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); font-size:.74rem; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        {{ \App\Support\LeagueCoverage::formatName($m?->league, $m?->league_country) }}
                    </td>
                    <td style="color:#93c5fd; font-weight:700;">{{ $pick->over_15_prob ? round($pick->over_15_prob) . '%' : '-' }}</td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                        {{ $m?->match_time?->setTimezone('Africa/Lagos')->format('H:i') ?? '-' }}
                    </td>
                    <td>
                        @if(($tips[0]['ai_agrees'] ?? null) === true)
                            <span class="badge badge-green">✅ Agreed</span>
                        @else
                            <span class="badge badge-gray"></span>
                        @endif
                    </td>
                    <td>
                        @if($pick->over15_notified === true)
                            @php $home = $m?->home_score ?? 0; $away = $m?->away_score ?? 0; @endphp
                            @if(($home + $away) >= 2)
                                <span class="badge badge-green">✓ Won</span>
                            @else
                                <span class="badge badge-red">✗ Lost</span>
                            @endif
                        @else
                            @if($m && in_array($m->status, ['1H','HT','2H','ET','BT','P','LIVE']))
                                <span class="badge" style="background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.3);">🔴 LIVE</span>
                            @else
                                <span class="badge badge-gray">⏳ Pending</span>
                            @endif
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.picks.regenerate', $pick) }}"
                              onsubmit="return confirm('Re-run AI for this match and push notification?');">
                            @csrf
                            <input type="hidden" name="type" value="over15">
                            <button type="submit" style="background:rgba(99,102,241,.2);color:#a5b4fc;border:1px solid rgba(99,102,241,.3);padding:2px 10px;border-radius:5px;font-size:.72rem;cursor:pointer;">🔄 Regen</button>
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
        <a href="{{ route('stats.index') }}" target="_blank" style="font-size:.72rem; color:var(--dim); text-decoration:none;">Full stats →</a>
    </div>

    @if($history->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">No previous Over 1.5 picks recorded yet.</div>
    @else
        @foreach($history as $date => $dayPicks)
        @php
            $dayResolved = $dayPicks->where('over15_notified', true);
            $dayCorrect  = $dayResolved->filter(function($p) {
                $m = $p->match;
                return $m && (($m->home_score ?? 0) + ($m->away_score ?? 0)) >= 2;
            })->count();
            $dayTotal    = $dayResolved->count();
        @endphp
        <div style="margin-bottom:1rem;">
            <div style="display:flex; align-items:center; gap:.5rem; padding:.4rem .25rem; border-bottom:1px solid var(--border); margin-bottom:.4rem;">
                <span style="font-weight:700; font-size:.78rem; color:var(--text);">
                    {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}
                </span>
                @if($dayTotal > 0)
                <span style="font-size:.7rem; color:var(--dim);">({{ $dayCorrect }}/{{ $dayTotal }} correct)</span>
                @endif
            </div>
            <div style="overflow-x:auto;">
                <table class="a-table" style="font-size:.78rem;">
                    <tbody>
                        @foreach($dayPicks as $pick)
                        @php $m = $pick->match; @endphp
                        <tr>
                            <td style="width:50px; font-weight:700; color:#93c5fd;">#{{ $pick->over15_rank }}</td>
                            <td>
                                @include('admin.partials.fixture-mini', ['match' => $m])
                                @if($m && in_array($m->status, ['FT','AET','PEN']))
                                    <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}:{{ $m->away_score }})</span>
                                @endif
                            </td>
                            <td style="color:#93c5fd; font-weight:700;">O1.5</td>
                            <td style="color:var(--dim);">{{ $pick->over_15_prob ? round($pick->over_15_prob) . '%' : '-' }}</td>
                            <td style="text-align:right;">
                                @if($pick->over15_notified === true)
                                    @php $home = $m?->home_score ?? 0; $away = $m?->away_score ?? 0; @endphp
                                    @if(($home + $away) >= 2)
                                        <span class="badge badge-green">✓</span>
                                    @else
                                        <span class="badge badge-red">✗</span>
                                    @endif
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

<div style="margin-top:1.25rem; padding:.875rem 1rem; border-radius:8px; background:rgba(147,197,253,.07); border:1px solid rgba(147,197,253,.18); font-size:.74rem; color:#93c5fd;">
    <strong>ℹ️ How Over 1.5 picks work:</strong>
    Over 1.5 picks auto-select at <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">06:00</code> via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">picks:select</code>.
    Picks with the highest Over 1.5 probability are selected each day.
    Accuracy is tracked using <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">over15_notified=true</code> as the resolved indicator, a pick wins when total home + away goals &ge; 2.
    Outcomes resolve every 5 min via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">predictions:check-outcomes</code>, which notifies Telegram + OneSignal.
</div>

@endsection
