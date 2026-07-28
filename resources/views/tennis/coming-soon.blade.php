@extends('layouts.app')

@section('title', 'Tennis Predictions — Coming Soon | TavsScore')
@section('meta_description', 'ATP & WTA tennis predictions are coming soon to TavsScore — surface Elo, form and rankings, built separately from football.')

@push('styles')
<style>
    .ts-soon { max-width: 640px; margin: 4rem auto; text-align: center; padding: 0 1rem; }
    .ts-soon-icon { font-size: 4rem; line-height: 1; }
    .ts-soon h1 { font-size: clamp(1.8rem, 5vw, 2.6rem); font-weight: 900; color: #fff; margin: 1rem 0 .5rem; letter-spacing: -.02em; }
    .ts-soon-badge { display:inline-block; background: rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.3); color:#6ee7b7; font-weight:800; font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; padding:.35rem .9rem; border-radius:999px; }
    .ts-soon p { color: var(--text-dim); line-height: 1.7; font-size: .95rem; margin: 1rem auto 0; max-width: 30rem; }
    .ts-soon-feats { display:flex; gap:.6rem; justify-content:center; flex-wrap:wrap; margin-top:1.75rem; }
    .ts-soon-feats span { background: var(--card); border:1px solid var(--border); border-radius:10px; padding:.55rem .9rem; font-size:.8rem; color:var(--text); font-weight:600; }
    .ts-soon-cta { display:inline-block; margin-top:2rem; background:#10b981; color:#04121f; font-weight:800; text-decoration:none; padding:.7rem 1.4rem; border-radius:10px; font-size:.9rem; }
</style>
@endpush

@section('content')
<div class="wrap">
    <div class="ts-soon">
        <div class="ts-soon-icon">🎾</div>
        <div class="ts-soon-badge">Coming Soon</div>
        <h1>Tennis Predictions</h1>
        <p>
            We're putting the finishing touches on our ATP &amp; WTA model — surface Elo, recent form,
            rankings and head-to-head, built completely separately from football. It'll go live once we're
            confident every pick is worth your trust.
        </p>
        <div class="ts-soon-feats">
            <span>🏆 ATP &amp; WTA</span>
            <span>📊 Surface Elo</span>
            <span>🔥 Form &amp; H2H</span>
            <span>🎯 Daily best picks</span>
        </div>
        <a class="ts-soon-cta" href="{{ route('picks.index') }}">Meanwhile, see today's football picks →</a>
    </div>
</div>
@endsection
