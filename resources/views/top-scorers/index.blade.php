@extends('layouts.app')

@section('title', ($leagueName ? $leagueName.' Top Scorers' : 'Top Scorers').' | TavsScore')
@section('meta_description', 'Top scorers and assist leaders, goals, assists, minutes and match ratings across every league TavsScore covers.')
@section('og_title', 'Top Scorers | TavsScore')

@push('styles')
<style>
    .ts-wrap { padding: 2.5rem 0 4rem; }
    .ts-head { margin-bottom: 1.5rem; }
    .ts-title { font-size: clamp(1.6rem,4vw,2.4rem); font-weight:900; color:#fff; letter-spacing:-.02em; }
    .ts-sub   { font-size:.9rem; color:var(--text-dim); margin-top:.35rem; }

    .ts-controls { display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; margin-bottom:1.25rem; }
    .ts-select {
        background:var(--card); border:1px solid var(--border); color:var(--text);
        border-radius:10px; padding:.55rem .9rem; font-size:.85rem; font-weight:600; max-width:100%; cursor:pointer;
    }
    .ts-tabs { display:flex; gap:.4rem; }
    .ts-tab {
        padding:.4rem .85rem; border-radius:999px; font-size:.78rem; font-weight:700;
        border:1px solid var(--border); background:var(--card); color:var(--text-dim); text-decoration:none;
    }
    .ts-tab.active { background:var(--green-dim); border-color:var(--green-border); color:#6ee7b7; }

    .ts-tablewrap { overflow-x:auto; border:1px solid var(--border); border-radius:14px; background:var(--card); }
    table.ts-table { width:100%; border-collapse:collapse; min-width:620px; }
    .ts-table th, .ts-table td { padding:.7rem .6rem; text-align:center; font-size:.83rem; white-space:nowrap; }
    .ts-table th { color:var(--text-dim); font-weight:700; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid var(--border); }
    .ts-table td { border-bottom:1px solid rgba(255,255,255,.04); color:var(--text); }
    .ts-table tr:last-child td { border-bottom:none; }
    .ts-table .col-player { text-align:left; }
    .ts-rank { font-weight:800; color:var(--text-dim); width:2rem; }
    .ts-player { display:flex; align-items:center; gap:.6rem; }
    .ts-player img { width:28px; height:28px; border-radius:50%; object-fit:cover; flex-shrink:0; background:#1a2230; }
    .ts-pname { font-weight:700; }
    .ts-pteam { display:block; font-size:.7rem; color:var(--text-dim); font-weight:500; }
    .ts-big { font-weight:900; color:#fff; }

    .ts-empty { text-align:center; padding:4rem 1rem; color:var(--text-dim); }
    .ts-empty-icon { font-size:2.5rem; margin-bottom:.75rem; }

    @media (max-width:640px) {
        .ts-table th, .ts-table td { padding:.55rem .45rem; font-size:.78rem; }
    }
</style>
@endpush

@section('content')
<div class="wrap ts-wrap">
    @include('partials.more-page-hero', [
        'moreKicker' => 'League intelligence',
        'moreTitle' => $metric === 'assists' ? 'The creators shaping the league.' : 'The scorers shaping the league.',
        'moreDescription' => 'Explore the players driving each competition, with the latest goal, assist, minutes and rating context in one place.',
    ])
    <div class="ts-head">
        <p class="ts-sub">{{ $leagueName ? $leagueName.', '.$season.'/'.($season + 1) : 'Season '.$season }}</p>
    </div>

    <div class="ts-controls">
        @if(!empty($leagues))
        <form method="GET" action="{{ route('top-scorers.index') }}">
            <input type="hidden" name="metric" value="{{ $metric }}">
            <select name="league" class="ts-select" onchange="this.form.submit()">
                @foreach($leagues as $id => $name)
                    <option value="{{ $id }}" @selected($id === $leagueId)>{{ $name }}</option>
                @endforeach
            </select>
        </form>
        @endif
        <div class="ts-tabs">
            <a href="{{ route('standings.index', ['league' => $leagueId]) }}" class="ts-tab">Table</a>
            <a href="{{ route('top-scorers.index', ['league' => $leagueId, 'metric' => 'goals']) }}" class="ts-tab {{ $metric === 'goals' ? 'active' : '' }}">Goals</a>
            <a href="{{ route('top-scorers.index', ['league' => $leagueId, 'metric' => 'assists']) }}" class="ts-tab {{ $metric === 'assists' ? 'active' : '' }}">Assists</a>
        </div>
    </div>

    @if($players->isEmpty())
        <div class="ts-empty">
            <div class="ts-empty-icon">⚽</div>
            <p>No player stats available yet. These populate after the weekly player-stats fetch runs.</p>
        </div>
    @else
    <div class="ts-tablewrap">
        <table class="ts-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th class="col-player">Player</th>
                    <th>Pos</th>
                    <th>{{ $metric === 'assists' ? 'Ast' : 'Goals' }}</th>
                    <th>{{ $metric === 'assists' ? 'Goals' : 'Ast' }}</th>
                    <th>Apps</th>
                    <th>Min</th>
                    <th>Rating</th>
                </tr>
            </thead>
            <tbody>
                @foreach($players as $i => $p)
                <tr>
                    <td class="ts-rank">{{ $i + 1 }}</td>
                    <td class="col-player">
                        <div class="ts-player">
                            @if($p->player_photo)<img src="{{ $p->player_photo }}" alt="" loading="lazy">@endif
                            <span>
                                <span class="ts-pname">{{ $p->player_name }}</span>
                                <span class="ts-pteam">{{ $p->team_name }}</span>
                            </span>
                        </div>
                    </td>
                    <td>{{ $p->position ?? 'N/A' }}</td>
                    <td class="ts-big">{{ $metric === 'assists' ? $p->assists : $p->goals }}</td>
                    <td>{{ $metric === 'assists' ? $p->goals : $p->assists }}</td>
                    <td>{{ $p->appearances }}</td>
                    <td>{{ $p->minutes }}</td>
                    <td>{{ $p->rating ? number_format($p->rating, 2) : 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
