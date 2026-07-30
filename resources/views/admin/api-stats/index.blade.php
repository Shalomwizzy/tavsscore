@extends('layouts.admin')
@section('title', 'API Stats')
@section('page-title', 'API-Football Stats')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">📈 API-Football Stats</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <a href="{{ route('standings.index', ['league' => $leagueId]) }}" target="_blank" class="btn-a btn-blue">↗ Public Standings</a>
        <a href="{{ route('top-scorers.index', ['league' => $leagueId]) }}" target="_blank" class="btn-a btn-blue">↗ Public Top Scorers</a>
    </div>
</div>

{{-- Summary --}}
<div class="stat-grid">
    <div class="stat-card"><span class="stat-val">{{ number_format($summary['standings_rows']) }}</span><span class="stat-lbl">Standing rows</span></div>
    <div class="stat-card"><span class="stat-val">{{ number_format($summary['team_rows']) }}</span><span class="stat-lbl">Team-stat rows</span></div>
    <div class="stat-card"><span class="stat-val">{{ number_format($summary['player_rows']) }}</span><span class="stat-lbl">Player-stat rows</span></div>
    <div class="stat-card"><span class="stat-val">{{ $summary['leagues'] }}</span><span class="stat-lbl">Leagues · Season {{ $season }}</span></div>
    <div class="stat-card"><span class="stat-val">{{ number_format($summary['injuries']) }}</span><span class="stat-lbl">Injury rows</span></div>
    <div class="stat-card"><span class="stat-val">{{ number_format($summary['api_predictions']) }}</span><span class="stat-lbl">API predictions</span></div>
    <div class="stat-card"><span class="stat-val">{{ number_format($summary['fixture_stats']) }}</span><span class="stat-lbl">Fixture-stat rows</span></div>
    <div class="stat-card"><span class="stat-val">{{ number_format($summary['transfers']) }}</span><span class="stat-lbl">Transfers</span></div>
    <div class="stat-card"><span class="stat-val">{{ number_format($summary['coaches']) }}</span><span class="stat-lbl">Coaches</span></div>
</div>

{{-- League selector + fetch controls --}}
<div style="background:var(--card,#111826); border:1px solid var(--border); border-radius:12px; padding:1rem; margin-bottom:1.25rem;">
    <form method="GET" action="{{ route('admin.api-stats.index') }}" style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; margin-bottom:.85rem;">
        <label style="font-size:.72rem; color:var(--dim); font-weight:700; text-transform:uppercase;">League</label>
        <select name="league" onchange="this.form.submit()" style="background:#0d1420; border:1px solid var(--border); color:var(--text); border-radius:8px; padding:.45rem .7rem; font-size:.82rem; max-width:100%;">
            @forelse($leagues as $id => $name)
                <option value="{{ $id }}" @selected($id === $leagueId)>{{ $name }} ({{ $id }})</option>
            @empty
                <option>No leagues ingested yet</option>
            @endforelse
        </select>
    </form>

    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        @foreach([
            'admin.api-stats.standings' => '⬇ Fetch Standings',
            'admin.api-stats.teams'     => '⬇ Fetch Team Stats',
            'admin.api-stats.players'   => '⬇ Fetch Player Stats (≤5 pages)',
        ] as $route => $label)
        <form method="POST" action="{{ route($route) }}">
            @csrf
            <input type="hidden" name="league" value="{{ $leagueId }}">
            <input type="hidden" name="season" value="{{ $season }}">
            <button type="submit" class="btn-a btn-green">{{ $label }}</button>
        </form>
        @endforeach
    </div>
    <p style="font-size:.7rem; color:var(--dim); margin-top:.6rem;">
        Fetches run synchronously for the selected league only. Bulk/all-league pulls run automatically on the cron
        (standings daily, team stats Mon/Thu, players weekly).
    </p>
</div>

