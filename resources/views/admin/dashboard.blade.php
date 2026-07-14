@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

{{-- ── API quota status banner ── --}}
@php $quotaExhausted = \Illuminate\Support\Facades\Cache::get('api_football_quota_exhausted'); @endphp
@if($quotaExhausted)
<div style="margin-bottom:1.25rem; padding:.85rem 1.1rem; border-radius:9px; background:rgba(245,158,11,.10); border:1px solid rgba(245,158,11,.28); color:#fcd34d; font-size:.82rem; line-height:1.6;">
    <strong style="color:#fff;">⚠️ API-Football daily quota hit.</strong>
    Live scores, fresh fixtures and bookmaker consensus are paused until quota resets (24h).
    Existing predictions still work; new ones may use cached/stale data. Upgrade plan or wait for reset.
</div>
@endif

{{-- Accuracy strip --}}
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));">
    <div class="stat-card" style="background:linear-gradient(135deg,rgba(245,158,11,.10),rgba(245,158,11,.04));border-color:rgba(245,158,11,.25);">
        <span class="stat-val" style="color:#fcd34d;">
            @if($stats['accuracy_7d'] !== null){{ $stats['accuracy_7d'] }}%@else-@endif
        </span>
        <span class="stat-lbl">⭐ 7-day accuracy</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#6ee7b7;">{{ $stats['correct_7d'] }}</span>
        <span class="stat-lbl">✓ Correct (7d)</span>
    </div>
    <div class="stat-card">
        <span class="stat-val" style="color:#fca5a5;">{{ $stats['wrong_7d'] }}</span>
        <span class="stat-lbl">✗ Wrong (7d)</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ $stats['picks_today'] }}/3</span>
        <span class="stat-lbl">📌 Picks today</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">
            @if($stats['accuracy_all'] !== null){{ $stats['accuracy_all'] }}%@else-@endif
        </span>
        <span class="stat-lbl">📈 All-time accuracy</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ $stats['picks_resolved'] }}</span>
        <span class="stat-lbl">🎯 Picks resolved</span>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-val">{{ $stats['live_matches'] }}</span>
        <span class="stat-lbl">🔴 Live matches</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ $stats['total_matches'] }}</span>
        <span class="stat-lbl">⚽ Total matches</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ $stats['total_predictions'] }}</span>
        <span class="stat-lbl">📊 Predictions</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ $stats['published_posts'] }}</span>
        <span class="stat-lbl">📰 Published posts</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ $stats['total_posts'] }}</span>
        <span class="stat-lbl">📝 Total posts</span>
    </div>
    <div class="stat-card">
        <span class="stat-val">{{ $stats['ai_posts'] }}</span>
        <span class="stat-lbl">🤖 AI posts</span>
    </div>
</div>

