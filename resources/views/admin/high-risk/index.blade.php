@extends('layouts.admin')

@section('title', 'High Risk Desk')

@push('styles')
<style>
    .high-risk-desk { max-width: 1180px; }
    .high-risk-hero { position: relative; overflow: hidden; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 1.25rem; padding: 1.35rem; border: 1px solid rgba(251, 113, 133, .28); border-radius: 18px; background: radial-gradient(circle at 88% 6%, rgba(244, 63, 94, .22), transparent 27%), linear-gradient(125deg, #261020, #121d31); }
    .high-risk-hero::after { content: 'HIGH RISK'; position: absolute; right: -.2rem; bottom: -1.7rem; color: rgba(255,255,255,.035); font-size: 5.5rem; font-weight: 950; letter-spacing: -.09em; white-space: nowrap; pointer-events: none; }
    .high-risk-hero-copy, .high-risk-hero-actions { position: relative; z-index: 1; }
    .high-risk-kicker { display: inline-flex; align-items: center; gap: .4rem; color: #fecdd3; font-size: .62rem; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; }
    .high-risk-kicker::before { content: ''; width: 7px; height: 7px; border-radius: 99px; background: #fb7185; box-shadow: 0 0 0 4px rgba(251,113,133,.12); }
    .high-risk-hero h1 { margin: .42rem 0 .36rem; color: #fff; font-size: clamp(1.45rem, 3vw, 2rem); letter-spacing: -.045em; }
    .high-risk-hero p { max-width: 650px; margin: 0; color: #cbd5e1; font-size: .76rem; line-height: 1.6; }
    .high-risk-brand { display: flex; align-items: center; gap: .62rem; padding: .48rem .58rem; border: 1px solid rgba(255,255,255,.13); border-radius: 12px; background: rgba(2, 6, 23, .28); color: #e2e8f0; font-size: .68rem; font-weight: 850; white-space: nowrap; }
    .high-risk-brand img { display: block; width: 31px; height: 31px; object-fit: contain; border-radius: 8px; }
    .high-risk-open { display: inline-flex; justify-content: center; margin-top: .6rem; padding: .48rem .65rem; border: 1px solid rgba(255,255,255,.15); border-radius: 9px; color: #fff; background: rgba(255,255,255,.06); text-decoration: none; font-size: .66rem; font-weight: 850; }
    .high-risk-open:hover { color: #fff; background: rgba(255,255,255,.12); }
    .high-risk-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; margin: 1rem 0; }
    .high-risk-stat { padding: .88rem .9rem; border: 1px solid var(--border); border-radius: 13px; background: var(--card); }
    .high-risk-stat strong { display: block; color: #f8fafc; font-size: 1.28rem; line-height: 1; }
    .high-risk-stat.alert strong { color: #fda4af; }
    .high-risk-stat span { display: block; margin-top: .34rem; color: var(--dim); font-size: .6rem; font-weight: 850; letter-spacing: .075em; text-transform: uppercase; }
    .high-risk-panel { margin-top: .9rem; padding: 1rem; border: 1px solid var(--border); border-radius: 15px; background: var(--card); }
    .high-risk-panel-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .8rem; margin-bottom: .8rem; }
    .high-risk-panel h2 { margin: 0; color: #f8fafc; font-size: .9rem; letter-spacing: -.02em; }
    .high-risk-panel p { margin: .2rem 0 0; color: var(--dim); font-size: .68rem; line-height: 1.5; }
    .high-risk-chip { flex-shrink: 0; padding: .28rem .48rem; border: 1px solid rgba(251,113,133,.24); border-radius: 99px; background: rgba(251,113,133,.1); color: #fda4af; font-size: .58rem; font-weight: 900; letter-spacing: .075em; text-transform: uppercase; }
    .high-risk-board { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
    .high-risk-prediction { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: .5rem; min-height: 57px; padding: .55rem .62rem; border: 1px solid rgba(255,255,255,.075); border-radius: 11px; background: rgba(2, 6, 23, .22); }
    .high-risk-logos { display: flex; align-items: center; min-width: 28px; }
    .high-risk-logos img { width: 24px; height: 24px; object-fit: contain; }
    .high-risk-logos img + img { margin-left: -6px; border-radius: 99px; background: #111827; }
    .high-risk-tennis { display: grid; place-items: center; width: 27px; height: 27px; border-radius: 9px; background: rgba(251,113,133,.12); font-size: .9rem; }
    .high-risk-match { overflow: hidden; color: #e2e8f0; font-size: .7rem; font-weight: 850; white-space: nowrap; text-overflow: ellipsis; }
    .high-risk-market { overflow: hidden; margin-top: .13rem; color: #94a3b8; font-size: .61rem; white-space: nowrap; text-overflow: ellipsis; }
    .high-risk-probability { color: #fecdd3; font-size: .68rem; font-weight: 900; }
    .high-risk-more { margin-top: .6rem; color: #94a3b8; font-size: .66rem; text-align: center; }
    .high-risk-more summary { cursor: pointer; list-style: none; }
    .high-risk-more summary::-webkit-details-marker { display: none; }
    .high-risk-ticket { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .8rem; align-items: center; padding: .85rem; border: 1px solid rgba(255,255,255,.075); border-radius: 13px; background: rgba(2, 6, 23, .2); }
    .high-risk-ticket + .high-risk-ticket { margin-top: .55rem; }
    .high-risk-ticket-label { color: #94a3b8; font-size: .59rem; font-weight: 850; letter-spacing: .09em; text-transform: uppercase; }
    .high-risk-code { margin-top: .16rem; color: #fff; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 1.05rem; font-weight: 900; letter-spacing: .1em; }
    .high-risk-ticket-meta { margin-top: .22rem; color: #94a3b8; font-size: .66rem; }
    .high-risk-ticket-actions { display: flex; align-items: center; gap: .42rem; }
    .high-risk-status { padding: .3rem .45rem; border-radius: 99px; font-size: .57rem; font-weight: 900; letter-spacing: .075em; text-transform: uppercase; }
    .high-risk-status.published { color: #fef3c7; border: 1px solid rgba(245,158,11,.25); background: rgba(245,158,11,.1); }
    .high-risk-status.won { color: #a7f3d0; border: 1px solid rgba(16,185,129,.24); background: rgba(16,185,129,.1); }
    .high-risk-status.lost { color: #fecaca; border: 1px solid rgba(239,68,68,.24); background: rgba(239,68,68,.1); }
    .high-risk-copy { border: 0; padding: .35rem .45rem; border-radius: 7px; background: rgba(255,255,255,.07); color: #cbd5e1; font: inherit; font-size: .62rem; font-weight: 800; cursor: pointer; }
    .high-risk-copy:hover { color: #fff; background: rgba(255,255,255,.13); }
    .high-risk-empty { padding: 2rem 1rem; border: 1px dashed rgba(148,163,184,.24); border-radius: 12px; color: var(--dim); font-size: .74rem; text-align: center; }
    .high-risk-history { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .55rem; }
    .high-risk-history-item { display: flex; align-items: center; gap: .55rem; padding: .65rem; border: 1px solid rgba(255,255,255,.065); border-radius: 11px; background: rgba(2, 6, 23, .18); }
    .high-risk-history-icon { display: grid; place-items: center; flex: 0 0 27px; width: 27px; height: 27px; border-radius: 8px; font-size: .76rem; font-weight: 900; }
    .high-risk-history-item.won .high-risk-history-icon { color: #6ee7b7; background: rgba(16,185,129,.12); }
    .high-risk-history-item.lost .high-risk-history-icon { color: #fca5a5; background: rgba(239,68,68,.11); }
    .high-risk-history-code { color: #e2e8f0; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .68rem; font-weight: 850; letter-spacing: .06em; }
    .high-risk-history-meta { margin-top: .12rem; color: #94a3b8; font-size: .6rem; }
    @media (max-width: 850px) { .high-risk-history { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 680px) { .high-risk-hero { grid-template-columns: 1fr; } .high-risk-hero-actions { justify-self: start; } .high-risk-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } .high-risk-board { grid-template-columns: 1fr; } .high-risk-ticket { grid-template-columns: 1fr; } .high-risk-ticket-actions { justify-content: space-between; } .high-risk-history { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
@php
    $todayLegs = $today_codes->sum(fn ($code) => is_array($code->fixtures) ? count($code->fixtures) : 0);
    $won = $history->where('status', 'won')->count();
    $largest = $today_codes->max('total_odds');
    $selections = collect($preview['selections'] ?? []);
@endphp

<div class="high-risk-desk">
    <section class="high-risk-hero">
        <div class="high-risk-hero-copy">
            <span class="high-risk-kicker">Prediction control room</span>
            <h1>High Risk Desk</h1>
            <p>Big-odds accumulator oversight for data-qualified Football and Tennis selections. Keep the board transparent: this market is intentionally speculative and is never presented as a safe pick.</p>
        </div>
        <div class="high-risk-hero-actions">
            <div class="high-risk-brand"><img src="{{ asset('icons/_.jpeg') }}" alt="TavsScore"> TavsScore high odds</div>
            <a class="high-risk-open" href="{{ route('high-risk.index') }}" target="_blank" rel="noopener">Open user page ↗</a>
        </div>
    </section>

    <section class="high-risk-stats" aria-label="High risk overview">
        <div class="high-risk-stat alert"><strong>{{ $today_codes->count() }}</strong><span>Live tickets today</span></div>
        <div class="high-risk-stat"><strong>{{ $todayLegs }}</strong><span>Booked selections</span></div>
        <div class="high-risk-stat alert"><strong>{{ $largest ? number_format((float) $largest, 0).'×' : 'N/A' }}</strong><span>Highest live odds</span></div>
        <div class="high-risk-stat"><strong>{{ $history->isNotEmpty() ? $won.'/'.$history->count() : 'N/A' }}</strong><span>Settled wins shown</span></div>
    </section>

    @if($preview)
        <section class="high-risk-panel">
            <div class="high-risk-panel-head">
                <div>
                    <h2>Today’s prediction board</h2>
                    <p>{{ $preview['title'] }}. The target is 15.00× to 1,500× only after SportyBet confirms the exact selections.</p>
                </div>
                <span class="high-risk-chip">{{ $selections->count() }} selections</span>
            </div>

            <div class="high-risk-board">
                @foreach($selections->take(6) as $leg)
                    <article class="high-risk-prediction">
                        <div class="high-risk-logos">
                            @if(($leg['sport'] ?? 'football') === 'tennis')
                                <span class="high-risk-tennis">🎾</span>
                            @else
                                @if(!empty($leg['home_logo']))<img src="{{ $leg['home_logo'] }}" alt="">@endif
                                @if(!empty($leg['away_logo']))<img src="{{ $leg['away_logo'] }}" alt="">@endif
                            @endif
                        </div>
                        <div style="min-width:0">
                            <div class="high-risk-match">{{ $leg['home'] }} vs {{ $leg['away'] }}</div>
                            <div class="high-risk-market">@nodash($leg['market'] ?? 'Model market')</div>
                        </div>
                        <span class="high-risk-probability">{{ number_format((float) ($leg['model_prob'] ?? 0), 0) }}%</span>
                    </article>
                @endforeach
            </div>

            @if($selections->count() > 6)
                <details class="high-risk-more">
                    <summary>View {{ $selections->count() - 6 }} more model selections</summary>
                    <div class="high-risk-board" style="margin-top:.55rem">
                        @foreach($selections->slice(6) as $leg)
                            <article class="high-risk-prediction">
                                <div class="high-risk-logos">
                                    @if(($leg['sport'] ?? 'football') === 'tennis')
                                        <span class="high-risk-tennis">🎾</span>
                                    @else
                                        @if(!empty($leg['home_logo']))<img src="{{ $leg['home_logo'] }}" alt="">@endif
                                        @if(!empty($leg['away_logo']))<img src="{{ $leg['away_logo'] }}" alt="">@endif
                                    @endif
                                </div>
                                <div style="min-width:0">
                                    <div class="high-risk-match">{{ $leg['home'] }} vs {{ $leg['away'] }}</div>
                                    <div class="high-risk-market">@nodash($leg['market'] ?? 'Model market')</div>
                                </div>
                                <span class="high-risk-probability">{{ number_format((float) ($leg['model_prob'] ?? 0), 0) }}%</span>
                            </article>
                        @endforeach
                    </div>
                </details>
            @endif
        </section>
    @endif

    <section class="high-risk-panel">
        <div class="high-risk-panel-head">
            <div>
                <h2>Today’s ticket queue</h2>
                <p>A booking code appears only after the Mac worker verifies the live SportyBet ticket.</p>
            </div>
            <span class="high-risk-chip">{{ now('Africa/Lagos')->format('D, d M') }}</span>
        </div>

        @forelse($today_codes as $code)
            @php $legs = is_array($code->fixtures) ? $code->fixtures : []; @endphp
            <article class="high-risk-ticket">
                <div>
                    <div class="high-risk-ticket-label">{{ ucfirst($code->platform ?: 'Booking provider') }} booking code</div>
                    <div class="high-risk-code">{{ strtoupper($code->code) }}</div>
                    <div class="high-risk-ticket-meta">{{ number_format((float) $code->total_odds, 2) }}× odds · {{ count($legs) }} selections · {{ $code->created_at?->timezone('Africa/Lagos')?->format('H:i') ?? 'N/A' }}</div>
                </div>
                <div class="high-risk-ticket-actions">
                    <span class="high-risk-status {{ $code->status }}">{{ $code->status }}</span>
                    <button class="high-risk-copy" type="button" data-copy-code="{{ $code->code }}">Copy code</button>
                </div>
            </article>
        @empty
            <div class="high-risk-empty">No live high-risk ticket has been created today. The model board can still show candidates while it waits for SportyBet to confirm a valid code.</div>
        @endforelse
    </section>

    <section class="high-risk-panel">
        <div class="high-risk-panel-head">
            <div>
                <h2>Settled history</h2>
                <p>{{ $won }} won from {{ $history->count() }} settled high-risk tickets shown. Results stay visible for review.</p>
            </div>
            <span class="high-risk-chip">Last 30</span>
        </div>

        @if($history->isNotEmpty())
            <div class="high-risk-history">
                @foreach($history as $code)
                    <article class="high-risk-history-item {{ $code->status }}">
                        <span class="high-risk-history-icon">{{ $code->status === 'won' ? '✓' : '×' }}</span>
                        <div>
                            <div class="high-risk-history-code">{{ strtoupper($code->code) }}</div>
                            <div class="high-risk-history-meta">{{ $code->status === 'won' ? 'Won' : 'Lost' }} · {{ number_format((float) $code->total_odds, 2) }}× · {{ $code->settled_at?->timezone('Africa/Lagos')?->format('d M') ?? 'N/A' }}</div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="high-risk-empty">No high-risk ticket has settled yet. Settlements will appear here automatically.</div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-code]');
    if (!button) return;

    const original = button.textContent;
    try {
        await navigator.clipboard.writeText(button.dataset.copyCode);
        button.textContent = 'Copied ✓';
    } catch (_) {
        button.textContent = button.dataset.copyCode;
    }
    setTimeout(() => button.textContent = original, 1800);
});
</script>
@endpush
