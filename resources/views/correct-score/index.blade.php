@extends('layouts.app')

@section('title', 'Correct Score Predictions | TavsScore: High-Risk AI Scoreline Forecasts')
@section('meta_description', 'AI exact score forecasts using Poisson models and team data. High-risk / long-shot predictions. Best for insight and fun, not as a primary betting strategy.')
@section('og_image', asset('images/og-correct-score.jpg'))

@push('styles')
<style>
    .cs-hero {
        padding: 2rem 0 1.5rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 2rem;
    }
    .cs-badge {
        display: inline-flex; align-items: center; gap: .4rem;
        background: linear-gradient(135deg, rgba(139,92,246,.15), rgba(139,92,246,.05));
        border: 1px solid rgba(139,92,246,.35);
        color: #c4b5fd; font-size: .7rem; font-weight: 800;
        padding: 3px 10px; border-radius: 999px; letter-spacing: .04em;
        margin-bottom: .75rem;
    }
    .cs-title { font-size: 1.5rem; font-weight: 900; color: #fff; letter-spacing: -.02em; margin-bottom: .4rem; }
    .cs-sub   { font-size: .82rem; color: var(--text-dim); line-height: 1.6; max-width: 580px; }

    .cs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .cs-card {
        background: var(--card);
        border: 1px solid rgba(139,92,246,.2);
        border-radius: 14px;
        padding: 1.25rem;
        position: relative;
        overflow: hidden;
        transition: border-color 180ms, transform 180ms;
    }
    .cs-card::before {
        content: '';
        position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, #8b5cf6, #6d28d9);
    }
    .cs-card:hover { border-color: rgba(139,92,246,.45); transform: translateY(-2px); }

    .cs-league { font-size: .68rem; color: var(--text-dim); margin-bottom: .3rem; font-weight: 600; }
    .cs-teams  { font-size: .95rem; font-weight: 800; color: #fff; margin-bottom: .875rem; line-height: 1.3; }
    .cs-vs     { color: var(--text-muted); font-weight: 400; font-size: .8rem; }

    .cs-scores {
        display: flex; gap: .5rem; flex-wrap: wrap;
        margin-bottom: .875rem;
    }
    .cs-score-chip {
        display: flex; flex-direction: column; align-items: center;
        background: rgba(139,92,246,.1); border: 1px solid rgba(139,92,246,.25);
        border-radius: 10px; padding: .5rem .75rem; min-width: 64px;
        transition: background 150ms;
    }
    .cs-score-chip.top {
        background: rgba(139,92,246,.2); border-color: rgba(139,92,246,.5);
    }
    .cs-score-num  { font-size: 1.05rem; font-weight: 900; color: #c4b5fd; letter-spacing: .02em; }
    .cs-score-pct  { font-size: .62rem; color: var(--text-dim); margin-top: 1px; }

    .cs-tip {
        background: rgba(139,92,246,.08); border: 1px solid rgba(139,92,246,.18);
        border-radius: 8px; padding: .45rem .75rem;
        font-size: .75rem; color: #c4b5fd; font-weight: 700;
        margin-bottom: .75rem;
    }

    .cs-kickoff {
        font-size: .68rem; color: var(--text-muted);
        padding-top: .65rem; border-top: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
    }

    .cs-empty {
        grid-column: 1 / -1;
        padding: 4rem 1.5rem; text-align: center;
        background: var(--card); border: 1px dashed rgba(139,92,246,.2); border-radius: 14px;
    }
    .cs-empty-icon  { font-size: 2.5rem; margin-bottom: .75rem; }
    .cs-empty-title { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: .5rem; }
    .cs-empty-sub   { font-size: .8rem; color: var(--text-dim); max-width: 400px; margin: 0 auto; line-height: 1.6; }

    .cs-disclaimer {
        background: rgba(139,92,246,.07); border: 1px solid rgba(139,92,246,.18);
        border-radius: 10px; padding: .875rem 1rem;
        font-size: .74rem; color: #c4b5fd; line-height: 1.6;
        margin-bottom: 2rem;
    }

    @media (max-width: 600px) {
        .cs-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="wrap">

    {{-- Hero --}}
    <div class="cs-hero">
        <div class="cs-badge">🎯 CORRECT SCORE · HIGH RISK</div>
        <h1 class="cs-title">Correct Score Forecasts</h1>
        <p class="cs-sub">
            Our AI's most likely scorelines, derived from Poisson modelling, attack/defence ratings and recent form.
            Exact scores are the hardest market in football. Use these for insight, fun, or small-stake long shots, not as a primary strategy.
        </p>
    </div>

    @include('partials.date-nav')

    {{-- Risk disclaimer --}}
    <div class="cs-disclaimer">
        <strong style="color:#fff;">⚠️ High-Risk Market.</strong>
        Correct score is significantly harder to predict than 1X2, Over/Under, or BTTS.
        Even a strong AI model will miss exact scores regularly. That is the nature of the market.
        Use these as <strong>long-shot entertainment</strong>, not primary picks.
        For lower-risk AI picks see our
        <a href="{{ route('picks.index') }}" style="color:#c4b5fd;font-weight:700;">Daily Picks</a>,
        <a href="{{ route('gg-picks.index') }}" style="color:#c4b5fd;font-weight:700;">GG Picks</a>, or
        <a href="{{ route('draw-picks.index') }}" style="color:#c4b5fd;font-weight:700;">Draw Picks</a>.
        🔞 18+ only · Never stake what you cannot afford to lose.
    </div>

    {{-- Grid --}}
    <div class="cs-grid">
        @forelse($predictions as $pred)
        @php
            $match   = $pred->match;
            $scores  = $pred->likely_scores ?? [];
            $kickoff = $match?->match_time?->setTimezone('Africa/Lagos')->format('H:i') . ' Lagos';
            $outcome = $pred->predicted_outcome;
            $conf    = $pred->confidence;
            $waText  = urlencode("🎯 TavsScore Correct Score Prediction\n{$match?->home_team} vs {$match?->away_team}\nTop score: " . ($scores[0]['score'] ?? '') . " (" . ($scores[0]['pct'] ?? '') . "%)\nAI tip: {$outcome}\n🔗 " . url('/correct-score'));
        @endphp
        <div class="cs-card fade-up">
            <div class="cs-league">{{ $match?->league }} · {{ $match?->league_country }}</div>
            <div class="cs-teams">
                {{ $match?->home_team }}
                <span class="cs-vs">vs</span>
                {{ $match?->away_team }}
            </div>

            {{-- Likely scorelines --}}
            <div class="cs-scores">
                @foreach($scores as $i => $s)
                <div class="cs-score-chip {{ $i === 0 ? 'top' : '' }}">
                    <span class="cs-score-num">{{ $s['score'] }}</span>
                    <span class="cs-score-pct">{{ $s['pct'] }}%</span>
                </div>
                @endforeach
            </div>

            @if($outcome && $outcome !== 'Competitive Match')
            <div class="cs-tip">
                🤖 AI tip: {{ $outcome }}{{ $conf ? " · {$conf}% confidence" : '' }}
            </div>
            @endif

            <div class="cs-kickoff">
                <span>🕐 Kickoff {{ $kickoff }}</span>
                <a href="https://wa.me/?text={{ $waText }}" target="_blank" rel="noopener"
                   style="display:inline-flex;align-items:center;gap:.3rem;background:#25D366;color:#fff;font-size:.65rem;font-weight:700;padding:3px 9px;border-radius:999px;text-decoration:none;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.558 4.118 1.534 5.845L.055 23.454l5.742-1.505A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.372l-.36-.214-3.71.972.989-3.615-.234-.371A9.818 9.818 0 012.182 12C2.182 6.575 6.575 2.182 12 2.182c5.424 0 9.818 4.393 9.818 9.818 0 5.424-4.394 9.818-9.818 9.818z"/></svg>
                    Share
                </a>
            </div>
        </div>
        @empty
        <div class="cs-empty">
            <div class="cs-empty-icon">🎯</div>
            <div class="cs-empty-title">No Correct Score Predictions Yet</div>
            <p class="cs-empty-sub">
                Correct score predictions are generated automatically once our AI analyses today's matches.
                Check back after predictions are loaded for today.
            </p>
        </div>
        @endforelse
    </div>

    <div style="height:1.5rem;"></div>
</div>
@endsection
