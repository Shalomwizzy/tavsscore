@extends('layouts.app')
@section('title', 'Rollover Challenge — Day ' . ($challenge ? $challenge->currentDay() - 1 : 0) . ' of 10')
@section('meta_description', 'Follow TavsScore\'s 10-day rollover challenge. One ultra-safe pick per day, compounding stakes for maximum returns. Triple-AI validated — all 3 engines must agree.')

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
    .rv-date-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
    .rv-date-nav a, .rv-date-nav span {
        font-size: .78rem; font-weight: 700; color: var(--text-dim);
        text-decoration: none; padding: .35rem .75rem; border-radius: 999px;
        border: 1px solid var(--border); background: var(--card);
    }
    .rv-date-nav a:hover { color: var(--text); background: rgba(255,255,255,.05); }
    .rv-date-nav .rv-date-center { border: none; background: none; color: #fff; font-size: .88rem; }

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
        .rv-tbl th:nth-child(4), .rv-tbl td:nth-child(4) { display: none; }
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
    $wonPicks   = $allPicks->where('status', 'won')->count();
    $totalPicks = $allPicks->count();
@endphp

<div class="wrap" style="padding-top:1.5rem; padding-bottom:3rem;">

    {{-- Date navigation --}}
    <div class="rv-date-nav">
        @if($prevPick)
            <a href="{{ route('rollover.show', $prevPick->pick_date->format('Y-m-d')) }}">← {{ $prevPick->pick_date->format('M d') }}</a>
        @else
            <span style="opacity:.3;">← Prev</span>
        @endif

        <span class="rv-date-center">{{ $viewDate->format('F j, Y') }}</span>

        @if($nextPick)
            <a href="{{ route('rollover.show', $nextPick->pick_date->format('Y-m-d')) }}">{{ $nextPick->pick_date->format('M d') }} →</a>
        @else
            <span style="opacity:.3;">Next →</span>
        @endif
    </div>

    @if(! $challenge)
        <div class="rv-empty">
            <div class="rv-empty-icon">🎯</div>
            <div class="rv-empty-title">No Rollover Challenge Yet</div>
            <p style="font-size:.82rem; margin-top:.5rem;">The first challenge will start soon. Check back tomorrow!</p>
        </div>
    @else

    {{-- Challenge hero --}}
    @php
        $currentBalance = $challenge->currentBalance();
        $projFinal      = $challenge->projectedFinal();
        $daysDone       = $allPicks->where('status', 'won')->count();
        $displayDay     = $totalPicks > 0 ? $totalPicks : ($isToday && $todayPick ? 1 : 0);
    @endphp

    @if($challenge->status === 'complete' && $daysDone >= 10)
    <div class="rv-complete">
        <div class="rv-complete-icon">🏆</div>
        <div class="rv-complete-title">Challenge Complete!</div>
        <p style="color:var(--text-dim); font-size:.82rem; margin-bottom:.5rem;">
            10/10 picks correct — {{ number_format($currentBalance, 0) }} NGN from {{ number_format((float)$challenge->initial_stake, 0) }} NGN starting stake.
        </p>
        <p style="font-size:.74rem; color:#6ee7b7;">Next challenge starts tomorrow. Come back then!</p>
    </div>
    @elseif($challenge->status === 'complete' && $daysDone < 10)
    <div class="rv-bust">
        <div class="rv-bust-icon">💀</div>
        <div class="rv-bust-title">Challenge Over — Pick Lost</div>
        <p style="color:var(--text-dim); font-size:.82rem; margin-bottom:.5rem;">
            Made it {{ $daysDone }} days. A new challenge starts tomorrow.
        </p>
    </div>
    @endif

    <div class="rv-hero">
        <div class="rv-hero-label">10-Day Rollover Challenge</div>
        <div class="rv-hero-day">Day {{ $totalPicks }}/10</div>
        <div class="rv-hero-sub">{{ $wonPicks }}/{{ $totalPicks }} picks correct so far</div>

        <div class="rv-balance-row">
            <div class="rv-bal-item">
                <span class="rv-bal-val">{{ number_format((float)$challenge->initial_stake, 0) }} NGN</span>
                <span class="rv-bal-lbl">Starting stake</span>
            </div>
            <div class="rv-bal-item">
                <span class="rv-bal-val" style="color:#fcd34d;">{{ number_format($currentBalance, 0) }} NGN</span>
                <span class="rv-bal-lbl">Current balance</span>
            </div>
            <div class="rv-bal-item">
                <span class="rv-bal-val" style="color:#6ee7b7;">{{ number_format($projFinal, 0) }} NGN</span>
                <span class="rv-bal-lbl">Projected (if all win)</span>
            </div>
        </div>

        {{-- 10-day progress dots --}}
        <div class="rv-progress">
            @for($d = 1; $d <= 10; $d++)
            @php
                $dp = $allPicks->firstWhere('day_number', $d);
                $dotClass = 'rv-dot';
                $label    = $d;
                if ($dp) {
                    if ($dp->status === 'won')  { $dotClass .= ' won';  $label = '✓'; }
                    elseif ($dp->status === 'lost') { $dotClass .= ' lost'; $label = '✗'; }
                    elseif ($dp->status === 'void') { $dotClass .= ' void'; }
                    elseif ($isToday && $dp->pick_date->toDateString() === $todayDate) { $dotClass .= ' today'; }
                    else { $dotClass .= ' today'; }
                }
            @endphp
            <div class="{{ $dotClass }}" title="Day {{ $d }}">{{ $label }}</div>
            @endfor
        </div>
    </div>

    {{-- Today's pick --}}
    @if($todayPick)
    @php
        $m         = $todayPick->match;
        $kickoff   = $m?->match_time?->setTimezone($tz)->format('H:i') . ' Lagos';
        $waText    = urlencode("🎯 TavsScore Rollover Day {$todayPick->day_number}/10\n{$m?->home_team} vs {$m?->away_team}\nTip: {$todayPick->groq_verdict} @ {$todayPick->implied_odds} odds\nStake: " . number_format($todayPick->stake_amount, 0) . " → Return: " . number_format($todayPick->potential_return, 0) . " NGN\n🔗 " . url('/rollover'));
        $cardExtra = $todayPick->status === 'won' ? 'rv-result-won' : ($todayPick->status === 'lost' ? 'rv-result-lost' : '');
    @endphp
    <div class="rv-pick-card {{ $cardExtra }}">
        <div class="rv-pick-badge">
            📅 Day {{ $todayPick->day_number }} of 10 — {{ $todayPick->pick_date->format('M d, Y') }}
        </div>

        @if($todayPick->status === 'won')
        <div class="rv-result-banner won">✅ Pick Won! {{ $todayPick->result_score }} — Balance upgraded to {{ number_format((float)$todayPick->potential_return, 0) }} NGN</div>
        @elseif($todayPick->status === 'lost')
        <div class="rv-result-banner lost">❌ Pick Lost {{ $todayPick->result_score }} — Challenge resets tomorrow</div>
        @endif

        <div class="rv-pick-teams">{{ $m?->home_team }} vs {{ $m?->away_team }}</div>
        <div class="rv-pick-league">{{ $m?->league }}{{ $m?->league_country ? ' · ' . $m->league_country : '' }}</div>

        <div class="rv-pick-tip">
            ✅ {{ $todayPick->groq_verdict }}
        </div>

        <div class="rv-ai-badges">
            <span class="rv-ai-badge rv-ai-groq">🤖 AI #1: {{ $todayPick->groq_verdict }}</span>
            @if($todayPick->gemini_verdict)
            <span class="rv-ai-badge rv-ai-gemini">🤖 AI #2: {{ $todayPick->gemini_verdict }}</span>
            @endif
            @if($todayPick->mistral_verdict)
            <span class="rv-ai-badge rv-ai-gemini">🤖 AI #3: {{ $todayPick->mistral_verdict }}</span>
            @endif
            @if($todayPick->both_agree)
            <span class="rv-ai-badge rv-ai-agreed">✓ All 3 engines agree</span>
            @endif
        </div>

        <div class="rv-odds-row">
            <div class="rv-odds-item">
                <span class="rv-odds-val">{{ $todayPick->implied_odds }}</span>
                <span class="rv-odds-lbl">Implied odds</span>
            </div>
            <div class="rv-odds-item">
                <span class="rv-odds-val">{{ number_format((float)$todayPick->stake_amount, 0) }} NGN</span>
                <span class="rv-odds-lbl">Today's stake</span>
            </div>
            <div class="rv-odds-item">
                <span class="rv-odds-val" style="color:#6ee7b7;">{{ number_format((float)$todayPick->potential_return, 0) }} NGN</span>
                <span class="rv-odds-lbl">Return if correct</span>
            </div>
        </div>

        <div class="rv-kickoff">
            <span>🕐 Kickoff {{ $kickoff }}</span>
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
        <div style="font-weight:800; color:#fff; margin-bottom:.35rem;">Today's Pick Not Yet Selected</div>
        <p style="font-size:.8rem; color:var(--text-dim);">The AI selects the safest game each day at 09:30 Lagos time. Check back soon.</p>
    </div>
    @endif

    {{-- Disclaimer --}}
    <div class="rv-disclaimer">
        ⚠️ <strong>Rollover is a compounding strategy, not a guarantee.</strong> The AI picks the safest available match using triple cross-validation — three independent engines must all agree — but no prediction is certain. Only stake what you can afford to lose. If the pick loses, the challenge resets — this is normal.
    </div>

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
                        <th>Stake</th>
                        <th>Return</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allPicks as $rp)
                    @php $rm = $rp->match; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('rollover.show', $rp->pick_date->format('Y-m-d')) }}">
                                Day {{ $rp->day_number }}<br>
                                <span style="color:var(--text-dim); font-size:.65rem;">{{ $rp->pick_date->format('M d') }}</span>
                            </a>
                        </td>
                        <td style="color:#fff; font-weight:600; white-space:nowrap;">
                            {{ $rm?->home_team }} vs {{ $rm?->away_team }}
                            @if($rp->result_score)
                                <span style="color:var(--text-dim); font-size:.7rem;"> ({{ $rp->result_score }})</span>
                            @endif
                        </td>
                        <td style="color:#6ee7b7; font-weight:700;">
                            {{ $rp->groq_verdict }}
                            @if($rp->both_agree) <span title="Both AIs agree" style="font-size:.65rem;">✓✓</span> @endif
                        </td>
                        <td style="color:#fcd34d; font-weight:700;">{{ $rp->implied_odds }}</td>
                        <td style="color:var(--text-dim);">{{ number_format((float)$rp->stake_amount, 0) }}</td>
                        <td style="color:#6ee7b7;">{{ number_format((float)$rp->potential_return, 0) }}</td>
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
                </tbody>
            </table>
        </div>
    </div>
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
                <div style="font-weight:700; color:#6ee7b7; font-size:.8rem; margin-bottom:.25rem;">Compound Stakes</div>
                <div style="font-size:.74rem; color:var(--text-dim);">Start with any amount. If today's pick wins, your full return (stake + profit) becomes tomorrow's stake automatically.</div>
            </div>
            <div style="background:rgba(99,102,241,.05); border:1px solid rgba(99,102,241,.15); border-radius:10px; padding:.875rem;">
                <div style="font-size:1.2rem; margin-bottom:.35rem;">🎯</div>
                <div style="font-weight:700; color:#a5b4fc; font-size:.8rem; margin-bottom:.25rem;">Safe Low Odds</div>
                <div style="font-size:.74rem; color:var(--text-dim);">We target odds between 1.10 and 1.50 — the sweet spot where high confidence matches low risk for a single-game stake.</div>
            </div>
            <div style="background:rgba(251,191,36,.05); border:1px solid rgba(251,191,36,.15); border-radius:10px; padding:.875rem;">
                <div style="font-size:1.2rem; margin-bottom:.35rem;">🔄</div>
                <div style="font-weight:700; color:#fbbf24; font-size:.8rem; margin-bottom:.25rem;">10-Day Cycles</div>
                <div style="font-size:.74rem; color:var(--text-dim);">Each challenge runs for 10 days. After a win streak completes 10 days, the AI rests and a fresh challenge begins.</div>
            </div>
        </div>
    </div>

    @endif
</div>
@endsection
