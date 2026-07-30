@extends('layouts.app')

@section('title', 'Booking Codes | TavsScore')
@section('meta_description', "Load today's TavsScore booking codes, view their selections, and follow verified win/loss outcomes.")

@push('styles')
<style>
    .bc-page { padding:1.5rem 0 2.5rem; }
    .bc-hero { padding:clamp(1.45rem,4vw,3rem); border:1px solid rgba(45,212,191,.22); border-radius:22px; background:radial-gradient(circle at 85% 10%,rgba(45,212,191,.14),transparent 30%),linear-gradient(135deg,#0c1825,#101c2a 58%,#10251f); overflow:hidden; position:relative; }
    .bc-kicker { color:#5eead4; font-size:.72rem; font-weight:850; letter-spacing:.12em; text-transform:uppercase; }
    .bc-title { color:#fff; margin:.45rem 0 .55rem; font-size:clamp(1.7rem,4vw,2.65rem); line-height:1.05; letter-spacing:-.04em; }
    .bc-intro { max-width:650px; color:#b8c6d8; line-height:1.65; font-size:.92rem; margin:0; }
    .bc-hero-chip { position:absolute;right:1.25rem;top:1.2rem;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.06);border-radius:999px;padding:.45rem .7rem;color:#d5f8f1;font-size:.72rem;font-weight:800; }
    .bc-stat-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; margin:1rem 0 1.35rem; }
    .bc-stat { border:1px solid var(--border); background:var(--card); border-radius:13px; padding:.85rem 1rem; }
    .bc-stat b { color:#fff; display:block; font-size:1.12rem; } .bc-stat span { color:var(--text-dim); font-size:.71rem; }
    .bc-date-nav { display:flex; align-items:center; justify-content:center; gap:.55rem; flex-wrap:wrap; margin:1.05rem 0 1.35rem; }
    .bc-date-btn { display:inline-flex; min-height:40px; align-items:center; justify-content:center; padding:.55rem .78rem; border:1px solid var(--border); border-radius:10px; background:var(--card); color:#dce8f4; font-size:.74rem; font-weight:850; text-decoration:none; }
    .bc-date-btn:hover { border-color:rgba(45,212,191,.55); color:#7cf2e2; } .bc-date-picker { min-height:40px; box-sizing:border-box; padding:.45rem .68rem; border:1px solid rgba(45,212,191,.3); border-radius:10px; color:#fff; background:#132333; font:750 .78rem inherit; color-scheme:dark; }
    .bc-heading { display:flex; align-items:end; justify-content:space-between; gap:1rem; margin:1.7rem 0 .8rem; }
    .bc-heading h2 { margin:0; color:#fff; font-size:1.1rem; } .bc-heading p { margin:.25rem 0 0; color:var(--text-dim); font-size:.77rem; }
    .bc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(285px,1fr)); gap:1rem; }
    .bc-card { position:relative; display:flex; flex-direction:column; background:var(--card); border:1px solid var(--border); border-radius:16px; padding:1rem; overflow:hidden; }
    .bc-card:before { content:""; position:absolute; inset:0 auto 0 0; width:3px; background:#2dd4bf; }
    .bc-top { display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem; }
    .bc-platform { color:#fff; font-size:.94rem; font-weight:850; } .bc-label { color:var(--text-dim); font-size:.69rem; margin-top:.2rem; }
    .bc-odds { text-align:right; color:#fcd34d; font-size:1.03rem; font-weight:900; } .bc-odds small { display:block; color:var(--text-dim); font-size:.62rem; font-weight:700; text-transform:uppercase; }
    .bc-code-box { margin:1rem 0 .7rem; padding:.8rem .85rem; border-radius:11px; background:rgba(255,255,255,.035); border:1px dashed rgba(255,255,255,.18); }
    .bc-code { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.1em; color:#fff; font-size:1.25rem; font-weight:900; overflow-wrap:anywhere; }
    .bc-note { color:#aebed0; font-size:.77rem; line-height:1.5; min-height:2.3em; }
    .bc-actions { display:flex; gap:.55rem; margin-top:1rem; flex-wrap:wrap; } .bc-btn { border-radius:8px; padding:.55rem .75rem; font-size:.73rem; font-weight:850; cursor:pointer; text-decoration:none; }
    .bc-copy { background:#1eaa9b; color:#061514; border:1px solid #49dfcf; } .bc-copy:hover { background:#4cdbcc; }
    .bc-load { color:#f5c969; border:1px solid rgba(245,201,105,.35); background:rgba(245,201,105,.07); }
    .bc-load:hover { background:rgba(245,201,105,.14); color:#fff0bd; }
    .bc-fixtures { margin-top:.75rem; } .bc-fixtures summary { cursor:pointer; list-style:none; color:#aebed0; font-size:.73rem; font-weight:750; } .bc-fixtures summary::-webkit-details-marker { display:none; }
    .bc-fixtures ul { list-style:none; padding:0; margin:.55rem 0 0; border-top:1px solid var(--border); } .bc-fixtures li { padding:.42rem 0; border-bottom:1px solid var(--border); color:#aebed0; font-size:.71rem; line-height:1.35; }
    .bc-fixtures em { color:#77e5d8; font-style:normal; font-weight:750; }
    .bc-leg { display:grid;grid-template-columns:1fr auto;gap:.45rem;align-items:center; } .bc-leg-score { color:#fff;font-weight:850;font-size:.7rem;text-align:right; } .bc-leg-status { display:inline-flex;margin-top:.2rem;border-radius:999px;padding:.13rem .34rem;font-size:.58rem;font-weight:900;text-transform:uppercase; } .bc-leg-status.won { color:#74f4c7;background:rgba(52,211,153,.12); } .bc-leg-status.lost { color:#fda4af;background:rgba(251,113,133,.12); } .bc-leg-status.pending,.bc-leg-status.unresolved { color:#f8cf72;background:rgba(245,158,11,.11); } .bc-leg-status.void { color:#9ca3af;background:rgba(156,163,175,.13); }
    .bc-time { margin-top:.85rem; color:var(--text-dim); font-size:.67rem; }
    .bc-empty { border:1px dashed var(--border); border-radius:15px; padding:2.5rem 1rem; text-align:center; color:var(--text-dim); }
    .bc-empty b { display:block; color:#fff; margin:.5rem 0 .25rem; }
    .bc-history { overflow:hidden; border:1px solid var(--border); background:var(--card); border-radius:15px; }
    .bc-history-row { display:grid; grid-template-columns:86px 1fr auto auto; gap:.75rem; align-items:center; padding:.75rem 1rem; border-bottom:1px solid var(--border); }
    .bc-history-row:last-child { border-bottom:0; } .bc-status { display:inline-flex; align-items:center; width:max-content; padding:.28rem .52rem; border-radius:999px; font-size:.65rem; font-weight:900; text-transform:uppercase; }
    .bc-status.won { color:#74f4c7; border:1px solid rgba(52,211,153,.32); background:rgba(52,211,153,.11); } .bc-status.lost { color:#fda4af; border:1px solid rgba(251,113,133,.32); background:rgba(251,113,133,.1); }
    .bc-history-code { color:#fff; font:800 .77rem ui-monospace,SFMono-Regular,Menlo,monospace; } .bc-history-note { color:var(--text-dim); font-size:.69rem; margin-top:.17rem; }
    .bc-disclaimer { padding:.9rem 1rem; margin-top:1.2rem; border-radius:11px; background:rgba(245,158,11,.06); border:1px solid rgba(245,158,11,.22); color:#d7bd83; font-size:.73rem; line-height:1.6; }
    @media (max-width:650px) { .bc-page{padding-top:.85rem}.bc-hero{border-radius:15px;padding:1.25rem}.bc-hero-chip{position:static;display:inline-flex;margin-top:1rem}.bc-stat-grid{grid-template-columns:1fr 1fr}.bc-stat:last-child{grid-column:1 / -1}.bc-grid{grid-template-columns:1fr}.bc-history-row{grid-template-columns:74px 1fr auto}.bc-history-row .bc-history-time{display:none}.bc-title{font-size:1.8rem} }
</style>
@endpush

@section('content')
<div class="wrap bc-page">
    <section class="bc-hero">
        <div class="bc-kicker">TavsScore booking desk</div>
        <h1 class="bc-title">{{ $isToday ? "Today's tickets, ready to load." : 'Tickets for '.$selectedDate->format('D, d M Y') }}</h1>
        <p class="bc-intro">Copy a code into the matching sportsbook to load its saved selections. We only publish a ticket from <strong>2.00 total odds</strong>, then track its final outcome here.</p>
        <span class="bc-hero-chip">🎟️ No account details required</span>
    </section>

    <div class="bc-stat-grid">
        <div class="bc-stat"><b>{{ $codes->count() }}</b><span>{{ $isToday ? 'Available today' : 'Available on this date' }}</span></div>
        <div class="bc-stat"><b>2.00+</b><span>Minimum combined odds</span></div>
        <div class="bc-stat"><b>{{ $settledCount ? number_format(($wonCount / $settledCount) * 100, 0) . '%' : 'N/A' }}</b><span>Verified booking-code win rate</span></div>
    </div>

    <nav class="bc-date-nav" aria-label="Booking-code date navigation">
        <a class="bc-date-btn" href="{{ route('booking-codes.index', ['date' => $previousDate]) }}">← Previous day</a>
        <form method="GET" action="{{ route('booking-codes.index') }}"><label for="booking-date" class="sr-only">Choose booking-code date</label><input id="booking-date" class="bc-date-picker" type="date" name="date" value="{{ $selectedDate->toDateString() }}" max="{{ now('Africa/Lagos')->toDateString() }}" onchange="this.form.submit()"></form>
        @if($nextDate)<a class="bc-date-btn" href="{{ route('booking-codes.index', ['date' => $nextDate]) }}">Next day →</a>@else<span class="bc-date-btn" style="opacity:.45;cursor:not-allowed">Today</span>@endif
    </nav>

    <div class="bc-heading"><div><h2>Live booking codes</h2><p>Check the bookmaker's final prices before you place any ticket.</p></div></div>
    @if($codes->isEmpty())
        <div class="bc-empty"><div style="font-size:2rem">🎟️</div><b>No active code for {{ $isToday ? 'today' : $selectedDate->format('d M Y') }}</b><span>Choose another day to review previously published tickets and their outcomes.</span></div>
    @else
        <div class="bc-grid">
            @foreach($codes as $bc)
                @php
                    $slug = strtolower(str_replace(' ', '', $bc->platform));
                    $affiliate = $affiliates->get($slug);
                    $howToMap = ['bet9ja' => 'Bet9ja app → Booking Code', '1xbet' => '1xBet app → Coupon Code', '1win' => '1Win app → Betting Slip → Coupon Code', 'sportybet' => 'SportyBet app → Booking Code', 'betway' => 'Betway app → Booking Code', 'parimatch' => 'Parimatch app → Booking Code'];
                    $howTo = $howToMap[$slug] ?? $bc->platform . ' app → Booking Code';
                @endphp
                <article class="bc-card">
                    <div class="bc-top"><div><div class="bc-platform">{{ $bc->platform }}</div><div class="bc-label">{{ $bc->note ?: ($bc->slip_ref ? str_replace('-', ' ', $bc->slip_ref) : 'Today\'s ticket') }}</div></div><div class="bc-odds">{{ number_format((float) $bc->total_odds, 2) }}<small>total odds</small></div></div>
                    <div class="bc-code-box"><div class="bc-label">Booking code</div><div class="bc-code">{{ strtoupper($bc->code) }}</div></div>
                    <div class="bc-note">How to load: {{ $howTo }} → enter the code.</div>
                    <div class="bc-actions"><button type="button" class="bc-btn bc-copy" onclick="copyBookingCode('{{ strtoupper($bc->code) }}', this)">Copy code</button>@if($bc->link)<a class="bc-btn bc-load" href="{{ $bc->link }}" target="_blank" rel="noopener sponsored">Open ticket ↗</a>@elseif($affiliate)<a class="bc-btn bc-load" href="{{ $affiliate->register_url }}" target="_blank" rel="noopener sponsored">Open {{ $bc->platform }} ↗</a>@endif</div>
                    @if($bc->legs->isNotEmpty())
                        <details class="bc-fixtures"><summary>⌄ View {{ $bc->legs->count() }} saved selections &amp; results</summary><ul>@foreach($bc->legs as $leg)<li class="bc-leg"><div>{{ $leg->home_team ?: 'Home' }} vs {{ $leg->away_team ?: 'Away' }}<br><em>@nodash($leg->market)</em><br><span class="bc-leg-status {{ $leg->status }}">{{ $leg->status }}</span></div><div class="bc-leg-score">@if($leg->home_score !== null){{ $leg->home_score }}:{{ $leg->away_score }}@else Awaiting result @endif</div></li>@endforeach</ul></details>
                    @elseif(is_array($bc->fixtures) && count($bc->fixtures))
                        <details class="bc-fixtures"><summary>⌄ View {{ count($bc->fixtures) }} saved selections</summary><ul>@foreach($bc->fixtures as $leg)<li>{{ $leg['home'] ?? 'Home' }} vs {{ $leg['away'] ?? 'Away' }}<br><em>@nodash($leg['market'] ?? 'Selection')</em></li>@endforeach</ul></details>
                    @endif
                    <div class="bc-time">Published {{ $bc->created_at->setTimezone('Africa/Lagos')->diffForHumans() }}</div>
                </article>
            @endforeach
        </div>
    @endif

    <div class="bc-heading"><div><h2>Verified outcomes</h2><p>Every automated ticket remains in history once every saved leg has been settled.</p></div></div>
    @if($history->isEmpty())
        <div class="bc-empty" style="padding:1.5rem"><b>No settled booking-code results yet</b><span>Results will appear here automatically after the final scores are checked.</span></div>
    @else
        <section class="bc-history">
            @foreach($history as $item)
                <div class="bc-history-row">
                    <span class="bc-status {{ $item->status }}">{{ $item->status === 'won' ? '✓ Won' : '× Lost' }}</span>
                    <div><div class="bc-history-code">{{ strtoupper($item->code) }} · {{ $item->platform }}</div><div class="bc-history-note">{{ $item->note ?: 'Booking ticket' }}
                        @if($item->legs->isNotEmpty())
                            · {{ $item->legs->where('status','won')->count() }}/{{ $item->legs->count() }} legs won
                        @endif
                    </div></div>
                    <strong style="color:#fcd34d;font-size:.77rem">{{ number_format((float) $item->total_odds, 2) }}</strong>
                    <time class="bc-history-time" style="color:var(--text-dim);font-size:.68rem">{{ $item->settled_at?->setTimezone('Africa/Lagos')->format('M d, H:i') }}</time>
                </div>
                @if($item->legs->isNotEmpty())<details class="bc-fixtures" style="margin:0 .9rem .75rem"><summary>⌄ Match-by-match outcome</summary><ul>@foreach($item->legs as $leg)<li class="bc-leg"><div>{{ $leg->home_team }} vs {{ $leg->away_team }}<br><em>@nodash($leg->market)</em><br><span class="bc-leg-status {{ $leg->status }}">{{ $leg->status }}</span></div><div class="bc-leg-score">{{ $leg->home_score !== null ? $leg->home_score.':'.$leg->away_score : 'N/A' }}</div></li>@endforeach</ul></details>@endif
            @endforeach
        </section>
    @endif
    <div class="bc-disclaimer">⚠️ <strong>For entertainment only.</strong> A booking code does not guarantee a result or lock bookmaker odds. Verify each selection before placing any bet and never stake more than you can afford to lose.</div>
</div>
@endsection

@push('scripts')
<script>
function copyBookingCode(code, button) { navigator.clipboard.writeText(code).then(function () { var text = button.textContent; button.textContent = 'Copied ✓'; setTimeout(function () { button.textContent = text; }, 1800); }).catch(function () { button.textContent = 'Copy failed'; }); }
</script>
@endpush