{{-- Standings --}}
<div class="page-hd" style="margin-bottom:.6rem;"><span class="page-hd-title" style="font-size:1rem;">🏆 {{ $leagueName ?? 'Standings' }}</span></div>
<div style="overflow-x:auto; border:1px solid var(--border); border-radius:12px; margin-bottom:1.5rem;">
    <table class="a-table" style="min-width:640px;">
        <thead><tr><th>#</th><th>Team</th><th>P</th><th>W</th><th>D</th><th>L</th><th>GF</th><th>GA</th><th>GD</th><th>Pts</th><th>Form</th></tr></thead>
        <tbody>
            @forelse($standings as $s)
            <tr>
                <td>{{ $s->rank ?? 'N/A' }}</td>
                <td style="font-weight:600;">{{ $s->team_name }}</td>
                <td>{{ $s->played }}</td><td>{{ $s->win }}</td><td>{{ $s->draw }}</td><td>{{ $s->lose }}</td>
                <td>{{ $s->goals_for }}</td><td>{{ $s->goals_against }}</td>
                <td>{{ $s->goals_diff > 0 ? '+' : '' }}{{ $s->goals_diff }}</td>
                <td style="font-weight:800; color:#fff;">{{ $s->points }}</td>
                <td style="font-size:.7rem;">{{ $s->form ? substr($s->form, -5) : 'N/A' }}</td>
            </tr>
            @empty
            <tr><td colspan="11" style="text-align:center; color:var(--dim); padding:1.5rem;">No standings for this league yet, use “Fetch Standings”.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Team stats --}}
<div class="page-hd" style="margin-bottom:.6rem;"><span class="page-hd-title" style="font-size:1rem;">📊 Team Statistics</span></div>
<div style="overflow-x:auto; border:1px solid var(--border); border-radius:12px; margin-bottom:1.5rem;">
    <table class="a-table" style="min-width:680px;">
        <thead><tr><th>Team</th><th>Played</th><th>W-D-L</th><th>GF</th><th>GA</th><th>Avg GF</th><th>Avg GA</th><th>Clean Sheets</th><th>Failed to Score</th></tr></thead>
        <tbody>
            @forelse($teamStats as $t)
            <tr>
                <td style="font-weight:600;">{{ $t->team_name }}</td>
                <td>{{ $t->played_total }}</td>
                <td>{{ $t->wins_total }}-{{ $t->draws_total }}-{{ $t->loses_total }}</td>
                <td>{{ $t->goals_for_total }}</td><td>{{ $t->goals_against_total }}</td>
                <td>{{ $t->goals_for_avg ?? 'N/A' }}</td><td>{{ $t->goals_against_avg ?? 'N/A' }}</td>
                <td>{{ $t->clean_sheets_total }}</td><td>{{ $t->failed_to_score_total }}</td>
            </tr>
            @empty
            <tr><td colspan="9" style="text-align:center; color:var(--dim); padding:1.5rem;">No team stats for this league yet, use “Fetch Team Stats”.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Top scorers --}}
<div class="page-hd" style="margin-bottom:.6rem;"><span class="page-hd-title" style="font-size:1rem;">⚽ Top Scorers (top 20)</span></div>
<div style="overflow-x:auto; border:1px solid var(--border); border-radius:12px;">
    <table class="a-table" style="min-width:600px;">
        <thead><tr><th>#</th><th>Player</th><th>Team</th><th>Pos</th><th>Goals</th><th>Assists</th><th>Apps</th><th>Min</th><th>Rating</th></tr></thead>
        <tbody>
            @forelse($topScorers as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td style="font-weight:600;">{{ $p->player_name }}</td>
                <td>{{ $p->team_name }}</td>
                <td>{{ $p->position ?? 'N/A' }}</td>
                <td style="font-weight:800; color:#fff;">{{ $p->goals }}</td>
                <td>{{ $p->assists }}</td><td>{{ $p->appearances }}</td><td>{{ $p->minutes }}</td>
                <td>{{ $p->rating ? number_format($p->rating, 2) : 'N/A' }}</td>
            </tr>
            @empty
            <tr><td colspan="9" style="text-align:center; color:var(--dim); padding:1.5rem;">No player stats for this league yet, use “Fetch Player Stats”.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
