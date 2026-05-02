{{-- Compact, single-row admin pagination --}}
<div class="pg-wrap">
    <span class="pg-meta">
        Showing <strong>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong>
        of <strong>{{ number_format($paginator->total()) }}</strong>
    </span>

    <nav class="pg-nav" aria-label="Pagination">
        @if($paginator->onFirstPage())
            <span class="pg-btn is-disabled" aria-disabled="true">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pg-btn" aria-label="Previous page">‹</a>
        @endif

        @php
            $current = $paginator->currentPage();
            $last    = $paginator->lastPage();
            $start   = max(1, $current - 1);
            $end     = min($last, $current + 1);
        @endphp

        @if($start > 1)
            <a href="{{ $paginator->url(1) }}" class="pg-btn">1</a>
            @if($start > 2)<span class="pg-ellipsis">…</span>@endif
        @endif

        @for($p = $start; $p <= $end; $p++)
            @if($p === $current)
                <span class="pg-btn is-active" aria-current="page">{{ $p }}</span>
            @else
                <a href="{{ $paginator->url($p) }}" class="pg-btn">{{ $p }}</a>
            @endif
        @endfor

        @if($end < $last)
            @if($end < $last - 1)<span class="pg-ellipsis">…</span>@endif
            <a href="{{ $paginator->url($last) }}" class="pg-btn">{{ $last }}</a>
        @endif

        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pg-btn" aria-label="Next page">›</a>
        @else
            <span class="pg-btn is-disabled" aria-disabled="true">›</span>
        @endif
    </nav>
</div>

@once
@push('styles')
<style>
.pg-wrap {
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:.6rem;
    padding:.875rem 0 0; margin-top:.75rem;
    border-top:1px solid var(--border);
}
.pg-meta { font-size:.74rem; color:var(--dim); }
.pg-meta strong { color:var(--text); font-weight:700; }
.pg-nav  { display:inline-flex; gap:.25rem; align-items:center; flex-wrap:nowrap; }
.pg-btn  {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:30px; height:30px; padding:0 .55rem;
    border-radius:6px; font-size:.78rem; font-weight:700;
    background:rgba(255,255,255,.04); border:1px solid var(--border);
    color:var(--text); text-decoration:none; line-height:1;
    transition:background 140ms, border-color 140ms, color 140ms;
}
.pg-btn:hover:not(.is-disabled):not(.is-active) {
    background:rgba(255,255,255,.08); border-color:rgba(255,255,255,.18);
}
.pg-btn.is-active {
    background:var(--green-d); border-color:var(--green-b); color:#6ee7b7; cursor:default;
}
.pg-btn.is-disabled { opacity:.32; cursor:not-allowed; }
.pg-ellipsis { color:var(--dim); padding:0 .15rem; font-size:.78rem; }
@media (max-width:520px) { .pg-meta { font-size:.7rem; } .pg-btn { min-width:28px; height:28px; font-size:.74rem; } }
</style>
@endpush
@endonce
