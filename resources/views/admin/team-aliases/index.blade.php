@extends('layouts.admin')
@section('title', 'Team Aliases, TavsScore Admin')
@section('page-title', 'Team Aliases Queue')

@push('styles')
<style>
    .ta-note   { font-size:.78rem; color:var(--text-dim); background:rgba(99,102,241,.06); border:1px solid rgba(99,102,241,.18); border-radius:10px; padding:.7rem .9rem; margin-bottom:1.25rem; line-height:1.55; }
    .ta-note strong { color:#fff; }
    .ta-strip  { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:.75rem; margin-bottom:1.5rem; }
    .ta-stat   { background:var(--card); border:1px solid var(--border); border-radius:10px; padding:.85rem 1rem; }
    .ta-stat-lbl { font-size:.62rem; color:var(--text-dim); font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
    .ta-stat-val { font-size:1.35rem; font-weight:900; color:#fff; margin-top:.25rem; }

    .ta-filters { display:flex; gap:.5rem; margin-bottom:1rem; align-items:center; }
    .ta-filter-btn { font-size:.7rem; padding:.4rem .9rem; border-radius:8px; border:1px solid var(--border); background:transparent; color:var(--text-dim); text-decoration:none; font-weight:700; }
    .ta-filter-btn.active { background:rgba(99,102,241,.15); border-color:rgba(99,102,241,.35); color:#c4b5fd; }
    .ta-bulk { margin-left:auto; }

    .ta-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .ta-table { width:100%; min-width:680px; border-collapse:collapse; font-size:.78rem; }
    .ta-table th { text-align:left; font-size:.6rem; font-weight:800; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; padding:.4rem .7rem; border-bottom:1px solid var(--border); }
    .ta-table td { padding:.5rem .7rem; border-bottom:1px solid rgba(255,255,255,.04); color:var(--text); vertical-align:middle; }
    .ta-alias { color:#fff; font-weight:700; }
    .ta-team  { color:var(--text-dim); font-size:.72rem; }
    .ta-suggestion { display:inline-block; margin:.15rem .25rem 0 0; font-size:.68rem; padding:2px 8px; border-radius:999px; background:rgba(251,191,36,.10); border:1px solid rgba(251,191,36,.28); color:#fde68a; font-weight:700; }
    .ta-actions { display:flex; gap:.3rem; align-items:center; flex-wrap:wrap; }
    .ta-btn { font-size:.68rem; padding:.3rem .55rem; border-radius:6px; border:0; cursor:pointer; font-weight:700; }
    .ta-btn-approve { background:rgba(16,185,129,.15); border:1px solid rgba(16,185,129,.3); color:#6ee7b7; }
    .ta-btn-merge   { background:rgba(251,191,36,.15); border:1px solid rgba(251,191,36,.3); color:#fde68a; }
    .ta-btn-approve:hover { background:rgba(16,185,129,.25); }
    .ta-btn-merge:hover   { background:rgba(251,191,36,.25); }
    .ta-flash { padding:.6rem .9rem; background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.28); border-radius:8px; color:#6ee7b7; font-size:.78rem; margin-bottom:1rem; }

    @media (max-width:640px) {
        .ta-strip { grid-template-columns:1fr 1fr; }
        .ta-filters { flex-wrap:wrap; }
        .ta-bulk { margin-left:0; width:100%; }
        .ta-bulk button { width:100%; }
    }
</style>
@endpush

@section('content')

<div class="ta-note">
    <strong>Team canonicalisation queue.</strong>
    Every new team name the provider ships gets registered here. Merge duplicate spellings
    (Bayern München / Bayern Munich) into a single canonical team, or approve genuine new teams
    to keep them distinct. Aliases with a highlighted suggestion likely collide with an existing team.
</div>

@if(session('success'))
    <div class="ta-flash">{{ session('success') }}</div>
@endif

<div class="ta-strip">
    <div class="ta-stat">
        <div class="ta-stat-lbl">👀 Pending review</div>
        <div class="ta-stat-val" style="color:{{ $stats['pending'] > 20 ? '#fde68a' : '#fff' }};">{{ number_format($stats['pending']) }}</div>
    </div>
    <div class="ta-stat">
        <div class="ta-stat-lbl">✓ Reviewed</div>
        <div class="ta-stat-val" style="color:#6ee7b7;">{{ number_format($stats['reviewed']) }}</div>
    </div>
    <div class="ta-stat">
        <div class="ta-stat-lbl">🏷️ Canonical teams</div>
        <div class="ta-stat-val">{{ number_format($stats['teams']) }}</div>
    </div>
</div>

<div class="ta-filters">
    <a href="{{ route('admin.team-aliases.index', ['filter' => 'pending']) }}"  class="ta-filter-btn {{ $filter === 'pending' ? 'active' : '' }}">Pending ({{ $stats['pending'] }})</a>
    <a href="{{ route('admin.team-aliases.index', ['filter' => 'reviewed']) }}" class="ta-filter-btn {{ $filter === 'reviewed' ? 'active' : '' }}">Reviewed</a>
    <a href="{{ route('admin.team-aliases.index', ['filter' => 'all']) }}"     class="ta-filter-btn {{ $filter === 'all' ? 'active' : '' }}">All</a>

    <form method="POST" action="{{ route('admin.team-aliases.bulk-approve-unique') }}" class="ta-bulk" onsubmit="return confirm('Auto-approve every pending alias whose name doesn\'t collide with an existing team? This is safe, only unambiguous names get approved.');">
        @csrf
        <button type="submit" class="ta-btn ta-btn-approve" style="font-size:.72rem; padding:.42rem .75rem;">⚡ Bulk-approve non-colliding</button>
    </form>
</div>

<div class="ta-scroll"><table class="ta-table">
    <thead>
        <tr>
            <th>Alias</th>
            <th>Current canonical team</th>
            <th>Suggested duplicates</th>
            <th style="text-align:right;">First seen</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse($aliases as $alias)
        <tr>
            <td class="ta-alias">{{ $alias->alias }}</td>
            <td class="ta-team">
                #{{ $alias->team_id }} · {{ $alias->team?->canonical_name ?? '(deleted)' }}
                @if($alias->reviewed)
                    <span style="display:inline-block; margin-left:.3rem; font-size:.6rem; color:#6ee7b7;">✓ reviewed</span>
                @endif
            </td>
            <td>
                @if(isset($suggestions[$alias->id]))
                    @foreach($suggestions[$alias->id] as $candidate)
                        <form method="POST" action="{{ route('admin.team-aliases.merge', $alias) }}" style="display:inline;" onsubmit="return confirm('Merge \'{{ $alias->alias }}\' into #{{ $candidate->id }} {{ $candidate->canonical_name }}?');">
                            @csrf
                            <input type="hidden" name="target_team_id" value="{{ $candidate->id }}">
                            <button type="submit" class="ta-suggestion" style="cursor:pointer; border:1px solid rgba(251,191,36,.28); background:rgba(251,191,36,.10);">
                                ↦ #{{ $candidate->id }} {{ $candidate->canonical_name }}
                            </button>
                        </form>
                    @endforeach
                @else
                    <span style="color:var(--text-dim); font-size:.7rem;"> no obvious duplicates </span>
                @endif
            </td>
            <td style="text-align:right; color:var(--text-dim); font-size:.7rem; font-variant-numeric:tabular-nums;">
                {{ optional($alias->first_seen_at)->diffForHumans() }}
            </td>
            <td>
                @if(! $alias->reviewed)
                <div class="ta-actions">
                    <form method="POST" action="{{ route('admin.team-aliases.approve', $alias) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="ta-btn ta-btn-approve">✓ Keep distinct</button>
                    </form>
                </div>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--text-dim);">Nothing to review here.</td></tr>
        @endforelse
    </tbody>
</table></div>

<div style="margin-top:1rem;">
    {{ $aliases->links() }}
</div>

@endsection