{{-- ── Prediction Engine Infrastructure (Phase 1 + 1.5 + 2) ────────── --}}
<div class="a-card" style="margin-bottom:1.25rem; background:linear-gradient(135deg, rgba(16,185,129,.06), rgba(59,130,246,.04)); border-color:rgba(16,185,129,.25);">
    <div class="page-hd" style="margin-bottom:.9rem; display:flex; align-items:center; justify-content:space-between; gap:.75rem;">
        <span style="font-weight:800; font-size:.95rem; color:#fff;">
            🧠 Prediction Engine Infrastructure
            @if($infra['dc_enabled'])
                <span style="display:inline-block; background:linear-gradient(135deg,#10b981,#3b82f6); color:#fff; font-size:.6rem; font-weight:900; padding:2px 8px; border-radius:999px; margin-left:.4rem; letter-spacing:.04em;">DC v1.0 · ACTIVE</span>
            @else
                <span style="display:inline-block; background:rgba(107,114,128,.2); color:#9ca3af; font-size:.6rem; font-weight:900; padding:2px 8px; border-radius:999px; margin-left:.4rem;">DC OFF</span>
            @endif
        </span>
        <a href="{{ route('admin.model-metrics.index') }}" class="btn-a" style="background:rgba(99,102,241,.15); border:1px solid rgba(99,102,241,.3); color:#c4b5fd; font-size:.7rem;">📊 Model Metrics →</a>
    </div>

    <div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.6rem;">
        <div class="stat-card">
            <span class="stat-val" style="color:#6ee7b7;">{{ $infra['dc_leagues_fitted'] }}<span style="font-size:.7rem;color:var(--text-dim);">/{{ $infra['dc_leagues_target'] }}</span></span>
            <span class="stat-lbl">🎯 DC leagues fitted</span>
        </div>
        <div class="stat-card">
            <span class="stat-val">{{ number_format($infra['dc_teams_persisted']) }}</span>
            <span class="stat-lbl">⚽ DC team params</span>
        </div>
        <div class="stat-card">
            <span class="stat-val" style="font-size:1.05rem;">
                @if($infra['dc_last_fit'])
                    {{ $infra['dc_last_fit']->diffForHumans() }}
                @else
                    Never
                @endif
            </span>
            <span class="stat-lbl">🔄 Last DC refit</span>
        </div>
        <div class="stat-card">
            <span class="stat-val" style="color:#c4b5fd;">{{ number_format($infra['historical_matches']) }}</span>
            <span class="stat-lbl">📚 Trained on (matches)</span>
        </div>
        <div class="stat-card">
            <span class="stat-val">{{ number_format($infra['prediction_logs']) }}</span>
            <span class="stat-lbl">📝 Prediction logs</span>
        </div>
        <div class="stat-card">
            <span class="stat-val">{{ number_format($infra['teams_tracked']) }}</span>
            <span class="stat-lbl">🏷️ Teams tracked</span>
        </div>
        <div class="stat-card" @if($infra['teams_pending_review'] > 20) style="background:rgba(251,191,36,.08); border-color:rgba(251,191,36,.28);" @endif>
            <span class="stat-val" @if($infra['teams_pending_review'] > 20) style="color:#fbbf24;" @endif>{{ number_format($infra['teams_pending_review']) }}</span>
            <span class="stat-lbl">👀 Aliases pending review</span>
        </div>
        <div class="stat-card" @if($infra['held_for_review'] > 0) style="background:rgba(239,68,68,.08); border-color:rgba(239,68,68,.28);" @endif>
            <span class="stat-val" @if($infra['held_for_review'] > 0) style="color:#fca5a5;" @endif>{{ number_format($infra['held_for_review']) }}</span>
            <span class="stat-lbl">🚫 Held for review</span>
        </div>
    </div>

    @if(count($infra['logs_by_version']) > 0)
    <div style="margin-top:1rem; padding-top:.85rem; border-top:1px solid rgba(255,255,255,.06);">
        <div style="font-size:.65rem; font-weight:800; color:var(--text-dim); letter-spacing:.05em; text-transform:uppercase; margin-bottom:.4rem;">
            Prediction logs by model version
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:.4rem;">
            @foreach($infra['logs_by_version'] as $version => $count)
                @php
                    $isDc     = str_starts_with($version, 'dc-');
                    $isMarket = $version === 'market-closing';
                @endphp
                <span style="font-size:.7rem; padding:3px 9px; border-radius:999px;
                    background:{{ $isDc ? 'rgba(16,185,129,.12)' : ($isMarket ? 'rgba(251,191,36,.10)' : 'rgba(107,114,128,.12)') }};
                    border:1px solid {{ $isDc ? 'rgba(16,185,129,.28)' : ($isMarket ? 'rgba(251,191,36,.28)' : 'rgba(107,114,128,.28)') }};
                    color:#fff; font-weight:700;">
                    {{ $version }} · <span style="color:var(--text-dim); font-weight:600;">{{ number_format($count) }}</span>
                </span>
            @endforeach
        </div>
    </div>
    @endif
</div>

