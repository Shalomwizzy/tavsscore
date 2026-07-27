@extends('layouts.app')
@section('title', 'Rollover Challenge, Day ' . ($challenge ? $challenge->currentDay() - 1 : 0) . ' of 10')
@section('meta_description', 'Follow TavsScore\'s 10-day rollover challenge. One ultra-safe pick per day, triple-AI validated. All 3 engines must agree before a pick is selected.')

@push('styles')
<style>
    .rv-hero {
        background: linear-gradient(135deg, rgba(245,158,11,.12) 0%, rgba(251,191,36,.06) 100%);
        border: 1px solid rgba(245,158,11,.25);
        border-radius: 16px;
        padding: 2rem 1.5rem;
        margin-bottom: 1.5rem;
        text-align: center;
    }
    .rv-hero-label  { font-size: .72rem; font-weight: 700; color: #fbbf24; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .5rem; }
    .rv-hero-day    { font-size: 2.8rem; font-weight: 900; color: #fcd34d; line-height: 1; margin-bottom: .25rem; }
    .rv-hero-sub    { font-size: .82rem; color: var(--text-dim); margin-bottom: 1.25rem; }
    .rv-balance-row { display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
    .rv-bal-item    { text-align: center; }
    .rv-bal-val     { font-size: 1.4rem; font-weight: 800; color: #fff; display: block; }
    .rv-bal-lbl     { font-size: .66rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: .06em; }

    /* 10-day progress dots */
    .rv-progress    { display: flex; justify-content: center; gap: .4rem; flex-wrap: wrap; }
    .rv-dot         { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 800; border: 2px solid rgba(255,255,255,.1); color: var(--text-dim); }
    .rv-dot.won     { background: rgba(16,185,129,.25); border-color: #10b981; color: #6ee7b7; }
    .rv-dot.lost    { background: rgba(239,68,68,.2); border-color: #ef4444; color: #fca5a5; }
    .rv-dot.today   { background: rgba(245,158,11,.2); border-color: #f59e0b; color: #fcd34d; animation: pulseGold 2s infinite; }
    .rv-dot.void    { background: rgba(107,114,128,.15); border-color: rgba(107,114,128,.4); color: #6b7280; }
    @keyframes pulseGold {
        0%,100% { box-shadow: 0 0 0 0 rgba(245,158,11,.4); }
        50%      { box-shadow: 0 0 0 6px rgba(245,158,11,0); }
    }

    /* Date nav */
    .rv-date-nav { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; margin-bottom: 1.25rem; padding: .5rem .65rem; background: rgba(255,255,255,.025); border: 1px solid var(--border); border-radius: 10px; }
    .rv-date-nav a, .rv-date-nav span.rv-nav-btn {
        font-size: .74rem; font-weight: 700; color: var(--text);
        text-decoration: none; padding: .38rem .8rem; border-radius: 7px;
        border: 1px solid var(--border); background: rgba(255,255,255,.05);
        white-space: nowrap; transition: background 140ms;
    }
    .rv-date-nav a:hover { background: rgba(255,255,255,.1); border-color: rgba(99,179,237,.3); color: #fff; }
    .rv-nav-disabled { opacity: .35; cursor: not-allowed; }
    .rv-nav-today { margin-left: auto; background: rgba(245,158,11,.12) !important; border-color: rgba(245,158,11,.28) !important; color: #fcd34d !important; }
    .rv-date-input {
        background: rgba(8,13,26,.7); border: 1px solid var(--border); border-radius: 7px;
        padding: .38rem .6rem; color: var(--text); font-family: inherit;
        font-size: .76rem; font-weight: 700; cursor: pointer; color-scheme: dark;
    }

    /* Today's pick card */
    .rv-pick-card {
        background: var(--card);
        border: 1px solid rgba(245,158,11,.3);
        border-radius: 14px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }
    .rv-pick-card::before {
        content: '';
        position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, #f59e0b, #fbbf24, #f59e0b);
    }
    .rv-pick-badge   { display: inline-flex; align-items: center; gap: .4rem; font-size: .7rem; font-weight: 700; color: #fcd34d; background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.25); padding: 3px 10px; border-radius: 999px; margin-bottom: .875rem; }
    .rv-pick-teams   { font-size: 1.35rem; font-weight: 900; color: #fff; margin-bottom: .25rem; }
    .rv-pick-league  { font-size: .74rem; color: var(--text-dim); margin-bottom: 1rem; }
    .rv-pick-tip     { display: inline-flex; align-items: center; gap: .5rem; background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.3); color: #6ee7b7; font-weight: 800; font-size: .95rem; padding: .45rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
    .rv-ai-badges    { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .rv-ai-badge     { font-size: .68rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
    .rv-ai-groq      { background: rgba(99,102,241,.15); border: 1px solid rgba(99,102,241,.3); color: #a5b4fc; }
    .rv-ai-gemini    { background: rgba(59,130,246,.15); border: 1px solid rgba(59,130,246,.3); color: #93c5fd; }
    .rv-ai-agreed    { background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.3); color: #6ee7b7; }
    .rv-odds-row     { display: flex; gap: 1.25rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .rv-odds-item    { text-align: center; }
    .rv-odds-val     { font-size: 1.1rem; font-weight: 800; color: #fcd34d; display: block; }
    .rv-odds-lbl     { font-size: .62rem; color: var(--text-dim); text-transform: uppercase; }
    .rv-kickoff      { font-size: .74rem; color: var(--text-dim); padding-top: .875rem; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }

    /* Result states */
    .rv-result-won  { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.4); }
    .rv-result-lost { background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.35); }
    .rv-result-banner { display: flex; align-items: center; gap: .65rem; padding: .65rem .875rem; border-radius: 8px; font-weight: 700; font-size: .82rem; margin-bottom: .875rem; }
    .rv-result-banner.won  { background: rgba(16,185,129,.15); color: #6ee7b7; }
    .rv-result-banner.lost { background: rgba(239,68,68,.15); color: #fca5a5; }

    /* History table */
    .rv-table-wrap  { background: var(--card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; margin-bottom: 1.5rem; }
    .rv-table-hd    { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
    .rv-table-title { font-weight: 800; font-size: .9rem; color: #fff; }
    .rv-tbl         { width: 100%; border-collapse: collapse; }
    .rv-tbl th      { font-size: .68rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: .04em; padding: .6rem 1rem; border-bottom: 1px solid var(--border); text-align: left; }
    .rv-tbl td      { padding: .65rem 1rem; font-size: .8rem; border-bottom: 1px solid rgba(255,255,255,.03); vertical-align: middle; }
    .rv-tbl tr:last-child td { border-bottom: none; }
    .rv-tbl tr:hover td { background: rgba(255,255,255,.02); }
    .rv-tbl a        { color: var(--text-dim); text-decoration: none; font-size: .72rem; }
    .rv-tbl a:hover  { color: var(--text); }

    /* Status badges */
    .rv-badge        { display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 999px; font-size: .66rem; font-weight: 700; }
    .rv-badge-won    { background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.3); color: #6ee7b7; }
    .rv-badge-lost   { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); color: #fca5a5; }
    .rv-badge-pend   { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.25); color: #fcd34d; }
    .rv-badge-void   { background: rgba(107,114,128,.12); border: 1px solid rgba(107,114,128,.25); color: #9ca3af; }

    /* Empty */
    .rv-empty        { text-align: center; padding: 4rem 1.5rem; color: var(--text-dim); }
    .rv-empty-icon   { font-size: 3rem; margin-bottom: .75rem; }
    .rv-empty-title  { font-size: 1.1rem; font-weight: 800; color: var(--text); margin-bottom: .5rem; }

    /* Past challenges accordion */
    details.rv-table-wrap summary::-webkit-details-marker { display: none; }
    details.rv-table-wrap summary::marker { display: none; }
    details.rv-table-wrap[open] summary { border-bottom: 1px solid var(--border); }

    /* Disclaimer */
    .rv-disclaimer   { background: rgba(245,158,11,.06); border: 1px solid rgba(245,158,11,.18); border-radius: 10px; padding: .875rem 1rem; font-size: .74rem; color: #fbbf24; margin-bottom: 1.5rem; }

    /* Bust banner */
    .rv-bust         { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3); border-radius: 14px; padding: 1.5rem; text-align: center; margin-bottom: 1.5rem; }
    .rv-bust-icon    { font-size: 2.5rem; margin-bottom: .5rem; }
    .rv-bust-title   { font-size: 1.1rem; font-weight: 800; color: #fca5a5; margin-bottom: .35rem; }

    /* Complete banner */
    .rv-complete     { background: linear-gradient(135deg,rgba(16,185,129,.15),rgba(16,185,129,.05)); border: 1px solid rgba(16,185,129,.35); border-radius: 14px; padding: 1.5rem; text-align: center; margin-bottom: 1.5rem; }
    .rv-complete-icon { font-size: 2.5rem; margin-bottom: .5rem; }
    .rv-complete-title { font-size: 1.1rem; font-weight: 800; color: #6ee7b7; margin-bottom: .35rem; }

    @media (max-width: 600px) {
        .rv-odds-row { gap: .875rem; }
    }
</style>
@endpush

@section('content')
@php
    use Carbon\Carbon;
    $tz         = 'Africa/Lagos';
    $todayDate  = \Carbon\CarbonImmutable::now($tz)->toDateString();
    $isToday    = $viewDate->toDateString() === $todayDate;
    // Day-level tallies (a day = one rollover step, however many legs it holds).
    $wonPicks   = $wonDays;
    $totalPicks = $totalDays;
@endphp

<div class="wrap" style="padding-top:1.5rem; padding-bottom:3rem;">

    {{-- Date navigation --}}
    @php
        $todayDateStr = \Carbon\CarbonImmutable::now('Africa/Lagos')->toDateString();
        $viewDateStr  = $viewDate->toDateString();
        $isRvToday    = $viewDateStr === $todayDateStr;
        $minRv        = now('Africa/Lagos')->subDays(365)->toDateString();
    @endphp
    <div class="rv-date-nav">
        @if($prevPick)
            <a href="{{ route('rollover.show', $prevPick->pick_date->format('Y-m-d')) }}">‹ {{ $prevPick->pick_date->format('M d') }}</a>
        @else
            <span class="rv-nav-btn rv-nav-disabled">‹ Prev</span>
        @endif

        <input type="date"
               class="rv-date-input"
               value="{{ $viewDateStr }}"
               min="{{ $minRv }}"
               max="{{ $todayDateStr }}"
               aria-label="Jump to date"
               onchange="if(this.value) window.location='/rollover/'+this.value">

        @if($nextPick)
            <a href="{{ route('rollover.show', $nextPick->pick_date->format('Y-m-d')) }}">{{ $nextPick->pick_date->format('M d') }} ›</a>
        @else
            <span class="rv-nav-btn rv-nav-disabled">Next ›</span>
        @endif

        @unless($isRvToday)
            <a href="{{ route('rollover.index') }}" class="rv-nav-btn rv-nav-today">Today</a>
        @endunless
    </div>

    @if(! $challenge)
        <div class="rv-empty">
            <div class="rv-empty-icon">🎯</div>
            <div class="rv-empty-title">No Rollover Challenge Yet</div>
            <p style="font-size:.82rem; margin-top:.5rem;">The first challenge will start soon. Check back tomorrow!</p>
        </div>
    @else

    {{-- Challenge hero --}}
    @if($challenge->status === 'complete' && $wonPicks >= 10)
    <div class="rv-complete">
        <div class="rv-complete-icon">🏆</div>
        <div class="rv-complete-title">Challenge Complete!</div>
        <p style="color:var(--text-dim); font-size:.82rem; margin-bottom:.5rem;">
            10/10 picks correct. A perfect run — triple-AI validation held strong all 10 days.
        </p>
        <p style="font-size:.74rem; color:#6ee7b7;">Next challenge starts tomorrow. Come back then!</p>
    </div>
    @elseif($challenge->status === 'complete' && $wonPicks < 10)
    <div class="rv-bust">
        <div class="rv-bust-icon">💀</div>
        <div class="rv-bust-title">Challenge Over. Pick Lost</div>
        <p style="color:var(--text-dim); font-size:.82rem; margin-bottom:.5rem;">
            Made it {{ $wonPicks }} days. A new challenge starts tomorrow.
        </p>
    </div>
    @endif

    <div class="rv-hero">
        <div class="rv-hero-label">10-Day Rollover Challenge</div>
        <div class="rv-hero-day">Day {{ $totalPicks }}/10</div>
        <div class="rv-hero-sub">{{ $wonPicks }}/{{ $totalPicks }} picks correct so far</div>

        <div class="rv-balance-row">
            <div class="rv-bal-item">
                <span class="rv-bal-val">{{ $wonPicks }}/{{ $totalPicks }}</span>
                <span class="rv-bal-lbl">Picks correct</span>
            </div>
            <div class="rv-bal-item">
                <span class="rv-bal-val" style="color:#fcd34d;">{{ 10 - $totalPicks }}</span>
                <span class="rv-bal-lbl">Days remaining</span>
            </div>
            <div class="rv-bal-item">
                <span class="rv-bal-val" style="color:#6ee7b7;">{{ $streak }}</span>
                <span class="rv-bal-lbl">Current streak</span>
            </div>
        </div>

        {{-- 10-day progress dots --}}
        <div class="rv-progress">
            @for($d = 1; $d <= 10; $d++)
            @php
                $ds       = $dayStatuses[$d] ?? null;
                $dotClass = 'rv-dot';
                $label    = $d;
                if ($ds === 'won')       { $dotClass .= ' won';  $label = '✓'; }
                elseif ($ds === 'lost')  { $dotClass .= ' lost'; $label = '✗'; }
                elseif ($ds === 'pending'){ $dotClass .= ' today'; }
            @endphp
            <div class="{{ $dotClass }}" title="Day {{ $d }}">{{ $label }}</div>
            @endfor
        </div>
    </div>

    {{-- Today's ticket (1-5 legs) --}}
    @if($todayLegs->isNotEmpty())
    @php
        $dayNo        = $todayLegs->first()->day_number;
        $ticketStatus = $todayLegs->contains(fn ($l) => $l->status === 'lost') ? 'lost'
                        : ($todayLegs->contains(fn ($l) => $l->status === 'pending') ? 'pending' : 'won');
        $combinedOdds = round($todayLegs->reduce(fn ($c, $l) => $c * max(1.0, (float) $l->implied_odds), 1.0), 2);
        $dayReturn    = (float) $todayLegs->first()->potential_return;
        $waLines      = $todayLegs->map(fn ($l) => "{$l->match?->home_team} vs {$l->match?->away_team} — {$l->groq_verdict} @ {$l->implied_odds}")->implode("\n");
        $waText       = urlencode("🎯 TavsScore Rollover Day {$dayNo}/10\n{$waLines}\nTotal odds: {$combinedOdds}\n🔗 " . url('/rollover'));
        $cardExtra    = $ticketStatus === 'won' ? 'rv-result-won' : ($ticketStatus === 'lost' ? 'rv-result-lost' : '');
    @endphp
    <div class="rv-pick-card {{ $cardExtra }}">
        <div class="rv-pick-badge">
            📅 Day {{ $dayNo }} of 10 · {{ $todayLegs->first()->pick_date->format('M d, Y') }}
            · {{ $todayLegs->count() }} {{ Str::plural('leg', $todayLegs->count()) }} @ {{ $combinedOdds }} odds
        </div>

        @if($ticketStatus === 'won')
        <div class="rv-result-banner won">✅ Ticket Won! — challenge continues to Day {{ $dayNo + 1 }}</div>
        @elseif($ticketStatus === 'lost')
        <div class="rv-result-banner lost">❌ Ticket Lost. Challenge resets tomorrow</div>
        @endif

        @foreach($todayLegs as $leg)
        @php
            $m       = $leg->match;
            $kickoff = $m?->match_time?->setTimezone($tz)->format('H:i') . ' Lagos';
            $legIcon = match($leg->status) { 'won' => '✅', 'lost' => '❌', 'void' => '↩️', default => '⏳' };
        @endphp
        <div class="rv-leg" style="border-top:1px solid var(--border); padding:.85rem 0;">
            <div class="rv-pick-teams" style="font-size:.95rem;">{{ $m?->home_team }} vs {{ $m?->away_team }}</div>
            <div class="rv-pick-league">{{ $m?->league }}{{ $m?->league_country ? ' · ' . $m->league_country : '' }} · 🕐 {{ $kickoff }}</div>
            <div style="display:flex; align-items:center; justify-content:space-between; gap:.5rem; margin-top:.4rem;">
                <span class="rv-pick-tip" style="margin:0;">{{ $legIcon }} {{ $leg->groq_verdict }}</span>
                <span class="rv-odds-val" style="color:#fcd34d; font-weight:700;">@ {{ $leg->implied_odds }}
                    @if($leg->result_score)<span style="color:var(--text-dim); font-weight:500; font-size:.72rem;"> ({{ $leg->result_score }})</span>@endif
                </span>
            </div>
        </div>
        @endforeach

        <div class="rv-odds-row" style="border-top:1px solid var(--border); padding-top:.85rem;">
            <div class="rv-odds-item">
                <span class="rv-odds-val">{{ $combinedOdds }}</span>
                <span class="rv-odds-lbl">Combined odds</span>
            </div>
            <div class="rv-odds-item">
                <span class="rv-odds-val" style="color:#6ee7b7;">₦{{ number_format($dayReturn) }}</span>
                <span class="rv-odds-lbl">Ticket returns</span>
            </div>
            <div class="rv-odds-item">
                <span class="rv-odds-val">Day {{ $dayNo }}</span>
                <span class="rv-odds-lbl">of 10</span>
            </div>
        </div>

        <div class="rv-kickoff">
            <span>🎟 {{ $todayLegs->count() }}-leg safe ticket</span>
            <a href="https://wa.me/?text={{ $waText }}" target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:.35rem;background:#25D366;color:#fff;font-size:.68rem;font-weight:700;padding:4px 12px;border-radius:999px;text-decoration:none;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.558 4.118 1.534 5.845L.055 23.454l5.742-1.505A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.372l-.36-.214-3.71.972.989-3.615-.234-.371A9.818 9.818 0 012.182 12C2.182 6.575 6.575 2.182 12 2.182c5.424 0 9.818 4.393 9.818 9.818 0 5.424-4.394 9.818-9.818 9.818z"/></svg>
                Share
            </a>
        </div>
    </div>
    @else
    <div style="background:var(--card); border:1px solid var(--border); border-radius:14px; padding:2rem; text-align:center; margin-bottom:1.5rem;">
        <div style="font-size:2rem; margin-bottom:.5rem;">⏳</div>
        <div style="font-weight:800; color:#fff; margin-bottom:.35rem;">Today's Ticket Not Yet Selected</div>
        <p style="font-size:.8rem; color:var(--text-dim);">Each day at 10:30 Lagos time we build a ticket of the safest picks (up to ~2.00 combined odds). Check back soon.</p>
    </div>
    @endif

    {{-- Disclaimer --}}
    <div class="rv-disclaimer">
        ⚠️ <strong>For entertainment and educational purposes only.</strong> The Rollover Challenge is a hypothetical prediction tracker — no real money is involved, no transactions take place on this site. AI picks may be wrong. Never bet more than you can afford to lose. If you have a gambling problem, visit <a href="https://www.begambleaware.org" target="_blank" rel="noopener" style="color:#fcd34d; text-decoration:underline;">BeGambleAware.org</a>.
    </div>

    @include('partials.affiliate-strip')

    {{-- Challenge history table --}}
    @if($allPicks->isNotEmpty())
    <div class="rv-table-wrap">
        <div class="rv-table-hd">
            <span class="rv-table-title">📋 Challenge History</span>
            <span style="font-size:.72rem; color:var(--text-dim);">Started {{ $challenge->started_at->format('M d, Y') }}</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="rv-tbl">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Match</th>
                        <th>Tip</th>
                        <th>Odds</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dayGroups->sortKeys() as $day => $legs)
                    @php
                        $dayStatus = $legs->contains(fn ($l) => $l->status === 'lost') ? 'lost'
                                     : ($legs->contains(fn ($l) => $l->status === 'pending') ? 'pending' : 'won');
                        $dayOdds   = round($legs->reduce(fn ($c, $l) => $c * max(1.0, (float) $l->implied_odds), 1.0), 2);
                    @endphp
                    @foreach($legs as $i => $rp)
                    @php $rm = $rp->match; @endphp
                    <tr @if($dayStatus === 'lost') style="background:rgba(239,68,68,.06);" @endif>
                        @if($i === 0)
                        <td rowspan="{{ $legs->count() }}" style="vertical-align:top;">
                            <a href="{{ route('rollover.show', $rp->pick_date->format('Y-m-d')) }}">
                                Day {{ $day }}<br>
                                <span style="color:var(--text-dim); font-size:.65rem;">{{ $rp->pick_date->format('M d') }}</span>
                                @if($legs->count() > 1)<br><span style="color:var(--text-dim); font-size:.62rem;">{{ $legs->count() }} legs @ {{ $dayOdds }}</span>@endif
                            </a>
                        </td>
                        @endif
                        <td style="color:#fff; font-weight:600; white-space:nowrap;">
                            {{ $rm?->home_team }} vs {{ $rm?->away_team }}
                            @if($rp->result_score)
                                <span style="color:var(--text-dim); font-size:.7rem;"> ({{ $rp->result_score }})</span>
                            @endif
                        </td>
                        <td style="color:#6ee7b7; font-weight:700;">
                            {{ $rp->groq_verdict }}
                            @if($rp->both_agree) <span title="All AIs agree" style="font-size:.65rem;">✓✓</span> @endif
                        </td>
                        <td style="color:#fcd34d; font-weight:700;">{{ $rp->implied_odds }}</td>
                        <td>
                            @if($rp->status === 'won')
                                <span class="rv-badge rv-badge-won">✓ Won</span>
                            @elseif($rp->status === 'lost')
                                <span class="rv-badge rv-badge-lost">✗ Lost</span>
                            @elseif($rp->status === 'void')
                                <span class="rv-badge rv-badge-void">Void</span>
                            @else
                                <span class="rv-badge rv-badge-pend">⏳ Pending</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Past challenges --}}
    @if($pastChallenges->isNotEmpty())
    <div style="font-size:.78rem; font-weight:800; color:var(--text-dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.75rem;">🗂 Previous Challenges</div>
    @foreach($pastChallenges as $pc)
    @php
        $pcDays  = $pc->picks->groupBy('day_number');
        $pcDayStatus = fn ($legs) => $legs->contains(fn ($l) => $l->status === 'lost') ? 'lost'
                        : ($legs->contains(fn ($l) => $l->status === 'pending') ? 'pending' : 'won');
        $pcWon   = $pcDays->filter(fn ($legs) => $pcDayStatus($legs) === 'won')->count();
        $pcTotal = $pcDays->count();
        $pcResult = $pcWon >= 10 ? '🏆 Completed 10/10' : ($pcWon . '/' . $pcTotal . ' days won — bust day ' . ($pcWon + 1));
        $pcStart = $pc->started_at instanceof \Carbon\Carbon ? $pc->started_at : \Carbon\Carbon::parse($pc->started_at);
    @endphp
    <details class="rv-table-wrap" style="margin-bottom:1rem;" {{ $loop->first ? 'open' : '' }}>
        <summary style="padding:1rem 1.25rem; cursor:pointer; display:flex; align-items:center; justify-content:space-between; list-style:none;">
            <span class="rv-table-title">📋 Challenge: {{ $pcStart->format('M d, Y') }}</span>
            <span style="font-size:.72rem; color:var(--text-dim);">{{ $pcResult }}</span>
        </summary>
        <div style="overflow-x:auto;">
            <table class="rv-tbl">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Match</th>
                        <th>Tip</th>
                        <th>Odds</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pcDays->sortKeys() as $day => $legs)
                    @php $dOdds = round($legs->reduce(fn ($c, $l) => $c * max(1.0, (float) $l->implied_odds), 1.0), 2); @endphp
                    @foreach($legs as $i => $rp)
                    @php $rm = $rp->match; @endphp
                    <tr @if($pcDayStatus($legs) === 'lost') style="background:rgba(239,68,68,.06);" @endif>
                        @if($i === 0)
                        <td rowspan="{{ $legs->count() }}" style="vertical-align:top;">
                            <a href="{{ route('rollover.show', $rp->pick_date->format('Y-m-d')) }}">
                                Day {{ $day }}<br>
                                <span style="color:var(--text-dim); font-size:.65rem;">{{ $rp->pick_date->format('M d') }}</span>
                                @if($legs->count() > 1)<br><span style="color:var(--text-dim); font-size:.62rem;">{{ $legs->count() }} legs @ {{ $dOdds }}</span>@endif
                            </a>
                        </td>
                        @endif
                        <td style="color:#fff; font-weight:600; white-space:nowrap;">
                            {{ $rm?->home_team }} vs {{ $rm?->away_team }}
                            @if($rp->result_score)
                                <span style="color:var(--text-dim); font-size:.7rem;"> ({{ $rp->result_score }})</span>
                            @endif
                        </td>
                        <td style="color:#6ee7b7; font-weight:700;">{{ $rp->groq_verdict }}</td>
                        <td style="color:#fcd34d; font-weight:700;">{{ $rp->implied_odds }}</td>
                        <td>
                            @if($rp->status === 'won')
                                <span class="rv-badge rv-badge-won">✓ Won</span>
                            @elseif($rp->status === 'lost')
                                <span class="rv-badge rv-badge-lost">✗ Lost</span>
                            @else
                                <span class="rv-badge rv-badge-void">Void</span>
                            @endif
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

    {{-- How it works --}}
    <div style="background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1.25rem; margin-bottom:1.5rem;">
        <div style="font-weight:800; color:#fff; font-size:.9rem; margin-bottom:.875rem;">How the Rollover Works</div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:.75rem;">
            <div style="background:rgba(245,158,11,.05); border:1px solid rgba(245,158,11,.15); border-radius:10px; padding:.875rem;">
                <div style="font-size:1.2rem; margin-bottom:.35rem;">🤖</div>
                <div style="font-weight:700; color:#fcd34d; font-size:.8rem; margin-bottom:.25rem;">Triple AI Selection</div>
                <div style="font-size:.74rem; color:var(--text-dim);">Three independent AI engines each analyse the match separately with no knowledge of each other's conclusions. All three must reach the same outcome before a pick is selected.</div>
            </div>
            <div style="background:rgba(16,185,129,.05); border:1px solid rgba(16,185,129,.15); border-radius:10px; padding:.875rem;">
                <div style="font-size:1.2rem; margin-bottom:.35rem;">📈</div>
                <div style="font-weight:700; color:#6ee7b7; font-size:.8rem; margin-bottom:.25rem;">Compounding Odds</div>
                <div style="font-size:.74rem; color:var(--text-dim);">Each day's implied odds build on the last. We track the multiplier so you can see exactly how a 10-day winning streak accumulates. Stake whatever you can afford to lose — or nothing at all.</div>
            </div>
            <div style="background:rgba(99,102,241,.05); border:1px solid rgba(99,102,241,.15); border-radius:10px; padding:.875rem;">
                <div style="font-size:1.2rem; margin-bottom:.35rem;">🎯</div>
                <div style="font-weight:700; color:#a5b4fc; font-size:.8rem; margin-bottom:.25rem;">Safe Low Odds</div>
                <div style="font-size:.74rem; color:var(--text-dim);">We target odds between 1.10 and 1.50, the sweet spot where high confidence meets low risk for a single-game selection.</div>
            </div>
            <div style="background:rgba(251,191,36,.05); border:1px solid rgba(251,191,36,.15); border-radius:10px; padding:.875rem;">
                <div style="font-size:1.2rem; margin-bottom:.35rem;">🔄</div>
                <div style="font-weight:700; color:#fbbf24; font-size:.8rem; margin-bottom:.25rem;">10-Day Cycles</div>
                <div style="font-size:.74rem; color:var(--text-dim);">Each challenge runs for 10 days. After a win streak completes all 10, the AI rests and a fresh challenge begins the next day.</div>
            </div>
        </div>
    </div>

    @endif
</div>
@endsection
