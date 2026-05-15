@extends('layouts.app')

@section('title', 'Hall of Fame | TavsScore - Top Winners Leaderboard')
@section('meta_description', 'TavsScore Hall of Fame - see the top winners who turned our AI predictions into real money. Cumulative earnings, verified slips.')

@push('styles')
<style>
    .hof-hero {
        padding: 2rem 0 1.5rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 2rem;
    }
    .hof-badge {
        display: inline-flex; align-items: center; gap: .4rem;
        background: linear-gradient(135deg, rgba(251,191,36,.15), rgba(251,191,36,.05));
        border: 1px solid rgba(251,191,36,.35);
        color: #fcd34d; font-size: .7rem; font-weight: 800;
        padding: 3px 10px; border-radius: 999px; letter-spacing: .04em;
        margin-bottom: .75rem;
    }
    .hof-title { font-size: 1.5rem; font-weight: 900; color: #fff; letter-spacing: -.02em; margin-bottom: .4rem; }
    .hof-sub   { font-size: .82rem; color: var(--text-dim); line-height: 1.6; max-width: 560px; }

    /* Top 3 podium */
    .hof-podium {
        display: flex;
        justify-content: center;
        align-items: flex-end;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .hof-podium-card {
        background: var(--card);
        border-radius: 16px;
        padding: 1.25rem 1rem;
        text-align: center;
        position: relative;
        flex: 1;
        max-width: 200px;
        border: 1px solid var(--border);
        transition: transform 180ms;
    }
    .hof-podium-card:hover { transform: translateY(-3px); }
    .hof-podium-card.rank-1 {
        border-color: rgba(251,191,36,.5);
        background: linear-gradient(160deg, rgba(251,191,36,.08), var(--card));
        order: 2;
        padding-bottom: 1.75rem;
    }
    .hof-podium-card.rank-2 {
        border-color: rgba(156,163,175,.4);
        background: linear-gradient(160deg, rgba(156,163,175,.07), var(--card));
        order: 1;
    }
    .hof-podium-card.rank-3 {
        border-color: rgba(180,120,60,.4);
        background: linear-gradient(160deg, rgba(180,120,60,.07), var(--card));
        order: 3;
    }
    .hof-medal { font-size: 2rem; display: block; margin-bottom: .4rem; }
    .hof-pod-name { font-size: .9rem; font-weight: 800; color: #fff; margin-bottom: .3rem; word-break: break-word; }
    .hof-pod-amount {
        font-size: 1rem; font-weight: 900;
        margin-bottom: .25rem;
    }
    .rank-1 .hof-pod-amount { color: #fcd34d; }
    .rank-2 .hof-pod-amount { color: #d1d5db; }
    .rank-3 .hof-pod-amount { color: #b47c3c; }
    .hof-pod-wins { font-size: .68rem; color: var(--text-dim); }

    /* Table */
    .hof-table-wrap {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .hof-table {
        width: 100%;
        border-collapse: collapse;
        font-size: .82rem;
    }
    .hof-table thead th {
        padding: .6rem 1rem;
        text-align: left;
        font-size: .68rem;
        font-weight: 700;
        color: var(--text-dim);
        text-transform: uppercase;
        letter-spacing: .05em;
        border-bottom: 1px solid var(--border);
        background: var(--surface);
    }
    .hof-table tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background 120ms;
    }
    .hof-table tbody tr:last-child { border-bottom: none; }
    .hof-table tbody tr:hover { background: rgba(255,255,255,.03); }
    .hof-table td { padding: .75rem 1rem; vertical-align: middle; }

    .hof-rank-num {
        font-weight: 800; font-size: .8rem;
        color: var(--text-dim); width: 40px;
    }
    .hof-username { font-weight: 700; color: #fff; }
    .hof-amount   { font-weight: 800; color: #10b981; }
    .hof-wins-badge {
        display: inline-flex; align-items: center; gap: .25rem;
        background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.2);
        color: #6ee7b7; font-size: .65rem; font-weight: 700;
        padding: 2px 8px; border-radius: 999px;
    }
    .hof-last { font-size: .72rem; color: var(--text-dim); }

    .hof-empty {
        padding: 4rem 1.5rem; text-align: center;
        background: var(--card); border: 1px dashed rgba(251,191,36,.2);
        border-radius: 14px; margin-bottom: 2rem;
    }
    .hof-empty-icon  { font-size: 2.5rem; margin-bottom: .75rem; }
    .hof-empty-title { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: .5rem; }
    .hof-empty-sub   { font-size: .8rem; color: var(--text-dim); max-width: 380px; margin: 0 auto; }

    .hof-cta {
        background: linear-gradient(135deg, rgba(16,185,129,.1), rgba(16,185,129,.04));
        border: 1px solid rgba(16,185,129,.25);
        border-radius: 14px; padding: 1.5rem;
        text-align: center; margin-bottom: 2rem;
    }
    .hof-cta-title { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: .4rem; }
    .hof-cta-sub   { font-size: .8rem; color: var(--text-dim); margin-bottom: 1rem; }

    @media (max-width: 600px) {
        .hof-podium { flex-direction: column; align-items: center; }
        .hof-podium-card { max-width: 100%; width: 100%; order: unset !important; }
        .hof-table thead th:nth-child(4),
        .hof-table td:nth-child(4) { display: none; }
    }
</style>
@endpush

@section('content')
<div class="wrap">

    {{-- Hero --}}
    <div class="hof-hero">
        <div class="hof-badge">🏆 HALL OF FAME</div>
        <h1 class="hof-title">TavsScore Hall of Fame</h1>
        <p class="hof-sub">
            Real winners, verified slips. Every winning submission is reviewed by our team before it counts.
            Cumulative total across all approved submissions per username.
        </p>
    </div>

    @if($leaderboard->isEmpty())
    <div class="hof-empty">
        <div class="hof-empty-icon">🏆</div>
        <div class="hof-empty-title">No Winners Yet</div>
        <p class="hof-empty-sub">Be the first to submit your winning slip and claim the top spot.</p>
    </div>
    @else

    {{-- Top 3 Podium --}}
    @if($leaderboard->count() >= 2)
    <div class="hof-podium">
        @foreach($leaderboard->take(3) as $i => $winner)
        @php
            $rankClass = match($i) { 0 => 'rank-1', 1 => 'rank-2', 2 => 'rank-3', default => '' };
            $medal     = match($i) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => '' };
        @endphp
        <div class="hof-podium-card {{ $rankClass }}">
            <span class="hof-medal">{{ $medal }}</span>
            <div class="hof-pod-name">{{ $winner->username }}</div>
            <div class="hof-pod-amount">{{ $winner->currency }} {{ number_format($winner->total_won, 0) }}</div>
            <div class="hof-pod-wins">{{ $winner->total_wins }} {{ Str::plural('win', $winner->total_wins) }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Full leaderboard table --}}
    <div class="hof-table-wrap">
        <table class="hof-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Total Won</th>
                    <th>Wins</th>
                    <th>Last Win</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($leaderboard as $i => $winner)
                <tr class="hof-main-row" data-uid="hof-{{ $i }}" style="cursor:{{ $winner->total_wins > 1 ? 'pointer' : 'default' }};"
                    onclick="{{ $winner->total_wins > 1 ? "toggleWins('hof-{$i}')" : '' }}">
                    <td class="hof-rank-num">
                        @if($i === 0) 🥇
                        @elseif($i === 1) 🥈
                        @elseif($i === 2) 🥉
                        @else <span style="color:var(--text-dim);">#{{ $i + 1 }}</span>
                        @endif
                    </td>
                    <td class="hof-username">{{ $winner->username }}</td>
                    <td class="hof-amount">{{ $winner->currency }} {{ number_format($winner->total_won, 0) }}</td>
                    <td>
                        <span class="hof-wins-badge">🎯 {{ $winner->total_wins }} {{ Str::plural('win', $winner->total_wins) }}</span>
                    </td>
                    <td class="hof-last">{{ $winner->last_win->diffForHumans() }}</td>
                    <td style="text-align:right; width:30px;">
                        @if($winner->total_wins > 1)
                        <span id="hof-{{ $i }}-caret" style="color:var(--text-dim); font-size:.7rem; transition:transform 200ms; display:inline-block;">▼</span>
                        @endif
                    </td>
                </tr>
                @if($winner->total_wins > 0)
                <tr id="hof-{{ $i }}-detail" style="display:none;">
                    <td colspan="6" style="padding:0; background:rgba(255,255,255,.02);">
                        <div style="padding:.5rem .75rem .75rem 2.5rem; border-bottom:1px solid var(--border);">
                            <div style="font-size:.68rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.4rem;">Individual wins</div>
                            <div style="display:flex; flex-direction:column; gap:.3rem;">
                                @foreach($winner->wins as $j => $win)
                                <div style="display:flex; align-items:center; gap:.65rem; padding:.35rem .5rem; border-radius:7px; background:rgba(255,255,255,.03); font-size:.75rem;">
                                    <span style="color:#fcd34d; font-weight:800; min-width:22px;">#{{ $j + 1 }}</span>
                                    <span style="color:#6ee7b7; font-weight:800;">{{ $win->currency }} {{ number_format($win->amount, 0) }}</span>
                                    @if($win->platform)
                                    <span style="color:var(--text-dim); font-size:.68rem;">via {{ $win->platform }}</span>
                                    @endif
                                    @if($win->match)
                                    <span style="color:var(--text-dim); font-size:.68rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px;">{{ $win->match }}</span>
                                    @endif
                                    <span style="color:var(--text-dim); font-size:.65rem; margin-left:auto; white-space:nowrap;">{{ $win->date->format('M d, Y') }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>
    <script>
    function toggleWins(uid) {
        var detail = document.getElementById(uid + '-detail');
        var caret  = document.getElementById(uid + '-caret');
        if (!detail) return;
        var open = detail.style.display === 'table-row';
        detail.style.display = open ? 'none' : 'table-row';
        if (caret) caret.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
    }
    </script>
    @endif

    {{-- CTA --}}
    <div class="hof-cta">
        <div class="hof-cta-title">Won using our AI picks?</div>
        <p class="hof-cta-sub">Submit your winning slip and get on the board. Every verified win adds to your all-time total.</p>
        <a href="{{ route('winners.index') }}" class="btn" style="background:linear-gradient(135deg,#10b981,#059669); color:#fff; padding:.6rem 1.5rem; border-radius:8px; font-weight:700; font-size:.85rem; text-decoration:none; display:inline-block;">
            Submit Your Win
        </a>
    </div>

    <div style="height:1.5rem;"></div>
</div>
@endsection
