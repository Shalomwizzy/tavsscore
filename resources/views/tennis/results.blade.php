@extends('layouts.app')
@section('title', 'Tennis Results & Accuracy | TavsScore')
@section('meta_description', 'Transparent tennis prediction record: verified ATP and WTA results, wins, losses and strike rate.')

@push('styles')
<style>
.tr-page{max-width:1050px;margin:0 auto;padding:1.25rem 1rem 4rem}.tr-hero{padding:1.55rem;border:1px solid rgba(103,232,249,.24);border-radius:22px;background:radial-gradient(circle at 88% 5%,rgba(103,232,249,.2),transparent 29%),linear-gradient(130deg,#071725,#102c42);color:#fff}.tr-kicker{font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;font-weight:900;color:#67e8f9}.tr-hero h1{margin:.4rem 0;font-size:clamp(1.7rem,4vw,2.5rem);letter-spacing:-.045em}.tr-hero p{margin:0;max-width:640px;color:#c9d8e7;font-size:.86rem;line-height:1.6}.tr-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:.7rem;margin:1rem 0}.tr-stat{padding:1rem;border:1px solid var(--border);border-radius:15px;background:var(--card)}.tr-val{font-size:1.5rem;font-weight:900;color:#fff}.tr-lbl{margin-top:.25rem;color:var(--text-dim);font-size:.66rem;letter-spacing:.08em;font-weight:800;text-transform:uppercase}.tr-day{margin-top:.9rem;padding:1rem;border:1px solid var(--border);border-radius:16px;background:var(--card)}.tr-day-head{display:flex;justify-content:space-between;gap:.8rem;align-items:center;margin-bottom:.65rem;padding-bottom:.7rem;border-bottom:1px solid var(--border)}.tr-date{font-weight:900;color:#fff}.tr-day-score{color:var(--text-dim);font-size:.75rem}.tr-row{display:grid;grid-template-columns:1fr auto auto;gap:.8rem;align-items:center;padding:.75rem 0;border-bottom:1px solid rgba(255,255,255,.055)}.tr-row:last-child{border-bottom:0}.tr-match{font-weight:800;color:#fff;font-size:.88rem}.tr-meta{margin-top:.22rem;color:var(--text-dim);font-size:.68rem}.tr-pick{font-size:.75rem;font-weight:800;color:#a5f3fc;text-align:right}.tr-result{padding:.28rem .5rem;border-radius:999px;font-size:.68rem;font-weight:900;white-space:nowrap}.tr-win{color:#6ee7b7;background:rgba(16,185,129,.13);border:1px solid rgba(16,185,129,.3)}.tr-loss{color:#fca5a5;background:rgba(239,68,68,.11);border:1px solid rgba(239,68,68,.3)}.tr-pending{color:#cbd5e1;background:rgba(148,163,184,.1);border:1px solid rgba(148,163,184,.25)}.tr-empty{padding:3rem;text-align:center;color:var(--text-dim);border:1px dashed var(--border);border-radius:16px}@media(max-width:600px){.tr-page{padding:.8rem .7rem 3rem}.tr-stats{grid-template-columns:1fr 1fr}.tr-row{grid-template-columns:1fr auto}.tr-pick{grid-column:1;grid-row:2;text-align:left}.tr-result{grid-column:2;grid-row:1 / span 2}.tr-hero{padding:1.2rem}}
</style>
@endpush

@section('content')
<main class="tr-page">
    <header class="tr-hero"><div class="tr-kicker">Verified tennis outcomes</div><h1>The tennis track record.</h1><p>Every ATP and WTA prediction from the last 30 days is shown here once the final result is confirmed. Pending means the score has not yet been verified — it is never guessed.</p></header>
    <div class="tr-stats"><div class="tr-stat"><div class="tr-val" style="color:#67e8f9">{{ $summary['accuracy'] !== null ? $summary['accuracy'].'%' : '—' }}</div><div class="tr-lbl">30-day accuracy</div></div><div class="tr-stat"><div class="tr-val" style="color:#6ee7b7">{{ $summary['won'] }}</div><div class="tr-lbl">Won</div></div><div class="tr-stat"><div class="tr-val" style="color:#fca5a5">{{ $summary['lost'] }}</div><div class="tr-lbl">Lost</div></div><div class="tr-stat"><div class="tr-val">{{ $summary['pending'] }}</div><div class="tr-lbl">Awaiting verification</div></div></div>
    @forelse($by_day as $date => $day)
        <section class="tr-day"><div class="tr-day-head"><div class="tr-date">{{ \Carbon\Carbon::parse($date)->format('l, M j') }}</div><div class="tr-day-score">@if($day['resolved'])<strong>{{ $day['won'] }}/{{ $day['resolved'] }}</strong> correct @endif @if($day['pending']) · {{ $day['pending'] }} pending @endif</div></div>
            @foreach($day['predictions'] as $prediction)
                @php($match = $prediction->match)
                <div class="tr-row"><div><div class="tr-match">{{ $match?->player_one }} <span style="color:#94a3b8">vs</span> {{ $match?->player_two }}</div><div class="tr-meta">{{ $match?->tour }} · {{ $match?->tournament ?: 'Tennis' }}@if($match?->score) · Final {{ $match->score }}@endif</div></div><div class="tr-pick">{{ $prediction->predicted_winner }} to win<br><span style="color:var(--text-dim);font-size:.65rem">{{ $prediction->confidence }}% confidence</span></div><div>@if($prediction->was_correct === true)<span class="tr-result tr-win">✓ Won</span>@elseif($prediction->was_correct === false)<span class="tr-result tr-loss">✕ Lost</span>@else<span class="tr-result tr-pending">⌛ Pending</span>@endif</div></div>
            @endforeach
        </section>
    @empty
        <div class="tr-empty"><div style="font-size:2rem">🎾</div><strong style="display:block;color:#fff;margin:.45rem">No tennis record yet</strong><span>Verified results will appear here after the first completed predictions.</span></div>
    @endforelse
</main>
@endsection
