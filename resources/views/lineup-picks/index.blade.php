@extends('layouts.app')

@section('title', 'Lineup Picks | TavsScore - AI Predictions After Confirmed Lineups')
@section('meta_description', 'The most accurate football predictions on TavsScore - AI re-runs every pick after the official starting 11 is confirmed. Up to 10 picks per day.')

@push('styles')
<style>
    .lp-hero {
        padding: 2rem 0 1.5rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 2rem;
    }
    .lp-badge {
        display: inline-flex; align-items: center; gap: .4rem;
        background: linear-gradient(135deg, rgba(16,185,129,.15), rgba(16,185,129,.05));
        border: 1px solid rgba(16,185,129,.35);
        color: #6ee7b7; font-size: .7rem; font-weight: 800;
        padding: 3px 10px; border-radius: 999px; letter-spacing: .04em;
        margin-bottom: .75rem;
    }
    .lp-title { font-size: 1.5rem; font-weight: 900; color: #fff; letter-spacing: -.02em; margin-bottom: .4rem; }
    .lp-sub   { font-size: .82rem; color: var(--text-dim); line-height: 1.6; max-width: 620px; }

    .lp-how {
        display: flex; flex-wrap: wrap; gap: .5rem;
        margin-top: 1rem;
    }
    .lp-step {
        display: flex; align-items: center; gap: .4rem;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 8px; padding: .35rem .75rem;
        font-size: .72rem; color: var(--text-dim);
    }
    .lp-step strong { color: var(--text); }

    .lp-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .lp-card {
        background: var(--card);
        border: 1px solid rgba(16,185,129,.2);
        border-radius: 14px;
        padding: 1.25rem;
        transition: border-color 180ms, transform 180ms;
        position: relative;
        overflow: hidden;
    }
    .lp-card::before {
        content: '';
        position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, #10b981, #059669);
    }
    .lp-card:hover { border-color: rgba(16,185,129,.45); transform: translateY(-2px); }

    .lp-card-badge {
        display: inline-flex; align-items: center; gap: .3rem;
        background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.25);
        color: #6ee7b7; font-size: .62rem; font-weight: 800;
        padding: 2px 8px; border-radius: 999px; margin-bottom: .75rem;
        letter-spacing: .03em;
    }

    .lp-league { font-size: .68rem; color: var(--text-dim); margin-bottom: .3rem; font-weight: 600; }
    .lp-teams  { font-size: .95rem; font-weight: 800; color: #fff; margin-bottom: .75rem; line-height: 1.3; }
    .lp-teams-row { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:.55rem; }
    .lp-team-side { display:flex; align-items:center; gap:.45rem; min-width:0; }
    .lp-team-side.away { justify-content:flex-end; text-align:right; }
    .lp-team-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .lp-team-logo { width:28px; height:28px; object-fit:contain; flex-shrink:0; }
    .lp-vs     { color: var(--text-muted); font-weight: 400; font-size: .8rem; }

    .lp-tip {
        display: flex; align-items: center; justify-content: space-between;
        background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.18);
        border-radius: 8px; padding: .5rem .75rem; margin-bottom: .75rem;
    }
    .lp-tip-label { font-size: .8rem; font-weight: 800; color: #10b981; }
    .lp-tip-conf  {
        font-size: .72rem; font-weight: 800; color: #fff;
        background: rgba(16,185,129,.25); padding: 2px 8px; border-radius: 999px;
    }

    .lp-analysis {
        font-size: .75rem; color: var(--text-dim); line-height: 1.6;
        display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .lp-analysis .tip-line { color: #6ee7b7; font-weight: 600; }

    .lp-kickoff {
        font-size: .68rem; color: var(--text-muted);
        margin-top: .75rem; padding-top: .65rem;
        border-top: 1px solid var(--border);
    }

    .lp-empty {
        grid-column: 1 / -1;
        padding: 4rem 1.5rem; text-align: center;
        background: var(--card); border: 1px dashed rgba(16,185,129,.2); border-radius: 14px;
    }
    .lp-empty-icon  { font-size: 2.5rem; margin-bottom: .75rem; }
    .lp-empty-title { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: .5rem; }
    .lp-empty-sub   { font-size: .8rem; color: var(--text-dim); line-height: 1.6; max-width: 420px; margin: 0 auto; }

    .lp-empty-timeline {
        display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem;
        margin-top: 1.25rem;
    }
    .lp-tl-step {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 8px; padding: .4rem .9rem; font-size: .72rem; color: var(--text-dim);
    }
    .lp-tl-step strong { color: var(--text); }

    /* Result states */
    .lp-card.result-win  { border-color: rgba(16,185,129,.5); }
    .lp-card.result-loss { border-color: rgba(239,68,68,.4); }
    .lp-card.result-win::before  { background: linear-gradient(90deg, #10b981, #34d399); }
    .lp-card.result-loss::before { background: linear-gradient(90deg, #ef4444, #f87171); }

    .lp-result-banner {
        display: flex; align-items: center; justify-content: space-between;
        padding: .5rem .75rem; border-radius: 8px; font-weight: 800; font-size: .78rem;
        margin-bottom: .75rem;
    }
    .lp-result-banner.win  { background: rgba(16,185,129,.13); color: #6ee7b7; border: 1px solid rgba(16,185,129,.3); }
    .lp-result-banner.loss { background: rgba(239,68,68,.1);   color: #fca5a5; border: 1px solid rgba(239,68,68,.28); }
    .lp-result-score { font-size: .9rem; font-weight: 900; }

    @media (max-width: 600px) {
        .lp-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="wrap">

    {{-- Hero --}}
    <div class="lp-hero">
        <div class="lp-badge">⚡ LINEUP CONFIRMED PICKS</div>
        <h1 class="lp-title">AI Picks - After Confirmed Lineups</h1>
        <p class="lp-sub">
            These predictions are <strong>more accurate</strong> than regular picks - our AI re-analyses
            every match the moment the official starting 11 is confirmed, factoring in who's
            actually playing before giving you a recommendation.
        </p>
        <div class="lp-how">
            <div class="lp-step">1️⃣ <span><strong>Midnight</strong> - initial prediction from stats &amp; news</span></div>
            <div class="lp-step">2️⃣ <span><strong>60-75 min before kickoff</strong> - lineup published</span></div>
            <div class="lp-step">3️⃣ <span><strong>AI re-runs</strong> with actual starting 11 → appears here</span></div>
        </div>
    </div>

    @include('partials.date-nav')

    {{-- Grid --}}
    <div class="lp-grid">
        @forelse($picks as $pick)
        @php
            $match        = $pick->match;
            $tips         = is_array($pick->tips) ? $pick->tips : [];
            $topTip       = $tips[0] ?? null;
            $outcome      = $pick->predicted_outcome;
            $conf         = $pick->confidence;
            $analysis     = $pick->analysis ?? '';
            $kickoff      = $match?->match_time?->setTimezone('Africa/Lagos')->format('H:i') . ' Lagos';
            $likelyScores = is_array($pick->likely_scores) ? $pick->likely_scores : [];
            $isFt         = in_array($match?->status, ['FT', 'AET', 'PEN'], true);
            $isLive       = in_array($match?->status, ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE'], true);
            $score        = ($isFt || $isLive) && $match && $match->home_score !== null
                                ? $match->home_score . ':' . ($match->away_score ?? 0)
                                : null;
            $resultClass  = $isFt ? ($pick->was_correct ? 'result-win' : ($pick->was_correct === false ? 'result-loss' : '')) : '';
            $waText       = urlencode("⚡ TavsScore Lineup Pick\n{$match?->home_team} vs {$match?->away_team}\nTip: {$outcome}" . ($conf ? " ({$conf}%)" : '') . "\nKickoff: {$kickoff}\n🔗 " . url('/lineup-picks'));
        @endphp
        <div class="lp-card fade-up {{ $resultClass }}">
            <div class="lp-card-badge">⚡ Lineup Confirmed</div>

            {{-- Result banner for finished matches --}}
            @if($isFt && $pick->was_correct !== null)
            <div class="lp-result-banner {{ $pick->was_correct ? 'win' : 'loss' }}">
                <span>{{ $pick->was_correct ? '✅ Pick Won!' : '❌ Pick Lost' }}</span>
                @if($score)
                <span class="lp-result-score">{{ $score }}</span>
                @endif
            </div>
            @elseif($isLive && $score)
            <div class="lp-result-banner" style="background:rgba(239,68,68,.1); color:#f87171; border:1px solid rgba(239,68,68,.25); margin-bottom:.75rem;">
                <span>🔴 LIVE</span>
                <span class="lp-result-score">{{ $score }}</span>
            </div>
            @endif

            <div class="lp-league">{{ $match?->league }} · {{ $match?->league_country }}</div>
            @include('partials.fixture-showcase', ['match' => $match, 'accent' => '#fcd34d'])

            <div class="lp-tip">
                <span class="lp-tip-label">{{ $outcome }}</span>
                @if($conf)
                    <span class="lp-tip-conf">{{ $conf }}% confidence</span>
                @endif
            </div>

            @if(! empty($likelyScores))
            <div style="margin-bottom:.65rem; font-size:.68rem; color:var(--text-dim);">
                🎯 Likely scores:
                @foreach($likelyScores as $s)
                    <span style="background:rgba(255,255,255,.07); border-radius:4px; padding:1px 6px; margin-left:2px; color:var(--text);">
                        {{ $s['score'] }} <span style="opacity:.65;">({{ $s['pct'] }}%)</span>
                    </span>
                @endforeach
            </div>
            @endif

            @if($analysis)
            <div class="lp-analysis">
                @php $parts = explode('💡 Tip:', $analysis, 2); @endphp
                @if(count($parts) === 2)
                    {{ trim($parts[0]) }}
                    <span class="tip-line">💡 Tip: {{ trim($parts[1]) }}</span>
                @else
                    {{ $analysis }}
                @endif
            </div>
            @endif

            @if($topTip && isset($topTip['rationale']))
            <div style="margin-top:.6rem; font-size:.68rem; color:var(--text-muted); font-style:italic;">
                "{{ $topTip['rationale'] }}"
            </div>
            @endif

            @include('partials.match-intelligence', ['prediction' => $pick, 'accent' => '#6ee7b7'])

            <div class="lp-kickoff" style="display:flex; align-items:center; justify-content:space-between;">
                <span>🕐 Kickoff {{ $kickoff }}</span>
                <a href="https://wa.me/?text={{ $waText }}" target="_blank" rel="noopener"
                   style="display:inline-flex;align-items:center;gap:.3rem;background:#25D366;color:#fff;font-size:.65rem;font-weight:700;padding:3px 9px;border-radius:999px;text-decoration:none;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.558 4.118 1.534 5.845L.055 23.454l5.742-1.505A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.372l-.36-.214-3.71.972.989-3.615-.234-.371A9.818 9.818 0 012.182 12C2.182 6.575 6.575 2.182 12 2.182c5.424 0 9.818 4.393 9.818 9.818 0 5.424-4.394 9.818-9.818 9.818z"/></svg>
                    Share
                </a>
            </div>
        </div>
        @empty
        @if($dateMeta['is_today'] && ($offWindow['reason'] ?? null) === 'off_window')
        @include('partials.off-season-empty', ['resumeDate' => $offWindow['resume_date'] ?? null])
        @else
        <div class="lp-empty">
            @if(! $dateMeta['is_today'])
            <div class="lp-empty-icon">📁</div>
            <div class="lp-empty-title">No Lineup Picks on {{ $dateMeta['pretty'] }}</div>
            <p class="lp-empty-sub">No confirmed-lineup picks were generated for this date. Try a different day using the calendar above.</p>
            @else
            <div class="lp-empty-icon">⏳</div>
            <div class="lp-empty-title">Lineups Not Confirmed Yet</div>
            <p class="lp-empty-sub">
                Clubs publish their official starting 11 about 60 to 75 minutes before kickoff.
                Once lineups are confirmed, our AI re-analyses each match and the picks appear here automatically.
            </p>
            <div class="lp-empty-timeline">
                <div class="lp-tl-step">📋 <strong>~75 min before kickoff</strong> - lineups published</div>
                <div class="lp-tl-step">🤖 <strong>AI re-runs</strong> prediction with full squad info</div>
                <div class="lp-tl-step">🔔 <strong>Push notification</strong> sent to all subscribers</div>
            </div>
            @endif
        </div>
        @endif
        @endforelse
    </div>

    <div style="height:1.5rem;"></div>
</div>
@endsection
