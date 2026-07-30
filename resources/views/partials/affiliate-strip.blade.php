@php
    $affiliates = \App\Models\AffiliateLink::active()->get();
@endphp
@if($affiliates->isNotEmpty())
<div style="background:rgba(255,255,255,.025); border:1px solid var(--border); border-radius:12px; padding:1rem 1.25rem; margin-bottom:1.5rem;">
    <div style="font-size:.72rem; color:var(--text-dim); margin-bottom:.65rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700;">
        🎰 Want to place these picks? Choose a trusted platform:
    </div>
    <div style="display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;">
        @foreach($affiliates as $aff)
        <a href="{{ $aff->register_url }}" target="_blank" rel="noopener sponsored"
           style="display:inline-flex; align-items:center; gap:.35rem; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:8px; padding:.45rem .9rem; text-decoration:none; color:#fff; font-size:.75rem; font-weight:700; transition:background 150ms;"
           onmouseover="this.style.borderColor='{{ $aff->color }}40'; this.style.background='{{ $aff->color }}15'"
           onmouseout="this.style.borderColor='rgba(255,255,255,.1)'; this.style.background='rgba(255,255,255,.06)'">
            {{ $aff->logo_emoji }} {{ $aff->platform }}
            @if($aff->promo_text)
            <span style="font-size:.65rem; color:var(--text-dim); font-weight:400;"> {{ $aff->promo_text }}</span>
            @endif
        </a>
        @endforeach
    </div>
    <div style="font-size:.63rem; color:var(--text-muted); margin-top:.5rem;">18+ only. Gambling can be addictive. Please play responsibly.</div>
</div>
@endif
