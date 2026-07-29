@extends('layouts.app')

@section('title', 'High Risk Picks — big-odds accumulators')

@push('styles')
<style>
    .hr-wrap { max-width:820px; margin:0 auto; padding:1.25rem; }
    .hr-hero { background:linear-gradient(135deg,#3b0a0a,#7a1414); border:1px solid #b91c1c; border-radius:16px; padding:1.5rem; }
    .hr-hero h1 { margin:0 0 .4rem; font-size:1.9rem; font-weight:850; color:#fff; }
    .hr-hero p { margin:0; color:#fecaca; font-size:.9rem; }
    .hr-warn { margin:1rem 0; background:#450a0a; border:1px solid #ef4444; border-radius:10px; padding:.85rem 1rem; color:#fecaca; font-size:.82rem; font-weight:600; }
    .hr-card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.25rem; margin-top:1rem; }
    .hr-code { font-family:monospace; font-size:1.4rem; font-weight:800; color:#6ee7b7; letter-spacing:2px; }
    .hr-odds { display:inline-block; background:#7a1414; color:#fff; font-weight:800; border-radius:8px; padding:.2rem .7rem; font-size:.9rem; }
    .hr-leg { display:flex; justify-content:space-between; gap:.5rem; padding:.5rem 0; border-top:1px solid var(--border); font-size:.83rem; }
    .hr-leg small { color:var(--dim); }
    .hr-hist { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.6rem; }
    .hr-pill { font-size:.72rem; font-weight:700; padding:.25rem .6rem; border-radius:20px; }
    .hr-won { background:var(--green-dim,rgba(16,185,129,.15)); color:#6ee7b7; border:1px solid var(--green-border,rgba(16,185,129,.3)); }
    .hr-lost { background:rgba(239,68,68,.12); color:#fca5a5; border:1px solid rgba(239,68,68,.3); }
</style>
@endpush

@section('content')
<div class="hr-wrap">
    <div class="hr-hero">
        <h1>🎲 High Risk</h1>
        <p>The model's most confident calls stacked into one big-odds accumulator (100× and up). High reward — but every leg must land.</p>
    </div>

    <div class="hr-warn">
        ⚠️ <strong>For fun only.</strong> These are long-shot accumulators — they lose far more often than they win. Never stake money you can't afford to lose, and keep stakes small. This is not advice; bet responsibly (18+).
    </div>

    @forelse($codes as $code)
        <div class="hr-card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem;">
                <div><div style="font-size:.72rem;color:var(--dim);text-transform:uppercase;letter-spacing:1px;">SportyBet booking code</div><div class="hr-code">{{ $code->code }}</div></div>
                <span class="hr-odds">{{ number_format((float)$code->total_odds, 2) }}× odds</span>
            </div>
            @if(is_array($code->fixtures) && count($code->fixtures))
                <div style="margin-top:.75rem;">
                    @foreach($code->fixtures as $leg)
                        <div class="hr-leg"><span>{{ $leg['match'] ?? '—' }}</span><small>{{ $leg['market'] ?? '' }}</small></div>
                    @endforeach
                </div>
            @endif
            @if($code->link)
                <a href="{{ $code->link }}" target="_blank" rel="noopener" style="display:inline-block;margin-top:.9rem;background:#b91c1c;color:#fff;font-weight:700;border-radius:8px;padding:.5rem 1.1rem;text-decoration:none;font-size:.85rem;">Load on SportyBet →</a>
            @endif
        </div>
    @empty
        <div class="hr-card" style="text-align:center;color:var(--dim);">
            No high-risk ticket published yet today — check back after the daily picks are out.
        </div>
    @endforelse

    @if($history->isNotEmpty())
        <div class="hr-card">
            <div style="font-size:.8rem;color:var(--dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:.4rem;">Recent results — {{ $wonCount }}/{{ $history->count() }} won</div>
            <div class="hr-hist">
                @foreach($history as $h)
                    <span class="hr-pill {{ $h->status === 'won' ? 'hr-won' : 'hr-lost' }}">{{ $h->code }} {{ $h->status === 'won' ? '✓' : '✗' }} {{ number_format((float)$h->total_odds,0) }}×</span>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
