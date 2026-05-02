{{-- Newsletter signup partial. Pass $source as a string ('picks'/'footer'/etc) --}}
@php $source = $source ?? 'unknown'; @endphp

<div class="nl-card">
    <div class="nl-head">
        <span class="nl-badge">📬 Free Daily Picks</span>
        <h3 class="nl-title">Get tomorrow's 3 picks in your inbox</h3>
        <p class="nl-sub">Free, daily at 09:00 Lagos. Unsubscribe in one click.</p>
    </div>

    @if(session('newsletter_status'))
    <div class="nl-status">{{ session('newsletter_status') }}</div>
    @endif

    <form method="POST" action="{{ route('newsletter.subscribe') }}" class="nl-form">
        @csrf
        <input type="hidden" name="source" value="{{ $source }}">

        {{-- Honeypot --}}
        <div aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;">
            <label for="nl-website-{{ $source }}">Website</label>
            <input type="text" id="nl-website-{{ $source }}" name="website" tabindex="-1" autocomplete="off">
        </div>

        <input type="email" name="email" class="nl-input"
               placeholder="you@example.com"
               required maxlength="200"
               value="{{ old('email') }}">
        <button type="submit" class="nl-btn">Subscribe Free →</button>
    </form>
    @error('email')
    <div class="nl-error">{{ $message }}</div>
    @enderror
</div>

@once
@push('styles')
<style>
    .nl-card {
        background: linear-gradient(135deg, rgba(16,185,129,.08), rgba(16,185,129,.02));
        border: 1px solid rgba(16,185,129,.25);
        border-radius: 12px;
        padding: 1.25rem 1.4rem;
    }
    .nl-head { margin-bottom: .85rem; }
    .nl-badge {
        display:inline-block; font-size:.66rem; font-weight:800;
        color:#6ee7b7; text-transform:uppercase; letter-spacing:.06em;
        margin-bottom:.35rem;
    }
    .nl-title { font-size:1.05rem; font-weight:800; color:#fff; margin:0 0 .3rem; line-height:1.3; }
    .nl-sub   { font-size:.78rem; color:var(--text-dim); margin:0; line-height:1.5; }

    .nl-form { display:flex; gap:.45rem; flex-wrap:wrap; margin-top:.4rem; }
    .nl-input {
        flex:1; min-width:160px;
        background: rgba(8,13,26,.6); border:1px solid var(--border);
        border-radius:8px; padding:.6rem .85rem;
        color: var(--text); font-size:.85rem; font-family:inherit;
        outline:none; transition: border-color 140ms;
    }
    .nl-input:focus { border-color: rgba(16,185,129,.4); box-shadow: 0 0 0 3px rgba(16,185,129,.1); }
    .nl-btn {
        background: linear-gradient(135deg,#10b981,#059669);
        color:#fff; border:none; cursor:pointer;
        padding:.6rem 1.2rem; border-radius:8px;
        font-weight:800; font-size:.82rem;
        transition: opacity 140ms, transform 140ms;
    }
    .nl-btn:hover { opacity:.92; transform: translateY(-1px); }
    .nl-status {
        font-size:.78rem; color:#6ee7b7; margin-bottom:.65rem;
        padding:.5rem .75rem; background:rgba(16,185,129,.1);
        border:1px solid rgba(16,185,129,.25); border-radius:7px;
    }
    .nl-error { font-size:.74rem; color:#fca5a5; margin-top:.4rem; }
</style>
@endpush
@endonce
