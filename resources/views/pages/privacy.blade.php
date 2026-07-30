@extends('layouts.app')
@section('title', 'Privacy Policy - TavsScore')
@section('meta_description', 'How TavsScore collects and uses data. We keep it simple: we do not sell your information, we do not build profiles, and we tell you exactly what we do collect and why.')
@section('canonical', route('privacy'))

@section('content')
<div class="wrap" style="max-width:740px; padding-top:2.5rem; padding-bottom:3rem;">

    <nav style="font-size:.72rem; color:var(--text-dim); margin-bottom:1.75rem;">
        <a href="{{ route('home.index') }}" style="color:var(--text-dim); text-decoration:none;">Home</a>
        <span style="margin:0 .4rem; color:var(--text-muted)">›</span>
        <span>Privacy Policy</span>
    </nav>

    <h1 style="font-size:2rem; font-weight:900; color:#fff; letter-spacing:-.03em; margin-bottom:.5rem;">Privacy Policy</h1>
    <p style="font-size:.82rem; color:var(--text-dim); margin-bottom:2.5rem;">Last updated {{ date('F Y') }}. Written in plain English, not legalese.</p>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">The short version</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            We do not sell your data. We do not build profiles on you. We do not send marketing emails unless you specifically ask us to. The information we collect is the minimum needed to run the site and improve it over time.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            The longer version below explains the details. If something is unclear, <a href="{{ route('contact') }}" style="color:var(--green);">ask us</a> and we will explain it in plain language.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">What we collect when you visit</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            When you visit TavsScore, our server logs basic technical information automatically: your anonymised IP address, the page you visited, the browser and operating system you are using, and the time of your visit. This is standard for any website and helps us diagnose issues and understand how the site is being used in aggregate.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            We may also use analytics tools to understand which pages are popular and how people navigate the site. This data is always aggregated, we look at trends, not individuals.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Cookies</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            TavsScore uses a small number of cookies. Some are essential, for example, the cookie that remembers your session if you are logged in, and the CSRF token that keeps form submissions secure. You cannot turn these off without breaking the site.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            We may also use non-essential cookies for analytics or advertising purposes. You can decline these through your browser settings at any time. Most browsers let you block third-party cookies entirely, which is the easiest way to opt out.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Push notifications</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            If you subscribe to push notifications, we use OneSignal to deliver them. When you opt in, your browser generates a device token that OneSignal stores in order to send you alerts about confirmed lineup picks, Rollover updates, and other match information.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            We do not receive your name, email address, or any other personal information from this process, only the device token. OneSignal's own privacy policy governs how they handle that token.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            You can withdraw consent and unsubscribe at any time through your browser notification settings. Revoking permission deletes the association immediately.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Telegram</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            TavsScore operates a public Telegram channel where we share picks, match alerts, and Rollover updates. Telegram is a third-party platform and their own privacy policy governs the service. When you join our Telegram channel, we see only your Telegram username (if you have one set to public), we cannot see your phone number or any other account details, and we do not store subscriber lists.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Contact form messages</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            If you contact us through the contact form, we collect your name, email address, and your message. We use this only to respond to you. We do not add contact form submissions to any mailing list, and we do not share your details with third parties. Messages are kept for up to 12 months and then deleted.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Advertising</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            TavsScore is currently free with no advertising. If we introduce advertising or commercial partnerships in the future, we will update this section to explain exactly what data is involved and how to opt out. We will not introduce advertising without updating this policy first.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            Any future advertising partners will be listed here with a link to their privacy policy.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Third-party services we use</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            To run TavsScore we rely on several external services. Each has its own privacy practices.
        </p>
        <ul style="font-size:.88rem; color:var(--text-dim); line-height:1.85; padding-left:1.5rem;">
            <li style="margin-bottom:.5rem;"><strong style="color:var(--text);">API-Football</strong>, provides the match data that powers our scores and fixtures. We do not pass any personal information to them.</li>
            <li style="margin-bottom:.5rem;"><strong style="color:var(--text);">OneSignal</strong>, delivers push notifications to subscribed users. Receives only anonymous device tokens. Subject to OneSignal's privacy policy.</li>
            <li style="margin-bottom:.5rem;"><strong style="color:var(--text);">Telegram</strong>, our public channel for picks and alerts. Subject to Telegram's privacy policy.</li>
            <li style="margin-bottom:.5rem;"><strong style="color:var(--text);">Groq (LLaMA models)</strong>, one of three AI services used to generate predictions. We send only match data (team names, league, date, statistics), never any user information.</li>
            <li style="margin-bottom:.5rem;"><strong style="color:var(--text);">Google Gemini</strong>, cross-validation AI for predictions. Same data as above, no user information transmitted.</li>
            <li><strong style="color:var(--text);">Mistral AI</strong>, second cross-validation AI for predictions. Same data as above, no user information transmitted.</li>
        </ul>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Your rights</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            Under GDPR and similar regulations, you have the right to ask what personal data we hold about you, request it be corrected or deleted, object to its processing, and lodge a complaint with your national data protection authority. Because we collect very little personal data to begin with, most requests are straightforward to handle. Just <a href="{{ route('contact') }}" style="color:var(--green);">contact us</a> and we will deal with it promptly.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem; margin-bottom:1.25rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Children</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            TavsScore is not directed at children under 13. We do not knowingly collect information from children. If you believe a child under 13 has submitted personal information to us, please contact us and we will remove it immediately.
        </p>
    </div>

    <div style="background:var(--card); border:1px solid var(--border); border-radius:12px; padding:1.75rem;">
        <h2 style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.875rem;">Changes to this policy</h2>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85; margin-bottom:.875rem;">
            We will update this policy when our practices change. The date at the top of the page will always show you when it was last changed. We will not make material changes without posting a notice on the site first.
        </p>
        <p style="font-size:.88rem; color:var(--text-dim); line-height:1.85;">
            Questions? <a href="{{ route('contact') }}" style="color:var(--green);">Get in touch.</a>
        </p>
    </div>

</div>
@endsection
