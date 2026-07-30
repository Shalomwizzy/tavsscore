@extends('layouts.app')
@section('title', '500, Server Error | TavsScore')
@section('content')
<div class="wrap" style="min-height:60vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:4rem 1rem;">
    <div>
        <div style="font-size:3rem;margin-bottom:1rem;">⚙️</div>
        <h1 style="font-size:1.4rem;font-weight:900;color:#fff;margin-bottom:.75rem;">Something Went Wrong</h1>
        <p style="font-size:.85rem;color:var(--text-dim);margin-bottom:2rem;max-width:380px;">
            We hit an unexpected error. Our team has been notified. Please try again in a moment.
        </p>
        <a href="{{ route('home.index') }}" style="display:inline-block;background:var(--green);color:#fff;font-weight:700;font-size:.82rem;padding:.6rem 1.4rem;border-radius:8px;text-decoration:none;">
            Back to Home
        </a>
    </div>
</div>
@endsection
