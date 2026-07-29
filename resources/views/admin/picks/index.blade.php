@extends('layouts.admin')
@section('title', 'Daily Picks')
@section('page-title', 'Daily Picks')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">⭐ Daily Picks</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.picks.rebuild-daily') }}" onsubmit="return confirm('Pull latest fixtures, rebuild all prediction boards, then select and send only qualifying daily picks?');">@csrf<button type="submit" class="btn-a btn-green">↻ Pull data + rebuild Daily</button></form>
        <form method="POST" action="{{ route('admin.picks.rebuild-draw') }}" onsubmit="return confirm('Pull latest fixtures, rebuild all prediction boards, then select and send only qualifying draw picks?');">@csrf<button type="submit" class="btn-a btn-green">↻ Pull data + rebuild Draw</button></form>
        <form method="POST" action="{{ route('admin.picks.rebuild-gg') }}" onsubmit="return confirm('Pull latest fixtures, rebuild all prediction boards, then select and send only qualifying GG picks?');">@csrf<button type="submit" class="btn-a btn-green">↻ Pull data + rebuild GG</button></form>
        <form method="POST" action="{{ route('admin.picks.recheck') }}">
            @csrf
            <button type="submit" class="btn-a btn-gray" title="Re-check the last 7 days of picks against current scores">🔄 Re-check Outcomes</button>
        </form>
        <form method="POST" action="{{ route('admin.picks.refresh') }}"
              onsubmit="return confirm('Re-select today\'s 3 picks? This overwrites the current selection.');">
            @csrf
            <button type="submit" class="btn-a" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">⭐ Re-select Daily</button>
        </form>
        <form method="POST" action="{{ route('admin.picks.refresh-draw') }}"
              onsubmit="return confirm('Re-select today\'s draw picks?');">
            @csrf
            <button type="submit" class="btn-a" style="background:linear-gradient(135deg,#92400e,#78350f);color:#fcd34d;">🤝 Re-select Draw</button>
        </form>
        <form method="POST" action="{{ route('admin.picks.refresh-gg') }}"
              onsubmit="return confirm('Re-select today\'s GG picks?');">
            @csrf
            <button type="submit" class="btn-a" style="background:linear-gradient(135deg,#064e3b,#065f46);color:#6ee7b7;">⚽ Re-select GG</button>
        </form>
        <a href="{{ route('picks.index') }}" target="_blank" class="btn-a btn-blue">↗ Daily Picks</a>
        <a href="{{ route('draw-picks.index') }}" target="_blank" class="btn-a btn-blue">↗ Draw Picks</a>
        <a href="{{ route('gg-picks.index') }}" target="_blank" class="btn-a btn-blue">↗ GG Picks</a>
        <a href="{{ route('double-chance.index') }}" target="_blank" class="btn-a btn-blue">↗ Double Chance</a>
    </div>
</div>

