@extends('layouts.admin')
@section('title', 'GG Picks Admin')
@section('page-title', 'GG Picks')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">⚽ GG Picks</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.gg-picks.rebuild') }}" onsubmit="return confirm('Pull latest fixtures, rebuild model boards, then select and send only qualifying GG picks?');">@csrf<button type="submit" class="btn-a btn-green">↻ Pull data + rebuild</button></form>
        <form method="POST" action="{{ route('admin.gg-picks.refresh') }}"
              onsubmit="return confirm('Re-select today\'s GG picks? This overwrites the current selection.');">
            @csrf
            <button type="submit" class="btn-a" style="background:linear-gradient(135deg,#065f46,#064e3b);color:#6ee7b7;">⚽ Re-select Today</button>
        </form>
        <a href="{{ route('gg-picks.index') }}" target="_blank" class="btn-a btn-blue">↗ View Public Page</a>
    </div>
</div>

@if(session('success'))
<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#6ee7b7;">
    ✅ {{ session('success') }}
</div>
@endif

{{-- Stats strip --}}
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); margin-bottom:1.25rem;">
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(16,185,129,.10),rgba(16,185,129,.04));border-color:rgba(16,185,129,.25);">
        <span class="stat-val" style="color:#6ee7b7;">@if($accuracy !== null){{ $accuracy }}%@else N/A @endif</span>
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
<div class="a-card" style="margin-bottom:1.25rem; border-color:rgba(16,185,129,.25);">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">
            ⚽ Today's GG Picks
            <span style="background:rgba(16,185,129,.15);color:#6ee7b7;font-size:.65rem;padding:1px 8px;border-radius:999px;margin-left:.4rem;">{{ $todayPicks->count() }}/5</span>
        </span>
        <span style="font-size:.72rem; color:var(--dim);">{{ now('Africa/Lagos')->format('M d, Y') }}</span>
    </div>

    @if($todayPicks->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">
            No GG picks selected today.<br>
            <span style="font-size:.75rem;">Click "Re-select Today" above, or wait for the 06:00 cron.</span>
        </div>
    @else
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Match</th>
                    <th>League</th>
                    <th>Confidence</th>
                    <th>BTTS Prob</th>
                    <th>Kickoff (Lagos)</th>
                    <th>Triple AI</th>
                    <th>Result</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($todayPicks as $pick)
                @php $m = $pick->match; $tips = is_array($pick->tips) ? $pick->tips : []; @endphp
                <tr>
                    <td style="font-weight:800; color:#6ee7b7;">⚽ #{{ $pick->gg_rank }}</td>
                    <td>
                        @include('admin.partials.fixture-mini', ['match' => $m])
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}:{{ $m->away_score }})</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); font-size:.74rem; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        {{ \App\Support\LeagueCoverage::formatName($m?->league, $m?->league_country) }}
                    </td>
                    <td style="font-weight:700; color:#fff;">{{ $pick->confidence }}%</td>
                    <td style="color:#6ee7b7; font-weight:700;">
                        @php
                            $btts = null;
                            foreach ($tips as $tip) {
                                if (isset($tip['btts_prob'])) { $btts = $tip['btts_prob']; break; }
                            }
                        @endphp
                        {{ $btts !== null ? round($btts) . '%' : '-' }}
                    </td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                        {{ $m?->match_time?->setTimezone('Africa/Lagos')->format('H:i') ?? '-' }}
                    </td>
                    <td>
                        @if(($tips[0]['gemini_agrees'] ?? null) === true)
                            <span class="badge badge-green">✅ All 3 agreed</span>
                        @else
                            <span class="badge badge-gray"></span>
                        @endif
                    </td>
                    <td>
                        @if($pick->was_correct === true)
                            <span class="badge badge-green">✓ Won</span>
                        @elseif($pick->was_correct === false)
                            <span class="badge badge-red">✗ Lost</span>
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
                            <input type="hidden" name="type" value="gg">
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
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">No previous GG picks recorded yet.</div>
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
                <span style="font-size:.7rem; color:var(--dim);">({{ $dayCorrect }}/{{ $dayTotal }} correct)</span>
                @endif
            </div>
            <div style="overflow-x:auto;">
                <table class="a-table" style="font-size:.78rem;">
                    <tbody>
                        @foreach($dayPicks as $pick)
                        @php $m = $pick->match; @endphp
                        <tr>
                            <td style="width:50px; font-weight:700; color:#6ee7b7;">#{{ $pick->gg_rank }}</td>
                            <td>
                                @include('admin.partials.fixture-mini', ['match' => $m])
                                @if($m && in_array($m->status, ['FT','AET','PEN']))
                                    <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}:{{ $m->away_score }})</span>
                                @endif
                            </td>
                            <td style="color:#6ee7b7; font-weight:700;">Both Teams Score</td>
                            <td style="color:var(--dim);">{{ $pick->confidence }}%</td>
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

<div style="margin-top:1.25rem; padding:.875rem 1rem; border-radius:8px; background:rgba(16,185,129,.07); border:1px solid rgba(16,185,129,.18); font-size:.74rem; color:#6ee7b7;">
    <strong>ℹ️ How GG picks work:</strong>
    GG picks auto-select at <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">06:00</code> via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">picks:select</code>.
    Only predictions where all 3 AI engines independently agree on "Both Teams Score" with ≥60% confidence qualify.
    Outcomes resolve every 5 min via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">predictions:check-outcomes</code>, which notifies Telegram + OneSignal.
</div>

@endsection