{{-- Quick actions --}}
<div class="a-card" style="margin-bottom:1.25rem">
    <div class="page-hd" style="margin-bottom:.75rem">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">⚡ Quick Actions</span>
    </div>
    <div style="display:flex; flex-wrap:wrap; gap:.5rem;">
        <form method="POST" action="{{ route('admin.matches.fetch') }}">
            @csrf
            <button type="submit" class="btn-a btn-blue">⚽ Fetch Matches</button>
        </form>
        <form method="POST" action="{{ route('admin.predictions.generate') }}">
            @csrf
            <button type="submit" class="btn-a btn-green">📊 Generate Predictions</button>
        </form>
        <a href="{{ route('admin.picks') }}" class="btn-a" style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.28);color:#fcd34d;">⭐ Manage Picks</a>
        <a href="{{ route('stats.index') }}" target="_blank" class="btn-a btn-gray">📊 View Stats</a>
        <form method="POST" action="{{ route('admin.blog.auto-generate') }}">
            @csrf
            <button type="submit" class="btn-a" style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.28);color:#fcd34d;">🤖 AI Auto-Blog</button>
        </form>
        <a href="{{ route('admin.blog.create') }}" class="btn-a btn-gray">✏️ New Post</a>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">

    {{-- Recent matches --}}
    <div class="a-card">
        <div class="page-hd" style="margin-bottom:.875rem">
            <span style="font-weight:700; font-size:.9rem; color:#fff;">⚽ Recent Matches</span>
            <a href="{{ route('admin.matches') }}" style="font-size:.72rem; color:var(--dim); text-decoration:none;">View all →</a>
        </div>
        <div style="overflow-x:auto">
            <table class="a-table">
                <thead>
                    <tr>
                        <th>Match</th>
                        <th>League</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentMatches as $match)
                    <tr>
                        <td style="color:#fff; font-weight:600; white-space:nowrap;">
                            {{ $match->home_team }} {{ $match->home_score ?? '-' }} : {{ $match->away_score ?? '-' }} {{ $match->away_team }}
                        </td>
                        <td style="color:var(--dim); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ \App\Support\LeagueCoverage::formatName($match->league, $match->league_country) }}</td>
                        <td>
                            @if(in_array($match->status, ['1H','2H','HT','ET','BT','P','LIVE']))
                                <span class="badge badge-red">LIVE</span>
                            @elseif(in_array($match->status, ['FT','AET','PEN']))
                                <span class="badge badge-gray">FT</span>
                            @else
                                <span class="badge badge-blue">{{ $match->status }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="color:var(--dim); padding:1rem; text-align:center;">No matches yet. Click "Fetch Matches" above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent blog posts --}}
    <div class="a-card">
        <div class="page-hd" style="margin-bottom:.875rem">
            <span style="font-weight:700; font-size:.9rem; color:#fff;">📰 Recent Blog Posts</span>
            <a href="{{ route('admin.blog.index') }}" style="font-size:.72rem; color:var(--dim); text-decoration:none;">View all →</a>
        </div>
        <div style="overflow-x:auto">
            <table class="a-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPosts as $post)
                    <tr>
                        <td style="color:#fff; font-weight:600; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            {{ $post->title }}
                            @if($post->is_ai_generated)
                                <span class="badge badge-blue" style="margin-left:4px; font-size:.6rem">AI</span>
                            @endif
                        </td>
                        <td>
                            @if($post->is_published)
                                <span class="badge badge-green">Live</span>
                            @else
                                <span class="badge badge-gray">Draft</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.blog.edit', $post) }}" style="font-size:.72rem; color:var(--dim); text-decoration:none;">Edit</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="color:var(--dim); padding:1rem; text-align:center;">No posts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<div style="margin-top:1.25rem; padding:.875rem 1rem; border-radius:8px; background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); font-size:.75rem; color:#93c5fd;">
    <strong>🚀 Setup guide:</strong>
    1. Run <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px">php artisan fetch:matches</code> to pull today's fixtures from API-Football.
    2. Click <strong>Generate Predictions</strong> to create match predictions.
    3. Set <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px">GROQ_API_KEY</code> in .env for AI analysis &amp; auto-blog.
    4. Add <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px">* * * * * cd /path && php artisan schedule:run</code> to your server cron.
</div>

@endsection
