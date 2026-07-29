@extends('layouts.admin')

@section('title', 'High Risk')

@section('content')
<div style="max-width:820px;">
    <h1 style="font-size:1.3rem;font-weight:700;margin:0 0 .35rem;">🎲 High Risk</h1>
    <p style="color:var(--dim);font-size:.85rem;margin:0 0 1.25rem;">
        Auto-built big-odds accumulators (the model's 50%+ calls stacked to 100–1500× odds).
        They generate with the daily picks and are booked + sent to Telegram automatically.
        <a href="{{ route('high-risk.index') }}" target="_blank" style="color:var(--green,#10b981);">View public page →</a>
    </p>

    <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:1.25rem;margin-bottom:1.25rem;">
        <div class="sb-section-label" style="margin-bottom:.6rem;">Today's high-risk ticket</div>
        @forelse($today_codes as $c)
            <div style="border-top:1px solid var(--border);padding:.6rem 0;">
                <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                    <span style="font-family:monospace;font-weight:700;">{{ $c->code }}</span>
                    <span>{{ number_format((float)$c->total_odds,2) }}× · <span style="font-weight:700;{{ $c->status==='won'?'color:#059669':($c->status==='lost'?'color:#dc2626':'') }}">{{ ucfirst($c->status) }}</span></span>
                </div>
                @if(is_array($c->fixtures))
                    <div style="color:var(--dim);font-size:.78rem;margin-top:.35rem;">{{ count($c->fixtures) }} legs: {{ collect($c->fixtures)->pluck('match')->take(4)->implode(' · ') }}{{ count($c->fixtures)>4?' …':'' }}</div>
                @endif
            </div>
        @empty
            <p style="color:var(--dim);font-size:.85rem;margin:0;">No high-risk ticket yet today — it appears after the daily spec runs and the worker books it.</p>
        @endforelse
    </div>

    <div style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:1.25rem;">
        <div class="sb-section-label" style="margin-bottom:.6rem;">Results history ({{ $history->count() }})</div>
        @forelse($history as $h)
            <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding:.45rem 0;font-size:.82rem;">
                <span style="font-family:monospace;">{{ $h->code }}</span>
                <span>{{ number_format((float)$h->total_odds,0) }}× · <span style="font-weight:700;{{ $h->status==='won'?'color:#059669':'color:#dc2626' }}">{{ $h->status==='won'?'WON ✓':'LOST ✗' }}</span></span>
            </div>
        @empty
            <p style="color:var(--dim);font-size:.85rem;margin:0;">No settled high-risk tickets yet.</p>
        @endforelse
    </div>
</div>
@endsection
