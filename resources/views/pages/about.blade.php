@extends('layouts.app')

@section('title', 'About TavsScore - Football Intelligence, Built Transparently')
@section('meta_description', 'Learn how TavsScore combines football data, statistical modelling and independent AI checks to publish transparent football predictions, live scores and analysis.')
@section('canonical', route('about'))

@push('styles')
<style>
    .about-page { max-width: 920px; padding-bottom: 3.2rem; }
    .about-intro { display:grid; grid-template-columns: 1.3fr .7fr; gap:.75rem; margin-bottom:1rem; }
    .about-intro-card { padding:1rem; border:1px solid var(--border); border-radius:14px; background:var(--card); }
    .about-intro-card strong { display:block; color:#fff; font-size:1.05rem; letter-spacing:-.03em; }
    .about-intro-card span { display:block; margin-top:.25rem; color:var(--text-dim); font-size:.71rem; line-height:1.5; }
    .about-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    .about-card { padding:1.15rem; border:1px solid var(--border); border-radius:15px; background:linear-gradient(145deg,var(--card),rgba(19,29,48,.7)); }
    .about-card.wide { grid-column:1 / -1; }
    .about-card-icon { display:grid; place-items:center; width:31px; height:31px; border-radius:9px; background:rgba(16,185,129,.11); font-size:.94rem; }
    .about-card h2 { margin:.65rem 0 .42rem; color:#fff; font-size:.92rem; letter-spacing:-.02em; }
    .about-card p { margin:0; color:var(--text-dim); font-size:.76rem; line-height:1.7; }
    .about-card p + p { margin-top:.6rem; }
    .about-steps { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; margin-top:.75rem; }
    .about-step { padding:.7rem; border:1px solid rgba(255,255,255,.07); border-radius:11px; background:rgba(8,13,26,.3); }
    .about-step b { display:block; color:#6ee7b7; font-size:.61rem; letter-spacing:.08em; text-transform:uppercase; }
    .about-step span { display:block; margin-top:.25rem; color:#dbeafe; font-size:.7rem; font-weight:750; line-height:1.35; }
    .about-markets { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.7rem; }
    .about-markets span { padding:.28rem .48rem; border:1px solid rgba(148,163,184,.18); border-radius:99px; color:#cbd5e1; background:rgba(255,255,255,.025); font-size:.63rem; font-weight:750; }
    .about-note { display:flex; gap:.7rem; align-items:flex-start; margin-top:.75rem; padding:.85rem .95rem; border:1px solid rgba(251,113,133,.22); border-radius:12px; background:rgba(127,29,29,.1); color:#fecdd3; font-size:.72rem; line-height:1.58; }
    .about-note strong { color:#fff; }
    .about-cta { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.1rem; border:1px solid rgba(16,185,129,.24); border-radius:15px; background:linear-gradient(135deg,rgba(16,185,129,.13),rgba(14,116,144,.09)); }
    .about-cta h2 { margin:0; color:#fff; font-size:.92rem; }.about-cta p { margin:.22rem 0 0; color:#a7f3d0; font-size:.71rem; }
    @media(max-width:620px) { .about-intro,.about-grid,.about-steps { grid-template-columns:1fr; }.about-card.wide { grid-column:auto; }.about-cta { align-items:flex-start; flex-direction:column; } }
</style>
@endpush

@section('content')
<div class="wrap about-page">
    @include('partials.more-page-hero', [
        'moreKicker' => 'About TavsScore',
        'moreTitle' => 'Football intelligence, built in public.',
        'moreDescription' => 'Live match context, statistical modelling and transparent records—made for fans who want more than a scoreline.',
    ])

    <section class="about-intro">
        <article class="about-intro-card"><strong>Built for football fans.</strong><span>We make the information behind a match easier to read: live scores, form, performance context and clearly labelled prediction signals.</span></article>
        <article class="about-intro-card"><strong>Transparency first.</strong><span>Published picks are settled openly. Wins and losses both remain part of the record.</span></article>
    </section>

    <section class="about-grid">
        <article class="about-card">
            <span class="about-card-icon">⚽</span>
            <h2>Live coverage that has context</h2>
            <p>TavsScore covers major domestic competitions, UEFA tournaments and selected international football. Fixtures, scores, tables and player data are presented alongside the context that helps explain a match.</p>
        </article>
        <article class="about-card">
            <span class="about-card-icon">🎾</span>
            <h2>Football and Tennis, kept distinct</h2>
            <p>Football and tennis use separate prediction boards and data workflows. This keeps the models, performance records and result checks honest for each sport.</p>
        </article>

        <article class="about-card wide">
            <span class="about-card-icon">✦</span>
            <h2>How a prediction earns a place on the board</h2>
            <p>A prediction starts with the available match data—not a guess. Statistical probabilities, recent performance, match context and independent model review are used to decide whether a signal is strong enough to publish. If the evidence is weak or stale, the correct result is no pick.</p>
            <div class="about-steps">
                <div class="about-step"><b>01 · Data</b><span>Fixtures, form, results and available team context are collected.</span></div>
                <div class="about-step"><b>02 · Model</b><span>Statistical probabilities and model checks test the proposed outcome.</span></div>
                <div class="about-step"><b>03 · Publish</b><span>Only qualified signals are shown, then settled against the final score.</span></div>
            </div>
        </article>

        <article class="about-card">
            <span class="about-card-icon">📈</span>
            <h2>Specialist markets have separate rules</h2>
            <p>Goal lines, double chance, handicap, corners, draw picks, lineups and correct score are not treated as the same type of forecast. Each board has its own qualification and grading logic.</p>
            <div class="about-markets"><span>Goals</span><span>Handicap</span><span>Corners</span><span>Lineups</span><span>Double chance</span><span>Correct score</span></div>
        </article>
        <article class="about-card">
            <span class="about-card-icon">📜</span>
            <h2>The record is part of the product</h2>
            <p>Use the Daily Results, Results Archive and Track Record pages to see what was predicted and how it finished. Performance is useful only when the full sample—not selected wins—is visible.</p>
        </article>

        <article class="about-card wide">
            <span class="about-card-icon">📰</span>
            <h2>Football news with something to add</h2>
            <p>Our newsroom covers transfer developments, club news, match build-up and football stories with useful context. Articles are quality-checked for clarity, accuracy and original value before publication.</p>
            <div class="about-note"><span>⚠</span><span><strong>Predictions are informational and for entertainment only.</strong> Football is unpredictable. A confidence figure or a past record is not a guarantee, and nothing on TavsScore is a promise of profit or a reason to risk money.</span></div>
        </article>
    </section>

    <section class="about-cta" style="margin-top:.8rem">
        <div><h2>Found something we should improve?</h2><p>We read feedback, bug reports and football ideas.</p></div>
        <a href="{{ route('contact') }}" class="btn-ts btn-green">Send a message →</a>
    </section>
</div>
@endsection
