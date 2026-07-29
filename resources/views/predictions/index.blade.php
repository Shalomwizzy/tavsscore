@extends('layouts.app')

@section('title', ($dateMeta['is_today'] ? 'Match Predictions Today' : 'Predictions for ' . $dateMeta['pretty']) . ' | TavsScore AI Football Tips')
@section('meta_description', $dateMeta['is_today']
    ? 'Free AI-powered football match predictions for Premier League, Champions League, La Liga, Serie A, Bundesliga and more. Win/Draw/Loss probabilities updated daily.'
    : "TavsScore predictions archive - see what we predicted on {$dateMeta['pretty']} and how each one turned out.")
@section('canonical', $dateMeta['is_today'] ? url('/predictions') : url('/predictions?date=' . $dateMeta['iso']))

@push('styles')
<style>
    .preds-header {
        padding: 1.5rem 0 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: .75rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 1.25rem;
    }

    .pred-experience {
        position:relative; overflow:hidden; border:1px solid rgba(16,185,129,.24); border-radius:16px;
        padding:1.35rem; margin:1.35rem 0 1rem;
        background:radial-gradient(circle at 78% 0%,rgba(16,185,129,.17),transparent 31%),linear-gradient(135deg,#101b2d,#0a111e 72%);
    }
    .pred-experience:after { content:''; position:absolute; inset:auto -5% -90px auto; width:360px; height:220px; border:1px solid rgba(16,185,129,.15); border-radius:50%; transform:rotate(-17deg); pointer-events:none; }
    .pred-experience-grid { position:relative; z-index:1; display:grid; grid-template-columns:minmax(0,1.35fr) minmax(280px,.9fr); gap:1rem; align-items:end; }
    .pred-kicker { color:#6ee7b7; font-size:.68rem; font-weight:900; letter-spacing:.1em; text-transform:uppercase; }
    .pred-experience h1 { color:#fff; font-size:clamp(1.55rem,3vw,2.25rem); letter-spacing:-.04em; margin:.35rem 0; line-height:1; }
    .pred-experience p { color:var(--text-dim); font-size:.82rem; line-height:1.55; max-width:610px; margin:.55rem 0 0; }
    .pred-hero-actions { display:flex; gap:.55rem; flex-wrap:wrap; margin-top:1rem; }
    .pred-hero-btn { display:inline-flex; align-items:center; gap:.35rem; padding:.52rem .75rem; border-radius:8px; text-decoration:none; color:#d1fae5; background:rgba(16,185,129,.13); border:1px solid rgba(16,185,129,.34); font-size:.72rem; font-weight:800; }
    .pred-hero-btn.secondary { color:var(--text-dim); background:rgba(255,255,255,.04); border-color:var(--border); }
    .pred-health { display:grid; grid-template-columns:repeat(3,1fr); gap:.55rem; }
    .pred-health-card { padding:.75rem; min-height:78px; border-radius:10px; background:rgba(4,10,21,.48); border:1px solid rgba(255,255,255,.09); }
    .pred-health-value { color:#fff; font-size:1.2rem; font-weight:900; letter-spacing:-.03em; }
    .pred-health-label { color:var(--text-dim); font-size:.63rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; margin-top:.25rem; }

    /* Compact league-led signal cards: the primary prediction experience. */
    .pred-league-section { border:1px solid var(--border); border-radius:14px; overflow:hidden; background:var(--card); margin-bottom:1rem; }
    .pred-league-head { border:0; border-bottom:1px solid rgba(255,255,255,.07); border-radius:0; background:rgba(255,255,255,.018); padding:.72rem .9rem; }
    .signal-card { display:grid; grid-template-columns:76px minmax(230px,1.1fr) minmax(180px,.85fr) minmax(108px,.42fr); gap:1rem; align-items:center; padding:1rem .9rem; border-bottom:1px solid rgba(255,255,255,.055); transition:background .16s ease; }
    .signal-card:last-child { border-bottom:0; }
    .signal-card:hover { background:rgba(255,255,255,.025); }
    .signal-time { color:var(--text-dim); font-size:.68rem; font-weight:700; line-height:1.45; }
    .signal-time strong { color:#fff; display:block; font-size:.9rem; }
    .signal-clubs { display:flex; align-items:center; gap:.55rem; min-width:0; }
    .signal-crest { width:31px; height:31px; display:grid; place-items:center; overflow:hidden; border-radius:50%; flex-shrink:0; color:#fff; font-size:.61rem; font-weight:900; letter-spacing:-.02em; background:linear-gradient(135deg,#285d92,#0b2545); border:1px solid rgba(147,197,253,.38); box-shadow:0 4px 12px rgba(0,0,0,.25); }
    .signal-crest.away { background:linear-gradient(135deg,#1c785d,#093a31); border-color:rgba(110,231,183,.34); margin-left:-.8rem; margin-top:1.15rem; }
    .signal-crest img { width:82%; height:82%; object-fit:contain; }
    .signal-crest .crest-fallback { display:none; }
    .signal-team-names { min-width:0; }
    .signal-team-names strong { display:block; color:#fff; font-size:.84rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .signal-team-names span { display:block; color:var(--text-dim); font-size:.68rem; margin:.12rem 0; }
    .signal-state { color:var(--text-dim); font-size:.66rem; margin-top:.25rem; }
    .signal-pick { min-width:0; padding-left:.85rem; border-left:1px solid rgba(255,255,255,.09); }
    .signal-pick-label { color:#6ee7b7; font-size:.62rem; font-weight:900; text-transform:uppercase; letter-spacing:.07em; }
    .signal-pick-value { color:#fff; font-size:.88rem; font-weight:850; line-height:1.35; margin-top:.2rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .signal-confidence { min-width:0; }
    .signal-confidence-top { display:flex; align-items:baseline; justify-content:space-between; gap:.4rem; color:var(--text-dim); font-size:.64rem; font-weight:700; }
    .signal-confidence-top strong { color:#6ee7b7; font-size:.9rem; }
    .signal-confidence-track { height:5px; border-radius:999px; background:rgba(255,255,255,.09); overflow:hidden; margin-top:.4rem; }
    .signal-confidence-fill { height:100%; border-radius:inherit; background:linear-gradient(90deg,#10b981,#6ee7b7); }
    .signal-actions { display:flex; gap:.4rem; align-items:center; margin-top:.5rem; }
    .signal-details { grid-column:2/-1; margin-top:-.35rem; }
    .signal-details > summary { cursor:pointer;list-style:none;color:#6ee7b7;font-size:.69rem;font-weight:800;padding:.35rem 0; }
    .signal-details > summary::-webkit-details-marker { display:none; }
    .signal-details > summary:after { content:'+'; margin-left:.35rem; color:var(--text-dim); }
    .signal-details[open] > summary:after { content:'−'; }
    .signal-details-body { padding:.75rem; border-radius:9px; border:1px solid var(--border); background:rgba(4,10,21,.42); }

    .preds-title   { font-size:1.35rem; font-weight:800; color:#fff; letter-spacing:-.02em; margin:0 0 .2rem; }
    .preds-sub     { font-size:.75rem; color:var(--text-dim); }
    .preds-actions { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; }
    .count-text    { font-size:.75rem; color:var(--text-dim); white-space:nowrap; }

    .gen-btn {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .45rem 1rem; border-radius: 8px;
        background: var(--green-dim); border: 1px solid var(--green-border);
        color: #6ee7b7; font-size: .78rem; font-weight: 700;
        cursor: pointer; font-family: inherit;
        transition: background 160ms, opacity 160ms, transform 160ms;
    }
    .gen-btn:hover:not(:disabled) { background:rgba(16,185,129,.22); transform:translateY(-1px); }
    /* League section */
    .pred-league-section { margin-bottom: 1.25rem; }

    .pred-league-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .875rem;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 10px 10px 0 0;
    }

    .plh-left  { display:flex; align-items:center; gap:.5rem; }
    .plh-flag  { font-size:.95rem; line-height:1; }
    .plh-name  { font-size:.75rem; font-weight:700; color:var(--text); text-transform:uppercase; letter-spacing:.03em; }
    .plh-country { font-size:.67rem; color:var(--text-dim); margin-top:1px; }

    /* Prediction card */
    .pred-card {
        background: var(--card); border: 1px solid var(--border);
        border-top: none; overflow: hidden;
        transition: background 200ms;
    }
    .pred-card:last-child { border-radius: 0 0 10px 10px; }
    .pred-card:hover { background: var(--card-hover); }

    .pc-head {
        padding: .875rem 1.1rem .75rem;
        border-bottom: 1px solid rgba(255,255,255,.04);
        display: flex; justify-content: space-between;
        align-items: flex-start; gap: .875rem;
    }

    .pc-teams {
        font-size: .97rem; font-weight: 700; color: #fff;
        margin-bottom: .2rem; line-height: 1.3;
    }
    .pc-teams .vs { color:var(--text-muted); font-weight:400; font-size:.82rem; }
    .pc-meta      { font-size:.7rem; color:var(--text-muted); }
    .pc-live      { font-weight:700; color:#ef4444; }
    .pc-ft        { font-weight:700; color:var(--text); }

    .verdict-chip {
        flex-shrink: 0; display: inline-flex; align-items: center; gap: .3rem;
        padding: 4px 10px; border-radius: 999px;
        background: var(--green-dim); border: 1px solid var(--green-border);
        color: #6ee7b7; font-size: .7rem; font-weight: 700; white-space: nowrap;
    }

    .pc-body { padding: .875rem 1.1rem; }

    /* Probability bars */
    .prob-row {
        display: grid;
        grid-template-columns: 9rem 1fr 3.2rem;
        align-items: center; gap: .65rem; margin-bottom: .55rem;
    }
    .prob-row:last-child { margin-bottom:0; }
    .prob-label { font-size:.78rem; font-weight:600; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .prob-track { height:6px; border-radius:999px; background:rgba(255,255,255,.07); overflow:hidden; }
    .prob-fill  { height:100%; border-radius:999px; transition:width 500ms cubic-bezier(.4,0,.2,1); }
    .fill-best  { background:linear-gradient(90deg,#10b981,#06d6a0); }
    .fill-draw  { background:var(--yellow); }
    .fill-other { background:rgba(239,68,68,.5); }
    .prob-pct   { font-size:.78rem; font-weight:700; color:#fff; text-align:right; font-variant-numeric:tabular-nums; }

    /* Goal line chips */
    .goal-lines {
        display:flex; flex-wrap:wrap; gap:.4rem;
        margin-top:.875rem; padding-top:.875rem;
        border-top:1px solid rgba(255,255,255,.05);
    }
    .gl-chip {
        display:inline-flex; align-items:center; gap:.3rem;
        padding:4px 10px; border-radius:8px;
        font-size:.72rem; font-weight:700;
    }
    .gl-yes   { background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.25); color:#6ee7b7; }
    .gl-no    { background:rgba(239,68,68,.1);   border:1px solid rgba(239,68,68,.2);   color:#fca5a5; }
    .gl-mid   { background:rgba(245,158,11,.1);  border:1px solid rgba(245,158,11,.2);  color:#fcd34d; }
    .gl-label { opacity:.7; font-weight:500; }
    .gl-pct   { font-weight:800; }

    /* Analysis box */
    .analysis-box {
        margin-top: .875rem; padding: .8rem .95rem;
        border-radius: 8px; background: var(--surface);
        border: 1px solid var(--border);
    }
    .analysis-head { font-size:.67rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; }
    .analysis-text { font-size:.78rem; color:var(--text-dim); line-height:1.7; }

    /* Form guide dots */
    .form-dots { display:inline-flex; gap:3px; align-items:center; margin-left:.35rem; vertical-align:middle; }
    .form-dot  { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
    .fd-w { background:#10b981; }
    .fd-d { background:#f59e0b; }
    .fd-l { background:rgba(239,68,68,.7); }

    /* Result badge on card */
    .result-badge-card {
        display:inline-flex; align-items:center; gap:.25rem;
        padding:2px 8px; border-radius:999px; font-size:.68rem; font-weight:800;
    }
    .rb-win  { background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.28); color:#6ee7b7; }
    .rb-loss { background:rgba(239,68,68,.1);   border:1px solid rgba(239,68,68,.25);  color:#fca5a5; }

    /* Share button */
    .share-btn {
        background:none; border:1px solid var(--border); border-radius:7px;
        color:var(--text-dim); cursor:pointer; font-size:.72rem; font-weight:700;
        padding:4px 9px; transition:all 150ms; white-space:nowrap; font-family:inherit;
        display:inline-flex; align-items:center; gap:.3rem;
    }
    .share-btn:hover { border-color:rgba(99,179,237,.3); color:var(--text); }
    .share-btn.copied { border-color:rgba(16,185,129,.3); color:#6ee7b7; }

    .pc-detail-link {
        display:inline-flex; align-items:center; justify-content:center;
        width:30px; height:28px; border-radius:7px; text-decoration:none;
        background:rgba(255,255,255,.04); border:1px solid var(--border);
        color:var(--text-dim); font-weight:800; transition:all 150ms;
    }
    .pc-detail-link:hover { color:var(--text); background:rgba(255,255,255,.08); border-color:rgba(99,179,237,.3); }

    /* Card action row */
    .pc-actions { display:flex; align-items:center; gap:.5rem; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end; }

    /* Tip callout */
    .tip-callout {
        display: flex; align-items: flex-start; gap: .55rem;
        margin-top: .55rem; padding: .6rem .8rem;
        background: linear-gradient(135deg, rgba(245,158,11,.13), rgba(245,158,11,.06));
        border: 1px solid rgba(245,158,11,.3); border-radius: 7px;
    }

    /* Headline tip + collapsed alternatives */
    .tips-block { margin-top:.875rem; padding-top:.875rem; border-top:1px solid rgba(255,255,255,.05); }

    .tip-headline {
        background: linear-gradient(135deg, rgba(16,185,129,.10), rgba(16,185,129,.03));
        border: 1px solid rgba(16,185,129,.28);
        border-radius: 9px; padding: .65rem .85rem;
    }
    .tip-headline-label  { font-size:.62rem; font-weight:800; color:#6ee7b7; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.3rem; }
    .tip-headline-row    { display:flex; align-items:center; gap:.65rem; }
    .tip-headline-market { font-size:.92rem; font-weight:900; color:#fff; line-height:1.3; }
    .tip-headline-reason { font-size:.72rem; color:var(--text-dim); line-height:1.45; margin-top:.25rem; }
    .tip-headline-pill   { font-size:.78rem; font-weight:900; padding:5px 11px; border-radius:999px; font-variant-numeric:tabular-nums; flex-shrink:0; }

    .tip-alts { margin-top:.5rem; }
    .tip-alts > summary { cursor:pointer; font-size:.68rem; font-weight:700; color:var(--text-dim); padding:.35rem 0; list-style:none; user-select:none; }
    .tip-alts > summary:hover { color:var(--text); }
    .tip-alts > summary::-webkit-details-marker { display:none; }
    .tip-alts[open] > summary { color:var(--text); margin-bottom:.25rem; }

    .tip-row    {
        display:flex; gap:.55rem; align-items:center;
        padding:.4rem .55rem; border-radius:7px;
        background: rgba(255,255,255,.025); border:1px solid var(--border);
        margin-bottom:.35rem; transition: background 140ms, border-color 140ms;
    }
    .tip-row:hover { background: rgba(255,255,255,.05); border-color: rgba(245,158,11,.25); }
    .tip-text   { min-width:0; flex:1; }
    .tip-market { font-size:.78rem; font-weight:700; color:#fff; line-height:1.3; }
    .tip-reason { font-size:.7rem; color:var(--text-dim); line-height:1.4; margin-top:2px; }
    .tip-conf-pill {
        font-size:.7rem; font-weight:800; padding:3px 9px; border-radius:999px;
        font-variant-numeric: tabular-nums; flex-shrink:0;
    }
    .tip-conf-high   { background:rgba(16,185,129,.13); border:1px solid rgba(16,185,129,.28); color:#6ee7b7; }
    .tip-conf-medium { background:rgba(245,158,11,.13); border:1px solid rgba(245,158,11,.28); color:#fcd34d; }
    .tip-conf-low    { background:rgba(99,102,241,.13); border:1px solid rgba(99,102,241,.28); color:#a5b4fc; }
    .tip-info        { display:inline-block; margin-left:.3rem; font-size:.65rem; opacity:.7; cursor:help; }

    /* Language toggle (shared with picks/show) */
    .lang-toggle {
        display:inline-flex; gap:2px;
        background:rgba(255,255,255,.04); border:1px solid var(--border);
        border-radius:7px; padding:2px;
    }
    .lang-btn {
        background:transparent; border:none; cursor:pointer;
        font-family:inherit; font-size:.66rem; font-weight:700;
        padding:3px 8px; border-radius:5px; color:var(--text-dim);
        transition:background 140ms, color 140ms;
    }
    .lang-btn:hover:not(:disabled) { color:var(--text); background:rgba(255,255,255,.05); }
    .lang-btn.active { background:rgba(16,185,129,.18); color:#6ee7b7; }
    .lang-btn:disabled { opacity:.5; cursor:wait; }
    .tip-icon { font-size: .95rem; flex-shrink: 0; line-height: 1.4; }
    .tip-text  { font-size: .8rem; font-weight: 700; color: #fcd34d; line-height: 1.5; }

    /* Empty/loading */
    .gen-prompt { display:flex; flex-direction:column; align-items:center; gap:.65rem; }

    /* Archive / date picker */
    .archive-nav-p {
        display:flex; flex-wrap:wrap; gap:.45rem; align-items:center;
        margin: .25rem 0 1.1rem;
        padding:.55rem .7rem;
        background: rgba(255,255,255,.025);
        border: 1px solid var(--border);
        border-radius: 10px;
    }
    .arch-btn-p {
        display:inline-flex; align-items:center; padding:.4rem .8rem; border-radius:7px;
        background:rgba(255,255,255,.05); border:1px solid var(--border);
        color:var(--text); text-decoration:none; font-size:.74rem; font-weight:700; cursor:pointer;
        transition:background 140ms, border-color 140ms;
    }
    .arch-btn-p:hover:not(.arch-disabled-p) { background:rgba(255,255,255,.1); border-color:rgba(99,179,237,.3); color:#fff; }
    .arch-disabled-p { opacity:.35; cursor:not-allowed; }
    .arch-today-p { margin-left:auto; background:rgba(16,185,129,.12); border-color:rgba(16,185,129,.28); color:#6ee7b7; }
    .arch-input-p {
        background:rgba(8,13,26,.7); border:1px solid var(--border);
        border-radius:7px; padding:.4rem .6rem;
        color:var(--text); font-family:inherit; font-size:.78rem; font-weight:700; cursor:pointer;
        color-scheme: dark;
    }

    @media (max-width:640px) {
        .pred-experience { margin:1rem 0 .85rem;padding:1rem;border-radius:14px; }
        .pred-experience:after { width:220px;height:150px;bottom:-80px;right:-30px; }
        .pred-experience-grid { grid-template-columns:1fr;gap:.85rem; }
        .pred-experience h1 { font-size:1.65rem;line-height:1.06; }
        .pred-hero-actions { display:grid;grid-template-columns:1fr 1fr;gap:.45rem; }
        .pred-hero-btn { justify-content:center;text-align:center;padding:.58rem .5rem; }
        .pred-health { gap:.35rem; }
        .pred-health-card { min-height:66px;padding:.55rem .45rem; }
        .pred-health-value { font-size:1rem; }
        .pred-health-label { font-size:.54rem;letter-spacing:.035em; }
        .preds-header { padding:1rem 0 .85rem;margin-bottom:.85rem;flex-direction:column;align-items:flex-start; }
        .preds-title { font-size:1.15rem; }
        .pred-league-section { border-radius:12px;margin-bottom:.75rem; }
        .pred-league-head { padding:.62rem .7rem; }
        .plh-name { font-size:.69rem;max-width:185px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
        .signal-card { grid-template-columns:minmax(0,1fr) auto;gap:.7rem;padding:.8rem .72rem .72rem; }
        .signal-time { grid-column:1;grid-row:1;display:flex;align-items:center;gap:.45rem;font-size:.63rem; }
        .signal-time strong { display:inline;font-size:.78rem; }
        .signal-clubs { grid-column:1/-1;grid-row:2;padding:.15rem 0 .7rem;border-bottom:1px solid rgba(255,255,255,.07); }
        .signal-crest { width:30px;height:30px; }
        .signal-team-names strong { font-size:.8rem; }
        .signal-state { font-size:.61rem; }
        .signal-pick { grid-column:1/-1;grid-row:3;border-left:0;border-top:0;padding:.1rem 0 0; }
        .signal-pick-label { font-size:.58rem; }
        .signal-pick-value { white-space:normal;font-size:.95rem;line-height:1.28; }
        .signal-actions { margin-top:.42rem; }
        .signal-confidence { grid-column:2;grid-row:1;min-width:82px;padding:.42rem .5rem;border-radius:8px;background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.16); }
        .signal-confidence-top { font-size:.57rem; }
        .signal-confidence-top strong { font-size:.82rem; }
        .signal-details { grid-column:1/-1;grid-row:4;margin-top:0; }
        .signal-details > summary { padding:.55rem 0 .1rem;font-size:.71rem; }
        .signal-details-body { padding:.65rem; }
        .signal-details-body [style*="grid-template-columns:repeat(2"] { grid-template-columns:1fr !important; }
        .signal-details-body [style*="minmax(120px"] { grid-template-columns:repeat(2,minmax(0,1fr)) !important; }
        .prob-row { grid-template-columns:1fr 3rem;gap:.45rem; }
        .prob-label { display:none; }
        .goal-lines { gap:.3rem; }
        .gl-chip { padding:3px 7px;font-size:.64rem; }
        .analysis-box { padding:.7rem; }
        .tip-headline { padding:.6rem .65rem; }
        .tip-headline-row { align-items:flex-start; }
        .tip-headline-market { font-size:.82rem; }
        .archive-nav-p { padding:.48rem;gap:.35rem; }
        .arch-btn-p { padding:.38rem .55rem;font-size:.68rem; }
        .arch-input-p { flex:1;min-width:135px; }
    }
    @media (max-width:380px) {
        .pred-hero-actions { grid-template-columns:1fr; }
        .pred-health { grid-template-columns:1fr 1fr; }
        .pred-health-card:last-child { grid-column:1/-1; }
        .signal-details-body [style*="minmax(120px"] { grid-template-columns:1fr !important; }
    }

    /* Left info block in card header */
    .pc-info { min-width: 0; flex: 1; }

    /* ── Mobile card fix: stop actions from squishing team names ── */
    @media (max-width:600px) {
        .pc-head { flex-wrap: wrap; gap: .4rem; }
        /* Left block takes full first row */
        .pc-info { flex: 1 1 100%; }
        /* Actions sit below on their own row, left-aligned */
        .pc-actions { width: 100%; justify-content: flex-start; flex-wrap: wrap; gap: .4rem; }
        /* Trim long verdict chip so it never overflows */
        .verdict-chip { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        /* Date clips instead of wrapping character by character */
        .pc-meta { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    }
</style>
@endpush

@include('partials.language-toggle')

@section('content')
<div class="wrap">

    <section class="pred-experience">
        <div class="pred-experience-grid">
            <div>
                <div class="pred-kicker">TavsScore Football Intelligence</div>
                <h1>@if($dateMeta['is_today'])Today’s Football Signals@else Predictions for {{ $dateMeta['pretty'] }}@endif</h1>
                <p>Clear match signals built from current form, team news and modelled match trends. Tap any prediction for the full breakdown.</p>
                <div class="pred-hero-actions">
                    <a href="{{ route('picks.index') }}" class="pred-hero-btn">⭐ View today’s shortlist</a>
                    <a href="{{ route('daily-football-predictions.index', ['date' => $dateMeta['iso']]) }}" class="pred-hero-btn secondary">📅 Daily results</a>
                </div>
            </div>
            <div class="pred-health" aria-label="Prediction summary">
                <div class="pred-health-card"><div class="pred-health-value" id="metric-total">—</div><div class="pred-health-label">Signals today</div></div>
                <div class="pred-health-card"><div class="pred-health-value" id="metric-high" style="color:#6ee7b7">—</div><div class="pred-health-label">High confidence</div></div>
                <div class="pred-health-card"><div class="pred-health-value" id="metric-resolved" style="color:#93c5fd">—</div><div class="pred-health-label">Results verified</div></div>
            </div>
        </div>
    </section>

    <div class="preds-header">
        <div><div class="preds-title">Match predictions</div><div class="preds-sub">Win / Draw / Loss · Goals · Both teams to score · Match analysis</div></div>
        <div class="preds-actions"><span class="count-text" id="pred-count"></span></div>
    </div>

    <div class="ts-tabs mb-4" style="max-width:380px">
        <a href="{{ route('live.index') }}"       class="ts-tab">🔴 Live</a>
        <a href="{{ route('live.index') }}#today" class="ts-tab">📅 Today</a>
        <span class="ts-tab active">📊 Predictions</span>
    </div>

    {{-- ── Date picker / archive nav ── --}}
    <form method="GET" action="{{ route('predictions.index') }}" class="archive-nav-p" id="archive-nav-p">
        <a href="{{ route('predictions.index', ['date' => $dateMeta['prev_iso']]) }}"
           class="arch-btn-p" aria-label="Previous day">‹ Prev</a>

        <input type="date" name="date" id="archive-date-p"
               value="{{ $dateMeta['iso'] }}"
               min="{{ $dateMeta['min_iso'] }}"
               max="{{ $dateMeta['max_iso'] }}"
               class="arch-input-p"
               aria-label="Select a date"
               onchange="this.form.submit()">

        @if($dateMeta['next_iso'])
        <a href="{{ route('predictions.index', ['date' => $dateMeta['next_iso']]) }}"
           class="arch-btn-p" aria-label="Next day">Next ›</a>
        @else
        <span class="arch-btn-p arch-disabled-p">Next ›</span>
        @endif

        @unless($dateMeta['is_today'])
        <a href="{{ route('predictions.index') }}" class="arch-btn-p arch-today-p">Today</a>
        @endunless
    </form>

    <div id="loading-state" class="state-box">
        <span class="state-icon"><span class="spin"></span></span>
        <div class="state-title">Loading predictions…</div>
    </div>

    <div id="error-state" class="state-box" style="display:none">
        <span class="state-icon">⚠️</span>
        <div class="state-title">Failed to load predictions</div>
        <p class="state-sub" style="margin-bottom:.75rem">Check your connection and try again</p>
        <button onclick="loadPredictions()" class="gen-btn" style="margin-top:.5rem">↩ Retry</button>
    </div>

    <div id="empty-state" class="state-box" style="display:none">
        <div class="gen-prompt">
            <span class="state-icon">🔮</span>
            <div class="state-title">Predictions coming soon</div>
            <p class="state-sub">Today's predictions are generated automatically at 06:00 Lagos time.<br>
                Check back shortly or visit <a href="{{ route('picks.index') }}" style="color:#6ee7b7;">Daily Picks</a> for our top 3 picks.</p>
        </div>
    </div>

    <div id="preds-list" style="display:none"></div>

    <div style="height:2rem"></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var ARCHIVE_DATE = @json($dateMeta['is_today'] ? null : $dateMeta['iso']);
    var PRED_API = @json(url('/api/predictions')) + (ARCHIVE_DATE ? ('?date=' + ARCHIVE_DATE) : '');
    /* DOM */
    var elLoading = document.getElementById('loading-state');
    var elError   = document.getElementById('error-state');
    var elEmpty   = document.getElementById('empty-state');
    var elList    = document.getElementById('preds-list');
    var elCount   = document.getElementById('pred-count');
    var elMetricTotal = document.getElementById('metric-total');
    var elMetricHigh = document.getElementById('metric-high');
    var elMetricResolved = document.getElementById('metric-resolved');

    /* Top-league sort order (API-Football IDs) */
    var TOP_ORDER = [2,3,39,61,78,135,140,848,88,94,40,48,45,144,179,203,253,71,307,262,292];
    function leagueRank(id) { var i = TOP_ORDER.indexOf(Number(id)); return i === -1 ? 999 : i; }

    var FLAGS = {
        'england':'🏴󠁧󠁢󠁥󠁮󠁧󠁿','scotland':'🏴󠁧󠁢󠁳󠁣󠁴󠁿','spain':'🇪🇸','italy':'🇮🇹',
        'germany':'🇩🇪','france':'🇫🇷','netherlands':'🇳🇱','portugal':'🇵🇹',
        'belgium':'🇧🇪','turkey':'🇹🇷','russia':'🇷🇺','usa':'🇺🇸',
        'brazil':'🇧🇷','argentina':'🇦🇷','world':'🌍','europe':'🌍',
    };
    function flag(c) { return c && FLAGS[c.toLowerCase()] ? FLAGS[c.toLowerCase()] : '🏆'; }

    /* State helpers */
    function showState(s) {
        elLoading.style.display = s === 'loading' ? '' : 'none';
        elError.style.display   = s === 'error'   ? '' : 'none';
        elEmpty.style.display   = s === 'empty'   ? '' : 'none';
        elList.style.display    = s === 'ready'   ? 'block' : 'none';
    }

    /* Escape */
    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function fmtPct(v)  { return Number(v || 0).toFixed(1); }

    /* Format "Country · League". Drops "World"/blank/Unknown for international comps. */
    function formatLeague(league, country) {
        league  = String(league  || '').trim();
        country = String(country || '').trim();
        if (!league)  return country || '-';
        var lower = country.toLowerCase();
        if (!country || lower === 'world' || lower === 'unknown') return league;
        return country + ' · ' + league;
    }
    function fmtTime(iso) {
        if (!iso) return 'TBC';
        return new Intl.DateTimeFormat(undefined,{weekday:'short',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}).format(new Date(iso));
    }
    function initials(name) {
        return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 3).map(function(part){ return part.charAt(0); }).join('').toUpperCase();
    }
    function clubCrest(logo, name, away) {
        var classes = 'signal-crest' + (away ? ' away' : '');
        var fallback = esc(initials(name));
        if (!logo) return '<span class="'+classes+'">'+fallback+'</span>';
        return '<span class="'+classes+'"><img src="'+esc(logo)+'" alt="" loading="lazy" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'grid\';"><span class="crest-fallback">'+fallback+'</span></span>';
    }

    /* Outcome helpers */
    function outcomes(p) {
        var m = p.match || {};
        return [
            { key:'home', label: m.home_team || 'Home', value: Number(p.home_win_prob || 0) },
            { key:'draw', label: 'Draw',                value: Number(p.draw_prob     || 0) },
            { key:'away', label: m.away_team || 'Away', value: Number(p.away_win_prob || 0) },
        ];
    }

    function strongest(p) { return outcomes(p).reduce(function(s,o){ return o.value>s.value?o:s; }); }

    function fillClass(key, bestKey) {
        if (key === bestKey) return 'fill-best';
        if (key === 'draw')  return 'fill-draw';
        return 'fill-other';
    }

    function glChip(label, pct) {
        var cls = pct >= 60 ? 'gl-yes' : pct <= 40 ? 'gl-no' : 'gl-mid';
        return '<div class="gl-chip '+cls+'">'
            +'<span class="gl-label">'+esc(label)+'</span>'
            +'<span class="gl-pct">'+fmtPct(pct)+'%</span>'
            +'</div>';
    }

    function insightHtml(insight) {
        if (!insight || !insight.available) return '';
        function form(team) {
            var recent = team.recent || {};
            var dots = (team.form || []).map(function (item) {
                var colour = item.result === 'W' ? '#6ee7b7' : (item.result === 'L' ? '#fca5a5' : '#fcd34d');
                return '<span title="'+esc((item.venue||'')+' '+(item.score||'')+' vs '+(item.opponent||''))+'" style="display:inline-grid;place-items:center;width:18px;height:18px;border-radius:50%;font-size:.58rem;font-weight:900;color:'+colour+';background:rgba(255,255,255,.06);border:1px solid '+colour+'55;">'+esc(item.result||'?')+'</span>';
            }).join('') || '<span style="font-size:.67rem;color:var(--text-dim);">No recent results stored</span>';
            var logo = team.logo ? '<img src="'+esc(team.logo)+'" alt="" loading="lazy" style="width:25px;height:25px;object-fit:contain;">' : '';
            var numbers = recent.played ? '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.35rem;font-size:.65rem;color:var(--text-dim);margin-top:.55rem;"><span>Form <b style="color:#fff;float:right;">'+esc(recent.wins)+'-'+esc(recent.draws)+'-'+esc(recent.losses)+'</b></span><span>Goals <b style="color:#fff;float:right;">'+esc(recent.goals_for)+':'+esc(recent.goals_against)+'</b></span><span>Scored/game <b style="color:#fff;float:right;">'+esc(recent.gpg)+'</b></span><span>Conceded/game <b style="color:#fff;float:right;">'+esc(recent.cpg)+'</b></span></div>' : '';
            return '<div style="border:1px solid var(--border);border-radius:9px;padding:.75rem;min-width:0;"><div style="display:flex;align-items:center;gap:.45rem;margin-bottom:.55rem;">'+logo+'<strong style="font-size:.76rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+esc(team.name||'Team')+'</strong></div><div style="display:flex;gap:3px;flex-wrap:wrap;">'+dots+'</div>'+numbers+'</div>';
        }
        var reasons = (insight.reasons || []).map(function (reason) {
            return '<div style="font-size:.74rem;line-height:1.5;color:var(--text-dim);padding:.55rem .65rem;border-left:2px solid #6ee7b7;background:rgba(255,255,255,.025);">'+esc(reason)+'</div>';
        }).join('');
        var market = insight.market || {};
        var marketHtml = (market.metrics || []).length ? '<div style="margin-bottom:1rem;"><div style="font-size:.64rem;font-weight:900;letter-spacing:.07em;text-transform:uppercase;color:var(--text-dim);margin-bottom:.5rem;">Numbers behind this '+esc(market.outcome || 'pick')+'</div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:.45rem;">'+(market.metrics || []).map(function(metric){return '<div style="padding:.6rem;border:1px solid var(--border);border-radius:8px;background:rgba(255,255,255,.022);"><div style="font-size:.61rem;color:var(--text-dim);line-height:1.35;">'+esc(metric.label)+'</div><div style="font-size:.94rem;font-weight:900;color:#6ee7b7;margin-top:.18rem;">'+esc(metric.value)+'</div>'+(metric.hint?'<div style="font-size:.58rem;color:var(--text-muted);line-height:1.35;margin-top:.18rem;">'+esc(metric.hint)+'</div>':'')+'</div>';}).join('')+'</div></div>' : '';
        var h = insight.h2h || {};
        var h2h = h.total ? '<div style="margin-top:1rem;"><div style="font-size:.64rem;font-weight:900;letter-spacing:.07em;text-transform:uppercase;color:var(--text-dim);margin-bottom:.5rem;">Head to head · last '+esc(h.total)+'</div><div style="font-size:.68rem;color:var(--text-dim);margin-bottom:.45rem;"><b style="color:#fff;">'+esc((insight.home||{}).name||'Home')+' '+esc(h.home_wins)+'</b> wins · <b style="color:#fcd34d;">'+esc(h.draws)+' draws</b> · <b style="color:#fff;">'+esc((insight.away||{}).name||'Away')+' '+esc(h.away_wins)+'</b> wins</div><div style="display:grid;gap:.3rem;">'+(h.results||[]).map(function(r){return '<div style="display:flex;justify-content:space-between;gap:.75rem;font-size:.68rem;color:var(--text-dim);"><span>'+esc(r.score)+'</span><span style="white-space:nowrap;">'+esc(r.date)+'</span></div>';}).join('')+'</div></div>' : '';
        var injuries = ['home','away'].map(function(side) {
            var rows = ((insight.injuries || {})[side] || []); if (!rows.length) return '';
            return '<div style="padding:.7rem;border-radius:8px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.18);"><div style="font-size:.65rem;font-weight:800;color:#fca5a5;margin-bottom:.35rem;">🏥 '+esc((insight[side]||{}).name||'Team')+' unavailable</div>'+rows.map(function(row){return '<div style="font-size:.67rem;color:var(--text-dim);line-height:1.5;">'+esc(row.player)+' · '+esc(row.reason)+'</div>';}).join('')+'</div>';
        }).join('');
        return '<div style="margin-top:1rem;border:1px solid var(--border);border-radius:10px;padding:1rem;background:rgba(255,255,255,.018);"><div style="font-size:.72rem;font-weight:900;color:#fff;margin-bottom:.75rem;">🔎 Full match intelligence</div>'+(reasons?'<div style="display:grid;gap:.45rem;margin-bottom:1rem;"><div style="font-size:.64rem;font-weight:900;letter-spacing:.07em;text-transform:uppercase;color:#6ee7b7;">Why this pick was selected</div>'+reasons+'</div>':'')+marketHtml+'<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem;">'+form(insight.home||{})+form(insight.away||{})+'</div>'+h2h+(injuries?'<div style="margin-top:1rem;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem;">'+injuries+'</div>':'')+'</div>';
    }

    /* Render one card */
    function renderCard(p) {
        var m    = p.match || {};
        var best = strongest(p);
        var outs = outcomes(p);

        /* Use server-computed verdict, fall back to JS logic */
        var verdict = p.predicted_outcome
            ? esc(p.predicted_outcome)
            : (best.key==='home'
                ? esc(m.home_team||'Home')+' Win'
                : best.key==='away'
                    ? esc(m.away_team||'Away')+' Win'
                    : 'Draw');

        var liveSet     = ['1H','2H','HT','ET','BT','P','LIVE'];
        var finishedSet = ['FT','AET','PEN'];
        var liveScore   = '';
        if (m.status && liveSet.indexOf(m.status) !== -1) {
            liveScore = ' · <span class="pc-live">'
                + esc(m.home_score != null ? m.home_score : 0)
                + '–'
                + esc(m.away_score != null ? m.away_score : 0)
                + ' ' + esc(m.display_status || m.status)
                + '</span>';
        } else if (m.status && finishedSet.indexOf(m.status) !== -1 && m.home_score != null) {
            liveScore = ' · <span class="pc-ft">'
                + esc(m.home_score)
                + '–'
                + esc(m.away_score != null ? m.away_score : 0)
                + ' FT</span>';
        }

        /* Win/Draw/Loss bars */
        var barsHtml = outs.map(function(o){
            var pct = Math.min(100, Math.max(0, o.value));
            return '<div class="prob-row">'
                +'<div class="prob-label">'+esc(o.label)+'</div>'
                +'<div class="prob-track" role="progressbar" aria-valuenow="'+fmtPct(o.value)+'" aria-valuemin="0" aria-valuemax="100">'
                +'<div class="prob-fill '+fillClass(o.key,best.key)+'" style="width:'+pct+'%"></div>'
                +'</div>'
                +'<div class="prob-pct">'+fmtPct(o.value)+'%</div>'
                +'</div>';
        }).join('');

        /* Goal-line & BTTS chips */
        var glHtml = '';
        if (p.over_15_prob || p.over_25_prob || p.btts_prob) {
            glHtml = '<div class="goal-lines">'
                + (p.over_15_prob ? glChip('Over 1.5', p.over_15_prob) : '')
                + (p.over_25_prob ? glChip('Over 2.5', p.over_25_prob) : '')
                + (p.over_35_prob ? glChip('Over 3.5', p.over_35_prob) : '')
                + (p.btts_prob    ? glChip('BTTS',     p.btts_prob)    : '')
                +'</div>';
        }

        var isAi = p.analysis
            && p.analysis !== 'Analysis currently unavailable'
            && p.analysis.indexOf('Statistical model only') !== 0
            && p.analysis.indexOf('Analysis currently') !== 0;

        // Extract the 💡 tip sentence from the analysis (new format) or
        // fall back to detecting "clearest tip / best bet / I recommend" sentences
        var tipText = null;
        if (isAi && p.analysis) {
            var tipMatch = p.analysis.match(/💡\s*Tip:\s*(.+?)(?:\.|$)/i);
            if (tipMatch) {
                tipText = tipMatch[1].trim();
            } else {
                // Fallback: detect tip-like sentences in older analysis
                var sentences = p.analysis.match(/[^.!?]+[.!?]*/g) || [];
                for (var si = 0; si < sentences.length; si++) {
                    var s = sentences[si].toLowerCase();
                    if (s.indexOf('clearest tip') !== -1 || s.indexOf('best bet') !== -1 ||
                        s.indexOf('recommend') !== -1 || s.indexOf('back ') !== -1) {
                        tipText = sentences[si].replace(/^my\s+clearest\s+tip\s+is\s+to\s*/i, '').trim();
                        tipText = tipText.charAt(0).toUpperCase() + tipText.slice(1);
                        break;
                    }
                }
            }
        }

        // Remove the tip sentence from the body text so it isn't duplicated
        var bodyText = isAi ? p.analysis.replace(/\s*💡\s*Tip:[^.!?]*[.!?]?/i, '').trim()
                            : 'Probabilities based on recent scoring form and defensive record (Poisson model with home advantage).';

        var tipHtml = tipText
            ? '<div class="tip-callout"><span class="tip-icon">💡</span><span class="tip-text">'+esc(tipText)+'</span></div>'
            : '';

        /* Headline pick + collapsed alternatives */
        function tipConfClass(c) { return c >= 70 ? 'tip-conf-high' : (c >= 60 ? 'tip-conf-medium' : 'tip-conf-low'); }
        var tipsHtml = '';
        if (Array.isArray(p.tips) && p.tips.length > 0) {
            var head = p.tips[0];
            var alts = p.tips.slice(1);
            var hConfClass = tipConfClass(head.confidence || 0);
            var headInfoBadge = (head.verifiable === false)
                ? ' <span class="tip-info" title="Informational - corners/cards aren\'t auto-verified">ℹ️</span>'
                : '';
            tipsHtml = '<div class="tips-block">'
                +'<div class="tip-headline">'
                +'<div class="tip-headline-label">🎯 Best Pick</div>'
                +'<div class="tip-headline-row">'
                +'<div style="min-width:0;flex:1;">'
                +'<div class="tip-headline-market">'+esc(head.market||'')+headInfoBadge+'</div>'
                + (head.rationale ? '<div class="tip-headline-reason">'+esc(head.rationale)+'</div>' : '')
                +'</div>'
                +'<span class="tip-headline-pill '+hConfClass+'">'+(head.confidence||0)+'%</span>'
                +'</div>'
                +'</div>';

            if (alts.length > 0) {
                tipsHtml += '<details class="tip-alts"><summary>▸ Show '+alts.length+' alternative'+(alts.length!==1?'s':'')+'</summary>';
                alts.forEach(function (t) {
                    var infoBadge = (t.verifiable === false)
                        ? ' <span class="tip-info" title="Informational only">ℹ️</span>'
                        : '';
                    tipsHtml += '<div class="tip-row">'
                        +'<div class="tip-text">'
                        +'<div class="tip-market">'+esc(t.market||'')+infoBadge+'</div>'
                        + (t.rationale ? '<div class="tip-reason">'+esc(t.rationale)+'</div>' : '')
                        +'</div>'
                        +'<span class="tip-conf-pill '+tipConfClass(t.confidence||0)+'">'+(t.confidence||0)+'%</span>'
                        +'</div>';
                });
                tipsHtml += '</details>';
            }
            tipsHtml += '</div>';
        }

        /* Analysis box wrapping - wrap with data-* attrs for the language toggle partial */
        var langToggleBtns = isAi
            ? '<div class="lang-toggle" role="group" aria-label="Analysis language">'
                +'<button type="button" class="lang-btn active" data-lang="en">🇬🇧 EN</button>'
                +'<button type="button" class="lang-btn" data-lang="pidgin">🇳🇬 Pidgin</button>'
                +'<button type="button" class="lang-btn" data-lang="swahili">🇰🇪 Swahili</button>'
              +'</div>'
            : '';

        var analysisHtml = '<div class="analysis-box"'
            + (isAi
                ? ' data-pred-id="'+esc(String(p.id||''))+'"'
                  +' data-en="'+esc(p.analysis||'')+'"'
                  +' data-pidgin="'+esc(p.analysis_pidgin||'')+'"'
                  +' data-swahili="'+esc(p.analysis_swahili||'')+'"'
                : '')
            +'>'
            +'<div class="analysis-head" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">'
            +'<span>'+(isAi ? '🤖 AI Match Analysis' : '📈 Statistical Analysis')+'</span>'
            + (isAi ? '<span style="margin-left:auto;">'+langToggleBtns+'</span>' : '')
            +'</div>'
            +'<p class="analysis-text" data-role="body">'+esc(bodyText)+'</p>'
            + (tipText ? '<div class="tip-callout"><span class="tip-icon">💡</span><span class="tip-text" data-role="tip">'+esc(tipText)+'</span></div>' : '')
            +'</div>'
            + tipsHtml
            + insightHtml(p.insight);

        /* Form dots */
        function formDots(form) {
            if (!form || !form.length) return '';
            return '<span class="form-dots">'
                + form.map(function(r) {
                    var cls = r === 'W' ? 'fd-w' : (r === 'L' ? 'fd-l' : 'fd-d');
                    return '<span class="form-dot '+cls+'" title="'+r+'"></span>';
                }).join('')
                + '</span>';
        }

        /* Result badge */
        var resultBadge = '';
        if (p.was_correct === true) {
            resultBadge = '<span class="result-badge-card rb-win">✓ Won</span>';
        } else if (p.was_correct === false) {
            resultBadge = '<span class="result-badge-card rb-loss">✗ Lost</span>';
        }

        /* Share text */
        var shareText = esc(m.home_team||'?') + ' vs ' + esc(m.away_team||'?')
            + ' - ' + verdict
            + ' (' + (p.confidence || 'MEDIUM') + ' confidence)'
            + ' | TavsScore.com/picks';
        var shareBtnId = 'sb-' + (p.id || Math.random());

        /* Slug for the per-match SEO page */
        function slugify(s) {
            return String(s||'').toLowerCase()
                .replace(/[^a-z0-9\s-]/g,'')
                .trim().replace(/\s+/g,'-').replace(/-+/g,'-');
        }
        var detailUrl = m.api_id
            ? '/predictions/' + slugify((m.home_team||'') + ' vs ' + (m.away_team||'')) + '-' + m.api_id
            : null;
        var detailLink = detailUrl
            ? '<a href="'+detailUrl+'" class="pc-detail-link" aria-label="Full prediction page">→</a>'
            : '';

        var confidence = Math.round(Number(p.confidence || best.value || 0));
        var statusText = m.status && finishedSet.indexOf(m.status) !== -1
            ? (m.home_score != null ? 'Final score: '+esc(m.home_score)+'–'+esc(m.away_score) : 'Final result')
            : (m.status && liveSet.indexOf(m.status) !== -1 ? 'Live now' : 'Upcoming match');

        return '<article class="signal-card fade-up">'
            +'<div class="signal-time"><strong>'+esc(fmtTime(m.match_time))+'</strong><span>'+esc(m.status || 'Scheduled')+'</span></div>'
            +'<div class="signal-clubs">'
            +'<div>'+clubCrest(m.home_team_logo, m.home_team, false)+clubCrest(m.away_team_logo, m.away_team, true)+'</div>'
            +'<div class="signal-team-names"><strong>'+esc(m.home_team||'?')+formDots(p.home_form)+'</strong><span>vs</span><strong>'+esc(m.away_team||'?')+formDots(p.away_form)+'</strong><div class="signal-state">'+statusText+'</div></div>'
            +'</div>'
            +'<div class="signal-pick"><div class="signal-pick-label">Model signal</div><div class="signal-pick-value">'+verdict+'</div><div class="signal-actions">'+resultBadge+'<button class="share-btn" id="'+shareBtnId+'" onclick="sharePred(\''+shareText.replace(/'/g,"\\'")+ '\',\''+shareBtnId+'\')" title="Share">Share</button>'+detailLink+'</div></div>'
            +'<div class="signal-confidence"><div class="signal-confidence-top"><span>Confidence</span><strong>'+confidence+'%</strong></div><div class="signal-confidence-track"><div class="signal-confidence-fill" style="width:'+Math.min(100,Math.max(0,confidence))+'%"></div></div></div>'
            +'<details class="signal-details"><summary>Full match breakdown</summary><div class="signal-details-body">'+barsHtml+glHtml+analysisHtml+'</div></details>'
            +'</article>';
    }

    /* Group predictions by league and render */
    function renderList(preds) {
        if (!preds || preds.length === 0) {
            elCount.textContent = '';
            showState('empty');
            return;
        }

        /* Sort by top-league rank then by match time */
        preds.sort(function(a,b){
            var aRank = leagueRank(a.match && a.match.league_id);
            var bRank = leagueRank(b.match && b.match.league_id);
            if (aRank !== bRank) return aRank - bRank;
            return new Date((a.match&&a.match.match_time)||0) - new Date((b.match&&b.match.match_time)||0);
        });

        /* Group by league */
        var groups = {};
        var groupOrder = [];
        preds.forEach(function(p){
            var m   = p.match || {};
            var key = (m.league||'Unknown')+'|||'+(m.league_country||'');
            if (!groups[key]) {
                groups[key] = { league:m.league, country:m.league_country, id:m.league_id, cards:[] };
                groupOrder.push(key);
            }
            groups[key].cards.push(p);
        });

        var html = '';
        groupOrder.forEach(function(key){
            var g = groups[key];
            html += '<div class="pred-league-section fade-up">'
                +'<div class="pred-league-head">'
                +'<div class="plh-left">'
                +'<span class="plh-flag">'+esc(flag(g.country))+'</span>'
                +'<div><div class="plh-name">'+esc(g.league)+'</div>'
                +(g.country?'<div class="plh-country">'+esc(g.country)+'</div>':'')
                +'</div></div>'
                +'<span class="chip chip-green" style="font-size:.65rem">'+g.cards.length+' tip'+(g.cards.length!==1?'s':'')+'</span>'
                +'</div>'
                + g.cards.map(renderCard).join('')
                +'</div>';
        });

        elList.innerHTML = html;
        elCount.textContent = preds.length + ' prediction'+(preds.length!==1?'s':'')+' today';
        if (elMetricTotal) elMetricTotal.textContent = preds.length;
        if (elMetricHigh) elMetricHigh.textContent = preds.filter(function(p){ return Number(p.confidence || 0) >= 70; }).length;
        if (elMetricResolved) elMetricResolved.textContent = preds.filter(function(p){ return p.was_correct === true || p.was_correct === false; }).length;
        showState('ready');
    }

    /* Load predictions and render */
    window.loadPredictions = function () {
        showState('loading');
        elCount.textContent = '';

        fetch(PRED_API, { headers: { Accept: 'application/json' } })
            .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
            .then(function(d){
                var preds = Array.isArray(d.data) ? d.data : [];
                if (preds.length === 0) {
                    showState('empty');
                } else {
                    renderList(preds);
                }
            })
            .catch(function(){ showState('error'); });
    };

    loadPredictions();
}());

/* ── Share prediction ── */
function sharePred(text, btnId) {
    var btn = document.getElementById(btnId);
    if (navigator.share) {
        navigator.share({ text: text, url: window.location.origin + '/picks' }).catch(function(){});
        return;
    }
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            if (btn) {
                var orig = btn.innerHTML;
                btn.innerHTML = '✓ Copied!';
                btn.classList.add('copied');
                setTimeout(function() { btn.innerHTML = orig; btn.classList.remove('copied'); }, 1800);
            }
        });
    }
}
</script>
@endpush
