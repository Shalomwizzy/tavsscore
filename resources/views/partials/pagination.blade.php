{{-- Shared, theme-safe single-row pagination (admin + public). --}}
<div class="pgx-wrap">
    <span class="pgx-meta">
        Showing <strong>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong>
        of <strong>{{ number_format($paginator->total()) }}</strong>
    </span>

    <nav class="pgx-nav" aria-label="Pagination">
        @if($paginator->onFirstPage())
            <span class="pgx-btn is-disabled" aria-disabled="true">‹ Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pgx-btn" aria-label="Previous page">‹ Prev</a>
        @endif

        @php
            $current = $paginator->currentPage();
            $last    = $paginator->lastPage();
            $start   = max(1, $current - 1);
            $end     = min($last, $current + 1);
        @endphp

        @if($start > 1)
            <a href="{{ $paginator->url(1) }}" class="pgx-btn pgx-num">1</a>
            @if($start > 2)<span class="pgx-ellipsis">…</span>@endif
        @endif

        @for($p = $start; $p <= $end; $p++)
            @if($p === $current)
                <span class="pgx-btn pgx-num is-active" aria-current="page">{{ $p }}</span>
            @else
                <a href="{{ $paginator->url($p) }}" class="pgx-btn pgx-num">{{ $p }}</a>
            @endif
        @endfor

        @if($end < $last)
            @if($end < $last - 1)<span class="pgx-ellipsis">…</span>@endif
            <a href="{{ $paginator->url($last) }}" class="pgx-btn pgx-num">{{ $last }}</a>
        @endif

        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pgx-btn" aria-label="Next page">Next ›</a>
        @else
            <span class="pgx-btn is-disabled" aria-disabled="true">Next ›</span>
        @endif
    </nav>
</div>

@once
@push('styles')
<style>
.pgx-wrap {
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:.6rem;
    padding:.9rem 0 0; margin-top:.9rem;
    border-top:1px solid var(--border, rgba(255,255,255,.08));
}
.pgx-meta { font-size:.75rem; color:var(--dim, var(--muted, #94a3b8)); }
.pgx-meta strong { color:var(--text, #e2e8f0); font-weight:700; }
.pgx-nav { display:inline-flex; gap:.3rem; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
.pgx-btn {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:34px; height:34px; padding:0 .7rem;
    border-radius:8px; font-size:.8rem; font-weight:700; line-height:1;
    background:rgba(255,255,255,.04);
    border:1px solid var(--border, rgba(255,255,255,.1));
    color:var(--text, #e2e8f0); text-decoration:none; white-space:nowrap;
    transition:background 140ms, border-color 140ms, color 140ms;
}
.pgx-btn.pgx-num { padding:0 .35rem; }
.pgx-btn:hover:not(.is-disabled):not(.is-active) {
    background:rgba(255,255,255,.09); border-color:rgba(255,255,255,.2);
}
.pgx-btn.is-active {
    background:var(--green-dim, var(--green-d, rgba(16,185,129,.14)));
    border-color:var(--green-border, var(--green-b, rgba(16,185,129,.35)));
    color:#6ee7b7; cursor:default;
}
.pgx-btn.is-disabled { opacity:.35; cursor:not-allowed; }
.pgx-ellipsis { color:var(--dim, var(--muted, #94a3b8)); padding:0 .15rem; font-size:.8rem; }
@media (max-width:560px) {
    .pgx-wrap { justify-content:center; }
    .pgx-nav { width:100%; justify-content:center; }
    .pgx-btn { min-width:32px; height:32px; font-size:.76rem; }
}
</style>
@endpush
@endonce
