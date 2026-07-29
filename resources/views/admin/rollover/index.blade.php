@extends('layouts.admin')
@section('title', 'Rollover Challenge')
@section('page-title', 'Rollover Challenge')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">🔄 Rollover Challenge</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.rollover.rebuild-board') }}" onsubmit="return confirm('Pull latest fixtures and rebuild all model boards before selecting a rollover pick?');">@csrf<button type="submit" class="btn-a btn-gray">↻ Pull data + rebuild board</button></form>
        <form method="POST" action="{{ route('admin.rollover.select-pick') }}">
            @csrf
            <button type="submit" class="btn-a btn-green">🎯 Select Today's Pick</button>
        </form>
        <a href="{{ route('rollover.index') }}" target="_blank" class="btn-a btn-blue">↗ View Public Page</a>
    </div>
</div>

{{-- Active challenge card --}}
@if($challenge)
@php
    // A day is one rollover step, however many legs its ticket holds.
    $dayGroups  = $challenge->picks->groupBy('day_number')->sortKeys();
    $dayStatus  = fn ($legs) => $legs->contains(fn ($l) => $l->status === 'lost') ? 'lost'
                    : ($legs->contains(fn ($l) => $l->status === 'pending') ? 'pending' : 'won');
    $wonPicks   = $dayGroups->filter(fn ($l) => $dayStatus($l) === 'won')->count();
    $lostPicks  = $dayGroups->filter(fn ($l) => $dayStatus($l) === 'lost')->count();
    $pendPicks  = $dayGroups->filter(fn ($l) => $dayStatus($l) === 'pending')->count();
    $totalDays  = $dayGroups->count();
    $balance    = $challenge->currentBalance();
