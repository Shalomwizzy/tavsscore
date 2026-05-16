@php
if (! function_exists('extractTip')) {
    function extractTip(string $analysis): ?string
    {
        // New format: "💡 Tip: ..."
        if (preg_match('/💡\s*Tip:\s*(.+?)(?:\.|$)/iu', $analysis, $m)) {
            return trim($m[1]);
        }
        // Legacy fallback: detect "clearest tip / best bet / recommend / back" sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $analysis);
        foreach ($sentences as $s) {
            $lower = mb_strtolower($s);
            if (str_contains($lower, 'clearest tip') || str_contains($lower, 'best bet') ||
                str_contains($lower, 'recommend') || str_contains($lower, 'back ')) {
                return preg_replace('/^my\s+clearest\s+tip\s+is\s+to\s*/iu', '', trim($s));
            }
        }
        return null;
    }
}

if (! function_exists('stripTip')) {
    function stripTip(string $analysis): string
    {
        return trim(preg_replace('/\s*💡\s*Tip:[^.!?]*[.!?]?/iu', '', $analysis));
    }
}
@endphp

@extends('layouts.app')

@section('title', ($dateMeta['is_today'] ? "Today's Best Football Picks – {$date}" : "Football Picks Archive – {$dateMeta['pretty']}") . " | TavsScore")
@section('meta_description', $dateMeta['is_today']
    ? "Free daily football picks for {$date}. AI-analysed from 50+ matches across the Premier League, Champions League, La Liga, Bundesliga and more."
    : "Browse our football picks archive - see what we predicted on {$dateMeta['pretty']} and how each pick turned out. Full transparency, free forever.")
@section('og_title', ($dateMeta['is_today'] ? "Today's Best 3 Football Picks" : "Football Picks – {$dateMeta['pretty']}"))
@section('og_description', "Free daily football picks selected by AI from top European leagues.")
@section('canonical', $dateMeta['is_today'] ? url('/picks') : url('/picks?date=' . $dateMeta['iso']))

