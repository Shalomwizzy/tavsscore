@extends('layouts.app')

@section('title', 'TavsScore | AI Football Predictions, Live Scores & Daily Picks')
@section('meta_description', 'AI-powered football predictions across 100+ markets, verified daily. Today\'s pick of the day, live scores, the rollover challenge and goalscorer tips — all on TavsScore.')
@section('og_title', 'TavsScore — Football, called before kickoff')

@push('styles')
<style>
    .hm {
        --acc: #10b981; --mint: #6ee7b7; --ground: #080b0f; --panel: #121a23; --panel2: #16212c;
        --line: rgba(255,255,255,.08); --line2: rgba(255,255,255,.15); --ink: #eaf1f6; --mute: #8b98a5;
        --accdim: rgba(16,185,129,.13); --accbrd: rgba(16,185,129,.30); --live: #f5484b; --amber: #f59e0b;
        --mono: ui-monospace, "SF Mono", "JetBrains Mono", Menlo, Consolas, monospace;
        background: var(--ground); color: var(--ink); overflow-x: hidden;
    }
    .hm .wrap { max-width: 1180px; margin: 0 auto; padding: 0 1.5rem; }

    /* Hero */
    .hm-hero { position: relative; padding: 5rem 0 3.5rem; overflow: hidden; }
    .hm-flood { position: absolute; inset: -30% -10% auto -10%; height: 130%; z-index: 0; pointer-events: none;
        background:
          radial-gradient(52% 46% at 20% 6%, rgba(16,185,129,.22), transparent 70%),
          radial-gradient(46% 50% at 90% 16%, rgba(59,130,246,.13), transparent 72%),
          radial-gradient(60% 55% at 60% 100%, rgba(16,185,129,.10), transparent 70%);
        animation: hm-drift 18s ease-in-out infinite alternate; }
    @keyframes hm-drift { from { transform: translate3d(-2%,-1%,0) scale(1); } to { transform: translate3d(3%,2%,0) scale(1.08); } }
    .hm-lines { position: absolute; inset: 0; z-index: 0; pointer-events: none; opacity: .5;
        background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px); background-size: 100% 46px;
        -webkit-mask-image: linear-gradient(to bottom, transparent, #000 12%, #000 58%, transparent);
        mask-image: linear-gradient(to bottom, transparent, #000 12%, #000 58%, transparent); }
    .hm-hgrid { position: relative; z-index: 1; display: grid; grid-template-columns: 1.05fr .95fr; gap: 3rem; align-items: center; }
    .hm-hero-image { position:absolute; inset:0; z-index:0; background-size:cover; background-position:center; opacity:.46; filter:saturate(.85) contrast(1.08); }
    .hm-hero-image:after { content:""; position:absolute; inset:0; background:linear-gradient(90deg,#080b0f 4%,rgba(8,11,15,.72) 48%,rgba(8,11,15,.16)),linear-gradient(0deg,#080b0f 0%,transparent 45%); }
    @media (max-width: 900px) { .hm-hgrid { grid-template-columns: 1fr; gap: 2.25rem; } .hm-hero { padding: 3rem 0 2.25rem; } }

    .hm-eyebrow { display: inline-flex; align-items: center; gap: .5rem; font-size: .71rem; font-weight: 700;
        letter-spacing: .15em; text-transform: uppercase; color: var(--mint);
        background: var(--accdim); border: 1px solid var(--accbrd); padding: .35rem .8rem; border-radius: 999px; }
    .hm-pdot { width: 7px; height: 7px; border-radius: 50%; background: var(--acc); animation: hm-pulse 2s infinite; }
    @keyframes hm-pulse { 0%{box-shadow:0 0 0 0 rgba(16,185,129,.55);} 70%{box-shadow:0 0 0 9px rgba(16,185,129,0);} 100%{box-shadow:0 0 0 0 rgba(16,185,129,0);} }
    .hm-title { font-size: clamp(2.5rem,6vw,4.2rem); font-weight: 850; line-height: 1.02; letter-spacing: -.035em; margin: 1.25rem 0 1.1rem; text-wrap: balance; }
    .hm-title .g { background: linear-gradient(100deg, var(--mint), var(--acc) 60%, #38bdf8); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .hm-sub { font-size: 1.05rem; color: var(--mute); max-width: 30rem; line-height: 1.7; margin-bottom: 1.9rem; }
    .hm-ctas { display: flex; gap: .8rem; flex-wrap: wrap; align-items: center; }
    .hm-btn { font-weight: 750; font-size: .95rem; padding: .85rem 1.5rem; border-radius: 12px;
        background: linear-gradient(135deg, var(--acc), #059669); color: #052018;
        box-shadow: 0 8px 30px rgba(16,185,129,.35); transition: transform .16s, box-shadow .16s; }
    .hm-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(16,185,129,.5); color: #052018; }
    .hm-ghost { font-weight: 650; font-size: .92rem; padding: .85rem 1.1rem; color: var(--ink); }
    .hm-ghost:hover { color: var(--mint); }

    /* Pick of the day */
    .hm-pick { position: relative; background: linear-gradient(180deg, var(--panel2), var(--panel));
        border: 1px solid var(--line2); border-radius: 22px; padding: 1.5rem;
        box-shadow: 0 30px 70px -30px rgba(0,0,0,.85), inset 0 1px 0 rgba(255,255,255,.05); }
    .hm-pick-head { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.1rem; }
    .hm-pick-tag { font-size: .67rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--mint); }
    .hm-pick-league { font-size: .7rem; color: var(--mute); font-family: var(--mono); text-align: right; }
    .hm-teams { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.3rem; }
    .hm-team { display: flex; flex-direction: column; align-items: center; gap: .5rem; flex: 1; min-width: 0; }
    .hm-crest { width: 46px; height: 46px; border-radius: 50%; display: grid; place-items: center; overflow:hidden; font-weight: 800; font-size: .95rem;
        background: radial-gradient(circle at 30% 25%, #223040, #0e1620); border: 1px solid var(--line); }
    .hm-crest img { width:82%; height:82%; object-fit:contain; }
    .hm-tname { font-size: .8rem; font-weight: 700; text-align: center; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .hm-vs { font-family: var(--mono); font-size: .7rem; color: var(--mute); }
    .hm-pickbody { display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: center;
        padding: 1.1rem; background: rgba(0,0,0,.28); border: 1px solid var(--line); border-radius: 14px; }
    .hm-tiplabel { font-size: .66rem; color: var(--mute); text-transform: uppercase; letter-spacing: .08em; margin-bottom: .3rem; }
    .hm-tip { font-size: 1.3rem; font-weight: 800; letter-spacing: -.02em; line-height: 1.15; }
    .hm-tipmeta { margin-top: .5rem; font-size: .73rem; color: var(--mint); }
    .hm-ring { position: relative; width: 78px; height: 78px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; }
    .hm-ring::before { content: ""; position: absolute; width: 60px; height: 60px; border-radius: 50%; background: var(--panel); }
    .hm-ring b { position: relative; font-family: var(--mono); font-weight: 700; font-size: 1.15rem; }
    .hm-pickfoot { display: flex; align-items: center; justify-content: space-between; margin-top: 1.1rem; font-size: .72rem; color: var(--mute); flex-wrap: wrap; gap: .5rem; }
    .hm-cons { display: inline-flex; gap: .3rem; align-items: center; }
    .hm-aidot { width: 8px; height: 8px; border-radius: 50%; background: var(--acc); box-shadow: 0 0 8px rgba(16,185,129,.6); }
    .hm-pick-empty { text-align: center; padding: 2rem 1rem; color: var(--mute); }
    .hm-pick-empty .i { font-size: 2rem; margin-bottom: .5rem; }

    /* Scoreboard */
    .hm-sb { position: relative; z-index: 1; margin-top: 3.25rem; display: grid; grid-template-columns: repeat(4,1fr); gap: 1px;
        background: var(--line); border: 1px solid var(--line); border-radius: 16px; overflow: hidden; }
    .hm-sbcell { background: #0b1117; padding: 1.25rem 1rem; text-align: center; }
    .hm-sbnum { font-family: var(--mono); font-size: clamp(1.5rem,4vw,2.3rem); font-weight: 700; letter-spacing: -.02em; font-variant-numeric: tabular-nums; color: var(--mint); }
    .hm-sblabel { font-size: .66rem; color: var(--mute); text-transform: uppercase; letter-spacing: .1em; margin-top: .3rem; }
    @media (max-width: 620px) { .hm-sb { grid-template-columns: repeat(2,1fr); } }

    /* Slate ticker */
    .hm-slate { position: relative; z-index: 1; margin-top: 1rem; display: flex; gap: .6rem; overflow-x: auto; padding-bottom: .4rem; scrollbar-width: none; }
    .hm-slate::-webkit-scrollbar { display: none; }
    .hm-chip { flex: 0 0 auto; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: .55rem .8rem; font-size: .78rem; color: var(--mute); white-space: nowrap; }
    .hm-chip b { color: var(--ink); font-weight: 650; }
    .hm-chip .t { font-family: var(--mono); color: var(--mint); margin-right: .5rem; }

    /* Sections */
    .hm-band { padding: 4rem 0; }
    .hm-reveal { opacity: 0; transform: translateY(20px); transition: opacity .7s ease, transform .7s ease; }
    .hm-reveal.in { opacity: 1; transform: none; }
    .hm-eye { font-size: .71rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; color: var(--mute); }
    .hm-h2 { font-size: clamp(1.7rem,3.5vw,2.5rem); font-weight: 800; letter-spacing: -.03em; margin: .55rem 0 0; text-wrap: balance; }
    .hm-desc { color: var(--mute); max-width: 34rem; margin-top: .8rem; line-height: 1.7; }

    .hm-proof { display: flex; align-items: center; justify-content: space-between; gap: 2rem; flex-wrap: wrap;
        background: linear-gradient(120deg, var(--accdim), transparent 70%); border: 1px solid var(--accbrd); border-radius: 18px; padding: 2rem; }
    .hm-proofbig { font-family: var(--mono); font-weight: 700; font-size: clamp(2.3rem,6vw,3.3rem); color: var(--mint); font-variant-numeric: tabular-nums; }
    .hm-verified { display: inline-flex; align-items: center; gap: .4rem; font-size: .73rem; color: var(--mint); border: 1px solid var(--accbrd); border-radius: 999px; padding: .25rem .7rem; margin-top: .5rem; }

    .hm-props { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-top: 2.25rem; }
    @media (max-width: 900px) { .hm-props { grid-template-columns: repeat(2,1fr); } }
    @media (max-width: 520px) { .hm-props { grid-template-columns: 1fr; } }
    .hm-prop { background: var(--panel); border: 1px solid var(--line); border-radius: 16px; padding: 1.4rem; transition: transform .18s, border-color .18s; display: block; }
    .hm-prop:hover { transform: translateY(-4px); border-color: var(--accbrd); }
    .hm-propico { width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; font-size: 1.1rem; background: var(--accdim); border: 1px solid var(--accbrd); margin-bottom: .9rem; }
    .hm-prop h3 { font-size: 1rem; font-weight: 750; margin: 0 0 .4rem; letter-spacing: -.01em; color: var(--ink); }
    .hm-prop p { font-size: .83rem; color: var(--mute); margin: 0; line-height: 1.6; }
    .hm-prop .m { display: inline-block; margin-top: .8rem; font-size: .77rem; color: var(--mint); font-weight: 650; }

    .hm-roll { position: relative; overflow: hidden; background: #0b1117; border: 1px solid var(--line); border-radius: 22px; padding: 2.5rem;
        display: grid; grid-template-columns: 1.1fr .9fr; gap: 2.5rem; align-items: center; }
    .hm-roll::after { content: ""; position: absolute; right: -20%; top: -40%; width: 60%; height: 180%; background: radial-gradient(circle, rgba(16,185,129,.14), transparent 65%); pointer-events: none; }
    @media (max-width: 820px) { .hm-roll { grid-template-columns: 1fr; gap: 1.75rem; } }
    .hm-track { display: flex; gap: .4rem; margin: 1.3rem 0 .6rem; }
    .hm-td { flex: 1; height: 8px; border-radius: 4px; background: rgba(255,255,255,.08); }
    .hm-td.won { background: linear-gradient(90deg, var(--acc), var(--mint)); box-shadow: 0 0 10px rgba(16,185,129,.5); }
    .hm-td.today { background: rgba(245,158,11,.55); box-shadow: 0 0 10px rgba(245,158,11,.5); }
    .hm-rollbig { font-family: var(--mono); font-variant-numeric: tabular-nums; font-size: 3.4rem; font-weight: 700; color: var(--mint); }

    .hm-explore { display: grid; grid-template-columns: repeat(4,1fr); gap: .7rem; margin-top: 1.8rem; }
    @media (max-width: 820px) { .hm-explore { grid-template-columns: repeat(2,1fr); } }
    .hm-ex { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: .9rem 1rem; display: flex; align-items: center; gap: .6rem; font-size: .85rem; font-weight: 650; color: var(--mute); transition: color .15s, border-color .15s; }
    .hm-ex:hover { color: var(--ink); border-color: var(--line2); }

    .hm-footcta { text-align: center; padding: 5rem 0; position: relative; overflow: hidden; }
    .hm-footcta h2 { font-size: clamp(2rem,5vw,3.1rem); font-weight: 850; letter-spacing: -.035em; text-wrap: balance; margin: 0 0 1rem; }
    .hm-footcta p { color: var(--mute); max-width: 30rem; margin: 0 auto 2rem; }

    /* Editorial layers for the cinematic homepage experience. */
    .hm-signal-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-top:1.7rem; }
    .hm-signal,.hm-signal:visited,.hm-signal:hover { color:var(--ink)!important;text-decoration:none!important;background:linear-gradient(145deg,rgba(22,33,44,.96),rgba(11,17,23,.98));border:1px solid var(--line);border-radius:16px;padding:1rem;transition:transform .18s,border-color .18s; }
    .hm-signal:hover { transform:translateY(-4px); border-color:var(--accbrd); }
    .hm-signal-top { display:flex;justify-content:space-between;align-items:center;color:var(--mute);font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em; }
    .hm-signal-teams { display:flex;align-items:center;gap:.55rem;margin:1rem 0 .85rem; }
    .hm-signal-crest { display:grid;place-items:center;width:34px;height:34px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,#285d92,#0b2545);border:1px solid rgba(147,197,253,.35);color:#fff;font-size:.6rem;font-weight:900; }
    .hm-signal-crest img { width:82%;height:82%;object-fit:contain; }
    .hm-signal-crest.away { margin-left:-.9rem;margin-top:1.1rem;background:linear-gradient(135deg,#1c785d,#093a31);border-color:rgba(110,231,183,.3); }
    .hm-signal-names { min-width:0;flex:1; }
    .hm-signal-names strong,.hm-signal-pick { color:var(--ink)!important;text-decoration:none!important; }
    .hm-signal-names span { display:block;color:var(--mute);font-size:.67rem;margin:.1rem 0; }
    .hm-signal-label { color:var(--mint);font-size:.62rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em; }
    .hm-signal-pick { font-size:.95rem;font-weight:800;margin:.22rem 0 .65rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .hm-signal-bar { height:5px;border-radius:99px;background:rgba(255,255,255,.09);overflow:hidden; }
    .hm-signal-bar i { display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--acc),var(--mint)); }
    .hm-signal-foot { display:flex;justify-content:space-between;align-items:center;color:var(--mute);font-size:.67rem;margin-top:.5rem; }
    .hm-signal-foot b { color:var(--mint);font-family:var(--mono);font-size:.78rem; }
    .hm-feature-band { display:grid;grid-template-columns:1fr 1fr;min-height:315px;overflow:hidden;background:var(--panel);border:1px solid var(--line);border-radius:20px; }
    .hm-feature-copy { padding:2.4rem;display:flex;flex-direction:column;justify-content:center; }
    .hm-feature-image { min-height:260px;background:radial-gradient(circle at 50% 30%,rgba(16,185,129,.24),transparent 50%),linear-gradient(135deg,#142334,#081018);background-size:cover;background-position:center; }
    .hm-feature-points { display:grid;grid-template-columns:repeat(3,1fr);gap:.65rem;margin-top:1.4rem; }
    .hm-feature-point { color:var(--mute);font-size:.66rem;line-height:1.45; }
    .hm-feature-point b { display:block;color:var(--ink);font-size:.73rem;margin-top:.25rem; }
    .hm-results-card { border:1px solid var(--line);border-radius:16px;background:var(--panel);overflow:hidden;margin-top:1.5rem; }
    .hm-results-head { display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1rem;border-bottom:1px solid var(--line); }
    .hm-results-title { font-size:.86rem;font-weight:800; }
    .hm-results-table { width:100%;border-collapse:collapse; }
    .hm-results-table th { padding:.65rem .9rem;text-align:left;color:var(--mute);font-size:.6rem;text-transform:uppercase;letter-spacing:.08em; }
    .hm-results-table td { padding:.72rem .9rem;border-top:1px solid rgba(255,255,255,.05);font-size:.74rem;color:var(--mute); }
    .hm-results-table td strong { color:var(--ink); }
    .hm-outcome { display:inline-flex;padding:.2rem .5rem;border-radius:99px;font-size:.61rem;font-weight:900; }
    .hm-outcome.win { color:var(--mint);background:var(--accdim);border:1px solid var(--accbrd); }.hm-outcome.loss { color:#fca5a5;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25); }
    .hm-tennis-band { display:grid;grid-template-columns:.9fr 1.1fr;overflow:hidden;border:1px solid var(--line);border-radius:18px;background:var(--panel); }
    .hm-tennis-image { min-height:180px;background:linear-gradient(135deg,#1f4a37,#0a1920);background-size:cover;background-position:center; }
    .hm-tennis-copy { padding:1.7rem;align-self:center; }
    .hm-news-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;margin-top:1.5rem; }
    .hm-news { border:1px solid var(--line);border-radius:12px;overflow:hidden;background:var(--panel);color:inherit;transition:transform .16s,border-color .16s; }
    .hm-news:hover { transform:translateY(-3px);border-color:var(--accbrd); }.hm-news-image { height:105px;background:linear-gradient(135deg,#173144,#0b1117);background-size:cover;background-position:center; }.hm-news-body { padding:.8rem; }.hm-news-cat { color:var(--mint);font-size:.58rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em; }.hm-news h3 { font-size:.82rem;line-height:1.4;margin:.4rem 0;color:var(--ink); }.hm-news small { color:var(--mute);font-size:.63rem; }
    @media(max-width:860px) { .hm-signal-grid{grid-template-columns:1fr}.hm-feature-band,.hm-tennis-band{grid-template-columns:1fr}.hm-news-grid{grid-template-columns:repeat(2,1fr)}.hm-feature-image{min-height:220px} }
    @media(max-width:560px) { .hm .wrap{padding:0 1rem}.hm-pick{padding:1rem}.hm-pickbody{grid-template-columns:minmax(0,1fr);padding:.85rem}.hm-ring{margin:0 auto}.hm-feature-copy{padding:1.35rem}.hm-feature-points{grid-template-columns:1fr}.hm-news-grid{grid-template-columns:1fr}.hm-results-table th:nth-child(1),.hm-results-table td:nth-child(1),.hm-results-table th:nth-child(3),.hm-results-table td:nth-child(3){display:none;} }

    @media (prefers-reduced-motion: reduce) {
        .hm-flood { animation: none; } .hm-pdot { animation: none; }
        .hm-reveal { opacity: 1; transform: none; transition: none; }
    }
</style>
@endpush

@section('content')
@php
    $confPct   = $topPick ? (int) round($topPick->confidence ?? 0) : 0;
    $tips      = $topPick && is_array($topPick->tips) ? $topPick->tips : [];
    $agreement = $tips[0]['agreement_level'] ?? null;
    $winRate   = $last7Acc ?? $overallAcc;
    $crest     = fn ($name) => strtoupper(mb_substr(preg_replace('/[^A-Za-z ]/', '', (string) $name), 0, 3));
    $initials  = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(3)->map(fn ($part) => mb_substr($part, 0, 1))->join('');
@endphp
<div class="hm">

    {{-- ── HERO ── --}}
    <header class="hm-hero">
        @if($homeMedia['hero'])<div class="hm-hero-image" style="background-image:url('{{ asset($homeMedia['hero']) }}')"></div>@endif
        <div class="hm-flood"></div>
        <div class="hm-lines"></div>
        <div class="wrap">
            <div class="hm-hgrid">
                <div>
                    <span class="hm-eyebrow"><span class="hm-pdot"></span> TavsScore Football Intelligence</span>
                    <h1 class="hm-title">Football,<br><span class="g">Read Better.</span></h1>
                    <p class="hm-sub">Live scores, modelled match signals and verified daily results — one calm, clear football experience.</p>
                    <div class="hm-ctas">
                        <a href="{{ route('predictions.index') }}" class="hm-btn">Explore today’s signals →</a>
                        <a href="{{ route('live.index') }}" class="hm-ghost">See live scores</a>
                    </div>
                </div>

                {{-- Pick of the Day --}}
                <div class="hm-pick">
                    <div class="hm-pick-head">
                        <span class="hm-pick-tag">★ Pick of the Day</span>
                        @if($topPick && $topPick->match)
                            <span class="hm-pick-league">{{ \App\Support\LeagueCoverage::formatName($topPick->match->league, $topPick->match->league_country) }}<br>{{ $topPick->match->match_time?->timezone('Africa/Lagos')->format('D H:i') }}</span>
                        @endif
                    </div>

                    @if($topPick && $topPick->match)
                        <div class="hm-teams">
                            <div class="hm-team"><div class="hm-crest">@if($topPick->match->home_team_logo)<img src="{{ $topPick->match->home_team_logo }}" alt="" loading="lazy">@else{{ $crest($topPick->match->home_team) }}@endif</div><div class="hm-tname">{{ $topPick->match->home_team }}</div></div>
                            <div class="hm-vs">VS</div>
                            <div class="hm-team"><div class="hm-crest">@if($topPick->match->away_team_logo)<img src="{{ $topPick->match->away_team_logo }}" alt="" loading="lazy">@else{{ $crest($topPick->match->away_team) }}@endif</div><div class="hm-tname">{{ $topPick->match->away_team }}</div></div>
                        </div>
                        <div class="hm-pickbody">
                            <div>
                                <div class="hm-tiplabel">Best bet</div>
                                <div class="hm-tip">{{ $topPick->predicted_outcome }}</div>
                                @if($agreement === 'strong')
                                    <div class="hm-tipmeta">◆ Strong AI consensus</div>
                                @else
                                    <div class="hm-tipmeta">◆ Confirmed by our AI panel</div>
                                @endif
                            </div>
                            <div class="hm-ring" style="background: conic-gradient(var(--mint) {{ $confPct }}%, rgba(255,255,255,.09) 0);"><b>{{ $confPct }}%</b></div>
                        </div>
                        <div class="hm-pickfoot">
                            <span class="hm-cons"><span class="hm-aidot"></span><span class="hm-aidot"></span><span class="hm-aidot"></span><span class="hm-aidot"></span> 4-model consensus</span>
                            <a href="{{ route('picks.index') }}" style="color:var(--mint); font-weight:650;">All picks →</a>
                        </div>
                    @else
                        <div class="hm-pick-empty">
                            <div class="i">🌙</div>
                            <p>Today's picks drop at <strong style="color:var(--ink);">03:00 WAT</strong>. Our AI is still crunching the fixtures — check back shortly.</p>
                            <a href="{{ route('picks.index') }}" class="hm-btn" style="margin-top:.5rem; display:inline-block;">Browse picks →</a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Scoreboard --}}
            <div class="hm-sb">
                <div class="hm-sbcell"><div class="hm-sbnum" data-count="{{ (int) round($winRate ?? 0) }}" data-suffix="%">{{ $winRate !== null ? '0%' : '—' }}</div><div class="hm-sblabel">{{ $last7Acc !== null ? '7-day win rate' : 'Win rate' }}</div></div>
                <div class="hm-sbcell"><div class="hm-sbnum" data-count="{{ (int) $liveCount }}">0</div><div class="hm-sblabel">Live now</div></div>
                <div class="hm-sbcell"><div class="hm-sbnum" data-count="{{ (int) $todayPickCount }}">0</div><div class="hm-sblabel">Picks today</div></div>
                <div class="hm-sbcell"><div class="hm-sbnum" data-count="103">0</div><div class="hm-sblabel">Markets / match</div></div>
            </div>

            {{-- Today's slate ticker --}}
            @if($upcoming->isNotEmpty())
            <div class="hm-slate">
                @foreach($upcoming as $m)
                <div class="hm-chip"><span class="t">{{ $m->match_time?->timezone('Africa/Lagos')->format('H:i') }}</span><b>{{ $m->home_team }}</b> v <b>{{ $m->away_team }}</b></div>
                @endforeach
            </div>
            @endif
        </div>
    </header>

    {{-- ── TODAY'S STRONGEST SIGNALS ── --}}
    <section class="hm-band" style="padding-top:2.2rem;">
        <div class="wrap hm-reveal">
            <div style="display:flex;justify-content:space-between;gap:1rem;align-items:end;flex-wrap:wrap;">
                <div><div class="hm-eye">Today at a glance</div><h2 class="hm-h2">Today’s strongest signals</h2></div>
                <a href="{{ route('predictions.index') }}" class="hm-ghost" style="color:var(--mint);">View all predictions →</a>
            </div>
            @if($featuredSignals->isNotEmpty())
            <div class="hm-signal-grid">
                @foreach($featuredSignals as $signal)
                    @php($match = $signal->match)
                    @continue(! $match)
                    @php($confidence = (int) round($signal->confidence ?? 0))
                    <a href="{{ route('predictions.show', $match->slug) }}" class="hm-signal">
                        <div class="hm-signal-top"><span>{{ \App\Support\LeagueCoverage::formatName($match->league, $match->league_country) }}</span><span>{{ $match->match_time?->timezone(config('app.timezone'))->format('H:i') }}</span></div>
                        <div class="hm-signal-teams"><div><span class="hm-signal-crest">@if($match->home_team_logo)<img src="{{ $match->home_team_logo }}" alt="" loading="lazy">@else{{ $initials($match->home_team) }}@endif</span><span class="hm-signal-crest away">@if($match->away_team_logo)<img src="{{ $match->away_team_logo }}" alt="" loading="lazy">@else{{ $initials($match->away_team) }}@endif</span></div><div class="hm-signal-names"><strong>{{ $match->home_team }}</strong><span>vs</span><strong>{{ $match->away_team }}</strong></div></div>
                        <div class="hm-signal-label">Model signal</div><div class="hm-signal-pick">{{ $signal->predicted_outcome }}</div>
                        <div class="hm-signal-bar"><i style="width:{{ max(0, min(100, $confidence)) }}%"></i></div><div class="hm-signal-foot"><span>{{ $signal->is_daily_pick ? '⭐ Daily shortlist' : 'Form • team news • trends' }}</span><b>{{ $confidence }}%</b></div>
                    </a>
                @endforeach
            </div>
            @else
                <div style="margin-top:1.5rem;padding:1.4rem;border:1px dashed var(--line2);border-radius:14px;color:var(--mute);">Today’s match signals are being prepared. Check back shortly.</div>
            @endif
        </div>
    </section>

    {{-- ── VERIFIED PROOF ── --}}
    @if($overallAcc !== null && $totalResolved > 0)
    <section class="hm-band">
        <div class="wrap hm-reveal">
            <div class="hm-proof">
                <div>
                    <div class="hm-eye">Verified track record</div>
                    <div class="hm-proofbig">{{ $overallAcc }}% <span style="font-size:.9rem; color:var(--mute); font-family:inherit; font-weight:600;">hit rate</span></div>
                    <span class="hm-verified">✓ {{ number_format($totalResolved) }} picks graded &amp; scored — nothing hidden</span>
                </div>
                <a href="{{ route('track-record.index') }}" class="hm-ghost" style="border:1px solid var(--line2); border-radius:12px;">See the full record →</a>
            </div>
        </div>
    </section>
    @endif

    {{-- ── VALUE PROPS ── --}}
    <section class="hm-band" style="padding-top:1rem;">
        <div class="wrap hm-reveal">
            <div class="hm-eye">What you get</div>
            <h2 class="hm-h2">Not tips. A prediction engine.</h2>
            <div class="hm-props">
                <a href="{{ route('predictions.index') }}" class="hm-prop">
                    <div class="hm-propico">🧠</div>
                    <h3>Multi-AI consensus</h3>
                    <p>Four AI models analyse each match independently, then one makes the final, reasoned call.</p>
                    <span class="m">See predictions →</span>
                </a>
                <a href="{{ route('predictions.index') }}" class="hm-prop">
                    <div class="hm-propico">🎯</div>
                    <h3>103 markets a match</h3>
                    <p>1X2, over/under, BTTS, handicaps, HT/FT, corners &amp; cards — ranked most likely.</p>
                    <span class="m">Explore markets →</span>
                </a>
                <a href="{{ route('track-record.index') }}" class="hm-prop">
                    <div class="hm-propico">📈</div>
                    <h3>Verified daily</h3>
                    <p>Every pick graded win or loss. The whole track record is public.</p>
                    <span class="m">Track record →</span>
                </a>
                <a href="{{ route('goalscorer-picks.index') }}" class="hm-prop">
                    <div class="hm-propico">⚽</div>
                    <h3>Goalscorer picks</h3>
                    <p>The most likely scorers, from player form vs the opponent's defence.</p>
                    <span class="m">Today's scorers →</span>
                </a>
            </div>
        </div>
    </section>

    {{-- ── DATA FEATURE ── --}}
    <section class="hm-band" style="padding-top:1rem;">
        <div class="wrap hm-reveal">
            <div class="hm-feature-band">
                <div class="hm-feature-copy">
                    <div class="hm-eye" style="color:var(--mint);">The TavsScore difference</div>
                    <h2 class="hm-h2">See the game<br><span style="color:var(--mint);">before kickoff.</span></h2>
                    <p class="hm-desc">Our signals connect recent performance, team availability and match patterns so every prediction is easier to understand, not just easier to follow.</p>
                    <div class="hm-feature-points"><div class="hm-feature-point">📈<b>Recent form</b>Momentum and scoring trends</div><div class="hm-feature-point">📋<b>Team news</b>Injuries, suspensions and lineups</div><div class="hm-feature-point">◌<b>Match patterns</b>Goals, styles and matchup history</div></div>
                </div>
                <div class="hm-feature-image" @if($homeMedia['feature']) style="background-image:url('{{ asset($homeMedia['feature']) }}')" @endif></div>
            </div>
        </div>
    </section>

    {{-- ── VERIFIED RESULTS ── --}}
    <section class="hm-band" style="padding-top:1rem;">
        <div class="wrap hm-reveal">
            <div class="hm-eye">Transparent performance</div><h2 class="hm-h2">Verified results, not empty claims.</h2>
            <div class="hm-results-card">
                <div class="hm-results-head"><div><div class="hm-results-title">Recent daily results</div><div style="font-size:.68rem;color:var(--mute);margin-top:.18rem;">Every resolved daily pick is recorded as won or lost.</div></div><a href="{{ route('daily-football-predictions.index') }}" class="hm-ghost" style="color:var(--mint);">View all results →</a></div>
                @if($recentResults->isNotEmpty())
                    <table class="hm-results-table"><thead><tr><th>Date</th><th>Match</th><th>Prediction</th><th>Outcome</th></tr></thead><tbody>
                    @foreach($recentResults->take(5) as $result)
                        <tr><td>{{ $result->created_at?->timezone(config('app.timezone'))->format('M j') }}</td><td><strong>{{ $result->match?->home_team ?? 'Match' }} vs {{ $result->match?->away_team ?? '' }}</strong></td><td>{{ $result->predicted_outcome }}</td><td><span class="hm-outcome {{ $result->was_correct ? 'win' : 'loss' }}">{{ $result->was_correct ? '✓ WON' : '✗ LOST' }}</span></td></tr>
                    @endforeach
                    </tbody></table>
                @else
                    <div style="padding:1.5rem;color:var(--mute);font-size:.8rem;">Verified results will appear once daily picks are settled.</div>
                @endif
            </div>
        </div>
    </section>

    {{-- ── ROLLOVER ── --}}
    <section class="hm-band" style="padding-top:1rem;">
        <div class="wrap hm-reveal">
            <div class="hm-roll">
                <div>
                    <div class="hm-eye" style="color:var(--mint);">The Rollover Challenge</div>
                    <h2 class="hm-h2" style="margin-top:.5rem;">One carefully-picked leg a day. Ten days.</h2>
                    <p class="hm-desc">Our safest, highest-conviction selection each day — every leg must clear an 80% model probability with full AI agreement.</p>
                    <div class="hm-track">
                        @for($d = 1; $d <= 10; $d++)
                            <div class="hm-td {{ $d <= $rolloverWon ? 'won' : ($d === $rolloverDay ? 'today' : '') }}"></div>
                        @endfor
                    </div>
                    <div style="font-size:.8rem; color:var(--mute);">
                        @if($rollover)
                            <span style="color:var(--mint); font-family:var(--mono);">Day {{ max(1,$rolloverDay) }} of 10</span> · {{ $rolloverWon }} {{ \Illuminate\Support\Str::plural('leg', $rolloverWon) }} landed
                        @else
                            A fresh 10-day challenge starts soon.
                        @endif
                    </div>
                </div>
                <div style="text-align:center;">
                    <div class="hm-rollbig">{{ $rollover ? max(1,$rolloverDay) : 0 }}<span style="color:var(--mute); font-size:2rem;">/10</span></div>
                    <a href="{{ route('rollover.index') }}" class="hm-btn" style="margin-top:1rem; display:inline-block;">Follow the challenge →</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ── TENNIS ── --}}
    <section class="hm-band" style="padding-top:1rem;">
        <div class="wrap hm-reveal"><div class="hm-tennis-band"><div class="hm-tennis-image" @if($homeMedia['tennis']) style="background-image:url('{{ asset($homeMedia['tennis']) }}')" @endif></div><div class="hm-tennis-copy"><div class="hm-eye" style="color:var(--mint);">Tennis</div><h2 class="hm-h2" style="font-size:2rem;">More than football.<br>Smart tennis insights.</h2><p class="hm-desc">Match previews, player form and modelled signals across ATP and WTA tours.</p><a href="{{ route('tennis.index') }}" class="hm-ghost" style="display:inline-block;border:1px solid var(--line2);border-radius:10px;margin-top:1rem;color:var(--ink);">Explore tennis →</a></div></div></div>
    </section>

    {{-- ── NEWS ── --}}
    @if($recentPosts->isNotEmpty())
    <section class="hm-band" style="padding-top:1rem;">
        <div class="wrap hm-reveal"><div style="display:flex;justify-content:space-between;align-items:end;gap:1rem;flex-wrap:wrap;"><div><div class="hm-eye">News &amp; insights</div><h2 class="hm-h2">The football conversation.</h2></div><a href="{{ route('blog.index') }}" class="hm-ghost" style="color:var(--mint);">View all articles →</a></div><div class="hm-news-grid">@foreach($recentPosts as $post)<a class="hm-news" href="{{ route('blog.show', $post->slug) }}"><div class="hm-news-image" @if($post->image_url) style="background-image:url('{{ $post->image_url }}')" @endif></div><div class="hm-news-body"><div class="hm-news-cat">{{ $post->category }}</div><h3>{{ \Illuminate\Support\Str::limit($post->title, 62) }}</h3><small>{{ $post->published_at?->format('M j, Y') }} · {{ $post->reading_time }} min read</small></div></a>@endforeach</div></div>
    </section>
    @endif

    {{-- ── EXPLORE ── --}}
    <section class="hm-band" style="padding-top:1rem;">
        <div class="wrap hm-reveal">
            <div class="hm-eye">Everything else</div>
            <h2 class="hm-h2">Explore the platform</h2>
            <div class="hm-explore">
                <a href="{{ route('live.index') }}" class="hm-ex">⚡ Live Scores</a>
                <a href="{{ route('draw-picks.index') }}" class="hm-ex">🤝 Draw Picks</a>
                <a href="{{ route('gg-picks.index') }}" class="hm-ex">⚽ GG Picks</a>
                <a href="{{ route('over25-picks.index') }}" class="hm-ex">🔥 Over 2.5</a>
                <a href="{{ route('correct-score.index') }}" class="hm-ex">🎯 Correct Score</a>
                <a href="{{ route('double-chance.index') }}" class="hm-ex">🧩 Double Chance</a>
                <a href="{{ route('standings.index') }}" class="hm-ex">🏆 Standings</a>
                <a href="{{ route('top-scorers.index') }}" class="hm-ex">👟 Top Scorers</a>
                <a href="{{ route('africa.index') }}" class="hm-ex">🌍 Africa</a>
                <a href="{{ route('blog.index') }}" class="hm-ex">📰 Blog</a>
                <a href="{{ route('winners.index') }}" class="hm-ex">🥇 Winners Wall</a>
                <a href="{{ route('stats.index') }}" class="hm-ex">📊 Stats</a>
            </div>
        </div>
    </section>

    {{-- ── FOOTER CTA ── --}}
    <section class="hm-footcta">
        <div class="hm-flood" style="opacity:.5;"></div>
        <div class="wrap hm-reveal" style="position:relative;">
            <h2>Know before kickoff.</h2>
            <p>Today's picks are already live. See what the AI engine is calling.</p>
            <a href="{{ route('picks.index') }}" class="hm-btn">See today's picks →</a>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function countUp(el) {
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        var suffix = el.getAttribute('data-suffix') || '';
        if (reduce) { el.textContent = target + suffix; return; }
        var start = null, dur = 1100;
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))) + suffix;
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            e.target.classList.add('in');
            io.unobserve(e.target);
        });
    }, { threshold: .18 });
    document.querySelectorAll('.hm-reveal').forEach(function (el) { io.observe(el); });
    document.querySelectorAll('.hm-sb [data-count]').forEach(countUp);
}());
</script>
@endpush
