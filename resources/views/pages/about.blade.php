@extends('layouts.app')
@section('title', 'About TavsScore — Why We Built This')
@section('meta_description', 'TavsScore was built by a football fan who was tired of bloated score apps. Here is the story behind the platform, how our predictions work, and why we think football deserves better tools.')
@section('canonical', route('about'))

@section('content')
<div class="wrap" style="max-width:740px; padding-top:2.5rem; padding-bottom:3rem;">

    <nav style="font-size:.72rem; color:var(--text-dim); margin-bottom:1.75rem;">
        <a href="{{ route('home.index') }}" style="color:var(--text-dim); text-decoration:none;">Home</a>
        <span style="margin:0 .4rem; color:var(--text-muted)">›</span>
        <span>About</span>
    </nav>

    <h1 style="font-size:2rem; font-weight:900; color:#fff; letter-spacing:-.03em; margin-bottom:.5rem;">About TavsScore</h1>
    <p style="font-size:.95rem; color:var(--text-dim); line-height:1.7; margin-bottom:2.5rem;">Built by a football fan, for football fans.</p>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Why we built this</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            Most football apps are either a nightmare to load, buried in pop-ups, or give you scores three minutes late. We got frustrated with that. So we built TavsScore — fast, clean live scores with actual useful predictions, and real football writing that is not just copied from a press release.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            The name comes from the founder's handle. There is no corporate team behind this, no VC funding, no boardroom. Just a love of the game and a belief that football fans deserve better tools.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">What we cover</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            Live scores and fixtures from the competitions that matter most: the Premier League, UEFA Champions League, La Liga, Serie A, Bundesliga, Ligue 1, Europa League, Conference League, and around 20 other top-flight competitions worldwide.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            We pull real-time data from API-Football, which gives us accuracy and coverage across all the major leagues. Every score, kickoff time, and match status updates live without you needing to reload the page.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">How our predictions actually work</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            We do not just randomly assign percentages. Each day we select up to 50 of the most important fixtures from top competitions and run them through a two-stage model.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            The first stage is a Poisson probability model that calculates expected goals for both sides based on their recent attack and defensive records. This gives us statistically grounded baseline probabilities for the result, plus goal-line markets like Over 2.5 and both teams to score.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            The second stage sends each match to a large language model that has deep knowledge of team form, head-to-head history, squad strength, and match stakes. It overrides the baseline when the statistical model does not have enough data — for example when a title contender faces a relegation side, the AI knows that gap is bigger than average numbers suggest. The result is predictions that feel like they were written by someone who actually watches the game.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">The blog</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            Our blog covers match previews, tactical breakdowns, and the kind of football writing that explains the game rather than just reporting the score. Some articles are written by the editorial team, others are AI-assisted drafts that are then reviewed before publishing. AI-generated articles are always labelled clearly.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            We write about what is interesting, not what is trending. If that means a deep-dive into pressing metrics when most sites are running transfer rumours, so be it.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">A word on predictions and gambling</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            Our predictions are for entertainment and informational purposes only. We are not a betting tipster service and nothing on this site should be used as the basis for placing bets. Football is famously unpredictable — that is part of what makes it brilliant. Use our predictions to enjoy the game more, not to risk money.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Get in touch</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:1rem;">
            Found a bug? Got a suggestion? Just want to talk football? We genuinely read every message.
        </p>
        <a href="{{ route('contact') }}" class="btn-ts btn-green" style="font-size:.82rem;">Send a message</a>
    </div>

</div>
@endsection
