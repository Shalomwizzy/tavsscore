@extends('layouts.app')

@section('title', 'Booking Codes | TavsScore — Load AI Picks Instantly')
@section('meta_description', "Get today's TavsScore booking codes for SportyBet, 1xBet, 1Win and more. Load our AI-picked selections with one code — no manual entry needed.")

@push('styles')
<style>
    .bc-hero {
        padding: 3rem 0 2rem;
        background:
            radial-gradient(ellipse 70% 60% at 50% -10%, rgba(99,102,241,.10), transparent),
            radial-gradient(ellipse 50% 40% at 90% 80%, rgba(16,185,129,.07), transparent);
        border-bottom: 1px solid var(--border);
        margin-bottom: 2rem;
    }
    .bc-hero-title {
        font-size: clamp(1.6rem, 4vw, 2.2rem);
        font-weight: 900;
        color: #fff;
        margin-bottom: .5rem;
    }
    .bc-hero-sub {
        font-size: .9rem;
        color: var(--text-dim);
        max-width: 520px;
        line-height: 1.6;
    }
    .bc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2rem;
    }
    .bc-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1.25rem;
        position: relative;
    }
    .bc-platform {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--text-dim);
        margin-bottom: .35rem;
    }
    .bc-code {
        font-family: monospace;
        font-size: 1.75rem;
        font-weight: 900;
        color: #fff;
        letter-spacing: .08em;
        margin-bottom: .75rem;
        word-break: break-all;
    }
    .bc-note {
        font-size: .8rem;
        color: var(--text-dim);
        margin-bottom: .75rem;
        line-height: 1.5;
    }
    .bc-how {
        font-size: .75rem;
        color: var(--text-dim);
        background: rgba(255,255,255,.03);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: .6rem .75rem;
        margin-bottom: .875rem;
        line-height: 1.55;
    }
    .bc-copy-btn {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        background: rgba(99,102,241,.15);
        border: 1px solid rgba(99,102,241,.35);
        border-radius: 8px;
        color: #a5b4fc;
        font-size: .78rem;
        font-weight: 700;
        padding: .45rem 1rem;
        cursor: pointer;
        transition: background 140ms, color 140ms;
        margin-bottom: .75rem;
    }
    .bc-copy-btn:hover { background: rgba(99,102,241,.3); color: #fff; }
    .bc-copy-btn.copied { background: rgba(16,185,129,.15); border-color: rgba(16,185,129,.35); color: #6ee7b7; }
    .bc-affiliate-btn {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .75rem;
        font-weight: 700;
        color: #fcd34d;
        text-decoration: none;
        border: 1px solid rgba(245,158,11,.3);
        border-radius: 8px;
        padding: .4rem .9rem;
        background: rgba(245,158,11,.07);
        transition: background 140ms;
    }
    .bc-affiliate-btn:hover { background: rgba(245,158,11,.15); }
    .bc-date {
        font-size: .68rem;
        color: var(--text-muted, var(--text-dim));
        margin-top: .5rem;
    }
    .bc-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-dim);
    }
    .bc-empty-icon { font-size: 2.5rem; margin-bottom: .75rem; }
    .bc-empty-title { font-size: 1rem; font-weight: 700; color: var(--text-dim); }
    .bc-disclaimer {
        background: rgba(107,114,128,.07);
        border: 1px solid rgba(107,114,128,.2);
        border-radius: 10px;
        padding: .875rem 1rem;
        font-size: .74rem;
        color: var(--text-dim);
        line-height: 1.6;
        margin-top: 1.5rem;
        margin-bottom: 2rem;
    }
</style>
@endpush

@section('content')

<div class="bc-hero">
    <div class="wrap">
        <div class="bc-hero-title">🎟️ Today's Booking Codes</div>
        <p class="bc-hero-sub">
            A booking code lets you instantly load a pre-built bet slip. Enter the code in your sportsbook app and all picks are added automatically — no manual entry needed.
        </p>
    </div>
</div>

<div class="wrap">

    @if($codes->isEmpty())
    <div class="bc-empty">
        <div class="bc-empty-icon">🎟️</div>
        <div class="bc-empty-title">No booking codes today — check back later</div>
        <p style="font-size:.8rem; margin-top:.5rem;">We usually send codes each morning after picks are selected.</p>
    </div>
    @else
    <div class="bc-grid">
        @foreach($codes as $bc)
        @php
            $slug = strtolower(str_replace(' ', '', $bc->platform));
            $aff  = $affiliates->get($slug);
            $howToMap = [
                'bet9ja'    => "Open Bet9ja app → Booking Code → Enter code",
                '1xbet'     => "Open 1xBet app → Coupon Code → Enter code",
                '1win'      => "Open 1Win app → Betting Slip → Coupon Code → Enter code",
                'sportybet' => "Open SportyBet app → Booking Code → Enter code",
                'betway'    => "Open Betway app → Booking Code → Enter code",
                'parimatch' => "Open Parimatch app → Booking Code → Enter code",
            ];
            $howTo = $howToMap[$slug] ?? "Open {$bc->platform} app → Booking Code → Enter code";
        @endphp
        <div class="bc-card">
            <div class="bc-platform">{{ $bc->platform }}</div>
            <div class="bc-code" id="code-{{ $bc->id }}">{{ strtoupper($bc->code) }}</div>

            @if($bc->note)
            <div class="bc-note">📋 {{ $bc->note }}</div>
            @endif

            <div class="bc-how">📲 <strong>How to load:</strong> {{ $howTo }}</div>

            <button class="bc-copy-btn" onclick="copyCode('{{ strtoupper($bc->code) }}', this)">
                📋 Copy Code
            </button>

            @if($aff)
            <div style="margin-top:.25rem;">
                <a href="{{ $aff->register_url }}" target="_blank" rel="noopener sponsored" class="bc-affiliate-btn">
                    {{ $aff->logo_emoji }} Don't have a {{ $bc->platform }} account? Register free →
                </a>
            </div>
            @endif

            <div class="bc-date">
                Sent {{ $bc->created_at->setTimezone('Africa/Lagos')->diffForHumans() }}
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="bc-disclaimer">
        ⚠️ <strong>For entertainment only.</strong> Verify odds before placing any bet. AI picks are never guaranteed. Never stake more than you can afford to lose. If gambling is a problem for you, visit <a href="https://www.begambleaware.org" target="_blank" rel="noopener" style="color:inherit; text-decoration:underline;">BeGambleAware.org</a>.
    </div>

</div>

@push('scripts')
<script>
function copyCode(code, btn) {
    navigator.clipboard.writeText(code).then(function() {
        var original = btn.innerHTML;
        btn.innerHTML = '✅ Copied!';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.innerHTML = original;
            btn.classList.remove('copied');
        }, 2000);
    }).catch(function() {
        btn.innerHTML = '⚠️ Copy failed';
        setTimeout(function() { btn.innerHTML = '📋 Copy Code'; }, 2000);
    });
}
</script>
@endpush

@endsection
