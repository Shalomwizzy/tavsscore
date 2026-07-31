@extends('layouts.admin')
@section('title', 'Matches')
@section('page-title', 'Matches')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">⚽ Matches Database</span>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.matches.check-past-results') }}" onsubmit="return confirm('Check and settle every past Football prediction using verified results? Any match without a confirmed final score will remain pending.');">
            @csrf
            <button type="submit" class="btn-a btn-green">✓ Check past Football results</button>
        </form>
        <form method="POST" action="{{ route('admin.matches.fetch') }}">
            @csrf
            <button type="submit" class="btn-a btn-blue">🔄 Fetch from API-Football</button>
        </form>
    </div>
</div>

<div class="a-card">
    <div style="overflow-x:auto">
        <table class="a-table">
            <thead>
                <tr>
                    <th>Match</th>
                    <th>League</th>
                    <th>Country</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Kickoff</th>
                </tr>
            </thead>
            <tbody>
                @forelse($matches as $match)
                <tr>
                    <td>@include('admin.partials.fixture-mini', ['match' => $match])</td>
                    <td style="color:var(--dim); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $match->league }}</td>
                    <td style="color:var(--dim)">{{ $match->league_country }}</td>
                    <td style="color:#fff; font-weight:700; font-variant-numeric:tabular-nums;">
                        {{ $match->home_score ?? '-' }} : {{ $match->away_score ?? '-' }}
                    </td>
                    <td>
                        @if(in_array($match->status, ['1H','2H','HT','ET','BT','P','LIVE']))
                            <span class="badge badge-red">🔴 LIVE{{ $match->elapsed ? ' '.$match->elapsed."'" : '' }}</span>
                        @elseif(in_array($match->status, ['FT','AET','PEN']))
                            <span class="badge badge-gray">✅ FT</span>
                        @else
                            <span class="badge badge-blue">{{ $match->status }}</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); white-space:nowrap; font-size:.75rem;">
                        {{ $match->match_time?->format('M d, Y H:i') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="color:var(--dim); text-align:center; padding:2.5rem;">
                        No matches in database yet. Click "Fetch from API-Football" to pull today's fixtures.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($matches->hasPages())
        @include('partials.pagination', ['paginator' => $matches])
    @endif
</div>

@endsection
