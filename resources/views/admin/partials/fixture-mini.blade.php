@php
    $fixture = $match ?? null;
    $initials = function (?string $team): string {
        return collect(preg_split('/\\s+/', trim((string) $team)))->filter()->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->take(2)->implode('') ?: 'FC';
    };
@endphp

@if($fixture)
    <div style="display:flex;align-items:center;gap:.35rem;min-width:185px;max-width:270px;">
        @if($fixture->home_team_logo)
            <img src="{{ $fixture->home_team_logo }}" alt="" loading="lazy" style="width:23px;height:23px;object-fit:contain;flex-shrink:0;">
        @else
            <span aria-hidden="true" style="display:grid;place-items:center;width:23px;height:23px;border-radius:50%;background:#243a63;color:#dbeafe;font-size:.48rem;font-weight:900;flex-shrink:0;">{{ $initials($fixture->home_team) }}</span>
        @endif
        <span style="color:#fff;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $fixture->home_team ?? '?' }}</span>
        <span style="color:var(--dim);font-size:.66rem;font-weight:800;flex-shrink:0;">VS</span>
        <span style="color:#fff;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $fixture->away_team ?? '?' }}</span>
        @if($fixture->away_team_logo)
            <img src="{{ $fixture->away_team_logo }}" alt="" loading="lazy" style="width:23px;height:23px;object-fit:contain;flex-shrink:0;">
        @else
            <span aria-hidden="true" style="display:grid;place-items:center;width:23px;height:23px;border-radius:50%;background:#43225d;color:#f3e8ff;font-size:.48rem;font-weight:900;flex-shrink:0;">{{ $initials($fixture->away_team) }}</span>
        @endif
    </div>
@endif