{{-- Lineup Confirmed Picks - shown at top so it's always visible --}}
@php $leaderLineup = \App\Models\Prediction::with('match')
    ->where('has_lineup', true)
    ->whereNotNull('confidence')
    ->whereNotNull('predicted_outcome')
    ->where('predicted_outcome', '!=', 'Competitive Match')
    ->whereHas('match', fn ($q) => $q->whereDate('match_time', now('Africa/Lagos')->toDateString()))
    ->orderByDesc('confidence')
    ->limit(10)
    ->get(); @endphp

<div class="a-card" style="margin-bottom:1.25rem; border-color:rgba(16,185,129,.3);">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">⚡ Lineup Confirmed Picks Today <span style="background:rgba(16,185,129,.2);color:#6ee7b7;font-size:.65rem;padding:1px 8px;border-radius:999px;margin-left:.4rem;">{{ $leaderLineup->count() }}</span></span>
        <a href="{{ route('lineup-picks.index') }}" target="_blank" style="font-size:.72rem; color:#6ee7b7; text-decoration:none;">↗ Public page</a>
    </div>
    @if($leaderLineup->isEmpty())
        <p style="font-size:.78rem; color:var(--dim); text-align:center; padding:.75rem 0;">
            No lineup-confirmed predictions yet. They appear once clubs publish their starting 11 (~75 min before kickoff).
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
                    <th>Likely Score</th>
                    <th>Kickoff (Lagos)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leaderLineup as $pick)
                @php $m = $pick->match; $topScore = is_array($pick->likely_scores) ? ($pick->likely_scores[0] ?? null) : null; @endphp
                <tr>
                    <td>@include('admin.partials.fixture-mini', ['match' => $m])</td>
                    <td style="color:var(--dim); font-size:.74rem;">{{ $m?->league }}</td>
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
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- Summary strip --}}
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); margin-bottom:1.25rem;">
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(245,158,11,.10),rgba(245,158,11,.04));border-color:rgba(245,158,11,.25);">
        <span class="stat-val" style="color:#fcd34d;">
            @if($accuracy !== null){{ $accuracy }}%@else-@endif
        </span>
        <span class="stat-lbl">⭐ Daily accuracy</span>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(245,158,11,.07),rgba(245,158,11,.02));border-color:rgba(245,158,11,.15);">
        <span class="stat-val" style="color:#fcd34d;">
            @if($drawAccuracy !== null){{ $drawAccuracy }}%@else-@endif
        </span>
        <span class="stat-lbl">🤝 Draw accuracy</span>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(16,185,129,.07),rgba(16,185,129,.02));border-color:rgba(16,185,129,.15);">
        <span class="stat-val" style="color:#6ee7b7;">
            @if($ggAccuracy !== null){{ $ggAccuracy }}%@else-@endif
        </span>
        <span class="stat-lbl">⚽ GG accuracy</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#6ee7b7;">{{ $correctAll }}</span>
        <span class="stat-lbl">✓ Daily correct</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#fca5a5;">{{ $totalAll - $correctAll }}</span>
        <span class="stat-lbl">✗ Daily wrong</span>
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
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($today as $pick)
                @php $m = $pick->match; @endphp
                <tr>
                    <td style="font-weight:800; color:#fcd34d;">
                        {{ $pick->pick_rank === 1 ? '👑' : '⭐' }} #{{ $pick->pick_rank }}
                    </td>
                    <td>
                        @include('admin.partials.fixture-mini', ['match' => $m])
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}–{{ $m->away_score }})</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        {{ \App\Support\LeagueCoverage::formatName($m?->league, $m?->league_country) }}
                    </td>
                    <td style="color:#93c5fd; font-weight:700; white-space:nowrap;">{{ $pick->predicted_outcome ?? '-' }}</td>
                    <td style="font-weight:700; color:#6ee7b7;">{{ $pick->predicted_outcome ? number_format(\App\Support\PickHelpers::confidencePct($pick), 0) : '-' }}%</td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                        {{ $m?->match_time?->format('H:i') ?? '-' }}
                    </td>
                    <td>
                        @if($pick->was_correct === true)
                            <span class="badge badge-green">✓ Won</span>
                        @elseif($pick->was_correct === false)
                            <span class="badge badge-red">✗ Lost</span>
                        @else
                            <span class="badge badge-gray">⏳ Pending</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.picks.regenerate', $pick) }}"
                              onsubmit="return confirm('Re-run AI for this match and push notification?');">
                            @csrf
                            <input type="hidden" name="type" value="daily">
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
                            <td>
                                @include('admin.partials.fixture-mini', ['match' => $m])
                                @if($m && in_array($m->status, ['FT','AET','PEN']))
                                    <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}–{{ $m->away_score }})</span>
                                @endif
                            </td>
                            <td style="color:#93c5fd; font-weight:700; white-space:nowrap;">{{ $pick->predicted_outcome ?? '-' }}</td>
                            <td style="color:var(--dim);">{{ $pick->predicted_outcome ? number_format(\App\Support\PickHelpers::confidencePct($pick), 0) : '-' }}%</td>
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

