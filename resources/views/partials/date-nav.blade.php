{{--
    Shared date navigation bar for all pick-type pages.
    Requires $dateMeta array from ResolvesDateNav trait.
--}}
<form method="GET" action="{{ route($dateMeta['route']) }}" class="picks-date-nav" id="picks-date-nav">
    <a href="{{ route($dateMeta['route'], ['date' => $dateMeta['prev_iso']]) }}"
       class="pdn-btn" aria-label="Previous day">‹ Prev</a>

    <div class="pdn-center">
        <input type="date" name="date" id="picks-archive-date"
               value="{{ $dateMeta['iso'] }}"
               min="{{ $dateMeta['min_iso'] }}"
               max="{{ $dateMeta['max_iso'] }}"
               class="pdn-input"
               aria-label="Pick a date"
               onchange="this.form.submit()">
        @unless($dateMeta['is_today'])
        <span class="pdn-archive-label">📁 Archive</span>
        @endunless
    </div>

    @if($dateMeta['next_iso'])
    <a href="{{ route($dateMeta['route'], ['date' => $dateMeta['next_iso']]) }}"
       class="pdn-btn" aria-label="Next day">Next ›</a>
    @else
    <span class="pdn-btn pdn-disabled">Next ›</span>
    @endif

    @unless($dateMeta['is_today'])
    <a href="{{ route($dateMeta['route']) }}" class="pdn-btn pdn-today">Today</a>
    @endunless
</form>

@once
@push('styles')
<style>
.picks-date-nav {
    display: flex; align-items: center; gap: .45rem; flex-wrap: wrap;
    margin: 0 0 1.25rem;
    padding: .5rem .65rem;
    background: rgba(255,255,255,.025);
    border: 1px solid var(--border);
    border-radius: 10px;
}
.pdn-btn {
    display: inline-flex; align-items: center;
    padding: .38rem .8rem; border-radius: 7px;
    background: rgba(255,255,255,.05); border: 1px solid var(--border);
    color: var(--text); text-decoration: none; font-size: .74rem; font-weight: 700;
    transition: background 140ms, border-color 140ms; white-space: nowrap;
}
.pdn-btn:hover:not(.pdn-disabled) { background: rgba(255,255,255,.1); border-color: rgba(99,179,237,.3); color: #fff; }
.pdn-disabled { opacity: .35; cursor: not-allowed; }
.pdn-today { margin-left: auto; background: rgba(16,185,129,.12); border-color: rgba(16,185,129,.28); color: #6ee7b7; }
.pdn-today:hover { background: rgba(16,185,129,.22) !important; }
.pdn-center { display: flex; align-items: center; gap: .5rem; }
.pdn-input {
    background: rgba(8,13,26,.7); border: 1px solid var(--border);
    border-radius: 7px; padding: .38rem .6rem;
    color: var(--text); font-family: inherit; font-size: .76rem; font-weight: 700;
    cursor: pointer; color-scheme: dark;
}
.pdn-archive-label {
    font-size: .68rem; font-weight: 700; color: #fcd34d;
    background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.25);
    padding: 2px 8px; border-radius: 999px;
}
</style>
@endpush
@endonce
