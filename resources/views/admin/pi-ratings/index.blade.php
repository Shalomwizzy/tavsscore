@extends('layouts.admin')
@section('title', 'Pi-Ratings')
@section('page-title', 'Pi-Ratings')

@section('content')

<div class="page-hd">
    <div>
        <span class="page-hd-title">⚡ Team Pi-Ratings</span>
        <div style="font-size:.72rem;color:var(--dim);margin-top:.2rem;">
            Separate home/away skill ratings derived from every completed match. Updated live after each full-time result.
        </div>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
        <span style="font-size:.75rem;color:var(--dim);">{{ number_format($total) }} teams rated</span>
        <form method="POST" action="{{ route('admin.pi-ratings.rebuild') }}"
              onsubmit="return confirm('Truncate and rebuild ALL pi-ratings from full match history? This may take a moment.');">
            @csrf
            <button type="submit" class="btn-a btn-blue">🔄 Full Rebuild</button>
        </form>
    </div>
</div>

{{-- Top Overall --}}
<div class="a-card" style="margin-bottom:1.25rem;">
    <div style="font-weight:700;font-size:.88rem;color:#fff;margin-bottom:.875rem;">
        🏆 Top 50 Teams — Overall Rating
        <span style="font-size:.68rem;color:var(--dim);font-weight:400;margin-left:.5rem;">(average of home + away pi-rating)</span>
    </div>
    @if($topOverall->isEmpty())
        <p style="font-size:.8rem;color:var(--dim);text-align:center;padding:1.25rem;">
            No ratings yet. Run a Full Rebuild or wait for matches to complete.
        </p>
    @else
    <div style="overflow-x:auto;">
        <table class="a-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Team</th>
                    <th>Overall</th>
                    <th>Home π</th>
                    <th>Away π</th>
                    <th>Matches</th>
                    <th>Last Rated</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topOverall as $i => $r)
                @php
                    $overall = ($r->pi_home + $r->pi_away) / 2;
                    $color   = $overall > 0.15 ? '#34d399' : ($overall > 0 ? '#93c5fd' : ($overall > -0.1 ? 'var(--text)' : '#fca5a5'));
                @endphp
                <tr>
                    <td style="color:var(--dim);font-size:.72rem;">{{ $i + 1 }}</td>
                    <td style="color:#fff;font-weight:700;">{{ $r->team }}</td>
                    <td style="font-weight:800;color:{{ $color }};">{{ number_format($overall, 3) }}</td>
                    <td style="color:var(--dim);">{{ number_format($r->pi_home, 3) }}</td>
                    <td style="color:var(--dim);">{{ number_format($r->pi_away, 3) }}</td>
                    <td style="color:var(--dim);">{{ $r->matches_rated }}</td>
                    <td style="color:var(--dim);font-size:.72rem;">
                        {{ $r->last_match_at ? \Carbon\Carbon::parse($r->last_match_at)->diffForHumans() : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- Home vs Away split --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">

    {{-- Top Home --}}
    <div class="a-card">
        <div style="font-weight:700;font-size:.85rem;color:#fff;margin-bottom:.75rem;">🏠 Top 20 — Home π</div>
        <table class="a-table" style="font-size:.78rem;">
            <thead>
                <tr><th>#</th><th>Team</th><th>Home π</th><th>Matches</th></tr>
            </thead>
            <tbody>
                @foreach($topHome as $i => $r)
                <tr>
                    <td style="color:var(--dim);font-size:.7rem;">{{ $i + 1 }}</td>
                    <td style="color:#fff;font-weight:600;">{{ $r->team }}</td>
                    <td style="font-weight:800;color:#34d399;">{{ number_format($r->pi_home, 3) }}</td>
                    <td style="color:var(--dim);">{{ $r->matches_rated }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Top Away --}}
    <div class="a-card">
        <div style="font-weight:700;font-size:.85rem;color:#fff;margin-bottom:.75rem;">✈️ Top 20 — Away π</div>
        <table class="a-table" style="font-size:.78rem;">
            <thead>
                <tr><th>#</th><th>Team</th><th>Away π</th><th>Matches</th></tr>
            </thead>
            <tbody>
                @foreach($topAway as $i => $r)
                <tr>
                    <td style="color:var(--dim);font-size:.7rem;">{{ $i + 1 }}</td>
                    <td style="color:#fff;font-weight:600;">{{ $r->team }}</td>
                    <td style="font-weight:800;color:#93c5fd;">{{ number_format($r->pi_away, 3) }}</td>
                    <td style="color:var(--dim);">{{ $r->matches_rated }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>

{{-- Explainer --}}
<div class="a-card" style="font-size:.78rem;color:var(--dim);line-height:1.7;">
    <strong style="color:var(--text);">How pi-ratings work:</strong>
    Each team has a separate <em>home</em> and <em>away</em> rating. After every completed match, the rating is updated based on the actual vs expected goal-difference.
    K-factor = 0.075 · max goal-diff capped at 4.
    Positive ratings = team performs above average; negative = below.
    Source: Constantinou &amp; Fenton (2012) — pi-ratings outperform Elo for football prediction.
</div>

@endsection
