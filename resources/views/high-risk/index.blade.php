@extends('layouts.app')

@section('title', 'High Risk Picks, Big-Odds Accumulators | TavsScore')
@section('meta_description', 'TavsScore high-risk football accumulator picks. Big odds, full ticket legs and transparent settled history. Entertainment only, bet responsibly.')

@push('styles')
<style>
    .risk-page{max-width:1060px;margin:0 auto;padding:1.35rem 1rem 3rem}
    .risk-hero{position:relative;overflow:hidden;border:1px solid rgba(251,113,133,.3);border-radius:24px;padding:clamp(1.35rem,4vw,2.5rem);background:radial-gradient(circle at 84% 12%,rgba(244,63,94,.3),transparent 28%),linear-gradient(130deg,#261022 0%,#171229 48%,#0e1a2a 100%);box-shadow:0 22px 50px rgba(0,0,0,.25)}
    .risk-hero:after{content:'HIGH ODDS';position:absolute;right:-.45rem;bottom:-1.3rem;color:rgba(255,255,255,.035);font-weight:900;font-size:clamp(3.7rem,12vw,8.5rem);letter-spacing:-.09em;white-space:nowrap;pointer-events:none}
    .risk-kicker{position:relative;z-index:1;display:inline-flex;align-items:center;gap:.45rem;color:#fecdd3;font-size:.67rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
    .risk-kicker i{width:8px;height:8px;border-radius:50%;background:#fb7185;box-shadow:0 0 0 5px rgba(251,113,133,.13)}
    .risk-hero h1{position:relative;z-index:1;margin:.6rem 0 .55rem;font-size:clamp(2rem,6vw,3.35rem);line-height:1;font-weight:900;letter-spacing:-.065em;color:#fff}
    .risk-hero p{position:relative;z-index:1;max-width:630px;color:#cbd5e1;font-size:.93rem;line-height:1.7}
    .risk-metrics{position:relative;z-index:1;display:flex;flex-wrap:wrap;gap:.55rem;margin-top:1.35rem}
    .risk-metric{min-width:118px;padding:.68rem .82rem;border:1px solid rgba(255,255,255,.12);border-radius:12px;background:rgba(8,13,26,.34);backdrop-filter:blur(10px)}
    .risk-metric b{display:block;color:#fff;font-size:1.05rem;line-height:1.1}.risk-metric span{display:block;margin-top:.2rem;color:#cbd5e1;font-size:.62rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .risk-warning{display:flex;gap:.8rem;margin:1rem 0 1.3rem;padding:.85rem 1rem;border:1px solid rgba(251,113,133,.28);border-radius:14px;background:rgba(127,29,29,.12);color:#fecdd3;font-size:.78rem;line-height:1.55}.risk-warning strong{color:#fff}.risk-warning .risk-alert{font-size:1.1rem;line-height:1}
    .risk-section-head{display:flex;align-items:end;justify-content:space-between;gap:1rem;margin:1.55rem 0 .75rem}.risk-section-head h2{margin:0;color:#fff;font-size:1rem;letter-spacing:-.025em}.risk-section-head p{margin:.2rem 0 0;color:var(--text-dim);font-size:.72rem}.risk-live{display:inline-flex;align-items:center;gap:.35rem;flex-shrink:0;color:#fda4af;font-size:.63rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.risk-live:before{content:'';width:6px;height:6px;border-radius:50%;background:#fb7185;box-shadow:0 0 0 4px rgba(251,113,133,.12)}
    .risk-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:.85rem}.risk-ticket{overflow:hidden;border:1px solid var(--border);border-radius:18px;background:linear-gradient(145deg,rgba(31,41,55,.92),rgba(15,23,42,.92));box-shadow:0 14px 30px rgba(0,0,0,.13)}
    .risk-ticket-top{display:flex;justify-content:space-between;gap:.8rem;padding:1.05rem 1.05rem .9rem;border-bottom:1px solid rgba(255,255,255,.07)}.risk-platform{display:flex;align-items:center;gap:.45rem;color:#94a3b8;font-size:.63rem;font-weight:850;letter-spacing:.09em;text-transform:uppercase}.risk-platform span{display:grid;place-items:center;width:24px;height:24px;border-radius:8px;background:rgba(251,113,133,.13);font-size:.8rem}.risk-odds{align-self:start;padding:.38rem .56rem;border:1px solid rgba(251,113,133,.3);border-radius:999px;background:rgba(225,29,72,.13);color:#fecdd3;font-size:.76rem;font-weight:900;white-space:nowrap}
    .risk-code-line{display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.85rem 1.05rem .65rem}.risk-code-label{display:block;color:#64748b;font-size:.6rem;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.risk-code{display:block;margin-top:.16rem;color:#fff;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:1.44rem;font-weight:900;letter-spacing:.14em}.risk-copy{border:1px solid rgba(255,255,255,.12);border-radius:9px;background:rgba(255,255,255,.045);padding:.42rem .55rem;color:#cbd5e1;font:inherit;font-size:.66rem;font-weight:850;cursor:pointer;transition:.16s}.risk-copy:hover{border-color:rgba(251,113,133,.52);background:rgba(225,29,72,.13);color:#fff}
    .risk-legs{margin:0 .8rem .55rem;border:1px solid rgba(255,255,255,.06);border-radius:12px;background:rgba(8,13,26,.36)}.risk-legs summary{display:flex;align-items:center;justify-content:space-between;gap:.5rem;cursor:pointer;padding:.7rem .78rem;color:#cbd5e1;font-size:.7rem;font-weight:800;list-style:none}.risk-legs summary::-webkit-details-marker{display:none}.risk-legs summary span{color:#64748b;font-size:.62rem;font-weight:700}.risk-legs[open] summary{border-bottom:1px solid rgba(255,255,255,.06)}.risk-leg{padding:.63rem .78rem;border-bottom:1px solid rgba(255,255,255,.055)}.risk-leg:last-child{border-bottom:0}.risk-leg-main{display:flex;justify-content:space-between;gap:.5rem;color:#e2e8f0;font-size:.72rem;font-weight:750}.risk-leg-market{margin-top:.18rem;color:#94a3b8;font-size:.65rem}.risk-leg-prob{color:#6ee7b7;font-size:.64rem;font-weight:850;white-space:nowrap}
    .risk-actions{display:flex;gap:.5rem;padding:.25rem .8rem 1rem}.risk-action{display:inline-flex;align-items:center;justify-content:center;flex:1;min-height:36px;border-radius:10px;text-decoration:none;font-size:.68rem;font-weight:900;letter-spacing:.015em}.risk-action-primary{background:linear-gradient(135deg,#e11d48,#be123c);color:#fff}.risk-action-secondary{border:1px solid rgba(255,255,255,.11);background:rgba(255,255,255,.035);color:#cbd5e1}.risk-action:hover{color:#fff;filter:brightness(1.08)}
    .risk-empty{padding:2.4rem 1rem;border:1px dashed rgba(148,163,184,.22);border-radius:18px;background:rgba(15,23,42,.45);text-align:center}.risk-empty b{display:block;margin:.5rem 0 .28rem;color:#e2e8f0}.risk-empty p{margin:0;color:#64748b;font-size:.75rem}.risk-empty-icon{font-size:1.5rem}
    .risk-history{display:grid;grid-template-columns:repeat(auto-fit,minmax(176px,1fr));gap:.58rem}.risk-history-card{display:flex;align-items:center;gap:.62rem;padding:.7rem .75rem;border:1px solid var(--border);border-radius:12px;background:var(--card)}.risk-history-state{display:grid;place-items:center;width:29px;height:29px;border-radius:9px;font-size:.78rem;font-weight:900}.risk-history-card.won .risk-history-state{background:rgba(16,185,129,.13);color:#6ee7b7}.risk-history-card.lost .risk-history-state{background:rgba(239,68,68,.13);color:#fca5a5}.risk-history-code{color:#e2e8f0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.72rem;font-weight:850;letter-spacing:.07em}.risk-history-meta{margin-top:.08rem;color:#64748b;font-size:.62rem}
    @media(max-width:560px){.risk-page{padding:.85rem .75rem 2rem}.risk-hero{border-radius:18px}.risk-hero p{font-size:.8rem}.risk-metric{flex:1;min-width:0}.risk-section-head{align-items:start}.risk-grid{grid-template-columns:1fr}.risk-code{font-size:1.18rem}.risk-ticket-top{padding: .85rem}.risk-code-line{padding:.75rem .85rem}.risk-warning{font-size:.71rem}.risk-history{grid-template-columns:1fr 1fr}}
</style>
@endpush

@section('content')
@php
    $todayLegs = $codes->sum(fn ($code) => is_array($code->fixtures) ? count($code->fixtures) : 0);
    $bestOdds = $codes->max('total_odds');
    $settledCount = $history->count();
@endphp
<div class="risk-page">
    <section class="risk-hero">
        <div class="risk-kicker"><i></i> Entertainment market · 18+</div>
        <h1>High Risk<br>Big Odds.</h1>
        <p>Our most speculative accumulator board. Every leg must win, so these tickets are transparent, strictly optional, and built for small-stake entertainment only.</p>
        <div class="risk-metrics">
            <div class="risk-metric"><b>{{ $codes->count() }}</b><span>Live tickets today</span></div>
            <div class="risk-metric"><b>{{ $todayLegs }}</b><span>Legs in play</span></div>
            <div class="risk-metric"><b>{{ $bestOdds ? number_format((float) $bestOdds, 0).'×' : 'N/A' }}</b><span>Highest live odds</span></div>
            <div class="risk-metric"><b>{{ $settledCount ? $wonCount.'/'.$settledCount : 'N/A' }}</b><span>Settled ticket wins</span></div>
        </div>
    </section>

    <aside class="risk-warning"><span class="risk-alert">⚠</span><span><strong>High risk means a high chance of losing.</strong> A large combined odds number is not a safety signal. Never chase losses, never use money needed for essentials, and verify all ticket details in the bookmaker app before deciding.</span></aside>

    @if($preview)
        <div class="risk-section-head"><div><h2>Today’s High Risk predictions</h2><p>Data-qualified Football + Tennis selections. The booking code appears after SportyBet confirms live availability.</p></div><span class="risk-live">Model board live</span></div>
        <section class="risk-ticket"><div class="risk-ticket-top"><div class="risk-platform"><span>📊</span>{{ $preview['title'] }}</div><span class="risk-odds">Target 20.00×+</span></div><details class="risk-legs" open><summary>Model selections <span>{{ count($preview['selections'] ?? []) }} legs</span></summary>@foreach(($preview['selections'] ?? []) as $leg)<div class="risk-leg"><div class="risk-leg-main"><span>{{ ($leg['sport'] ?? 'football') === 'tennis' ? '🎾 ' : '⚽ ' }}{{ $leg['home'] }} vs {{ $leg['away'] }}</span><span class="risk-leg-prob">{{ number_format((float)($leg['model_prob'] ?? 0),0) }}%</span></div><div class="risk-leg-market">@nodash($leg['market'] ?? 'Model market')</div></div>@endforeach</details></section>
    @endif

    <div class="risk-section-head"><div><h2>Today’s high-odds tickets</h2><p>Booking codes are generated after model and availability checks.</p></div>@if($codes->isNotEmpty())<span class="risk-live">Open today</span>@endif</div>

    @if($codes->isNotEmpty())
        <div class="risk-grid">
            @foreach($codes as $code)
                @php $legs = is_array($code->fixtures) ? $code->fixtures : []; @endphp
                <article class="risk-ticket">
                    <div class="risk-ticket-top"><div class="risk-platform"><span>🎲</span>{{ $code->platform ?: 'Booking ticket' }}</div><span class="risk-odds">{{ number_format((float) $code->total_odds, 2) }}× odds</span></div>
                    <div class="risk-code-line"><div><span class="risk-code-label">Booking code</span><code class="risk-code">{{ strtoupper($code->code) }}</code></div><button class="risk-copy" type="button" data-copy-code="{{ $code->code }}">Copy code</button></div>
                    <details class="risk-legs" open>
                        <summary>Ticket selections <span>{{ count($legs) }} leg{{ count($legs) === 1 ? '' : 's' }} · tap to hide</span></summary>
                        @forelse($legs as $leg)
                            <div class="risk-leg"><div class="risk-leg-main"><span>{{ $leg['match'] ?? trim(($leg['home'] ?? 'Home').' vs '.($leg['away'] ?? 'Away')) }}</span>@if(isset($leg['model_prob']))<span class="risk-leg-prob">{{ number_format((float) $leg['model_prob'], 0) }}%</span>@endif</div><div class="risk-leg-market">@nodash($leg['market'] ?? 'Model market')@if(isset($leg['est_odds'])) · est. {{ number_format((float) $leg['est_odds'], 2) }}@endif</div></div>
                        @empty
                            <div class="risk-leg"><div class="risk-leg-market">Ticket details will appear after the booking provider confirms the selections.</div></div>
                        @endforelse
                    </details>
                    <div class="risk-actions">@if($code->link)<a class="risk-action risk-action-primary" href="{{ $code->link }}" target="_blank" rel="noopener">Open ticket ↗</a>@endif<a class="risk-action risk-action-secondary" href="{{ route('booking-codes.index') }}">Ticket history</a></div>
                </article>
            @endforeach
        </div>
    @else
        <div class="risk-empty"><div class="risk-empty-icon">🎲</div><b>No high-risk ticket is live yet.</b><p>The board will appear here only when a qualifying ticket is available.</p></div>
    @endif

    @if($history->isNotEmpty())
        <div class="risk-section-head"><div><h2>Settled ticket history</h2><p>Results stay visible. {{ $wonCount }} won from {{ $history->count() }} settled high-risk tickets shown.</p></div></div>
        <div class="risk-history">
            @foreach($history as $ticket)
                <div class="risk-history-card {{ $ticket->status === 'won' ? 'won' : 'lost' }}"><span class="risk-history-state">{{ $ticket->status === 'won' ? '✓' : '×' }}</span><div><div class="risk-history-code">{{ strtoupper($ticket->code) }}</div><div class="risk-history-meta">{{ $ticket->status === 'won' ? 'Won' : 'Lost' }} · {{ number_format((float) $ticket->total_odds, 0) }}× · {{ $ticket->settled_at?->timezone('Africa/Lagos')?->format('d M') ?? 'N/A' }}</div></div></div>
            @endforeach
        </div>
    @endif
</div>
@push('scripts')
<script>
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-code]');
    if (!button) return;
    const original = button.textContent;
    try { await navigator.clipboard.writeText(button.dataset.copyCode); button.textContent = 'Copied ✓'; }
    catch (_) { button.textContent = button.dataset.copyCode; }
    setTimeout(() => button.textContent = original, 1800);
});
</script>
@endpush
@endsection
