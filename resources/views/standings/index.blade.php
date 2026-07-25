@extends('layouts.app')

@section('title', ($leagueName ? $leagueName.' Table' : 'League Standings').' | TavsScore')
@section('meta_description', 'Live league standings and tables — position, points, form, goals for and against across every league TavsScore covers.')
@section('og_title', 'League Standings | TavsScore')

@push('styles')
<style>
    .ls-wrap { padding: 2.5rem 0 4rem; }
    .ls-head { margin-bottom: 1.5rem; }
    .ls-title { font-size: clamp(1.6rem,4vw,2.4rem); font-weight:900; color:#fff; letter-spacing:-.02em; }
    .ls-sub   { font-size:.9rem; color:var(--text-dim); margin-top:.35rem; }

    .ls-controls { display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; margin-bottom:1.25rem; }
    .ls-select {
        background:var(--card); border:1px solid var(--border); color:var(--text);
        border-radius:10px; padding:.55rem .9rem; font-size:.85rem; font-weight:600;
        max-width:100%; cursor:pointer;
    }
    .ls-tabs { display:flex; gap:.4rem; }
    .ls-tab {
        padding:.4rem .85rem; border-radius:999px; font-size:.78rem; font-weight:700;
        border:1px solid var(--border); background:var(--card); color:var(--text-dim); text-decoration:none;
    }
    .ls-tab.active { background:var(--green-dim); border-color:var(--green-border); color:#6ee7b7; }

    .ls-tablewrap { overflow-x:auto; border:1px solid var(--border); border-radius:14px; background:var(--card); }
    table.ls-table { width:100%; border-collapse:collapse; min-width:640px; }
    .ls-table th, .ls-table td { padding:.7rem .6rem; text-align:center; font-size:.83rem; white-space:nowrap; }
    .ls-table th { color:var(--text-dim); font-weight:700; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid var(--border); }
    .ls-table td { border-bottom:1px solid rgba(255,255,255,.04); color:var(--text); }
    .ls-table tr:last-child td { border-bottom:none; }
    .ls-table .col-team { text-align:left; }
    .ls-team { display:flex; align-items:center; gap:.6rem; }
    .ls-team img { width:22px; height:22px; object-fit:contain; flex-shrink:0; }
    .ls-team span { font-weight:700; overflow:hidden; text-overflow:ellipsis; }
    .ls-rank { font-weight:800; color:var(--text-dim); width:2rem; }
    .ls-pts  { font-weight:900; color:#fff; }
    .ls-gd-pos { color:#6ee7b7; }
    .ls-gd-neg { color:#fca5a5; }

    .ls-form { display:inline-flex; gap:3px; }
    .ls-form b { width:16px; height:16px; border-radius:4px; font-size:.6rem; font-weight:800; display:inline-flex; align-items:center; justify-content:center; color:#0b0f14; }
    .form-W { background:#34d399; }
    .form-D { background:#fbbf24; }
    .form-L { background:#f87171; }

    .ls-empty { text-align:center; padding:4rem 1rem; color:var(--text-dim); }
    .ls-empty-icon { font-size:2.5rem; margin-bottom:.75rem; }

    @media (max-width:640px) {
        .ls-table th, .ls-table td { padding:.55rem .45rem; font-size:.78rem; }
    }
</style>
@endpush

@section('content')
<div class="wrap ls-wrap">
    <div class="ls-head">
        <h1 class="ls-title">League Standings</h1>
        <p class="ls-sub">{{ $leagueName ? $leagueName.' — '.$season.'/'.($season + 1) : 'Season '.$season }}</p>
    </div>

    <div class="ls-controls">
        @if(!empty($leagues))
        <form method="GET" action="{{ route('standings.index') }}">
            <select name="league" class="ls-select" onchange="this.form.submit()">
                @foreach($leagues as $id => $name)
                    <option value="{{ $id }}" @selected($id === $leagueId)>{{ $name }}</option>
                @endforeach
            </select>
        </form>
        @endif
        <div class="ls-tabs">
            <a href="{{ route('standings.index', ['league' => $leagueId]) }}" class="ls-tab active">Table</a>
            <a href="{{ route('top-scorers.index', ['league' => $leagueId]) }}" class="ls-tab">Top Scorers</a>
        </div>
    </div>

    @if($rows->isEmpty())
        <div class="ls-empty">
            <div class="ls-empty-icon">📊</div>
            <p>No standings available yet. Tables populate once the season is underway and the daily stats fetch runs.</p>
        </div>
    @else
    <div class="ls-tablewrap">
        <table class="ls-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th class="col-team">Team</th>
                    <th>P</th><th>W</th><th>D</th><th>L</th>
                    <th>GF</th><th>GA</th><th>GD</th><th>Pts</th>
                    <th>Form</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    <td class="ls-rank">{{ $row->rank ?? '—' }}</td>
                    <td class="col-team">
                        <div class="ls-team">
                            @if($row->team_logo)<img src="{{ $row->team_logo }}" alt="" loading="lazy">@endif
                            <span>{{ $row->team_name }}</span>
                        </div>
                    </td>
                    <td>{{ $row->played }}</td>
                    <td>{{ $row->win }}</td>
                    <td>{{ $row->draw }}</td>
                    <td>{{ $row->lose }}</td>
                    <td>{{ $row->goals_for }}</td>
                    <td>{{ $row->goals_against }}</td>
                    <td class="{{ $row->goals_diff > 0 ? 'ls-gd-pos' : ($row->goals_diff < 0 ? 'ls-gd-neg' : '') }}">
                        {{ $row->goals_diff > 0 ? '+' : '' }}{{ $row->goals_diff }}
                    </td>
                    <td class="ls-pts">{{ $row->points }}</td>
                    <td>
                        @if($row->form)
                        <span class="ls-form">
                            @foreach(str_split(substr($row->form, -5)) as $r)
                                <b class="form-{{ $r }}">{{ $r }}</b>
                            @endforeach
                        </span>
                        @else — @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