{{-- Lineup Confirmed Picks --}}
<div class="a-card" style="margin-top:1.25rem; margin-bottom:1.25rem; border-color:rgba(16,185,129,.3);">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">⚡ Lineup Confirmed Picks - Today</span>
        <a href="{{ route('lineup-picks.index') }}" target="_blank" style="font-size:.72rem; color:#6ee7b7; text-decoration:none;">↗ Public page</a>
    </div>

    @if($lineupPicks->isEmpty())
        <div style="text-align:center; padding:1.25rem; color:var(--dim); font-size:.82rem;">
            No lineup-confirmed predictions yet today. They appear once clubs publish the official starting 11 (~75 min before kickoff).
        </div>
    @else
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>Match</th>
                    <th>League</th>
                    <th>Tip</th>
                    <th>Confidence</th>
                    <th>Kickoff (Lagos)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lineupPicks as $pick)
                @php $m = $pick->match; @endphp
                <tr>
                    <td>
                        @include('admin.partials.fixture-mini', ['match' => $m])
                    </td>
                    <td style="color:var(--dim); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        {{ $m?->league }}
                    </td>
                    <td style="color:#6ee7b7; font-weight:700; white-space:nowrap;">{{ $pick->predicted_outcome ?? '-' }}</td>
                    <td style="font-weight:700; color:#fff;">{{ $pick->confidence }}%</td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                        {{ $m?->match_time?->setTimezone('Africa/Lagos')->format('H:i') ?? '-' }}
                    </td>
                    <td>
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span class="badge badge-gray">FT {{ $m->home_score }}–{{ $m->away_score }}</span>
                        @elseif($m && in_array($m->status, ['1H','HT','2H','ET','BT','P']))
                            <span class="badge" style="background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.3);">🔴 LIVE</span>
                        @else
                            <span class="badge badge-green">⚡ Lineup Set</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- Draw Picks Today --}}
<div class="a-card" style="margin-top:1.25rem; border-color:rgba(245,158,11,.25);">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.875rem; flex-wrap:wrap; gap:.5rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">
            🤝 Draw Picks Today
            <span style="background:rgba(245,158,11,.15);color:#fcd34d;font-size:.65rem;padding:1px 8px;border-radius:999px;margin-left:.4rem;">{{ $drawPicks->count() }}/5</span>
        </span>
        <div style="display:flex; gap:.5rem; align-items:center;">
            @if($drawAccuracy !== null)
            <span style="font-size:.72rem; color:#fcd34d;">All-time: {{ $drawAccuracy }}%</span>
            @endif
            <a href="{{ route('draw-picks.index') }}" target="_blank" style="font-size:.72rem; color:#fcd34d; text-decoration:none;">↗ Public page</a>
        </div>
    </div>

    @if($drawPicks->isEmpty())
        <div style="text-align:center; padding:1.25rem; color:var(--dim); font-size:.82rem;">
            No draw picks selected today. Click "Re-select Draw" above to run the selection now.
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
                    <th>Kickoff</th>
                    <th>Triple AI</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach($drawPicks as $pick)
                @php $m = $pick->match; $tips = is_array($pick->tips) ? $pick->tips : []; @endphp
                <tr>
                    <td style="font-weight:800; color:#fcd34d;">🤝 #{{ $pick->draw_rank }}</td>
                    <td>
                        @include('admin.partials.fixture-mini', ['match' => $m])
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}–{{ $m->away_score }})</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); font-size:.74rem; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        {{ \App\Support\LeagueCoverage::formatName($m?->league, $m?->league_country) }}
                    </td>
                    <td style="font-weight:700; color:#fff;">{{ $pick->confidence }}%</td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                        {{ $m?->match_time?->setTimezone('Africa/Lagos')->format('H:i') ?? '-' }}
                    </td>
                    <td>
                        @if(($tips[0]['gemini_agrees'] ?? null) === true)
                            <span class="badge badge-green">✅ All 3 agreed</span>
                        @else
                            <span class="badge badge-gray">—</span>
                        @endif
                    </td>
                    <td>
                        @if($pick->was_correct === true)
                            <span class="badge badge-green">✓ Won</span>
                        @elseif($pick->was_correct === false)
                            <span class="badge badge-red">✗ Lost</span>
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

