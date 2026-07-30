@php
    $fixture = $match ?? null;
    if (! $fixture && isset($predictionId)) {
        $fixture = \App\Models\Prediction::query()->with('match')->find($predictionId)?->match;
    }
    $accent = $accent ?? '#6ee7b7';
    $compact = $compact ?? false;
    $badge = function (?string $team): string {
        return collect(preg_split('/\\s+/', trim((string) $team)))->filter()->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->take(2)->implode('') ?: 'FC';
    };
@endphp

@if($fixture)
<div style="display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:.65rem;padding:{{ $compact ? '.45rem 0' : '.7rem .1rem 1rem' }};border-bottom:{{ $compact ? '0' : '1px solid var(--border)' }};margin-bottom:{{ $compact ? '0' : '1rem' }};">
    <div style="display:flex;align-items:center;gap:.5rem;min-width:0;">
        @if($fixture->home_team_logo)
            <img src="{{ $fixture->home_team_logo }}" alt="" loading="lazy" style="width:{{ $compact ? '22px' : '34px' }};height:{{ $compact ? '22px' : '34px' }};object-fit:contain;flex-shrink:0;">
        @else
            <span aria-hidden="true" style="display:grid;place-items:center;width:{{ $compact ? '22px' : '34px' }};height:{{ $compact ? '22px' : '34px' }};border-radius:50%;background:linear-gradient(135deg,#243a63,#111827);border:1px solid rgba(255,255,255,.16);color:#dbeafe;font-size:{{ $compact ? '.5rem' : '.62rem' }};font-weight:900;flex-shrink:0;">{{ $badge($fixture->home_team) }}</span>
        @endif
        <strong style="color:#fff;font-size:{{ $compact ? '.73rem' : '.94rem' }};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $fixture->home_team }}</strong>
    </div>
    <div style="text-align:center;min-width:{{ $compact ? '48px' : '60px' }};">
        @if(in_array($fixture->status, ['1H','2H','HT','ET','BT','P','LIVE']))
            <div style="font-size:{{ $compact ? '.68rem' : '.78rem' }};font-weight:900;color:#f87171;">{{ $fixture->home_score ?? 0 }}:{{ $fixture->away_score ?? 0 }}</div>
            <div style="font-size:.57rem;font-weight:800;color:#f87171;letter-spacing:.05em;">LIVE{{ $fixture->elapsed ? ' '.$fixture->elapsed."'" : '' }}</div>
        @elseif(in_array($fixture->status, ['FT','AET','PEN']))
            <div style="font-size:{{ $compact ? '.68rem' : '.78rem' }};font-weight:900;color:#fff;">{{ $fixture->home_score }}:{{ $fixture->away_score }}</div>
            <div style="font-size:.57rem;font-weight:800;color:var(--text-dim);letter-spacing:.05em;">FT</div>
        @else
            <div style="font-size:{{ $compact ? '.68rem' : '.78rem' }};font-weight:900;color:{{ $accent }};">{{ $fixture->match_time?->setTimezone(config('app.timezone'))->format('H:i') ?? 'TBC' }}</div>
            <div style="font-size:.57rem;font-weight:800;color:var(--text-dim);letter-spacing:.05em;">{{ $fixture->match_time?->setTimezone(config('app.timezone'))->format('M j') }}</div>
        @endif
    </div>
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:.5rem;min-width:0;text-align:right;">
        <strong style="color:#fff;font-size:{{ $compact ? '.73rem' : '.94rem' }};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $fixture->away_team }}</strong>
        @if($fixture->away_team_logo)
            <img src="{{ $fixture->away_team_logo }}" alt="" loading="lazy" style="width:{{ $compact ? '22px' : '34px' }};height:{{ $compact ? '22px' : '34px' }};object-fit:contain;flex-shrink:0;">
        @else
            <span aria-hidden="true" style="display:grid;place-items:center;width:{{ $compact ? '22px' : '34px' }};height:{{ $compact ? '22px' : '34px' }};border-radius:50%;background:linear-gradient(135deg,#43225d,#111827);border:1px solid rgba(255,255,255,.16);color:#f3e8ff;font-size:{{ $compact ? '.5rem' : '.62rem' }};font-weight:900;flex-shrink:0;">{{ $badge($fixture->away_team) }}</span>
        @endif
    </div>
</div>
@endif