@push('styles')
<style>
    /* ── Page hero ── */
    .picks-hero {
        padding: 3.5rem 0 2.5rem;
        background:
            radial-gradient(ellipse 70% 60% at 50% -10%, rgba(245,158,11,.10), transparent),
            radial-gradient(ellipse 50% 40% at 90% 80%, rgba(16,185,129,.07), transparent);
        border-bottom: 1px solid var(--border);
    }

    .picks-eyebrow {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: 3px 12px; border-radius: 999px;
        background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.28);
        color: #fcd34d; font-size: .72rem; font-weight: 700;
        letter-spacing: .05em; text-transform: uppercase; margin-bottom: 1.1rem;
    }

    .picks-title {
        font-size: clamp(1.8rem, 5vw, 3rem); font-weight: 900; color: #fff;
        letter-spacing: -.03em; line-height: 1.1; margin-bottom: .75rem;
    }
    .picks-title .accent { color: #fcd34d; }

    .picks-subtitle {
        font-size: .95rem; color: var(--text-dim); line-height: 1.7;
        max-width: 520px; margin-bottom: 1.5rem;
    }

    .picks-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }

    /* Archive / date picker nav */
    .archive-nav {
        display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;
        margin-top:1.1rem; padding:.6rem .75rem;
        background: rgba(255,255,255,.025);
        border: 1px solid var(--border);
        border-radius: 11px;
    }
    .arch-btn {
        display:inline-flex; align-items:center; gap:.3rem;
        padding:.45rem .85rem; border-radius:8px;
        background:rgba(255,255,255,.05); border:1px solid var(--border);
        color:var(--text); text-decoration:none;
        font-size:.78rem; font-weight:700; font-family:inherit; cursor:pointer;
        transition:background 140ms, border-color 140ms;
    }
    .arch-btn:hover:not(.arch-disabled) { background:rgba(255,255,255,.1); border-color:rgba(99,179,237,.3); color:#fff; }
    .arch-disabled { opacity:.35; cursor:not-allowed; }
    .arch-today { background:rgba(16,185,129,.12); border-color:rgba(16,185,129,.28); color:#6ee7b7; margin-left:auto; }
    .arch-today:hover { background:rgba(16,185,129,.18); }
    .arch-input {
        background:rgba(8,13,26,.7); border:1px solid var(--border);
        border-radius:8px; padding:.45rem .65rem;
        color:var(--text); font-family:inherit; font-size:.82rem;
        font-weight:700; cursor:pointer;
        color-scheme: dark;
    }
    .arch-input:focus { outline:none; border-color:rgba(16,185,129,.4); box-shadow:0 0 0 3px rgba(16,185,129,.10); }

    .picks-badge {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: 4px 12px; border-radius: 999px; font-size: .73rem; font-weight: 700;
    }
    .badge-date   { background: var(--surface); border: 1px solid var(--border); color: var(--text-dim); }
    .badge-source { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.2); color: #6ee7b7; }
    .badge-free   { background: rgba(59,130,246,.1); border: 1px solid rgba(59,130,246,.25); color: #93c5fd; }

    /* ── Accuracy strip ── */
    .accuracy-strip {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 14px; padding: 1.25rem 1.5rem;
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;
    }

    .accuracy-stat {
        display: flex; align-items: center; gap: 1rem;
    }

    .accuracy-pct {
        font-size: 2.5rem; font-weight: 900; line-height: 1;
    }
    .accuracy-pct.good  { color: #6ee7b7; }
    .accuracy-pct.ok    { color: #fcd34d; }
    .accuracy-pct.low   { color: #fca5a5; }
    .accuracy-pct.none  { color: var(--text-dim); }

    .accuracy-label { font-size: .78rem; color: var(--text-dim); line-height: 1.6; }
    .accuracy-label strong { color: var(--text); display: block; font-size: .85rem; }

    .accuracy-dots { display: flex; gap: .35rem; flex-wrap: wrap; }

    .adot {
        width: 28px; height: 28px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .75rem; font-weight: 800;
    }
    .adot-win  { background: rgba(16,185,129,.18); border: 1px solid rgba(16,185,129,.35); color: #6ee7b7; }
    .adot-loss { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.25);  color: #fca5a5; }

    /* ── Cards ── */
    .picks-section { padding: 2.5rem 0 4rem; }
    .picks-grid    { display: flex; flex-direction: column; gap: 1.5rem; }

    .pick-card {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 16px; overflow: hidden;
        transition: border-color 200ms, transform 200ms;
    }
    .pick-card:hover { border-color: rgba(245,158,11,.3); transform: translateY(-2px); }
    .pick-card.result-win  { border-color: rgba(16,185,129,.35); }
    .pick-card.result-loss { border-color: rgba(239,68,68,.3); }

    .pick-card-top { padding: 1.5rem 1.75rem 0; }

    .pick-card-rank {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: 3px 10px; border-radius: 999px; font-size: .7rem;
        font-weight: 800; letter-spacing: .04em; text-transform: uppercase;
        margin-bottom: 1.1rem;
    }
    .rank-1 { background: rgba(245,158,11,.15); border: 1px solid rgba(245,158,11,.3);  color: #fcd34d; }
    .rank-2 { background: rgba(148,163,184,.1); border: 1px solid rgba(148,163,184,.2); color: #94a3b8; }
    .rank-3 { background: rgba(180,120,60,.12); border: 1px solid rgba(180,120,60,.25); color: #c4895a; }

    .pick-league-row {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: .6rem;
    }
    .pick-league-name { font-size: .73rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: .05em; }
    .pick-time        { font-size: .73rem; font-weight: 700; color: var(--text-dim); font-variant-numeric: tabular-nums; }

    .pick-match-row {
        display: flex; align-items: center; justify-content: space-between;
        gap: 1rem; margin-bottom: 1.25rem;
    }
    .pick-team      { font-size: 1.2rem; font-weight: 800; color: #fff; line-height: 1.2; flex: 1; }
    .pick-team.away { text-align: right; }
    .pick-vs        { font-size: .78rem; font-weight: 800; color: var(--text-dim); padding: 0 .5rem; flex-shrink: 0; }

    /* ── The pick callout ── */
    .pick-callout {
        background: linear-gradient(135deg, rgba(245,158,11,.12) 0%, rgba(245,158,11,.06) 100%);
        border: 1px solid rgba(245,158,11,.25); border-radius: 10px;
        padding: 1.1rem 1.4rem;
        display: flex; align-items: center; justify-content: space-between;
        gap: 1rem; margin-bottom: 1.25rem;
    }
    .pick-callout.callout-win  { background: linear-gradient(135deg, rgba(16,185,129,.12), rgba(16,185,129,.05)); border-color: rgba(16,185,129,.3); }
    .pick-callout.callout-loss { background: linear-gradient(135deg, rgba(239,68,68,.1), rgba(239,68,68,.04)); border-color: rgba(239,68,68,.25); }

    .pick-callout-label   { font-size: .68rem; font-weight: 700; color: rgba(252,211,77,.6); text-transform: uppercase; letter-spacing: .06em; margin-bottom: .25rem; }
    .pick-callout-value   { font-size: 1.25rem; font-weight: 900; color: #fcd34d; letter-spacing: -.01em; }
    .callout-win  .pick-callout-label { color: rgba(110,231,183,.6); }
    .callout-win  .pick-callout-value { color: #6ee7b7; }
    .callout-loss .pick-callout-label { color: rgba(252,165,165,.6); }
    .callout-loss .pick-callout-value { color: #fca5a5; }

    /* ── Result badge ── */
    .result-badge {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: 6px 14px; border-radius: 999px;
        font-size: .8rem; font-weight: 800; letter-spacing: .02em;
        flex-shrink: 0;
    }
    .result-win-badge  { background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.3); color: #6ee7b7; }
    .result-loss-badge { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.25); color: #fca5a5; }
    .result-pending-badge { background: rgba(107,114,128,.12); border: 1px solid rgba(107,114,128,.2); color: #64748b; }

    /* ── Confidence ── */
    .conf-badge {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: 3px 10px; border-radius: 999px; font-size: .7rem;
        font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
    }
    .conf-HIGH   { background: rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.25); color:#6ee7b7; }
    .conf-MEDIUM { background: rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.25); color:#fcd34d; }
    .conf-SOLID  { background: rgba(59,130,246,.1);  border:1px solid rgba(59,130,246,.25); color:#93c5fd; }

    .confidence-row {
        display: flex; align-items: center; gap: .75rem; margin-bottom: 0;
        flex-direction: column; align-items: flex-end; gap: .4rem;
    }

    .gl-chips  { display: flex; gap: .4rem; flex-wrap: wrap; }
    .gl-chip   { padding: 2px 9px; border-radius: 999px; font-size: .68rem; font-weight: 700; border: 1px solid; }
    .chip-yes  { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.25); color:#6ee7b7; }
    .chip-no   { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.25);  color:#fca5a5; }
    .chip-mid  { background: rgba(245,158,11,.1); border-color: rgba(245,158,11,.25); color:#fcd34d; }

    /* ── Probability bar ── */
    .prob-section { padding: 0 1.75rem; margin-bottom: 1.25rem; }
    .prob-bar-outer {
        height: 8px; border-radius: 999px; background: var(--surface);
        border: 1px solid var(--border); overflow: hidden; display: flex; margin-bottom: .45rem;
    }
    .prob-seg-home { background: #10b981; }
    .prob-seg-draw { background: #f59e0b; }
    .prob-seg-away { background: #3b82f6; }
    .prob-labels   { display: flex; justify-content: space-between; font-size: .68rem; font-weight: 700; color: var(--text-dim); }
    .prob-labels span:nth-child(1) { color: #6ee7b7; }
    .prob-labels span:nth-child(2) { color: #fcd34d; }
    .prob-labels span:nth-child(3) { color: #93c5fd; text-align: right; }

    /* ── Analysis ── */
    .pick-analysis-section { padding: 0 1.75rem; margin-bottom: 1.25rem; }
    .pick-analysis-header  {
        font-size: .68rem; font-weight: 700; color: var(--text-dim);
        text-transform: uppercase; letter-spacing: .06em; margin-bottom: .5rem;
        display: flex; align-items: center; gap: .35rem;
    }
    .pick-analysis-text { font-size: .83rem; color: var(--text); line-height: 1.75; }

    /* ── Tip callout ── */
    .pick-tip-callout {
        display: flex; align-items: flex-start; gap: .6rem;
        margin-top: .75rem; padding: .85rem 1rem;
        background: linear-gradient(135deg, rgba(245,158,11,.14), rgba(245,158,11,.06));
        border: 1px solid rgba(245,158,11,.32); border-radius: 9px;
    }
    .pick-tip-icon { font-size: 1.1rem; flex-shrink: 0; line-height: 1.4; }
    .pick-tip-body { }
    .pick-tip-label { font-size: .62rem; font-weight: 800; color: rgba(252,211,77,.65); text-transform: uppercase; letter-spacing: .07em; margin-bottom: .2rem; }
    .pick-tip-text  { font-size: .88rem; font-weight: 700; color: #fcd34d; line-height: 1.5; }

    /* ── Card footer ── */
    .pick-card-footer {
        padding: .85rem 1.75rem; border-top: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
        background: rgba(255,255,255,.015); font-size: .68rem; color: var(--text-dim);
        flex-wrap: wrap; gap: .5rem;
    }

    /* ── Yesterday section ── */
    .yesterday-section { padding: 0 0 3rem; }
    .yesterday-grid    { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-top: 1.25rem; }

    .yesterday-card {
        background: var(--card); border: 1px solid var(--border);
        border-radius: 12px; padding: 1.1rem 1.25rem;
    }
    .yesterday-card.y-win  { border-color: rgba(16,185,129,.3); }
    .yesterday-card.y-loss { border-color: rgba(239,68,68,.25); }
    .yesterday-card.y-pending { opacity: .65; }

    .y-league { font-size: .65rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: .05em; margin-bottom: .4rem; }
    .y-match  { font-size: .82rem; font-weight: 700; color: #fff; margin-bottom: .5rem; line-height: 1.3; }
    .y-pick   { font-size: .75rem; color: var(--text-dim); margin-bottom: .5rem; }
    .y-result { display: flex; align-items: center; justify-content: space-between; gap: .5rem; flex-wrap: wrap; }
    .y-score  { font-size: .8rem; font-weight: 800; color: var(--text); font-variant-numeric: tabular-nums; }

    /* ── Empty ── */
    .picks-empty { text-align: center; padding: 4rem 1rem; }
    .picks-empty-icon  { font-size: 3rem; margin-bottom: 1rem; }
    .picks-empty-title { font-size: 1.25rem; font-weight: 800; color: #fff; margin-bottom: .5rem; }
    .picks-empty-desc  { font-size: .88rem; color: var(--text-dim); line-height: 1.7; }

    /* ── FAQ ── */
    .faq-section { padding: 3rem 0 4rem; border-top: 1px solid var(--border); }
    .faq-grid    { display: grid; grid-template-columns: repeat(2,1fr); gap: 1rem; margin-top: 1.75rem; }
    .faq-item    { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem 1.4rem; }
    .faq-q       { font-size: .85rem; font-weight: 700; color: #fff; margin-bottom: .5rem; }
    .faq-a       { font-size: .8rem; color: var(--text-dim); line-height: 1.7; }

    @media (max-width: 768px) {
        .yesterday-grid { grid-template-columns: 1fr; }
        .faq-grid       { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
        .pick-team { font-size: 1rem; }
        .pick-callout-value { font-size: 1.05rem; }
        .pick-card-top { padding: 1.25rem 1.25rem 0; }
        .prob-section, .pick-analysis-section { padding: 0 1.25rem; }
        .pick-card-footer { padding: .75rem 1.25rem; }
        .accuracy-strip { flex-direction: column; align-items: flex-start; }
    }

    /* Headline pick + alternatives on picks card */
    .picks-tips-block { padding:.95rem 1.1rem; border-top:1px solid var(--border); background: rgba(255,255,255,.01); }

    .pt-headline {
        background: linear-gradient(135deg, rgba(16,185,129,.10), rgba(16,185,129,.03));
        border: 1px solid rgba(16,185,129,.28);
        border-radius: 10px; padding: .8rem .95rem;
    }
    .pt-headline-label { font-size:.66rem; font-weight:800; color:#6ee7b7; text-transform:uppercase; letter-spacing:.07em; margin-bottom:.4rem; }
    .pt-headline-row { display:flex; align-items:center; gap:.75rem; }
    .pt-headline-market { font-size:1.05rem; font-weight:900; color:#fff; line-height:1.3; }
    .pt-headline-reason { font-size:.78rem; color:var(--text-dim); line-height:1.5; margin-top:.3rem; }
    .pt-headline-pill { font-size:.95rem; font-weight:900; padding:7px 14px; border-radius:999px; font-variant-numeric:tabular-nums; flex-shrink:0; }

    .pt-alts {
        margin-top:.65rem;
    }
    .pt-alts > summary {
        cursor:pointer; font-size:.74rem; font-weight:700;
        color:var(--text-dim); padding:.45rem 0;
        list-style:none; user-select:none;
    }
    .pt-alts > summary:hover { color:var(--text); }
    .pt-alts > summary::-webkit-details-marker { display:none; }
    .pt-alts[open] > summary { color:var(--text); margin-bottom:.3rem; }
    .pt-alts-list { display:flex; flex-direction:column; gap:.35rem; }

    .pt-row { display:flex; gap:.65rem; align-items:center; padding:.45rem .65rem; border-radius:7px; background:rgba(255,255,255,.025); border:1px solid var(--border); }
    .pt-row > div:first-child { flex:1; min-width:0; }
    .pt-market { font-size:.82rem; font-weight:700; color:#fff; line-height:1.3; }
    .pt-reason { font-size:.7rem; color:var(--text-dim); line-height:1.4; margin-top:2px; }
    .pt-pill { font-size:.7rem; font-weight:800; padding:3px 9px; border-radius:999px; font-variant-numeric:tabular-nums; flex-shrink:0; }
    .pt-conf-high   { background:rgba(16,185,129,.13); border:1px solid rgba(16,185,129,.28); color:#6ee7b7; }
    .pt-conf-medium { background:rgba(245,158,11,.13); border:1px solid rgba(245,158,11,.28); color:#fcd34d; }
    .pt-conf-low    { background:rgba(99,102,241,.13); border:1px solid rgba(99,102,241,.28); color:#a5b4fc; }
    .pt-info        { display:inline-block; margin-left:.3rem; font-size:.7rem; opacity:.7; cursor:help; }
    .pt-consensus   { font-size:.7rem; font-weight:700; margin-top:.45rem; padding:3px 9px; border-radius:6px; display:inline-block; }
    .pt-consensus-ok   { background:rgba(16,185,129,.10); border:1px solid rgba(16,185,129,.25); color:#6ee7b7; }
    .pt-consensus-warn { background:rgba(245,158,11,.10); border:1px solid rgba(245,158,11,.28); color:#fcd34d; }

    /* Confidence band label */
    .pt-band { font-size:.75rem; font-weight:800; margin-top:.35rem; letter-spacing:.01em; }

    /* Why-we-like-it bullet list */
    .pt-reasons { margin-top:.85rem; padding:.7rem .9rem; background:rgba(255,255,255,.025); border:1px solid var(--border); border-radius:8px; }
    .pt-reasons-label { font-size:.66rem; color:var(--text-dim); font-weight:800; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; }
    .pt-reasons-list  { margin:0; padding:0 0 0 1.05rem; color:var(--text); font-size:.78rem; line-height:1.65; }
    .pt-reasons-list li { margin-bottom:.2rem; }

    /* Mobile: stack pill below market name on narrow screens so nothing wraps awkwardly */
    @media (max-width: 480px) {
        .pt-headline-row { flex-direction: column; align-items: stretch; gap: .5rem; }
        .pt-headline-pill { align-self: flex-start; }
        .pt-headline-market { font-size: .95rem; }
        .pt-consensus { display: block; margin: .35rem 0 0 !important; }
        .accuracy-strip { flex-direction: column; align-items: flex-start; gap: .65rem; }
        .picks-tips-block { padding: .75rem .85rem; }
        .pt-reasons-list { font-size: .82rem; }
    }

    /* Streak widget */
    .streak-bar {
        display:flex; align-items:center; gap:.85rem; flex-wrap:wrap;
        padding:.85rem 1.1rem; border-radius:12px;
        margin-bottom:1.5rem; font-size:.86rem;
    }
    .streak-bar.win  { background:linear-gradient(135deg,rgba(245,158,11,.16),rgba(245,158,11,.04)); border:1px solid rgba(245,158,11,.32); color:#fcd34d; }
    .streak-bar.loss { background:linear-gradient(135deg,rgba(239,68,68,.10),rgba(239,68,68,.03));  border:1px solid rgba(239,68,68,.25);   color:#fca5a5; }
    .streak-bar.cool { background:rgba(255,255,255,.03); border:1px solid var(--border); color:var(--text-dim); }
    .streak-num   { font-size:1.45rem; font-weight:900; line-height:1; }
    .streak-best  { margin-left:auto; font-size:.74rem; color:var(--text-dim); font-weight:700; }

    /* Language toggle */
    .pick-analysis-header { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; }
    .lang-toggle {
        display:inline-flex; gap:2px; margin-left:auto;
        background:rgba(255,255,255,.04); border:1px solid var(--border);
        border-radius:7px; padding:2px;
    }
    .lang-btn {
        background:transparent; border:none; cursor:pointer;
        font-family:inherit; font-size:.66rem; font-weight:700;
        padding:3px 7px; border-radius:5px; color:var(--text-dim);
        transition:background 140ms, color 140ms;
    }
    .lang-btn:hover:not(:disabled) { color:var(--text); background:rgba(255,255,255,.05); }
    .lang-btn.active { background:rgba(16,185,129,.18); color:#6ee7b7; }
    .lang-btn:disabled { opacity:.5; cursor:wait; }
    @media (max-width:520px) {
        .lang-toggle { margin-left:0; width:100%; justify-content:center; margin-top:.45rem; }
        .lang-btn { padding:4px 9px; font-size:.7rem; }
    }
</style>
@endpush

@section('content')

{{-- ── Hero ── --}}
<section class="picks-hero">
    <div class="wrap">
        <div class="picks-eyebrow">⭐ Free Daily Picks</div>
        <h1 class="picks-title">
            @if($dateMeta['is_today'])Today's Best <span class="accent">3 Picks</span>
            @else<span class="accent">3 Picks</span> from {{ $dateMeta['pretty'] }}
            @endif
        </h1>
        <p class="picks-subtitle">
            @if($dateMeta['is_today'])
                Every day our AI analyses {{ $count > 0 ? $count : "today's" }} matches and surfaces only the predictions it is most confident about (65%+ confidence, no draws). Some days that's 3 picks, some days fewer - quality over quantity.
            @else
                Browsing our archive - these are the picks we put out on this day, plus the actual results.
            @endif
        </p>
        <div class="picks-meta">
            <span class="picks-badge badge-date">📅 {{ $date }}</span>
            @if($dateMeta['is_today'])
                <span class="picks-badge badge-source">🤖 AI + Poisson Model</span>
                <span class="picks-badge badge-free">✅ Always Free</span>
            @else
                <a href="{{ route('picks.index') }}" class="picks-badge badge-source" style="text-decoration:none;">↩ Back to today</a>
            @endif
        </div>

        {{-- ── Date picker / archive nav ── --}}
        <form method="GET" action="{{ route('picks.index') }}" class="archive-nav" id="archive-nav">
            <a href="{{ route('picks.index', ['date' => $dateMeta['prev_iso']]) }}"
               class="arch-btn"
               aria-label="Previous day">‹ Prev day</a>

            <input type="date" name="date" id="archive-date"
                   value="{{ $dateMeta['iso'] }}"
                   min="{{ $dateMeta['min_iso'] }}"
                   max="{{ $dateMeta['max_iso'] }}"
                   class="arch-input"
                   aria-label="Select a date"
                   onchange="this.form.submit()">

            @if($dateMeta['next_iso'])
            <a href="{{ route('picks.index', ['date' => $dateMeta['next_iso']]) }}"
               class="arch-btn"
               aria-label="Next day">Next day ›</a>
            @else
            <span class="arch-btn arch-disabled">Next day ›</span>
            @endif

            @unless($dateMeta['is_today'])
            <a href="{{ route('picks.index') }}" class="arch-btn arch-today">Jump to today</a>
            @endunless
        </form>
    </div>
</section>

{{-- ── Picks section ── --}}
<section class="picks-section">
    <div class="wrap">

        {{-- ── Streak ── --}}
        @if($streak['count'] >= 2)
        <div class="streak-bar {{ $streak['type'] === 'win' ? 'win' : 'loss' }}">
            @if($streak['type'] === 'win')
                <span style="font-size:1.4rem;">🔥</span>
                <div>
                    <div><span class="streak-num">{{ $streak['count'] }}</span> wins in a row</div>
                    <div style="font-size:.7rem; opacity:.78; margin-top:2px;">Hot streak - last {{ $streak['count'] }} settled picks all hit.</div>
                </div>
            @else
                <span style="font-size:1.4rem;">📉</span>
                <div>
                    <div><span class="streak-num">{{ $streak['count'] }}</span> misses in a row</div>
                    <div style="font-size:.7rem; opacity:.78; margin-top:2px;">Variance happens - every model has cold spells.</div>
                </div>
            @endif
            @if($streak['best'] > 0)
                <span class="streak-best">🏆 Best ever: {{ $streak['best'] }} wins</span>
            @endif
        </div>
        @endif

        {{-- ── 7-day accuracy strip ── --}}
        @if($accuracy['total'] > 0)
        @php
            $pct      = $accuracy['pct'];
            $pctClass = $pct >= 60 ? 'good' : ($pct >= 45 ? 'ok' : 'low');
        @endphp
        <div class="accuracy-strip">
            <div class="accuracy-stat">
                <div class="accuracy-pct {{ $pctClass }}">{{ $pct }}%</div>
                <div class="accuracy-label">
                    <strong>Last 7 Days Accuracy</strong>
                    {{ $accuracy['correct'] }} correct out of {{ $accuracy['total'] }} picks
                </div>
            </div>
            <div class="accuracy-dots">
                {{-- Show dots for recent resolved picks (max 21 = 7 days × 3) --}}
                @php
                    $recentResolved = \App\Models\Prediction::query()
                        ->with('match')
                        ->where('is_daily_pick', true)
                        ->whereNotNull('was_correct')
                        ->whereHas('match', fn($q) => $q->where('match_time', '>=', now(config('app.timezone'))->subDays(7)->startOfDay())->where('match_time', '<', now(config('app.timezone'))->startOfDay()))
                        ->orderByDesc('created_at')
                        ->limit(21)
                        ->get();
                @endphp
                @foreach($recentResolved as $rp)
                <div class="adot {{ $rp->was_correct ? 'adot-win' : 'adot-loss' }}"
                     title="{{ $rp->match?->home_team }} vs {{ $rp->match?->away_team }}: {{ $rp->predicted_outcome }}">
                    {{ $rp->was_correct ? '✓' : '✗' }}
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Picks for selected date ── --}}
        @if($formatted->isEmpty())
        <div class="picks-empty">
            @if($dateMeta['is_today'])
                <div class="picks-empty-icon">🔍</div>
                <div class="picks-empty-title">No high-confidence picks today</div>
                <p class="picks-empty-desc">
                    Our AI reviewed today's matches but none cleared the 65% confidence threshold.
                    We'd rather show you nothing than a bad tip - check back later as more match data comes in.
                </p>
            @else
                <div class="picks-empty-icon">📅</div>
                <div class="picks-empty-title">No picks for {{ $dateMeta['pretty'] }}</div>
                <p class="picks-empty-desc">
                    We didn't publish daily picks on this date. Use the date picker above to browse another day,
                    or <a href="{{ route('picks.index') }}" style="color:var(--green); text-decoration:none; font-weight:700;">jump back to today's picks →</a>
                </p>
            @endif
        </div>
        @else
        <div class="picks-grid">
            @foreach($formatted as $pick)
            @php
                $rankClass   = 'rank-' . $pick['rank'];
                $rankLabel   = match($pick['rank']) { 1 => '👑 Pick of the Day', 2 => '⭐ Pick #2', 3 => '🔥 Pick #3', default => 'Pick' };
                $confClass   = 'conf-' . $pick['confidence'];
                $o25Class    = $pick['o25'] >= 60 ? 'chip-yes' : ($pick['o25'] <= 40 ? 'chip-no' : 'chip-mid');
                $bttsClass   = $pick['btts'] >= 60 ? 'chip-yes' : ($pick['btts'] <= 40 ? 'chip-no' : 'chip-mid');
                $resultClass = $pick['was_correct'] === true ? 'result-win' : ($pick['was_correct'] === false ? 'result-loss' : '');
                $calloutClass = $pick['was_correct'] === true ? 'callout-win' : ($pick['was_correct'] === false ? 'callout-loss' : '');
            @endphp
            <article class="pick-card {{ $resultClass }}">
                <div class="pick-card-top">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.1rem;">
                        <div class="pick-card-rank {{ $rankClass }}" style="margin-bottom:0;">{{ $rankLabel }}</div>
                        @if($pick['was_correct'] === true)
                            <span class="result-badge result-win-badge">✓ Correct</span>
                        @elseif($pick['was_correct'] === false)
                            <span class="result-badge result-loss-badge">✗ Incorrect</span>
                        @elseif(in_array($pick['match']['status'] ?? '', ['FT','AET','PEN']))
                            <span class="result-badge result-pending-badge">Checking…</span>
                        @endif
                    </div>

                    <div class="pick-league-row">
                        <span class="pick-league-name">{{ $pick['match']['league'] }}</span>
                        <span class="pick-time">
                            @if(in_array($pick['match']['status'], ['1H','2H','ET','HT','BT','P','LIVE']))
                                <span style="color:#ef4444; display:inline-flex; align-items:center; gap:.35rem;">
                                    <span class="live-dot"></span>
                                    @if($pick['live_score'])
                                        <strong style="color:#fff; font-variant-numeric:tabular-nums;">{{ $pick['live_score'] }}</strong>
                                    @endif
                                    LIVE
                                    @if($pick['match']['elapsed'])
                                        · {{ $pick['match']['elapsed'] }}'
                                    @elseif($pick['match']['status'] === 'HT')
                                        · HT
                                    @endif
                                </span>
                            @elseif(in_array($pick['match']['status'], ['FT','AET','PEN']))
                                <strong style="color:#fff; font-variant-numeric:tabular-nums;">{{ $pick['live_score'] ?? $pick['actual_score'] ?? '-' }}</strong>
                                <span style="color:var(--text-dim); margin-left:.3rem;">FT</span>
                            @else
                                KO {{ $pick['match']['time'] }}
                            @endif
                        </span>
                    </div>

                    <div class="pick-match-row">
                        <span class="pick-team home">{{ $pick['match']['home'] }}</span>
                        <span class="pick-vs">vs</span>
                        <span class="pick-team away">{{ $pick['match']['away'] }}</span>
                    </div>

                    <div class="pick-callout {{ $calloutClass }}">
                        <div>
                            <div class="pick-callout-label">Our Prediction</div>
                            <div class="pick-callout-value">{{ $pick['pick_emoji'] }} {{ $pick['pick_label'] }}</div>
                        </div>
                        <div class="confidence-row">
                            <span class="conf-badge {{ $confClass }}">{{ $pick['confidence'] }} Confidence</span>
                            <div class="gl-chips">
                                <span class="gl-chip {{ $o25Class }}">O2.5 {{ number_format($pick['o25'],0) }}%</span>
                                <span class="gl-chip {{ $bttsClass }}">BTTS {{ number_format($pick['btts'],0) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="prob-section">
                    <div class="prob-bar-outer">
                        <div class="prob-seg-home" style="width:{{ number_format($pick['hw'],1) }}%"></div>
                        <div class="prob-seg-draw"  style="width:{{ number_format($pick['d'],1) }}%"></div>
                        <div class="prob-seg-away"  style="width:{{ number_format($pick['aw'],1) }}%"></div>
                    </div>
                    <div class="prob-labels">
                        <span>{{ $pick['match']['home'] }} {{ number_format($pick['hw'],1) }}%</span>
                        <span>Draw {{ number_format($pick['d'],1) }}%</span>
                        <span>{{ $pick['match']['away'] }} {{ number_format($pick['aw'],1) }}%</span>
                    </div>
                </div>

                {{-- ── Headline pick + alternatives ── --}}
                @if(!empty($pick['tips']))
                @php
                    $headline = $pick['tips'][0];
                    $alternatives = array_slice($pick['tips'], 1);
                    $hConf = (int) ($headline['confidence'] ?? 0);
                    $hClass = $hConf >= 70 ? 'pt-conf-high' : ($hConf >= 60 ? 'pt-conf-medium' : 'pt-conf-low');
                    $hVerifiable = ! array_key_exists('verifiable', $headline) || $headline['verifiable'] === true;
                    $band     = \App\Support\PickHelpers::confidenceBand($hConf);
                    $reasons  = \App\Support\PickHelpers::reasonBullets($pick['analysis'] ?? null, 3);
                @endphp
                <div class="picks-tips-block">
                    <div class="pt-headline">
                        <div class="pt-headline-label">🎯 Best Pick</div>
                        <div class="pt-headline-row">
                            <div style="min-width:0; flex:1;">
                                <div class="pt-headline-market">
                                    {{ $headline['market'] ?? '-' }}
                                    @unless($hVerifiable)
                                    <span class="pt-info" title="Corners/cards aren't auto-verified - informational only">ℹ️</span>
                                    @endunless
                                </div>
                                <div class="pt-band" style="color:{{ $band['color'] }};">
                                    {{ $band['emoji'] }} {{ $band['label'] }} confidence
                                </div>
                                @if(!empty($headline['rationale']))
                                <div class="pt-headline-reason">{{ $headline['rationale'] }}</div>
                                @endif
                                @if(array_key_exists('market_implied', $headline))
                                <div class="pt-consensus {{ ($headline['market_agrees'] ?? false) ? 'pt-consensus-ok' : 'pt-consensus-warn' }}">
                                    {{ ($headline['market_agrees'] ?? false) ? '🤝 Bookmakers agree' : '⚠️ Market disagrees' }}
                                    <span style="opacity:.78;">- bookies say {{ $headline['market_implied'] }}%</span>
                                </div>
                                @endif
                                @if(array_key_exists('gemini_agrees', $headline))
                                <div class="pt-consensus {{ ($headline['gemini_agrees'] ?? false) ? 'pt-consensus-ok' : 'pt-consensus-warn' }}" style="margin-left:.35rem;">
                                    {{ ($headline['gemini_agrees'] ?? false) ? '🤖🤖🤖 All 3 AIs agree' : '⚠️ AIs disagree' }}
                                </div>
                                @endif
                                @if(!empty($pick['historical_pct']))
                                <div class="pt-consensus" style="margin-left:.35rem;color:rgba(180,180,210,.85);">
                                    📊 Historically {{ $pick['historical_pct'] }}% correct at this confidence level
                                </div>
                                @endif
                                @if(!empty($pick['odds_movement']))
                                @php $mov = $pick['odds_movement']; @endphp
                                <div class="pt-consensus {{ $mov['direction'] === 'with' ? 'pt-consensus-ok' : ($mov['direction'] === 'against' ? 'pt-consensus-warn' : '') }}" style="margin-left:.35rem;">
                                    @if($mov['direction'] === 'with')
                                        📈 Market drifting with this pick
                                    @elseif($mov['direction'] === 'against')
                                        📉 Market drifting against this pick
                                    @else
                                        ➡️ Market holding steady
                                    @endif
                                    <span style="opacity:.72;">({{ $mov['delta'] > 0 ? '+' : '' }}{{ $mov['delta'] }}pp since prediction)</span>
                                </div>
                                @endif
                            </div>
                            <span class="pt-headline-pill {{ $hClass }}">{{ $hConf }}%</span>
                        </div>
                    </div>

                    @if(!empty($reasons))
                    <div class="pt-reasons">
                        <div class="pt-reasons-label">💡 Why we like it</div>
                        <ul class="pt-reasons-list">
                            @foreach($reasons as $r)
                            <li>{{ $r }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if(!empty($alternatives))
                    <details class="pt-alts">
                        <summary>▸ Show {{ count($alternatives) }} alternative{{ count($alternatives) !== 1 ? 's' : '' }}</summary>
                        <div class="pt-alts-list">
                            @foreach($alternatives as $alt)
                            @php
                                $aConf = (int) ($alt['confidence'] ?? 0);
                                $aClass = $aConf >= 70 ? 'pt-conf-high' : ($aConf >= 60 ? 'pt-conf-medium' : 'pt-conf-low');
                                $aVerifiable = ! array_key_exists('verifiable', $alt) || $alt['verifiable'] === true;
                            @endphp
                            <div class="pt-row">
                                <div style="min-width:0;">
                                    <div class="pt-market">
                                        {{ $alt['market'] ?? '-' }}
                                        @unless($aVerifiable)<span class="pt-info" title="Informational only">ℹ️</span>@endunless
                                    </div>
                                    @if(!empty($alt['rationale']))
                                    <div class="pt-reason">{{ $alt['rationale'] }}</div>
                                    @endif
                                </div>
                                <span class="pt-pill {{ $aClass }}">{{ $aConf }}%</span>
                            </div>
                            @endforeach
                        </div>
                    </details>
                    @endif
                </div>
                @endif

                @if($pick['is_ai'] && !blank($pick['analysis']))
                @php
                    $tip      = extractTip($pick['analysis']);
                    $bodyText = $tip ? stripTip($pick['analysis']) : $pick['analysis'];
                @endphp
                <div class="pick-analysis-section"
                     data-pred-id="{{ $pick['id'] }}"
                     data-en="{{ $pick['analysis'] }}"
                     data-pidgin="{{ $pick['analysis_pidgin'] }}"
                     data-swahili="{{ $pick['analysis_swahili'] }}"
                     data-french="{{ $pick['analysis_french'] ?? '' }}">
                    <div class="pick-analysis-header">
                        🤖 <span>AI Analysis</span>
                        <div class="lang-toggle" role="group" aria-label="Analysis language">
                            <button type="button" class="lang-btn active" data-lang="en">🇬🇧 EN</button>
                            <button type="button" class="lang-btn" data-lang="pidgin">🇳🇬 Pidgin</button>
                            <button type="button" class="lang-btn" data-lang="swahili">🇰🇪 Swahili</button>
                            <button type="button" class="lang-btn" data-lang="french">🇫🇷 French</button>
                        </div>
                    </div>
                    @if(!blank($bodyText))
                    <div class="pick-analysis-text" data-role="body">{{ $bodyText }}</div>
                    @endif
                    @if($tip)
                    <div class="pick-tip-callout">
                        <span class="pick-tip-icon">💡</span>
                        <div class="pick-tip-body">
                            <div class="pick-tip-label">Top Tip</div>
                            <div class="pick-tip-text" data-role="tip">{{ ucfirst($tip) }}</div>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                @if(!empty($pick['likely_scores']))
                <div style="padding:0 1.75rem; margin-bottom:.75rem; font-size:.72rem; color:var(--text-dim);">
                    🎯 Likely scores:
                    @foreach($pick['likely_scores'] as $s)
                        <span style="background:rgba(255,255,255,.07); border-radius:4px; padding:1px 7px; margin-left:3px; color:var(--text);">
                            {{ $s['score'] }} <span style="opacity:.6;">({{ $s['pct'] }}%)</span>
                        </span>
                    @endforeach
                </div>
                @endif

                <div class="pick-card-footer" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem;">
                    <span>📊 Based on form, H2H &amp; tactical analysis</span>
                    @php
                        $waMatchLabel = ($pick['match']['home_team'] ?? '') . ' vs ' . ($pick['match']['away_team'] ?? '');
                        $waTip = $pick['pick_label'] ?? $pick['predicted_outcome'] ?? '';
                        $waConf = isset($pick['confidence']) ? " ({$pick['confidence']}%)" : '';
                        $waMsg = urlencode("⭐ TavsScore Daily Pick\n{$waMatchLabel}\nTip: {$waTip}{$waConf}\n🔗 " . url('/picks'));
                    @endphp
                    <a href="https://wa.me/?text={{ $waMsg }}" target="_blank" rel="noopener"
                       style="display:inline-flex;align-items:center;gap:.3rem;background:#25D366;color:#fff;font-size:.65rem;font-weight:700;padding:4px 10px;border-radius:999px;text-decoration:none;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.558 4.118 1.534 5.845L.055 23.454l5.742-1.505A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.372l-.36-.214-3.71.972.989-3.615-.234-.371A9.818 9.818 0 012.182 12C2.182 6.575 6.575 2.182 12 2.182c5.424 0 9.818 4.393 9.818 9.818 0 5.424-4.394 9.818-9.818 9.818z"/></svg>
                        Share on WhatsApp
                    </a>
                </div>
            </article>

            @if(!$loop->last)
            <div style="text-align:center; padding:.5rem 0; min-height:30px;">
                {{-- Google AdSense in-feed slot --}}
                {{-- <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-XXXXXXX" data-ad-slot="YYYYYYY" data-ad-format="fluid" data-ad-layout-key="-fb+5w+4e-db+86"></ins> --}}
            </div>
            @endif
            @endforeach
        </div>
        @endif

        {{-- ── Newsletter signup ── --}}
        <div style="margin-top:2rem;">
            @include('partials.newsletter-form', ['source' => 'picks'])
        </div>

        {{-- ── Disclaimer ── --}}
        <div style="margin-top:2.5rem; padding:1.25rem 1.5rem; background:var(--card); border:1px solid var(--border); border-radius:12px; font-size:.78rem; color:var(--text-dim); line-height:1.75;">
            <strong style="color:var(--text);">Important:</strong>
            These predictions are generated by a statistical AI model for entertainment purposes only.
            They are <strong style="color:var(--text);">not</strong> financial advice, betting tips, or guaranteed outcomes.
            Football is unpredictable - no model predicts results with certainty.
            Please gamble responsibly. If gambling is causing you harm, visit
            <a href="https://www.begambleaware.org" target="_blank" rel="noopener noreferrer" style="color:#93c5fd;">BeGambleAware.org</a>.
        </div>
    </div>
</section>

{{-- ── Yesterday's Results ── --}}
@if($yesterdayFormatted->isNotEmpty())
<section class="yesterday-section">
    <div class="wrap">
        <div class="section-label">Yesterday's Picks</div>
        <h2 class="section-title" style="font-size:1.25rem;">How did we do?</h2>
        <div class="yesterday-grid">
            @foreach($yesterdayFormatted as $pick)
            @php
                $yClass = $pick['was_correct'] === true ? 'y-win' : ($pick['was_correct'] === false ? 'y-loss' : 'y-pending');
            @endphp
            <div class="yesterday-card {{ $yClass }}">
                <div class="y-league">{{ $pick['match']['league'] }}</div>
                <div class="y-match">{{ $pick['match']['home'] }} vs {{ $pick['match']['away'] }}</div>
                <div class="y-pick">{{ $pick['pick_emoji'] }} {{ $pick['pick_label'] }}</div>
                <div class="y-result">
                    @if($pick['was_correct'] === true)
                        <span class="result-badge result-win-badge" style="font-size:.72rem;">✓ Correct</span>
                    @elseif($pick['was_correct'] === false)
                        <span class="result-badge result-loss-badge" style="font-size:.72rem;">✗ Incorrect</span>
                    @else
                        <span class="result-badge result-pending-badge" style="font-size:.72rem;">Pending</span>
                    @endif
                    @if($pick['actual_score'])
                        <span class="y-score">{{ $pick['actual_score'] }}</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ── FAQ ── --}}
<section class="faq-section">
    <div class="wrap">
        <div class="section-label">Questions</div>
        <h2 class="section-title">How do the daily picks work?</h2>
        <div class="faq-grid">
            <div class="faq-item">
                <div class="faq-q">How are the 3 picks chosen each day?</div>
                <div class="faq-a">
                    Our system analyses every match from top European leagues - Premier League,
                    Champions League, La Liga, Bundesliga, Serie A, Ligue 1 and more. It scores
                    each prediction by the margin of confidence between the likely outcome and
                    the next most probable one, then selects the top 3 with variety.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-q">What model powers the predictions?</div>
                <div class="faq-a">
                    A two-stage system: first, Poisson distribution calculates goal probabilities
                    from each team's recent attack and defence stats. Then three independent AI engines
                    each enrich that with knowledge of current form, head-to-head history, injuries and
                    tactical context — and must all reach the same conclusion before any tip is published.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-q">Why do you show when picks are wrong?</div>
                <div class="faq-a">
                    Because transparency is the only honest thing to do. Every prediction site
                    has losing days - the ones that hide results are the ones you should not trust.
                    We show wins and losses equally so you can judge for yourself.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-q">Is this free? Will it ever cost money?</div>
                <div class="faq-a">
                    Yes - free, forever. TavsScore is supported by advertising.
                    We will never charge for predictions, lock picks behind a paywall,
                    or promise accuracy for money. If you see any site doing that,
                    treat it with extreme caution.
                </div>
            </div>
        </div>
    </div>
</section>

@include('partials.language-toggle')

<div class="wrap" style="padding-bottom:2rem;">
    <div style="text-align:center; font-size:.7rem; color:var(--text-dim); line-height:1.75; padding:.875rem 1rem; border-top:1px solid var(--border); margin-top:2rem;">
        🔞 <strong style="color:var(--text-dim);">18+ only.</strong>
        These picks are for <strong style="color:var(--text-dim);">entertainment purposes only</strong> and do not constitute financial or betting advice.
        AI predictions are never guaranteed. Past accuracy is not a guarantee of future results.
        Please gamble responsibly — never stake money you cannot afford to lose.
    </div>
</div>

@endsection
