{{-- Pre-season / off-window empty state. Expects: $resumeDate (string|null). --}}
<div class="picks-empty" style="text-align:center; padding:4rem 1rem;">
    <div style="font-size:3rem; margin-bottom:1rem;">🌱</div>
    <div style="font-size:1.25rem; font-weight:800; color:#fff; margin-bottom:.5rem;">Top leagues are between seasons</div>
    <p style="font-size:.88rem; color:var(--text-dim); line-height:1.7; max-width:34rem; margin:0 auto;">
        No fixtures from our covered leagues are scheduled today — most of Europe is still in pre-season.
        @if(!empty($resumeDate))
            Regular predictions resume <strong style="color:var(--text);">{{ $resumeDate }}</strong>.
        @else
            Predictions resume as soon as the new season's fixtures kick off.
        @endif
        In the meantime, use the date picker above to browse past results.
    </p>
</div>
