@extends('layouts.app')
@section('title', 'Unsubscribed | TavsScore')

@section('content')
<div class="wrap" style="max-width:560px; padding:3rem 1rem;">
    @if($ok)
        <div style="background:var(--card); border:1px solid var(--border); border-radius:14px; padding:2rem; text-align:center;">
            <div style="font-size:2.2rem; margin-bottom:.5rem;">👋</div>
            <h1 style="font-size:1.3rem; font-weight:800; color:#fff; margin-bottom:.65rem;">You're unsubscribed</h1>
            <p style="color:var(--text-dim); font-size:.9rem; line-height:1.6;">
                <strong style="color:#fff;">{{ $email }}</strong> won't receive any more emails from us.
            </p>
            <p style="margin-top:1.25rem;"><a href="{{ route('home.index') }}" style="color:var(--green); text-decoration:none; font-weight:700;">← Back to TavsScore</a></p>
        </div>
    @else
        <div style="background:rgba(239,68,68,.05); border:1px solid rgba(239,68,68,.25); border-radius:14px; padding:2rem; text-align:center;">
            <div style="font-size:2.2rem; margin-bottom:.5rem;">❌</div>
            <h1 style="font-size:1.3rem; font-weight:800; color:#fff; margin-bottom:.65rem;">Couldn't unsubscribe</h1>
            <p style="color:var(--text-dim); font-size:.9rem;">{{ $message ?? 'Invalid link.' }}</p>
        </div>
    @endif
</div>
@endsection
