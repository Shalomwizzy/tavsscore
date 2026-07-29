@extends('layouts.app')

@section('title', 'Fantasy Best XI — Premier League Dream Team | TavsScore')
@section('meta_description', "This week's data-driven Fantasy Premier League best XI: optimal squad within £100m, captain pick, and the best-value players to buy — updated weekly from player form.")
@section('og_title', 'Fantasy Best XI | TavsScore')

@push('styles')
<style>
    .fpl-wrap { padding:1.35rem 0 4rem; }
    .fpl-hero { display:grid;grid-template-columns:1.05fr .95fr;min-height:300px;overflow:hidden;margin-bottom:1rem;border:1px solid rgba(16,185,129,.25);border-radius:20px;background:radial-gradient(circle at 14% 90%,rgba(16,185,129,.18),transparent 34%),linear-gradient(135deg,#101f2d,#0b1220 70%); }
    .fpl-head { position:relative;z-index:1;display:flex;flex-direction:column;justify-content:center;padding:2.2rem; }
    .fpl-kicker { color:#86efac;font-size:.63rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase; }
    .fpl-title { max-width:520px;font-size:clamp(1.8rem,4vw,3rem);font-weight:900;color:#fff;letter-spacing:-.055em;line-height:1.03;margin:.45rem 0; }
    .fpl-sub { max-width:510px;font-size:.82rem;color:#aab8c4;line-height:1.65;margin-top:.35rem; }
    .fpl-hero-links { display:flex;gap:.55rem;flex-wrap:wrap;margin-top:1.15rem; }
    .fpl-hero-link { display:inline-flex;align-items:center;gap:.35rem;padding:.42rem .7rem;border:1px solid rgba(255,255,255,.14);border-radius:999px;color:#ecfdf5;text-decoration:none;font-size:.69rem;font-weight:800;background:rgba(255,255,255,.045); }
    .fpl-hero-link:hover { color:#fff;border-color:rgba(52,211,153,.4); }
    .fpl-visual { position:relative;min-height:260px;background:radial-gradient(circle at 50% 20%,rgba(16,185,129,.3),transparent 38%),linear-gradient(135deg,#0c5139,#0a2022);background-position:center;background-size:cover; }
    .fpl-visual::after { content:'';position:absolute;inset:0;background:linear-gradient(90deg,#101d2d,transparent 28%,rgba(5,12,18,.1)),linear-gradient(0deg,rgba(5,12,18,.42),transparent 50%); }
    .fpl-visual-copy { position:absolute;right:1.25rem;bottom:1.15rem;z-index:1;text-align:right;color:#fff; }
    .fpl-visual-copy b { display:block;font-size:1.55rem;letter-spacing:-.04em; }
    .fpl-visual-copy span { color:#bbf7d0;font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em; }

    .fpl-stats { display:grid;grid-template-columns:repeat(4,1fr);gap:.65rem;margin:1rem 0 1.2rem; }
    .fpl-stat { background:linear-gradient(145deg,rgba(255,255,255,.035),rgba(255,255,255,.01));border:1px solid rgba(148,163,184,.14);border-radius:13px;padding:.85rem .6rem;text-align:center; }
    .fpl-stat-v { font-size:1.15rem; font-weight:900; color:#fff; font-variant-numeric:tabular-nums; }
    .fpl-stat-l { font-size:.62rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; margin-top:.15rem; }

    /* The pitch */
    .pitch { position:relative;max-width:900px;margin:0 auto;border-radius:18px;overflow:hidden;
        background:
            repeating-linear-gradient(0deg, #12A150 0 44px, #0F9648 44px 88px);
        border:1px solid rgba(255,255,255,.16);box-shadow:0 22px 50px rgba(0,0,0,.4);padding:1.6rem .5rem 1.1rem; }
    /* Real FPL field lines, drawn as a crisp SVG overlay behind the players. */
    .pitch-lines { position:absolute; inset:0; width:100%; height:100%; pointer-events:none; z-index:0; }

    .fpl-row { position:relative; z-index:1; display:flex; justify-content:center; gap:clamp(.35rem,2vw,1.6rem); margin-bottom:1.1rem; flex-wrap:wrap; }

    .player { width:78px; text-align:center; }
    .player-shirt { position:relative; width:50px; height:47px; margin:0 auto .3rem; filter:drop-shadow(0 4px 6px rgba(0,0,0,.4)); }
    .player-shirt svg { width:100%; height:100%; display:block; }
    .player-cap { position:absolute; top:-6px; right:-2px; width:20px; height:20px; border-radius:50%;
        background:#fff; color:#0b1220; font-size:.62rem; font-weight:900; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,.4); z-index:2; }
    .player-cap.v { background:#94a3b8; color:#0b1220; }
    .player-name { font-size:.68rem; font-weight:700; color:#fff; background:rgba(3,7,18,.72); border-radius:5px 5px 0 0;
        padding:2px 4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .player-pts { font-size:.66rem; font-weight:800; color:#0b1220; background:#e2e8f0; border-radius:0 0 5px 5px; padding:1px 4px; }
    .player-pts .price { color:#475569; font-weight:600; }

    .bench { max-width:900px;margin:1rem auto 0;background:linear-gradient(145deg,rgba(19,29,48,.98),rgba(13,20,34,.9));border:1px solid rgba(148,163,184,.14);border-radius:15px;padding:1rem; }
    .bench-hd { font-size:.72rem; font-weight:800; color:var(--text-dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.75rem; }
    .bench-row { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }

    .buy { max-width:900px;margin:1.75rem auto 0; }
    .buy-hd { font-size:1.05rem; font-weight:900; color:#fff; margin-bottom:.2rem; }
    .buy-sub { font-size:.8rem; color:var(--text-dim); margin-bottom:.9rem; }
    .buy-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:.7rem; }
    .buy-card { display:flex;align-items:center;gap:.7rem;background:linear-gradient(145deg,rgba(255,255,255,.035),rgba(255,255,255,.01));border:1px solid var(--border);border-radius:13px;padding:.8rem .85rem;transition:transform 160ms,border-color 160ms; }
    .buy-card:hover { transform:translateY(-2px);border-color:rgba(52,211,153,.32); }
    .buy-kit { width:38px; height:36px; flex-shrink:0; filter:drop-shadow(0 2px 3px rgba(0,0,0,.35)); }
    .buy-kit svg { width:100%; height:100%; display:block; }
    .buy-name { font-weight:800; color:#fff; font-size:.85rem; }
    .buy-meta { font-size:.7rem; color:var(--text-dim); }
    .buy-val { margin-left:auto; text-align:right; }
    .buy-val-v { font-weight:900; color:#6ee7b7; font-size:.95rem; }
    .buy-val-l { font-size:.6rem; color:var(--text-dim); text-transform:uppercase; }

    .fpl-note { max-width:900px;margin:1.5rem auto 0;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);border-radius:14px;padding:1rem 1.2rem;font-size:.78rem;color:var(--text-dim);line-height:1.6; }
    .fpl-empty { max-width:520px;margin:2rem auto;text-align:center;background:var(--card);border:1px solid var(--border);border-radius:16px;padding:2.5rem 1.5rem; }
    @media(max-width:680px) { .fpl-hero{grid-template-columns:1fr}.fpl-head{padding:1.5rem}.fpl-visual{min-height:210px}.fpl-stats{grid-template-columns:repeat(2,1fr)}.player{width:62px}.player-shirt{transform:scale(.88);transform-origin:bottom center;margin-bottom:.08rem}.fpl-row{gap:.18rem;margin-bottom:.8rem}.player-name{font-size:.6rem}.player-pts{font-size:.58rem}.buy-grid{grid-template-columns:1fr}.fpl-visual-copy{right:1rem;bottom:.85rem} }
</style>
@endpush

@section('content')
@php
    // A team-kit jersey SVG coloured (and patterned) by the club: solid with
    // contrasting sleeves, vertical stripes, or a diagonal sash.
    $bodyPath = 'M17,7 L23,13 Q30,17 37,13 L43,7 L41,17 L41,50 Q30,54 19,50 L19,17 Z';
    $jersey = function (array $kit) use ($bodyPath) {
        [$body, $trim, $pattern] = array_pad($kit ?: [], 3, null);
        $body ??= '#334155'; $trim ??= '#94a3b8'; $pattern ??= 'solid';
        $b = e($body); $s = e($trim);
        // Striped/sash kits keep sleeves in the body colour so the pattern reads
        // only on the torso; solid kits get contrasting sleeves.
        $sleeve = $pattern === 'solid' ? $s : $b;
        $uid = 'k'.\Illuminate\Support\Str::random(6);

        $fill = '';
        if ($pattern === 'stripes') {
            foreach ([20.5, 27.5, 34.5] as $x) {
                $fill .= '<rect x="'.$x.'" y="6" width="3.4" height="48" fill="'.$s.'"/>';
            }
        } elseif ($pattern === 'sash') {
            $fill = '<path d="M15,15 L22,11 L45,49 L38,53 Z" fill="'.$s.'"/>';
        }

        $overlay = $fill
            ? '<g clip-path="url(#'.$uid.')" stroke="none">'.$fill.'</g>'
              .'<path d="'.$bodyPath.'" fill="none"/>'
            : '';

        return '<svg viewBox="0 0 60 58" aria-hidden="true">'
            .'<defs><clipPath id="'.$uid.'"><path d="'.$bodyPath.'"/></clipPath></defs>'
            .'<g stroke="rgba(0,0,0,.22)" stroke-width="0.7" stroke-linejoin="round">'
            .'<path d="M2,21 L17,7 L23,16 L9,29 Z" fill="'.$sleeve.'"/>'
            .'<path d="M58,21 L43,7 L37,16 L51,29 Z" fill="'.$sleeve.'"/>'
            .'<path d="'.$bodyPath.'" fill="'.$b.'"/>'
            .$overlay
            .'<path d="M23,13 Q30,17 37,13 L35,9 Q30,13 25,9 Z" fill="'.$s.'" stroke="none"/>'
            .'</g></svg>';
    };
    $shirt = function ($p) use ($jersey) {
        $cap = ($p['is_captain'] ?? false) ? '<span class="player-cap">C</span>'
             : (($p['is_vice'] ?? false) ? '<span class="player-cap v">V</span>' : '');
        $name = \Illuminate\Support\Str::of($p['name'])->afterLast(' ');
        return '<div class="player">'
            .'<div class="player-shirt">'.$jersey($p['kit'] ?? []).$cap.'</div>'
            .'<div class="player-name">'.e($name).'</div>'
            .'<div class="player-pts">'.(int)$p['points'].' <span class="price">£'.number_format($p['price'],1).'</span></div>'
            .'</div>';
    };
@endphp

<div class="wrap fpl-wrap">
    <header class="fpl-hero">
        <div class="fpl-head">
            <div class="fpl-kicker">TavsScore fantasy intelligence</div>
            <h1 class="fpl-title">Your data-led Fantasy Best XI.</h1>
            <p class="fpl-sub">A weekly Premier League squad built from player form, fixture value and budget efficiency—so every selection has a reason behind it.</p>
            <div class="fpl-hero-links"><a href="#best-xi" class="fpl-hero-link">View this week’s XI ↓</a><a href="#players-to-buy" class="fpl-hero-link">Players to buy →</a></div>
        </div>
        <div class="fpl-visual" @if($fantasyHero) style="background-image:url('{{ asset($fantasyHero) }}')" @endif><div class="fpl-visual-copy"><span>Premier League</span><b>Best XI</b></div></div>
    </header>

    @if(! $squad)
        <div class="fpl-empty">
            <div style="font-size:2.5rem;">🧤</div>
            <div style="font-weight:800; color:#fff; margin:.5rem 0;">Squad not built yet</div>
            <p style="font-size:.85rem; color:var(--text-dim);">We build the Fantasy best XI once player stats are in for the season. Check back after the first gameweek.</p>
        </div>
    @else
        @php
            $xi = collect($squad->starting_xi);
            $rows = ['GK'=>$xi->where('position','GK'), 'DEF'=>$xi->where('position','DEF'), 'MID'=>$xi->where('position','MID'), 'FWD'=>$xi->where('position','FWD')];
        @endphp

        <div class="fpl-stats">
            <div class="fpl-stat"><div class="fpl-stat-v">{{ $squad->formation }}</div><div class="fpl-stat-l">Formation</div></div>
            <div class="fpl-stat"><div class="fpl-stat-v">£{{ number_format($squad->budget_used,1) }}m</div><div class="fpl-stat-l">Squad value</div></div>
            <div class="fpl-stat"><div class="fpl-stat-v">{{ $squad->total_points }}</div><div class="fpl-stat-l">Proj. points</div></div>
            <div class="fpl-stat"><div class="fpl-stat-v" style="font-size:.82rem;">{{ \Illuminate\Support\Str::of($squad->captain)->afterLast(' ') }}</div><div class="fpl-stat-l">Captain (C)</div></div>
        </div>

        <div class="pitch" id="best-xi">
            <svg class="pitch-lines" viewBox="0 0 100 140" preserveAspectRatio="none" aria-hidden="true">
                <g fill="none" stroke="rgba(255,255,255,.4)" stroke-width="0.5">
                    <rect x="2" y="2" width="96" height="136" rx="1"/>
                    {{-- Top penalty box, six-yard box, spot + D arc (the goal end) --}}
                    <rect x="21" y="2" width="58" height="22"/>
                    <rect x="38" y="2" width="24" height="9"/>
                    <path d="M 38 24 A 15 15 0 0 0 62 24"/>
                    <circle cx="50" cy="15" r="0.7" fill="rgba(255,255,255,.4)" stroke="none"/>
                    {{-- Halfway line + centre circle at the bottom --}}
                    <line x1="2" y1="118" x2="98" y2="118"/>
                    <circle cx="50" cy="118" r="15"/>
                    <circle cx="50" cy="118" r="0.7" fill="rgba(255,255,255,.4)" stroke="none"/>
                </g>
            </svg>
            @foreach(['GK','DEF','MID','FWD'] as $line)
                <div class="fpl-row">
                    @foreach($rows[$line] as $p){!! $shirt($p) !!}@endforeach
                </div>
            @endforeach
        </div>

        @if(! empty($squad->bench))
        <div class="bench">
            <div class="bench-hd">🪑 Bench</div>
            <div class="bench-row">
                @foreach($squad->bench as $p){!! $shirt($p) !!}@endforeach
            </div>
        </div>
        @endif

        @if(! empty($squad->transfers_in))
        <div class="buy" id="players-to-buy">
            <div class="buy-hd">📈 Players to buy</div>
            <div class="buy-sub">Best value outside the XI — high points for their price this week.</div>
            <div class="buy-grid">
                @foreach($squad->transfers_in as $t)
                <div class="buy-card">
                    <div class="buy-kit">{!! $jersey($t['kit'] ?? []) !!}</div>
                    <div>
                        <div class="buy-name">{{ $t['name'] }}</div>
                        <div class="buy-meta">{{ $t['position'] }} · {{ $t['team'] }} · £{{ number_format($t['price'],1) }}m</div>
                    </div>
                    <div class="buy-val">
                        <div class="buy-val-v">{{ $t['value'] }}</div>
                        <div class="buy-val-l">value</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="fpl-note">
            💡 <strong>How it's picked:</strong> every Premier League player is scored on their season form — goals (weighted by position), assists, appearances, match rating and goalkeeper saves — then priced by value. We solve for the highest-scoring legal squad (2 GK, 5 DEF, 5 MID, 3 FWD) inside a £100m budget and max 3 per club, choose the best XI + captain, and surface the best-value players to buy. Rebuilt every week as form changes.
            <div style="margin-top:.6rem; font-size:.72rem;">Last updated {{ optional($squad->built_at)->timezone('Africa/Lagos')->diffForHumans() ?? '—' }} · {{ $squad->gameweek }}</div>
        </div>
    @endif
</div>
@endsection
