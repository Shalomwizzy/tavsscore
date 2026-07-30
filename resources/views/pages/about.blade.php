@extends('layouts.app')
@section('title', 'About TavsScore - AI Football Predictions After Confirmed Lineups')
@section('meta_description', 'TavsScore uses triple-AI validation across Groq, Gemini, and Mistral to generate the most accurate football predictions online. Lineup picks, rollover challenge, 8 specialist markets, push alerts.')
@section('canonical', route('about'))

@section('content')
<div class="wrap" style="max-width:740px; padding-top:2.5rem; padding-bottom:3rem;">

    <nav style="font-size:.72rem; color:var(--text-dim); margin-bottom:1.75rem;">
        <a href="{{ route('home.index') }}" style="color:var(--text-dim); text-decoration:none;">Home</a>
        <span style="margin:0 .4rem; color:var(--text-muted)">›</span>
        <span>About</span>
    </nav>

    <h1 style="font-size:2rem; font-weight:900; color:#fff; letter-spacing:-.03em; margin-bottom:.5rem;">About TavsScore</h1>
    <p style="font-size:.95rem; color:var(--text-dim); line-height:1.7; margin-bottom:2.5rem;">Built by a football fan, for football fans. No corporate team, no VC money, just a serious love of the game and a belief that fans deserve better tools.</p>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Why we built this</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            Most football apps are either a nightmare to load, buried in pop-ups, or give you scores three minutes late. We got frustrated with that. So we built TavsScore, fast, clean live scores with actually useful predictions, and real football writing that is not just copied from a press release.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            The name comes from the founder's handle. There is no boardroom behind this. Just a love of the game and a determination to build tools that are genuinely worth using.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Live scores and coverage</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            Live scores and fixtures from the competitions that matter most, the Premier League, UEFA Champions League, La Liga, Serie A, Bundesliga, Ligue 1, Europa League, Conference League, and around 20 other top-flight competitions worldwide.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            We pull real-time data from API-Football. Every score, kickoff time, and match status updates live, including minute-by-minute events for ongoing matches.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">How our predictions actually work</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            Each day we run up to 50 of the day's most important fixtures through a three-stage AI pipeline that we call triple validation.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            <strong style="color:#fff;">Stage 1, Poisson model.</strong> We calculate expected goals for both sides based on recent attack and defensive records. This produces statistically grounded baseline probabilities for the result, plus goal-line markets like Over 2.5 and both teams to score.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            <strong style="color:#fff;">Stage 2, Groq LLaMA.</strong> The baseline is handed to a large language model running on Groq's infrastructure. It layers in form, head-to-head history, squad strength, and match stakes, then produces a first prediction with rationale.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            <strong style="color:#fff;">Stage 3, Gemini + Mistral cross-check.</strong> The Groq prediction is sent to both Google Gemini and Mistral AI independently. If all three agree, that prediction goes out with high confidence. If they disagree, the prediction is flagged or held back.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            Three AI models, independently validated, before anything reaches you. That is why our confidence scores are meaningful rather than made up.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Lineup Picks, our most accurate predictions</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            The moment a club's official starting 11 is confirmed, usually 60 to 75 minutes before kickoff, our AI re-runs the full analysis with the actual squad data. These are the Lineup Picks, and they are consistently more accurate than the midnight predictions because they account for who is actually playing.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            Up to 10 Lineup Picks per day appear on a dedicated page, updated in real time as lineups are confirmed throughout the day. Past picks show the final result and whether the prediction was correct.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">8 specialist pick markets</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            Beyond the main predictions, TavsScore publishes up to 10 specialist picks per market each day. Every market has its own dedicated page with calendar history so you can browse results from any previous date.
        </p>
        <ul style="font-size:.88rem; color:var(--text-dim); line-height:1.85; padding-left:1.5rem;">
            <li style="margin-bottom:.4rem;"><strong style="color:var(--text);">Draw Picks</strong>, matches where a draw is the statistically strongest outcome</li>
            <li style="margin-bottom:.4rem;"><strong style="color:var(--text);">GG Picks (Both Teams to Score)</strong>, matches where both sides are expected to find the net</li>
            <li style="margin-bottom:.4rem;"><strong style="color:var(--text);">Lineup Picks</strong>, confirmed-lineup AI re-analysis, highest accuracy</li>
            <li style="margin-bottom:.4rem;"><strong style="color:var(--text);">Over 1.5 Goals</strong>, matches very likely to produce at least two goals</li>
            <li style="margin-bottom:.4rem;"><strong style="color:var(--text);">Over 2.5 Goals</strong>, higher-scoring matches with three or more goals expected</li>
            <li style="margin-bottom:.4rem;"><strong style="color:var(--text);">Double Chance</strong>, picks covering two outcomes for additional security</li>
            <li style="margin-bottom:.4rem;"><strong style="color:var(--text);">Correct Score</strong>, specific scoreline predictions with percentage likelihood</li>
            <li style="margin-bottom:.4rem;"><strong style="color:var(--text);">Team Goals 3+</strong>, matches where one team is likely to score three or more</li>
        </ul>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-top:.875rem;">
            All picks are verified against actual results after each match finishes, so you can see the track record on any date you browse.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">The Rollover Challenge</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            The Rollover Challenge is a 10-day hypothetical accumulator that starts with a set stake and compounds the returns day by day. Each day the AI selects one pick across the 10 days, and the running total is tracked publicly so you can follow along.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            This is an entertainment feature, the stake amounts are illustrative, not a prompt to bet. It is a way of showing how a disciplined selection process performs over a sustained period, rather than cherry-picking individual wins.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Push notifications and Telegram</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            When Lineup Picks are confirmed for the day, we send a push notification to everyone subscribed through the site. You can opt in with one tap and opt out just as easily, no account required.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            We also publish picks, match alerts, and Rollover updates to our Telegram channel. Both channels are free to follow.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">The blog</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            Our blog covers match previews, tactical breakdowns, and the kind of football writing that explains the game rather than just reporting the score. Some articles are written by the editorial team; others are AI-assisted drafts that are reviewed before publishing. AI-generated articles are always labelled clearly.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            We write about what is interesting, not what is trending. If that means a deep-dive into pressing metrics when most sites are running transfer rumours, so be it.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">A word on predictions and gambling</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            Our predictions are for entertainment and informational purposes only. We are not a betting tipster service and nothing on this site should be used as the basis for placing bets. Football is famously unpredictable, that is part of what makes it brilliant. Use our predictions to enjoy the game more, not to risk money.
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
