@extends('layouts.admin')
@section('title', 'Goalscorer Picks')
@section('page-title', 'Goalscorer Picks')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">⚽ Goalscorer Picks — {{ $date }}</span>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.goalscorer-picks.rebuild') }}" onsubmit="return confirm('Pull latest fixtures, standings and player statistics, then send qualifying goalscorer picks? This uses API credits.');">@csrf<button type="submit" class="btn-a btn-green">↻ Pull player data + rebuild</button></form>
        <a href="{{ route('goalscorer-picks.index') }}" target="_blank" class="btn-a btn-blue">↗ Public Page</a>
    </div>
</div>

<div style="font-size:.78rem; color:var(--dim); background:var(--card,#111826); border:1px solid var(--border); border-radius:10px; padding:.7rem .9rem; margin-bottom:1.25rem;">
    Computed live from player scoring rates vs opponent defence (no stored table). Anytime scorer is ~50–60% even for elite strikers — higher-odds, not bankers. Needs <code>player_statistics</code> populated (<code>stats:fetch-players</code>).
</div>

<div style="overflow-x:auto; border:1px solid var(--border); border-radius:12px;">
    <table class="a-table" style="min-width:720px;">
        <thead>
            <tr><th>#</th><th>Player</th><th>Team</th><th>Fixture</th><th>vs</th><th>Anytime</th><th>2+ Goals</th><th>Season</th></tr>
        </thead>
        <tbody>
            @forelse($picks as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td style="font-weight:600; color:#fff;">{{ $p['player'] }}</td>
                <td>{{ $p['team'] }}</td>
                <td style="font-size:.75rem;">{{ $p['match'] }} · {{ $p['kickoff'] }}</td>
                <td>{{ $p['opponent'] }}</td>
                <td style="font-weight:800; color:#6ee7b7;">{{ number_format($p['probability'],0) }}%</td>
                <td>{{ !empty($p['two_plus']) ? number_format($p['two_plus'],0).'%' : '—' }}</td>
                <td style="font-size:.75rem; color:var(--dim);">{{ $p['goals'] }}g / {{ $p['apps'] }} apps</td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center; color:var(--dim); padding:2rem;">
                No goalscorer picks for today. Populate player stats (<code>stats:fetch-players</code>) and ensure there are covered fixtures today.
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
