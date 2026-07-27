@extends('layouts.app')

@section('title', 'Fantasy Best XI — Premier League Dream Team | TavsScore')
@section('meta_description', "This week's data-driven Fantasy Premier League best XI: optimal squad within £100m, captain pick, and the best-value players to buy — updated weekly from player form.")
@section('og_title', 'Fantasy Best XI | TavsScore')

@push('styles')
<style>
    .fpl-wrap { padding: 2rem 0 4rem; }
    .fpl-head { text-align:center; margin-bottom:1.25rem; }
    .fpl-title { font-size:clamp(1.6rem,4vw,2.4rem); font-weight:900; color:#fff; letter-spacing:-.02em; }
    .fpl-sub { font-size:.9rem; color:var(--text-dim); margin-top:.4rem; }

    .fpl-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:.6rem; max-width:640px; margin:1.25rem auto; }
    .fpl-stat { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:.7rem .5rem; text-align:center; }
    .fpl-stat-v { font-size:1.15rem; font-weight:900; color:#fff; font-variant-numeric:tabular-nums; }
    .fpl-stat-l { font-size:.62rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; margin-top:.15rem; }

    /* The pitch */
    .pitch { position:relative; max-width:760px; margin:0 auto; border-radius:16px; overflow:hidden;
        background:
            repeating-linear-gradient(0deg, #12A150 0 44px, #0F9648 44px 88px);
        border:1px solid rgba(255,255,255,.12); box-shadow:0 20px 50px rgba(0,0,0,.4); padding:1.4rem .5rem 1rem; }
    .pitch::before { content:""; position:absolute; inset:0; background:
        radial-gradient(circle at 50% 0, rgba(255,255,255,.12), transparent 60%),
        linear-gradient(transparent 0, transparent calc(50% - 1px), rgba(255,255,255,.18) 50%, transparent calc(50% + 1px)); pointer-events:none; }
    .pitch::after { content:""; position:absolute; left:50%; top:calc(50% - 46px); width:92px; height:92px; transform:translateX(-50%);
        border:2px solid rgba(255,255,255,.18); border-radius:50%; pointer-events:none; }

    .fpl-row { position:relative; z-index:1; display:flex; justify-content:center; gap:clamp(.35rem,2vw,1.6rem); margin-bottom:1.1rem; flex-wrap:wrap; }

    .player { width:78px; text-align:center; }
    .player-shirt { position:relative; width:52px; height:52px; margin:0 auto .3rem; border-radius:50%;
        background:#0b1220; border:3px solid var(--ring,#10b981); overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,.35); }
    .player-shirt img { width:100%; height:100%; object-fit:cover; }
    .player-shirt.gk  { --ring:#fbbf24; }
    .player-shirt.def { --ring:#38bdf8; }
    .player-shirt.mid { --ring:#34d399; }
    .player-shirt.fwd { --ring:#f87171; }
    .player-cap { position:absolute; top:-6px; right:-6px; width:20px; height:20px; border-radius:50%;
        background:#fff; color:#0b1220; font-size:.62rem; font-weight:900; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,.4); }
    .player-cap.v { background:#94a3b8; color:#0b1220; }
    .player-name { font-size:.68rem; font-weight:700; color:#fff; background:rgba(3,7,18,.72); border-radius:5px 5px 0 0;
        padding:2px 4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .player-pts { font-size:.66rem; font-weight:800; color:#0b1220; background:#e2e8f0; border-radius:0 0 5px 5px; padding:1px 4px; }
    .player-pts .price { color:#475569; font-weight:600; }

    .bench { max-width:760px; margin:1rem auto 0; background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1rem; }
    .bench-hd { font-size:.72rem; font-weight:800; color:var(--text-dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.75rem; }
    .bench-row { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }

    .buy { max-width:760px; margin:1.5rem auto 0; }
    .buy-hd { font-size:1.05rem; font-weight:900; color:#fff; margin-bottom:.2rem; }
    .buy-sub { font-size:.8rem; color:var(--text-dim); margin-bottom:.9rem; }
    .buy-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:.7rem; }
    .buy-card { display:flex; align-items:center; gap:.7rem; background:var(--card); border:1px solid var(--border); border-radius:12px; padding:.7rem .85rem; }
    .buy-photo { width:42px; height:42px; border-radius:50%; object-fit:cover; background:#1a2230; flex-shrink:0; }
    .buy-name { font-weight:800; color:#fff; font-size:.85rem; }
    .buy-meta { font-size:.7rem; color:var(--text-dim); }
    .buy-val { margin-left:auto; text-align:right; }
    .buy-val-v { font-weight:900; color:#6ee7b7; font-size:.95rem; }
    .buy-val-l { font-size:.6rem; color:var(--text-dim); text-transform:uppercase; }

    .fpl-note { max-width:760px; margin:1.5rem auto 0; background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1rem 1.2rem; font-size:.78rem; color:var(--text-dim); line-height:1.6; }
    .fpl-empty { max-width:520px; margin:3rem auto; text-align:center; background:var(--card); border:1px solid var(--border); border-radius:16px; padding:2.5rem 1.5rem; }
</style>
@endpush

@section('content')
@php
    $pos = ['GK'=>'gk','DEF'=>'def','MID'=>'mid','FWD'=>'fwd'];
    $shirt = function ($p) use ($pos) {
        $cls = $pos[$p['position']] ?? 'mid';
        $cap = ($p['is_captain'] ?? false) ? '<span class="player-cap">C</span>'
             : (($p['is_vice'] ?? false) ? '<span class="player-cap v">V</span>' : '');
        $img = $p['photo'] ? '<img src="'.e($p['photo']).'" alt="'.e($p['name']).'" loading="lazy">' : '';
        $name = \Illuminate\Support\Str::of($p['name'])->afterLast(' ');
        return '<div class="player">'
            .'<div class="player-shirt '.$cls.'">'.$img.$cap.'</div>'
            .'<div class="player-name">'.e($name).'</div>'
            .'<div class="player-pts">'.(int)$p['points'].' <span class="price">£'.number_format($p['price'],1).'</span></div>'
            .'</div>';
    };
@endphp

<div class="wrap fpl-wrap">
    <div class="fpl-head">
        <div class="fpl-title">⚽ Fantasy Best XI</div>
        <div class="fpl-sub">The optimal Premier League Fantasy squad this week — picked by player form &amp; value, updated weekly.</div>
    </div>

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

        <div class="pitch">
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
        <div class="buy">
            <div class="buy-hd">📈 Players to buy</div>
            <div class="buy-sub">Best value outside the XI — high points for their price this week.</div>
            <div class="buy-grid">
                @foreach($squad->transfers_in as $t)
                <div class="buy-card">
                    <img class="buy-photo" src="{{ $t['photo'] }}" alt="{{ $t['name'] }}" loading="lazy">
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