{{-- GG Picks Today --}}
<div class="a-card" style="margin-top:1.25rem; border-color:rgba(16,185,129,.25);">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.875rem; flex-wrap:wrap; gap:.5rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">
            ⚽ GG Picks Today
            <span style="background:rgba(16,185,129,.15);color:#6ee7b7;font-size:.65rem;padding:1px 8px;border-radius:999px;margin-left:.4rem;">{{ $ggPicks->count() }}/5</span>
        </span>
        <div style="display:flex; gap:.5rem; align-items:center;">
            @if($ggAccuracy !== null)
            <span style="font-size:.72rem; color:#6ee7b7;">All-time: {{ $ggAccuracy }}%</span>
            @endif
            <a href="{{ route('gg-picks.index') }}" target="_blank" style="font-size:.72rem; color:#6ee7b7; text-decoration:none;">↗ Public page</a>
        </div>
    </div>

    @if($ggPicks->isEmpty())
        <div style="text-align:center; padding:1.25rem; color:var(--dim); font-size:.82rem;">
            No GG picks selected today. Click "Re-select GG" above to run the selection now.
        </div>
    @else
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Match</th>
                    <th>League</th>
                    <th>BTTS %</th>
                    <th>Confidence</th>
                    <th>Kickoff</th>
                    <th>Triple AI</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ggPicks as $pick)
                @php $m = $pick->match; $tips = is_array($pick->tips) ? $pick->tips : []; @endphp
                <tr>
                    <td style="font-weight:800; color:#6ee7b7;">⚽ #{{ $pick->gg_rank }}</td>
                    <td>
                        @include('admin.partials.fixture-mini', ['match' => $m])
                        @if($m && in_array($m->status, ['FT','AET','PEN']))
                            <span style="color:var(--dim); font-size:.72rem; margin-left:.4rem;">({{ $m->home_score }}–{{ $m->away_score }})</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); font-size:.74rem; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        {{ \App\Support\LeagueCoverage::formatName($m?->league, $m?->league_country) }}
                    </td>
                    <td style="color:#6ee7b7; font-weight:700;">{{ $pick->btts_prob ? round($pick->btts_prob) . '%' : '-' }}</td>
                    <td style="font-weight:700; color:#fff;">{{ $pick->confidence }}%</td>
                    <td style="color:var(--dim); font-size:.74rem; white-space:nowrap;">
                        {{ $m?->match_time?->setTimezone('Africa/Lagos')->format('H:i') ?? '-' }}
                    </td>
                    <td>
                        @if(($tips[0]['gemini_agrees'] ?? null) === true)
                            <span class="badge badge-green">✅ All 3 agreed</span>
                        @else
                            <span class="badge badge-gray">—</span>
                        @endif
                    </td>
                    <td>
                        @if($pick->was_correct === true)
                            <span class="badge badge-green">✓ Won</span>
                        @elseif($pick->was_correct === false)
                            <span class="badge badge-red">✗ Lost</span>
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

<div style="margin-top:1.25rem; padding:.875rem 1rem; border-radius:8px; background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); font-size:.74rem; color:#93c5fd;">
    <strong>ℹ️ How picks work:</strong>
    Daily/Draw/GG picks auto-select at <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">06:00</code> via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">picks:select</code>.
    Outcomes auto-resolve every 5 minutes via <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;">predictions:check-outcomes</code>. Notifications fire to Telegram + OneSignal on each win/loss.
    Re-selecting overwrites existing picks. Re-checking outcomes re-evaluates results from the last 7 days.
</div>

@endsection
