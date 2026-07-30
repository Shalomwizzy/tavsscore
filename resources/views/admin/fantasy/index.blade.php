@extends('layouts.admin')
@section('title', 'Fantasy Best XI')
@section('page-title', 'Fantasy Best XI')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">⚽ Fantasy Best XI</span>
</div>

@if(session('success'))
<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#6ee7b7;">
    ✅ {{ session('success') }}
</div>
@endif
@if(session('error'))
<div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.82rem;color:#fca5a5;">
    ⚠️ {{ session('error') }}
</div>
@endif

<div class="a-card" style="margin-bottom:1.25rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
        <div style="font-size:.82rem; color:var(--dim); max-width:34rem; line-height:1.6;">
            The best XI is rebuilt automatically each week from stored player stats.
            Use the button to rebuild it now (e.g. after a fresh <code>stats:fetch-players</code> run).
        </div>
        <div style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap;">
            <a href="{{ route('fantasy.index') }}" target="_blank" rel="noopener"
               style="background:rgba(255,255,255,.06); color:#a5b4fc; border:1px solid var(--border); border-radius:8px; padding:.55rem 1rem; font-size:.82rem; font-weight:600; text-decoration:none;">
                👁 Preview public page ↗
            </a>
            <form method="POST" action="{{ route('admin.fantasy.rebuild') }}" style="margin:0;">
                @csrf
                <button type="submit" style="background:#6366f1; color:#fff; border:none; border-radius:8px; padding:.6rem 1.1rem; font-weight:700; font-size:.85rem; cursor:pointer;">
                    🔄 Rebuild now
                </button>
            </form>
        </div>
    </div>
</div>

@forelse($squads as $squad)
<div class="a-card" style="margin-bottom:1rem;">
    <div class="page-hd" style="margin-bottom:.75rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">{{ $squad->gameweek }}</span>
        <span style="font-size:.72rem; color:var(--dim);">
            {{ $squad->formation }} · £{{ number_format($squad->budget_used,1) }}m ·
            {{ $squad->total_points }} pts · (C) {{ $squad->captain }} ·
            built {{ optional($squad->built_at)->timezone('Africa/Lagos')->format('M d, H:i') }}
        </span>
    </div>
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr><th>Pos</th><th>Player</th><th>Team</th><th>£</th><th>Pts</th><th>Role</th></tr>
            </thead>
            <tbody>
                @foreach($squad->starting_xi as $p)
                <tr>
                    <td style="font-weight:700;">{{ $p['position'] }}</td>
                    <td style="color:#fff;">{{ $p['name'] }}</td>
                    <td style="color:var(--dim);">{{ $p['team'] }}</td>
                    <td>£{{ number_format($p['price'],1) }}</td>
                    <td style="color:#6ee7b7; font-weight:700;">{{ (int) $p['points'] }}</td>
                    <td>
                        @if($p['is_captain'] ?? false)<span style="color:#fcd34d; font-weight:800;">Captain</span>
                        @elseif($p['is_vice'] ?? false)<span style="color:#94a3b8;">Vice</span>
                        @else <span style="color:var(--dim);">XI</span>@endif
                    </td>
                </tr>
                @endforeach
                @foreach($squad->bench as $p)
                <tr style="opacity:.6;">
                    <td>{{ $p['position'] }}</td>
                    <td>{{ $p['name'] }}</td>
                    <td style="color:var(--dim);">{{ $p['team'] }}</td>
                    <td>£{{ number_format($p['price'],1) }}</td>
                    <td>{{ (int) $p['points'] }}</td>
                    <td style="color:var(--dim);">Bench</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="a-card" style="text-align:center; padding:2rem; color:var(--dim);">
    No Fantasy squad built yet. Click <strong>Rebuild now</strong> (needs player stats, run <code>stats:fetch-players</code> first).
</div>
@endforelse

@endsection