@endphp
<div class="a-card" style="margin-bottom:1.25rem; border-color:rgba(245,158,11,.3);">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; flex-wrap:wrap; gap:.5rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">
            📋 Current Challenge
            <span style="font-size:.7rem; margin-left:.5rem; padding:2px 8px; border-radius:999px;
                background:{{ $challenge->status === 'active' ? 'rgba(16,185,129,.15)' : 'rgba(107,114,128,.15)' }};
                border:1px solid {{ $challenge->status === 'active' ? 'rgba(16,185,129,.3)' : 'rgba(107,114,128,.25)' }};
                color:{{ $challenge->status === 'active' ? '#6ee7b7' : '#9ca3af' }};">
                {{ ucfirst($challenge->status) }}
            </span>
        </span>
        <span style="font-size:.72rem; color:var(--dim);">Started {{ $challenge->started_at->format('M d, Y') }}</span>
    </div>

    <div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); margin-bottom:1rem;">
        <div class="stat-card" style="background:rgba(245,158,11,.06); border-color:rgba(245,158,11,.2);">
            <span class="stat-val" style="color:#fcd34d;">{{ number_format((float)$challenge->initial_stake, 0) }}</span>
            <span class="stat-lbl">Starting Stake (NGN)</span>
        </div>
        <div class="stat-card" style="background:rgba(16,185,129,.06); border-color:rgba(16,185,129,.2);">
            <span class="stat-val" style="color:#6ee7b7;">{{ number_format($balance, 0) }}</span>
            <span class="stat-lbl">Current Balance (NGN)</span>
        </div>
        <div class="stat-card">
            <span class="stat-val" style="color:#6ee7b7;">{{ $wonPicks }}</span>
            <span class="stat-lbl">✓ Won</span>
        </div>
        <div class="stat-card">
            <span class="stat-val" style="color:#fca5a5;">{{ $lostPicks }}</span>
            <span class="stat-lbl">✗ Lost</span>
        </div>
        <div class="stat-card">
            <span class="stat-val" style="color:#fcd34d;">{{ $pendPicks }}</span>
            <span class="stat-lbl">⏳ Pending</span>
        </div>
        <div class="stat-card">
            <span class="stat-val">{{ $totalDays }}/10</span>
            <span class="stat-lbl">Days Used</span>
        </div>
    </div>

    {{-- Picks table --}}
    @if($challenge->picks->isNotEmpty())
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Match</th>
                    <th>League</th>
                    <th>Tip</th>
                    <th>AI Consensus</th>
                    <th>Odds</th>
                    <th>Stake</th>
                    <th>Return</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($dayGroups as $day => $legs)
                @foreach($legs as $i => $rp)
                @php $rm = $rp->match; @endphp
                <tr>
                    @if($i === 0)
                    <td rowspan="{{ $legs->count() }}" style="font-weight:800; color:#fcd34d; vertical-align:top; white-space:nowrap;">Day {{ $day }}@if($legs->count() > 1)<br><span style="font-size:.62rem; color:var(--dim); font-weight:600;">{{ $legs->count() }} legs</span>@endif</td>
                    @endif
                    <td>@include('admin.partials.fixture-mini', ['match' => $rm])</td>
                    <td style="color:var(--dim); font-size:.74rem; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        {{ \App\Support\LeagueCoverage::formatName($rm?->league, $rm?->league_country) }}
                    </td>
                    <td style="color:#6ee7b7; font-weight:700;">{{ $rp->groq_verdict }}</td>
                    <td style="font-size:.72rem;">
                        <span style="color:#a5b4fc;">G: {{ $rp->groq_verdict }}</span><br>
                        @if($rp->gemini_verdict)
                        <span style="color:#93c5fd;">M: {{ $rp->gemini_verdict }}</span><br>
                        @endif
                        @if($rp->both_agree)
                        <span style="color:#6ee7b7; font-weight:700;">✓ Agreed</span>
                        @else
                        <span style="color:var(--dim);">Single AI</span>
                        @endif
                    </td>
                    <td style="color:#fcd34d; font-weight:700;">{{ $rp->implied_odds }}</td>
                    <td>{{ number_format((float)$rp->stake_amount, 0) }}</td>
                    <td style="color:#6ee7b7;">{{ number_format((float)$rp->potential_return, 0) }}</td>
                    <td style="color:var(--dim);">{{ $rp->result_score ?? '-' }}</td>
                    <td>
                        @if($rp->status === 'won')
                            <span class="badge badge-green">✓ Won</span>
                        @elseif($rp->status === 'lost')
                            <span class="badge badge-red">✗ Lost</span>
                        @elseif($rp->status === 'void')
                            <span class="badge badge-gray">Void</span>
                        @else
                            <span class="badge badge-gray">⏳ Pending</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:.3rem; align-items:center; flex-wrap:wrap;">
                            {{-- Quick void for pending --}}
                            @if($rp->status === 'pending')
                            <form method="POST" action="{{ route('admin.rollover.void-pick', $rp) }}" onsubmit="return confirm('Void this pick?');">
                                @csrf
                                <button type="submit" class="btn-a btn-red" style="padding:2px 8px; font-size:.67rem;">Void</button>
                            </form>
                            @endif
                            {{-- Override button — all picks --}}
                            <button onclick="toggleOverride('override-{{ $rp->id }}')"
                                    style="background:rgba(245,158,11,.15); border:1px solid rgba(245,158,11,.35); color:#fcd34d; border-radius:5px; padding:2px 8px; font-size:.67rem; font-weight:700; cursor:pointer;">
                                ✏️ Override
                            </button>
                        </div>
                        {{-- Override form (hidden by default) --}}
                        <div id="override-{{ $rp->id }}" style="display:none; margin-top:.5rem; background:rgba(245,158,11,.06); border:1px solid rgba(245,158,11,.2); border-radius:8px; padding:.65rem;">
                            <form method="POST" action="{{ route('admin.rollover.override-pick', $rp) }}"
                                  onsubmit="return confirm('Override pick #{{ $rp->day_number }} status? This also updates the challenge status.');">
                                @csrf
                                <div style="font-size:.68rem; color:#fcd34d; font-weight:700; margin-bottom:.4rem;">Override Day {{ $rp->day_number }}</div>
                                <div style="display:flex; gap:.35rem; flex-wrap:wrap; margin-bottom:.4rem;">
                                    <select name="status" style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:5px; padding:3px 6px; font-size:.7rem; flex:1;">
                                        @foreach(['won' => '✅ Won', 'lost' => '❌ Lost', 'pending' => '⏳ Pending', 'void' => '⬜ Void'] as $val => $lbl)
                                            <option value="{{ $val }}" {{ $rp->status === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="result_score" value="{{ $rp->result_score }}" placeholder="Score e.g. 2-1"
                                           pattern="\d+-\d+"
                                           style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:5px; padding:3px 6px; font-size:.7rem; width:80px;">
                                </div>
                                <button type="submit" style="background:rgba(245,158,11,.2); border:1px solid rgba(245,158,11,.4); color:#fcd34d; border-radius:5px; padding:3px 12px; font-size:.7rem; font-weight:700; cursor:pointer; width:100%;">
                                    Save Override
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <p style="font-size:.8rem; color:var(--dim); text-align:center; padding:1rem;">No picks in this challenge yet.</p>
    @endif
</div>
@else
<div class="a-card" style="margin-bottom:1.25rem; text-align:center; padding:2rem;">
    <div style="font-size:2rem; margin-bottom:.5rem;">🎯</div>
    <div style="font-weight:800; color:#fff; margin-bottom:.5rem;">No Active Challenge</div>
    <p style="font-size:.8rem; color:var(--dim);">Start a new challenge below.</p>
</div>
@endif

{{-- New challenge form --}}
<div class="a-card" style="margin-bottom:1.25rem;">
    <div style="font-weight:700; font-size:.9rem; color:#fff; margin-bottom:.875rem;">🚀 Start New Challenge</div>
    <form method="POST" action="{{ route('admin.rollover.new-challenge') }}"
          onsubmit="return confirm('This will end the current challenge (if active) and start fresh. Continue?');">
        @csrf
        <div style="display:flex; gap:.75rem; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:200px;">
                <label class="form-label">Initial Stake (NGN)</label>
                <input type="number" name="initial_stake" value="10000" min="1000" max="1000000" step="1000" class="form-input">
                <div class="form-hint">The starting stake amount for the new 10-day run.</div>
            </div>
            <button type="submit" class="btn-a" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff; margin-bottom:1.1rem;">
                🔄 Start New Challenge
            </button>
        </div>
    </form>
</div>

{{-- Past challenges --}}
@if($history->count() > 1)
<div style="font-weight:700; font-size:.9rem; color:#fff; margin:1.25rem 0 .75rem;">📜 Past Challenges <span style="font-size:.72rem; color:var(--dim); font-weight:500;">(click to expand)</span></div>
@foreach($history as $ch)
@php
    $chDays    = $ch->picks->groupBy('day_number')->sortKeys();
    $chStatusOf = fn ($legs) => $legs->contains(fn ($l) => $l->status === 'lost') ? 'lost'
                    : ($legs->contains(fn ($l) => $l->status === 'pending') ? 'pending' : 'won');
    $chWon  = $chDays->filter(fn ($l) => $chStatusOf($l) === 'won')->count();
    $chLost = $chDays->filter(fn ($l) => $chStatusOf($l) === 'lost')->count();
    $chTot  = $chDays->count();
@endphp
<details class="a-card" style="margin-bottom:.75rem;" {{ $loop->first ? 'open' : '' }}>
    <summary style="cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; list-style:none;">
        <span style="font-weight:700; color:#fff; font-size:.85rem;">📋 {{ $ch->started_at->format('M d, Y') }}</span>
        <span style="font-size:.74rem; color:var(--dim);">
            {{ $chTot }}/10 days · <span style="color:#6ee7b7;">{{ $chWon }}W</span>/<span style="color:#fca5a5;">{{ $chLost }}L</span> ·
            ₦{{ number_format($ch->currentBalance(), 0) }} ·
            @if($ch->status === 'active')<span style="color:#6ee7b7;">Active</span>
            @elseif($ch->status === 'complete' && $chWon >= 10)<span style="color:#6ee7b7;">🏆 Complete</span>
            @else<span style="color:#9ca3af;">Ended</span>@endif
        </span>
    </summary>
    <div style="overflow-x:auto; margin-top:.75rem;">
        <table class="a-table">
            <thead><tr><th>Day</th><th>Match</th><th>Tip</th><th>Odds</th><th>Score</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($chDays as $day => $legs)
                @foreach($legs as $i => $rp)
                @php $rm = $rp->match; @endphp
                <tr>
                    @if($i === 0)<td rowspan="{{ $legs->count() }}" style="font-weight:800; color:#fcd34d; vertical-align:top;">Day {{ $day }}</td>@endif
                    <td>@include('admin.partials.fixture-mini', ['match' => $rm])</td>
                    <td style="color:#6ee7b7; font-weight:600;">{{ $rp->groq_verdict }}</td>
                    <td style="color:#fcd34d;">{{ $rp->implied_odds }}</td>
                    <td style="color:var(--dim);">{{ $rp->result_score ?? '-' }}</td>
                    <td>
                        @if($rp->status === 'won')<span class="badge badge-green">✓</span>
                        @elseif($rp->status === 'lost')<span class="badge badge-red">✗</span>
                        @elseif($rp->status === 'void')<span class="badge badge-gray">Void</span>
                        @else<span class="badge badge-gray">⏳</span>@endif
                    </td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</details>
@endforeach
@endif

<script>
function toggleOverride(id) {
    var el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
@endsection
