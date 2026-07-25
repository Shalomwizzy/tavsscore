@extends('layouts.app')

@php
    $title = $match->home_team . ' vs ' . $match->away_team . ' - Prediction & AI Analysis';
    $desc  = 'Win/Draw/Loss probabilities, Over 2.5 & BTTS lines, and a full AI breakdown for ' . $match->home_team . ' vs ' . $match->away_team . ' (' . \App\Support\LeagueCoverage::formatName($match->league, $match->league_country) . ').';
@endphp

@section('title', $title . ' | TavsScore')
@section('meta_description', $desc)
@section('canonical', url('/predictions/' . $match->slug))
@section('og_title', $title)
@section('og_description', $desc)

@push('styles')
<style>
    .pp-hero { padding:1.25rem 0 .25rem; }
    .pp-league { font-size:.74rem; color:var(--text-dim); font-weight:700; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; }
    .pp-title { font-size:1.55rem; font-weight:800; color:#fff; letter-spacing:-.02em; }
    .pp-meta  { font-size:.82rem; color:var(--text-dim); margin-top:.3rem; }
    .pp-meta strong { color:var(--text); }

    .pp-grid { display:grid; grid-template-columns: 1.4fr .9fr; gap:1rem; margin-top:1.25rem; }
    @media (max-width:780px) { .pp-grid { grid-template-columns: 1fr; } }

    .pp-card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.1rem 1.25rem; }
    .pp-card h2 { font-size:.95rem; color:#fff; font-weight:800; margin-bottom:.85rem; }

    .pp-verdict { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
    .pp-verdict-label { font-size:.7rem; color:var(--text-dim); font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
    .pp-verdict-value { font-size:1.4rem; font-weight:800; color:#fcd34d; margin-top:.2rem; }
    .pp-conf { font-size:.78rem; padding:.3rem .75rem; border-radius:999px; background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.28); color:#6ee7b7; font-weight:800; }

    .pp-bar { height:10px; border-radius:999px; background:rgba(255,255,255,.05); overflow:hidden; display:flex; margin:.85rem 0 .5rem; }
    .pp-bar-h { background:linear-gradient(90deg,#10b981,#06d6a0); }
    .pp-bar-d { background:#f59e0b; }
    .pp-bar-a { background:rgba(239,68,68,.65); }

    .pp-bar-labels { display:flex; justify-content:space-between; font-size:.74rem; color:var(--text-dim); font-variant-numeric:tabular-nums; }

    .pp-glchips { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.95rem; }
    .pp-glchip { padding:5px 11px; border-radius:8px; font-size:.74rem; font-weight:800; }
    .gl-yes { background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.28); color:#6ee7b7; }
    .gl-no  { background:rgba(239,68,68,.1);   border:1px solid rgba(239,68,68,.25);  color:#fca5a5; }
    .gl-mid { background:rgba(245,158,11,.1);  border:1px solid rgba(245,158,11,.25);  color:#fcd34d; }

    .pp-tip {
        margin-top:1rem; padding:.85rem 1rem; border-radius:10px;
        background:linear-gradient(135deg,rgba(245,158,11,.13),rgba(245,158,11,.05));
        border:1px solid rgba(245,158,11,.32);
        display:flex; gap:.65rem; align-items:flex-start;
    }
    .pp-tip-icon { font-size:1.05rem; }
    .pp-tip-label { font-size:.66rem; color:rgba(252,211,77,.78); font-weight:800; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.2rem; }
    .pp-tip-text  { font-size:.92rem; font-weight:800; color:#fcd34d; line-height:1.45; }

    .pp-analysis { font-size:.85rem; line-height:1.7; color:var(--text); }

    .pp-result { padding:.6rem .85rem; border-radius:8px; font-weight:800; font-size:.85rem; margin-top:.85rem; display:inline-flex; align-items:center; gap:.4rem; }
    .pp-result.win  { background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.28); color:#6ee7b7; }
    .pp-result.loss { background:rgba(239,68,68,.1);   border:1px solid rgba(239,68,68,.28);  color:#fca5a5; }

    .pp-stat-row { display:grid; grid-template-columns:1fr auto; gap:.4rem; padding:.5rem 0; border-bottom:1px solid rgba(255,255,255,.04); font-size:.82rem; }
    .pp-stat-row:last-child { border-bottom:none; }
    .pp-stat-row span:first-child { color:var(--text-dim); }
    .pp-stat-row span:last-child  { color:#fff; font-weight:700; font-variant-numeric:tabular-nums; }

    .pp-back { display:inline-flex; gap:.4rem; align-items:center; color:var(--text-dim); text-decoration:none; font-size:.78rem; font-weight:700; margin-top:1.25rem; }
    .pp-back:hover { color:var(--text); }

    @media (max-width: 480px) {
        .pp-title { font-size: 1.3rem; }
        .pp-headline-row { flex-direction: column; align-items: stretch; gap: .55rem; }
        .pp-headline-pill { align-self: flex-start; }
        .pp-headline-market { font-size: 1rem; }
        .pp-tip-row { flex-wrap: wrap; }
        .pp-consensus { display: block; margin: .35rem 0 0 !important; }
        .pp-glchips .pp-glchip { font-size: .68rem; padding: 4px 9px; }
    }

    /* Headline pick (detail page) */
    .pp-headline-card {
        background: linear-gradient(135deg, rgba(16,185,129,.10), rgba(16,185,129,.03));
        border: 1px solid rgba(16,185,129,.32);
        border-radius: 10px; padding: 1rem 1.15rem;
    }
    .pp-headline-label  { font-size:.7rem; font-weight:800; color:#6ee7b7; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.45rem; }
    .pp-headline-row    { display:flex; align-items:center; gap:.85rem; }
    .pp-headline-market { font-size:1.2rem; font-weight:900; color:#fff; line-height:1.3; }
    .pp-headline-reason { font-size:.85rem; color:var(--text-dim); line-height:1.55; margin-top:.4rem; }
    .pp-headline-pill   { font-size:1.1rem; font-weight:900; padding:8px 16px; border-radius:999px; font-variant-numeric:tabular-nums; flex-shrink:0;
                          background:rgba(16,185,129,.18); border:1px solid rgba(16,185,129,.4); color:#6ee7b7; }

    .pp-alts { margin-top:.85rem; }
    .pp-alts > summary { cursor:pointer; font-size:.78rem; font-weight:700; color:var(--text-dim); padding:.5rem 0; list-style:none; user-select:none; }
    .pp-alts > summary:hover { color:var(--text); }
    .pp-alts > summary::-webkit-details-marker { display:none; }

    /* Alternative tip rows */
    .pp-tip-row {
        display:flex; gap:.7rem; align-items:center;
        padding:.6rem .8rem; border-radius:8px;
        background:rgba(255,255,255,.025); border:1px solid var(--border);
    }
    .pp-tip-row:hover { background:rgba(255,255,255,.05); border-color:rgba(245,158,11,.25); }
    .pp-tip-market { font-size:.86rem; font-weight:700; color:#fff; }
    .pp-tip-reason { font-size:.74rem; color:var(--text-dim); line-height:1.5; margin-top:3px; }
    .pp-tip-conf-pill { font-size:.72rem; font-weight:800; padding:4px 11px; border-radius:999px; font-variant-numeric:tabular-nums; flex-shrink:0; }
    .pp-tip-conf-high   { background:rgba(16,185,129,.13); border:1px solid rgba(16,185,129,.28); color:#6ee7b7; }
    .pp-tip-conf-medium { background:rgba(245,158,11,.13); border:1px solid rgba(245,158,11,.28); color:#fcd34d; }
    .pp-tip-conf-low    { background:rgba(99,102,241,.13); border:1px solid rgba(99,102,241,.28); color:#a5b4fc; }
    .pp-tip-info        { display:inline-block; margin-left:.45rem; padding:1px 6px; border-radius:999px; font-size:.6rem; font-weight:700; background:rgba(99,102,241,.10); border:1px solid rgba(99,102,241,.25); color:#a5b4fc; vertical-align:middle; }
    .pp-consensus   { font-size:.78rem; font-weight:700; margin-top:.55rem; padding:5px 11px; border-radius:7px; display:inline-block; }
    .pp-consensus-ok   { background:rgba(16,185,129,.10); border:1px solid rgba(16,185,129,.25); color:#6ee7b7; }
    .pp-consensus-warn { background:rgba(245,158,11,.10); border:1px solid rgba(245,158,11,.28); color:#fcd34d; }

    /* Language toggle */
    .lang-toggle {
        display:inline-flex; gap:2px; margin-left:auto;
        background:rgba(255,255,255,.04); border:1px solid var(--border);
        border-radius:7px; padding:2px;
    }
    .lang-btn {
        background:transparent; border:none; cursor:pointer;
        font-family:inherit; font-size:.7rem; font-weight:700;
        padding:4px 9px; border-radius:5px; color:var(--text-dim);
        transition:background 140ms, color 140ms;
    }
    .lang-btn:hover:not(:disabled) { color:var(--text); background:rgba(255,255,255,.05); }
    .lang-btn.active { background:rgba(16,185,129,.18); color:#6ee7b7; }
    .lang-btn:disabled { opacity:.5; cursor:wait; }
</style>
@endpush

{{-- Sports event structured data --}}
@push('scripts')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "SportsEvent",
    "name": @json($match->home_team . ' vs ' . $match->away_team),
    "startDate": @json($match->match_time?->toIso8601String()),
    "sport": "Football",
    "homeTeam":  { "@type": "SportsTeam", "name": @json($match->home_team) },
    "awayTeam":  { "@type": "SportsTeam", "name": @json($match->away_team) },
    "competitor": [
        { "@type": "SportsTeam", "name": @json($match->home_team) },
        { "@type": "SportsTeam", "name": @json($match->away_team) }
    ],
    "url": @json(url('/predictions/' . $match->slug))
}
</script>
@endpush

@include('partials.language-toggle')

@section('content')
<div class="wrap">
    <header class="pp-hero">
        <div class="pp-league">{{ \App\Support\LeagueCoverage::formatName($match->league, $match->league_country) }}</div>
        <h1 class="pp-title">{{ $match->home_team }} <span style="color:var(--text-dim); font-weight:700;">vs</span> {{ $match->away_team }}</h1>
        <div class="pp-meta">
            @if(in_array($match->status, ['FT','AET','PEN']))
                Full time · <strong>{{ $match->home_score }}–{{ $match->away_score }}</strong>
            @elseif(in_array($match->status, ['1H','2H','HT','ET','BT','P','LIVE']))
                <span style="color:#ef4444;">● LIVE</span>
                @if($match->elapsed) {{ $match->elapsed }}' @endif ·
                <strong>{{ $match->home_score ?? 0 }}–{{ $match->away_score ?? 0 }}</strong>
            @else
                Kick-off <strong>{{ $match->match_time?->format('H:i') }}</strong> · {{ $match->match_time?->format('D, M j') }}
            @endif
        </div>
    </header>

    <div class="pp-grid">
        {{-- Left column: prediction summary + analysis --}}
        <div>
            <div class="pp-card">
                <div class="pp-verdict">
                    <div>
                        <div class="pp-verdict-label">📊 Our Prediction</div>
                        <div class="pp-verdict-value">{{ $pred->predicted_outcome ?? '-' }}</div>
                    </div>
                    <div class="pp-conf">{{ $confidencePct }}% confidence</div>
                </div>

                <div class="pp-bar">
                    <div class="pp-bar-h" style="width:{{ number_format($pred->home_win_prob,1) }}%"></div>
                    <div class="pp-bar-d" style="width:{{ number_format($pred->draw_prob,1) }}%"></div>
                    <div class="pp-bar-a" style="width:{{ number_format($pred->away_win_prob,1) }}%"></div>
                </div>
                <div class="pp-bar-labels">
                    <span>{{ $match->home_team }} {{ number_format($pred->home_win_prob,1) }}%</span>
                    <span>Draw {{ number_format($pred->draw_prob,1) }}%</span>
                    <span>{{ $match->away_team }} {{ number_format($pred->away_win_prob,1) }}%</span>
                </div>

                @php
                    $o25 = (float) ($pred->over_25_prob ?? 0);
                    $btts = (float) ($pred->btts_prob ?? 0);
                    $o25Class  = $o25 >= 60 ? 'gl-yes' : ($o25 <= 40 ? 'gl-no' : 'gl-mid');
                    $bttsClass = $btts >= 60 ? 'gl-yes' : ($btts <= 40 ? 'gl-no' : 'gl-mid');
                @endphp
                <div class="pp-glchips">
                    <span class="pp-glchip {{ $o25Class }}">Over 2.5 - {{ number_format($o25,0) }}%</span>
                    <span class="pp-glchip {{ $bttsClass }}">BTTS - {{ number_format($btts,0) }}%</span>
                </div>

                @if($pred->was_correct === true)
                    <div class="pp-result win">✓ Pick was correct ({{ $match->home_score }}–{{ $match->away_score }})</div>
                @elseif($pred->was_correct === false)
                    <div class="pp-result loss">✗ Pick missed ({{ $match->home_score }}–{{ $match->away_score }})</div>
                @endif
            </div>

            @if(!empty($pred->tips))
            @php
                $tipsArr      = $pred->tips;
                $headline     = $tipsArr[0];
                $alternatives = array_slice($tipsArr, 1);
                $hConf        = (int) ($headline['confidence'] ?? 0);
                $hClass       = $hConf >= 70 ? 'pp-tip-conf-high' : ($hConf >= 60 ? 'pp-tip-conf-medium' : 'pp-tip-conf-low');
                $hVerifiable  = ! array_key_exists('verifiable', $headline) || $headline['verifiable'] === true;
            @endphp
            <div class="pp-card" style="margin-top:1rem;">
                <div class="pp-headline-card">
                    <div class="pp-headline-label">🎯 Best Pick</div>
                    <div class="pp-headline-row">
                        <div style="min-width:0; flex:1;">
                            <div class="pp-headline-market">
                                {{ $headline['market'] ?? '-' }}
                                @unless($hVerifiable)
                                <span class="pp-tip-info" title="Corners/cards aren't auto-verified - informational only">ℹ️ Info</span>
                                @endunless
                            </div>
                            @if(!empty($headline['rationale']))
                            <div class="pp-headline-reason">{{ $headline['rationale'] }}</div>
                            @endif
                            @if(array_key_exists('market_implied', $headline))
                            <div class="pp-consensus {{ ($headline['market_agrees'] ?? false) ? 'pp-consensus-ok' : 'pp-consensus-warn' }}">
                                {{ ($headline['market_agrees'] ?? false) ? '🤝 Bookmakers agree' : '⚠️ Market disagrees' }}
                                <span style="opacity:.78;">- bookies say {{ $headline['market_implied'] }}%</span>
                            </div>
                            @endif
                            @if(array_key_exists('gemini_agrees', $headline))
                            <div class="pp-consensus {{ ($headline['gemini_agrees'] ?? false) ? 'pp-consensus-ok' : 'pp-consensus-warn' }}" style="margin-left:.35rem;">
                                {{ ($headline['gemini_agrees'] ?? false) ? '🤖🤖🤖 All 3 AIs agree' : '⚠️ AIs disagree' }}
                            </div>
                            @endif
                        </div>
                        <span class="pp-headline-pill {{ $hClass }}">{{ $hConf }}%</span>
                    </div>
                </div>

                @if(!empty($alternatives))
                <details class="pp-alts">
                    <summary>▸ Show {{ count($alternatives) }} alternative{{ count($alternatives) !== 1 ? 's' : '' }}</summary>
                    @foreach($alternatives as $rec)
                    @php
                        $conf = (int) ($rec['confidence'] ?? 0);
                        $confClass = $conf >= 70 ? 'pp-tip-conf-high' : ($conf >= 60 ? 'pp-tip-conf-medium' : 'pp-tip-conf-low');
                        $verifiable = ! array_key_exists('verifiable', $rec) || $rec['verifiable'] === true;
                    @endphp
                    <div class="pp-tip-row" style="margin-top:.55rem;">
                        <div style="min-width:0; flex:1;">
                            <div class="pp-tip-market">
                                {{ $rec['market'] ?? '-' }}
                                @unless($verifiable)<span class="pp-tip-info" title="Informational only">ℹ️</span>@endunless
                            </div>
                            @if(!empty($rec['rationale']))
                            <div class="pp-tip-reason">{{ $rec['rationale'] }}</div>
                            @endif
                        </div>
                        <span class="pp-tip-conf-pill {{ $confClass }}">{{ $conf }}%</span>
                    </div>
                    @endforeach
                </details>
                @endif
            </div>
            @endif

            @if($isAi && !blank($body))
            <div class="pp-card" style="margin-top:1rem;"
                 id="pp-analysis-section"
                 data-pred-id="{{ $pred->id }}"
                 data-en="{{ $pred->analysis }}"
                 data-pidgin="{{ $pred->analysis_pidgin }}"
                 data-swahili="{{ $pred->analysis_swahili }}">
                <div style="display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; margin-bottom:.85rem;">
                    <h2 style="margin:0;">🤖 AI Match Analysis</h2>
                    <div class="lang-toggle" role="group" aria-label="Analysis language">
                        <button type="button" class="lang-btn active" data-lang="en">🇬🇧 EN</button>
                        <button type="button" class="lang-btn" data-lang="pidgin">🇳🇬 Pidgin</button>
                        <button type="button" class="lang-btn" data-lang="swahili">🇰🇪 Swahili</button>
                    </div>
                </div>
                <div class="pp-analysis" data-role="body">{{ $body }}</div>
                @if($tip)
                <div class="pp-tip">
                    <span class="pp-tip-icon">💡</span>
                    <div>
                        <div class="pp-tip-label">Top Tip</div>
                        <div class="pp-tip-text" data-role="tip">{{ ucfirst($tip) }}</div>
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- Right column: numbers --}}
        <div>
            <div class="pp-card">
                <h2>📈 Probability Breakdown</h2>
                <div class="pp-stat-row"><span>{{ $match->home_team }} win</span><span>{{ number_format($pred->home_win_prob,1) }}%</span></div>
                <div class="pp-stat-row"><span>Draw</span><span>{{ number_format($pred->draw_prob,1) }}%</span></div>
                <div class="pp-stat-row"><span>{{ $match->away_team }} win</span><span>{{ number_format($pred->away_win_prob,1) }}%</span></div>
                <div class="pp-stat-row"><span>Over 1.5 goals</span><span>{{ number_format($pred->over_15_prob ?? 0,1) }}%</span></div>
                <div class="pp-stat-row"><span>Over 2.5 goals</span><span>{{ number_format($o25,1) }}%</span></div>
                <div class="pp-stat-row"><span>Over 3.5 goals</span><span>{{ number_format($pred->over_35_prob ?? 0,1) }}%</span></div>
                <div class="pp-stat-row"><span>Both teams to score</span><span>{{ number_format($btts,1) }}%</span></div>
            </div>

            @if(!empty($pred->market_board))
            <div class="pp-card" style="margin-top:1rem;">
                <h2>🎯 Full Market Board</h2>
                <p style="font-size:.78rem; color:var(--text-dim); margin:-.3rem 0 .9rem;">
                    Our model's probability across every market, ranked most likely first.
                </p>
                @php
                    $board = $pred->market_board;
                    arsort($board);
                    $best = array_slice(array_filter($board, fn ($p) => $p <= 92), 0, 3, true);
                @endphp
                @if(!empty($best))
                <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem;">
                    @foreach($best as $label => $prob)
                    <div style="background:var(--green-dim); border:1px solid var(--green-border); border-radius:10px; padding:.5rem .8rem;">
                        <div style="font-size:.68rem; color:var(--text-dim);">{{ $label }}</div>
                        <div style="font-weight:800; color:#6ee7b7;">{{ number_format($prob,1) }}%</div>
                    </div>
                    @endforeach
                </div>
                @endif
                <div style="max-height:340px; overflow-y:auto;">
                    @foreach($board as $label => $prob)
                    <div class="pp-stat-row"><span>{{ $label }}</span><span>{{ number_format($prob,1) }}%</span></div>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($injuries) && $injuries->isNotEmpty())
            <div class="pp-card" style="margin-top:1rem;">
                <h2>🏥 Injuries & Suspensions</h2>
                @foreach($injuries as $team => $rows)
                <div style="margin-bottom:.6rem;">
                    <div style="font-weight:700; color:var(--text); font-size:.85rem; margin-bottom:.25rem;">{{ $team }}</div>
                    @foreach($rows as $i)
                    <div class="pp-stat-row" style="font-size:.8rem;">
                        <span>{{ $i->player_name }}</span>
                        <span style="color:#fca5a5;">{{ $i->reason ?? $i->type ?? 'Out' }}</span>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
            @endif

            @if(!empty($apiPrediction))
            <div class="pp-card" style="margin-top:1rem;">
                <h2>🔎 API-Football Model</h2>
                <p style="font-size:.78rem; color:var(--text-dim); margin:-.3rem 0 .8rem;">An independent statistical model, shown for comparison.</p>
                @if($apiPrediction->percent_home || $apiPrediction->percent_draw || $apiPrediction->percent_away)
                <div class="pp-stat-row"><span>{{ $match->home_team }} win</span><span>{{ $apiPrediction->percent_home ?? '—' }}</span></div>
                <div class="pp-stat-row"><span>Draw</span><span>{{ $apiPrediction->percent_draw ?? '—' }}</span></div>
                <div class="pp-stat-row"><span>{{ $match->away_team }} win</span><span>{{ $apiPrediction->percent_away ?? '—' }}</span></div>
                @endif
                @if($apiPrediction->advice)
                <div class="pp-stat-row"><span>Advice</span><span style="color:#6ee7b7;">{{ $apiPrediction->advice }}</span></div>
                @endif
            </div>
            @endif

            <div class="pp-card" style="margin-top:1rem; font-size:.78rem; color:var(--text-dim); line-height:1.6;">
                <strong style="color:var(--text);">How we predict:</strong>
                Probabilities come from a Poisson goal-expectation model fed by recent form, attack/defence ratings and home advantage. The AI summary is generated from the same numerical inputs and recent context.
            </div>
        </div>
    </div>

    <a href="{{ route('predictions.index') }}" class="pp-back">← All predictions</a>

    <div style="height:2rem"></div>
</div>
@endsection
