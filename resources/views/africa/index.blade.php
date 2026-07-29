@extends('layouts.app')
@section('title', 'African Football - NPFL, PSL, CAF Champions League & AFCON | TavsScore')
@section('meta_description', 'Live scores, fixtures and AI predictions across Nigerian Professional Football League, South Africa PSL, Ghana Premier, CAF Champions League, AFCON and every major African competition. Analysis available in Pidgin and Swahili.')
@section('canonical', url('/africa'))

@push('styles')
<style>
    .af-hero { position:relative;overflow:hidden;padding:2rem 1.5rem 1.7rem;margin:1.25rem 0 .3rem;border:1px solid rgba(245,158,11,.24);border-radius:19px;background:radial-gradient(circle at 88% 12%,rgba(245,158,11,.2),transparent 28%),radial-gradient(circle at 6% 100%,rgba(16,185,129,.14),transparent 30%),linear-gradient(135deg,#181d2b,#0b1220 70%); }
    .af-hero::after { content:'AFRICA';position:absolute;right:-.02rem;bottom:-1.2rem;color:rgba(255,255,255,.035);font-size:clamp(3.5rem,12vw,8rem);font-weight:900;letter-spacing:-.08em;pointer-events:none; }
    .af-eyebrow { position:relative;z-index:1;font-size:.64rem;font-weight:900;color:#fde68a;text-transform:uppercase;letter-spacing:.11em;margin-bottom:.4rem; }
    .af-title { position:relative;z-index:1;max-width:650px;font-size:clamp(1.55rem,4vw,2.5rem);font-weight:900;line-height:1.08;letter-spacing:-.045em;color:#fff; }
    .af-sub   { position:relative;z-index:1;font-size:.8rem;color:#b8c2d0;margin-top:.65rem;max-width:640px;line-height:1.7; }

    .af-section { margin-top:2.1rem; }
    .af-section h2 { font-size:1rem; font-weight:800; color:#fff; margin-bottom:.85rem; display:flex; align-items:center; gap:.55rem; }
    .af-section-count { font-size:.7rem; color:var(--text-dim); font-weight:700; }

    .af-card { background:linear-gradient(145deg,rgba(19,29,48,.98),rgba(13,20,34,.92));border:1px solid rgba(148,163,184,.13);border-radius:14px;padding:1rem 1.05rem;margin-bottom:.65rem;transition:transform 160ms,border-color 160ms; }
    .af-card:hover { transform:translateY(-2px);border-color:rgba(245,158,11,.34); }
    .af-card.live { border-color: rgba(239,68,68,.32); background:linear-gradient(135deg, rgba(239,68,68,.04), rgba(239,68,68,.01)); }
    .af-card.cont { border-color: rgba(245,158,11,.32); background:linear-gradient(135deg, rgba(245,158,11,.05), rgba(245,158,11,.01)); }

    .af-row { display:flex; align-items:center; justify-content:space-between; gap:.6rem; flex-wrap:wrap; }
    .af-league { font-size:.7rem; color:var(--text-dim); font-weight:700; text-transform:uppercase; letter-spacing:.04em; display:flex; align-items:center; gap:.4rem; }
    .af-time   { font-size:.74rem; color:var(--text-dim); font-weight:600; }
    .af-time .live-dot { width:6px; height:6px; }

    .af-match { color:#fff;font-weight:700;font-size:.92rem;line-height:1.4;margin-top:.3rem; }
    .af-tip { display:inline-flex; align-items:center; padding:3px 9px; border-radius:999px; background:rgba(245,158,11,.13); border:1px solid rgba(245,158,11,.25); color:#fcd34d; font-size:.72rem; font-weight:700; margin-top:.45rem; text-decoration:none; }
    .af-tip:hover { color:#fcd34d; }

    .af-comp-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem; }
    .af-comp h3 { font-size:.78rem; color:#fff; font-weight:800; margin-bottom:.55rem; padding-bottom:.4rem; border-bottom:1px solid var(--border); }

    .af-empty { padding:2rem 1rem; text-align:center; color:var(--text-dim); border:1px dashed rgba(255,255,255,.1); border-radius:11px; }

    .af-cta {
        margin-top:2.25rem; padding:1.25rem 1.5rem;
        background:linear-gradient(135deg,rgba(245,158,11,.12),rgba(245,158,11,.04));
        border:1px solid rgba(245,158,11,.28); border-radius:14px;
        display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;box-shadow:0 14px 32px rgba(0,0,0,.16);
    }
</style>
@endpush

@section('content')
<div class="wrap">
    <header class="af-hero">
        <div class="af-eyebrow">🌍 African Football Coverage</div>
        <h1 class="af-title">African football, in one intelligent match hub.</h1>
        <p class="af-sub">
            Real-time scores, predictions and AI match analysis across every major African competition.
            Tap any tip to read the breakdown in <strong style="color:#fcd34d;">English, 🇳🇬 Pidgin or 🇰🇪 Swahili</strong> -
            translated by our AI, free for everyone.
        </p>
    </header>

    {{-- ── LIVE NOW ── --}}
    @if($live->isNotEmpty())
    <section class="af-section">
        <h2>🔴 Live Now <span class="af-section-count">{{ $live->count() }} match{{ $live->count() === 1 ? '' : 'es' }}</span></h2>
        @foreach($live as $m)
        <div class="af-card live">
            <div class="af-row">
                <div class="af-league">⚽ {{ \App\Support\LeagueCoverage::formatName($m->league, $m->league_country) }}</div>
                <div class="af-time"><span class="live-dot"></span> {{ $m->elapsed }}'</div>
            </div>
            @include('partials.fixture-showcase', ['match' => $m, 'accent' => '#fca5a5', 'compact' => true])
            @if($m->prediction && $m->prediction->predicted_outcome)
            <a href="{{ route('predictions.show', $m->slug) }}" class="af-tip">📊 Tip: {{ $m->prediction->predicted_outcome }}</a>
            @endif
        </div>
        @endforeach
    </section>
    @endif

    {{-- ── CONTINENTAL (CAF Champions League / AFCON / etc.) ── --}}
    @if($continental->isNotEmpty())
    <section class="af-section">
        <h2>🏆 CAF & AFCON <span class="af-section-count">Continental competitions</span></h2>
        @foreach($continental as $m)
        <div class="af-card cont">
            <div class="af-row">
                <div class="af-league">🌍 {{ \App\Support\LeagueCoverage::formatName($m->league, $m->league_country) }}</div>
                <div class="af-time">
                    @if(in_array($m->status, ['FT','AET','PEN']))
                        FT · {{ $m->match_time?->format('M j') }}
                    @elseif(in_array($m->status, ['1H','2H','HT','ET','BT','P','LIVE']))
                        <span style="color:#ef4444;">● LIVE {{ $m->elapsed }}'</span>
                    @else
                        KO {{ $m->match_time?->format('H:i, M j') }}
                    @endif
                </div>
            </div>
            @include('partials.fixture-showcase', ['match' => $m, 'accent' => '#fcd34d', 'compact' => true])
            @if($m->prediction && $m->prediction->predicted_outcome)
            <a href="{{ route('predictions.show', $m->slug) }}" class="af-tip">📊 Tip: {{ $m->prediction->predicted_outcome }}</a>
            @endif
        </div>
        @endforeach
    </section>
    @endif

    {{-- ── UPCOMING by competition ── --}}
    <section class="af-section">
        <h2>📅 Upcoming Fixtures (next 72 hours)</h2>

        @if($upcoming->isEmpty())
            <div class="af-empty">No African fixtures in the next 72 hours. Check back later.</div>
        @else
            <div class="af-comp-grid">
                @foreach($upcoming as $compName => $compMatches)
                <div class="af-comp">
                    <h3>{{ $compName }}</h3>
                    @foreach($compMatches as $m)
                    <div class="af-card" style="padding:.65rem .8rem; margin-bottom:.4rem;">
                        <div class="af-time" style="margin-bottom:.25rem;">
                            KO {{ $m->match_time?->format('H:i') }} · {{ $m->match_time?->format('D M j') }}
                        </div>
                        @include('partials.fixture-showcase', ['match' => $m, 'accent' => '#6ee7b7', 'compact' => true])
                        @if($m->prediction && $m->prediction->predicted_outcome)
                        <a href="{{ route('predictions.show', $m->slug) }}" class="af-tip" style="font-size:.66rem; padding:1px 7px;">📊 {{ $m->prediction->predicted_outcome }}</a>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ── RECENT RESULTS ── --}}
    @if($finished->isNotEmpty())
    <section class="af-section">
        <h2>📜 Recent Results</h2>
        @foreach($finished as $m)
        <div class="af-card" style="padding:.7rem .9rem; margin-bottom:.4rem;">
            <div class="af-row">
                <div class="af-league">{{ \App\Support\LeagueCoverage::formatName($m->league, $m->league_country) }}</div>
                <div class="af-time">FT · {{ $m->match_time?->format('M j') }}</div>
            </div>
            <div class="af-match" style="font-size:.85rem; margin-top:.25rem;">
                @include('partials.fixture-showcase', ['match' => $m, 'accent' => '#93c5fd', 'compact' => true])
                @if($m->prediction && $m->prediction->was_correct === true)
                    <span style="color:#6ee7b7; font-size:.72rem; margin-left:.4rem;">✓</span>
                @elseif($m->prediction && $m->prediction->was_correct === false)
                    <span style="color:#fca5a5; font-size:.72rem; margin-left:.4rem;">✗</span>
                @endif
            </div>
        </div>
        @endforeach
    </section>
    @endif

    {{-- ── CTA --}}
    <div class="af-cta">
        <div>
            <div style="font-size:.7rem; font-weight:800; color:rgba(252,211,77,.78); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.35rem;">First in Africa</div>
            <div style="font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:.3rem;">⭐ Today's Best 3 Picks - in your language</div>
            <div style="font-size:.78rem; color:var(--text-dim); line-height:1.6; max-width:380px;">
                Our AI picks the 3 most confident bets from today's matches and translates the analysis into Pidgin and Swahili on demand. No subscriptions.
            </div>
        </div>
        <a href="{{ route('picks.index') }}" style="display:inline-flex; align-items:center; gap:.5rem; padding:.65rem 1.4rem; background:rgba(245,158,11,.18); border:1px solid rgba(245,158,11,.35); border-radius:9px; font-size:.85rem; font-weight:800; color:#fcd34d; text-decoration:none;">
            View Today's Picks →
        </a>
    </div>

    <div style="height:2rem"></div>
</div>
@endsection
