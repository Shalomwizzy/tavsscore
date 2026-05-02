@extends('layouts.app')
@section('title', 'Subscription Confirmed | TavsScore')

@section('content')
<div class="wrap" style="max-width:560px; padding:3rem 1rem;">
    @if($ok)
        <div style="background:linear-gradient(135deg,rgba(16,185,129,.10),rgba(16,185,129,.03)); border:1px solid rgba(16,185,129,.32); border-radius:14px; padding:2rem 2rem; text-align:center;">
            <div style="font-size:2.5rem; margin-bottom:.5rem;">✅</div>
            <h1 style="font-size:1.4rem; font-weight:800; color:#fff; margin-bottom:.65rem;">You're in!</h1>
            <p style="color:var(--text-dim); font-size:.92rem; line-height:1.6; margin-bottom:1.25rem;">
                <strong style="color:#fff;">{{ $email }}</strong> is confirmed. Tomorrow's 3 best picks will land in your inbox at <strong style="color:#fcd34d;">09:00 Lagos time</strong>.
            </p>
            <a href="{{ route('picks.index') }}" style="display:inline-flex; align-items:center; gap:.5rem; padding:.65rem 1.4rem; background:rgba(245,158,11,.18); border:1px solid rgba(245,158,11,.35); border-radius:9px; font-size:.9rem; font-weight:800; color:#fcd34d; text-decoration:none;">
                See today's picks →
            </a>
        </div>
    @else
        <div style="background:rgba(239,68,68,.05); border:1px solid rgba(239,68,68,.25); border-radius:14px; padding:2rem; text-align:center;">
            <div style="font-size:2.5rem; margin-bottom:.5rem;">❌</div>
            <h1 style="font-size:1.3rem; font-weight:800; color:#fff; margin-bottom:.65rem;">Confirmation failed</h1>
            <p style="color:var(--text-dim); font-size:.9rem;">{{ $message ?? 'Something went wrong.' }}</p>
            <p style="margin-top:1.25rem;"><a href="{{ route('picks.index') }}" style="color:var(--green); text-decoration:none; font-weight:700;">← Back to picks</a></p>
        </div>
    @endif
</div>
@endsection
