@extends('layouts.app')

@section('title', $post->title . ' | TavsScore')
@section('meta_description', $post->excerpt_or_generated)
@section('og_title', $post->title)
@section('og_description', $post->excerpt_or_generated)
@section('og_type', 'article')
@section('og_image', $post->absolute_image_url ?? '')
@section('canonical', url('/blog/' . $post->slug))

@push('styles')
<style>
    .article-wrap  { max-width:780px;margin:0 auto; }

    .article-header { position:relative;overflow:hidden;padding:2.25rem 2rem 1.8rem;border:1px solid rgba(96,165,250,.22);border-radius:18px;background:radial-gradient(circle at 92% 8%,rgba(59,130,246,.18),transparent 25%),linear-gradient(135deg,#111d33,#0b1220 68%);margin:1.25rem 0 1.35rem; }

    .article-cat {
        font-size:.72rem; font-weight:700; color:var(--green);
        text-transform:uppercase; letter-spacing:.07em; margin-bottom:.6rem;
    }

    .article-title {
        font-size:clamp(1.65rem,4.5vw,2.75rem);font-weight:900;
        color:#fff;letter-spacing:-.05em;line-height:1.06;margin-bottom:.9rem;
    }

    .article-meta {
        display:flex; align-items:center; flex-wrap:wrap; gap:.6rem;
        font-size:.75rem; color:var(--text-dim);
    }

    .article-meta-sep { color:var(--text-muted); }

    .article-hero {
        width:100%;border:1px solid rgba(148,163,184,.14);border-radius:16px;margin-bottom:1.5rem;
        aspect-ratio:16/9;object-fit:cover;box-shadow:0 18px 38px rgba(0,0,0,.2);
    }

    /* Article body */
    .article-body { color:#c8d5e8;line-height:1.85;font-size:.95rem;padding:0 .3rem; }

    .article-body p  { margin-bottom:1.25rem; }
    .article-body h2 { font-size:1.2rem; font-weight:800; color:#fff; margin:2rem 0 .75rem; letter-spacing:-.02em; }
    .article-body h3 { font-size:1rem; font-weight:700; color:#fff; margin:1.5rem 0 .6rem; }
    .article-body ul, .article-body ol { padding-left:1.5rem; margin-bottom:1.25rem; }
    .article-body li { margin-bottom:.4rem; }
    .article-body a  { color:var(--green); text-decoration:none; }
    .article-body a:hover { text-decoration:underline; }
    .article-body strong { color:#fff; font-weight:700; }

    /* Share bar */
    .share-bar {
        display:flex; align-items:center; gap:.6rem; flex-wrap:wrap;
        padding:1.1rem;background:linear-gradient(135deg,rgba(16,185,129,.1),rgba(16,185,129,.025));border:1px solid rgba(16,185,129,.22);
        border-radius:14px;margin:2rem 0;
    }
    .share-label { font-size:.75rem; font-weight:700; color:var(--text); }
    .share-btn {
        display:inline-flex; align-items:center; gap:.35rem;
        padding:5px 12px; border-radius:999px; font-size:.72rem; font-weight:700;
        text-decoration:none; transition:opacity 160ms;
        background:rgba(255,255,255,.06); border:1px solid var(--border); color:var(--text-dim);
    }
    .share-btn:hover { opacity:.8; color:var(--text); }

    /* Related */
    .related-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; }

    .related-card {
        background:linear-gradient(145deg,rgba(255,255,255,.035),rgba(255,255,255,.01));border:1px solid var(--border);border-radius:13px;
        padding:.875rem; text-decoration:none;
        transition:border-color 180ms, transform 180ms;
        display:flex; flex-direction:column; gap:.35rem;
    }
    .related-card:hover { border-color:rgba(16,185,129,.22); transform:translateY(-2px); }

    .related-cat   { font-size:.62rem; font-weight:700; color:var(--green); text-transform:uppercase; letter-spacing:.06em; }
    .related-title { font-size:.8rem; font-weight:700; color:#fff; line-height:1.3; }
    .related-date  { font-size:.67rem; color:var(--text-muted); }

    /* JSON-LD schema */
    @media (max-width:640px) {
        .related-grid { grid-template-columns:1fr; }
    }

    /* Blog language toggle */
    .blog-lang-toggle {
        display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;
        margin: 1.25rem 0;
        padding:.7rem .9rem; background:var(--card); border:1px solid var(--border); border-radius:10px;
    }
    .blog-lang-label { font-size:.7rem; color:var(--text-dim); font-weight:700; text-transform:uppercase; letter-spacing:.05em; margin-right:.25rem; }
    .blog-lang-btn {
        background:transparent; border:1px solid transparent; cursor:pointer;
        font-family:inherit; font-size:.78rem; font-weight:700;
        padding:5px 11px; border-radius:7px; color:var(--text-dim);
        transition:all 140ms;
    }
    .blog-lang-btn:hover:not(:disabled) { color:var(--text); background:rgba(255,255,255,.05); }
    .blog-lang-btn.active { background:rgba(16,185,129,.15); color:#6ee7b7; border-color:rgba(16,185,129,.28); }
    .blog-lang-btn:disabled { opacity:.5; cursor:wait; }
    .blog-lang-hint { font-size:.72rem; color:var(--text-dim); margin-left:auto; font-weight:600; }
</style>

@php
$jsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => $post->title,
    'description' => $post->excerpt_or_generated,
    'datePublished' => $post->published_at?->toIso8601String() ?? $post->created_at->toIso8601String(),
    'dateModified' => $post->updated_at->toIso8601String(),
    'author' => ['@type' => $post->is_ai_generated ? 'Organization' : 'Person', 'name' => $post->author],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'TavsScore',
        'url' => url('/'),
    ],
    'mainEntityOfPage' => url('/blog/' . $post->slug),
    'image' => $post->absolute_image_url,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp
@endpush

@section('content')
<script type="application/ld+json">{!! $jsonLd !!}</script>

<div class="wrap">
    <div class="article-wrap">

        {{-- Breadcrumb --}}
        <nav style="font-size:.72rem; color:var(--text-dim); padding:1.25rem 0 0; margin-bottom:-.5rem;">
            <a href="{{ route('home.index') }}" style="color:var(--text-dim); text-decoration:none;">Home</a>
            <span style="margin:0 .4rem; color:var(--text-muted)">›</span>
            <a href="{{ route('blog.index') }}" style="color:var(--text-dim); text-decoration:none;">Blog</a>
            <span style="margin:0 .4rem; color:var(--text-muted)">›</span>
            <span style="color:var(--text);">{{ $post->category }}</span>
        </nav>

        <header class="article-header">
            <div class="article-cat">{{ $post->category }}</div>
            <h1 class="article-title">{{ $post->title }}</h1>
            <div class="article-meta">
                <span>✍️ {{ $post->author }}</span>
                <span class="article-meta-sep">·</span>
                <span>{{ $post->published_at?->format('F j, Y') ?? $post->created_at->format('F j, Y') }}</span>
                <span class="article-meta-sep">·</span>
                <span>{{ $post->reading_time }} min read</span>
                @if($post->is_ai_generated)
                    <span class="chip chip-blue" style="font-size:.63rem">🤖 AI Generated</span>
                @endif
            </div>
        </header>

        @if($post->image_url)
            <img src="{{ $post->image_url }}" alt="{{ $post->title }}" class="article-hero" loading="lazy" decoding="async">
        @endif

        <div class="blog-lang-toggle"
             data-post-id="{{ $post->id }}"
             data-en-html="{{ e($post->content) }}"
             data-pidgin-html="{{ e($post->content_pidgin ?? '') }}"
             data-swahili-html="{{ e($post->content_swahili ?? '') }}">
            <span class="blog-lang-label">Read in:</span>
            <button type="button" class="blog-lang-btn active" data-lang="en">🇬🇧 English</button>
            <button type="button" class="blog-lang-btn" data-lang="pidgin">🇳🇬 Pidgin</button>
            <button type="button" class="blog-lang-btn" data-lang="swahili">🇰🇪 Swahili</button>
            <span class="blog-lang-hint" id="blog-lang-hint" style="display:none;">⏳ Translating…</span>
        </div>

        <div class="article-body" id="article-body">
            {!! $post->content !!}
        </div>

        @if($post->is_ai_generated)
            <aside style="margin:1.5rem .3rem 0;padding:1rem 1.05rem;border:1px solid rgba(16,185,129,.2);border-radius:12px;background:rgba(16,185,129,.055);color:#9fb0c4;font-size:.74rem;line-height:1.65;">
                <strong style="color:#d1fae5;">Editorial note:</strong>
                This AI-assisted TavsScore analysis is generated from the football data available to our platform at publication time. It is for information and entertainment, not a guarantee or financial advice.
            </aside>
        @endif

        {{-- Share --}}
        <div class="share-bar">
            <span class="share-label">Share:</span>
            <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode(route('blog.show', $post->slug)) }}"
               target="_blank" rel="noopener" class="share-btn">🐦 Twitter</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('blog.show', $post->slug)) }}"
               target="_blank" rel="noopener" class="share-btn">📘 Facebook</a>
            <a href="https://wa.me/?text={{ urlencode($post->title.' '.route('blog.show', $post->slug)) }}"
               target="_blank" rel="noopener" class="share-btn">💬 WhatsApp</a>
        </div>

        {{-- Related --}}
        @if($related->isNotEmpty())
        <section>
            <div style="font-size:.75rem; font-weight:700; color:var(--green); text-transform:uppercase; letter-spacing:.07em; margin-bottom:.875rem;">Related Articles</div>
            <div class="related-grid">
                @foreach($related as $rel)
                <a href="{{ route('blog.show', $rel->slug) }}" class="related-card">
                    <div class="related-cat">{{ $rel->category }}</div>
                    <div class="related-title">{{ $rel->title }}</div>
                    <div class="related-date">{{ $rel->published_at?->format('M d, Y') }}</div>
                </a>
                @endforeach
            </div>
        </section>
        @endif

        {{-- CTA --}}
        <div style="margin:2rem 0; padding:1.25rem; background:var(--green-dim); border:1px solid var(--green-border); border-radius:10px; text-align:center;">
            <div style="font-size:.9rem; font-weight:700; color:#fff; margin-bottom:.4rem;">⚽ Follow the action live</div>
            <p style="font-size:.78rem; color:var(--text-dim); margin-bottom:.875rem;">Real-time scores, AI predictions and more - all free on TavsScore.</p>
            <div style="display:flex; justify-content:center; gap:.5rem; flex-wrap:wrap;">
                <a href="{{ route('live.index') }}" class="btn-ts btn-green" style="font-size:.78rem; padding:.42rem .9rem;">🔴 Live Scores</a>
                <a href="{{ route('predictions.index') }}" class="btn-ts btn-outline" style="font-size:.78rem; padding:.42rem .9rem;">📊 Predictions</a>
            </div>
        </div>

        <div style="height:2rem"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var toggle = document.querySelector('.blog-lang-toggle');
    var body   = document.getElementById('article-body');
    var hint   = document.getElementById('blog-lang-hint');
    if (!toggle || !body) return;

    var btns = toggle.querySelectorAll('.blog-lang-btn');
    var postId = toggle.getAttribute('data-post-id');

    function applyHtml(html) { body.innerHTML = html || ''; }

    function load(lang) {
        var attr = 'data-' + lang + '-html';
        var saved = toggle.getAttribute(attr);
        if (saved) { applyHtml(saved); return Promise.resolve(); }
        if (lang === 'en') { applyHtml(toggle.getAttribute('data-en-html')); return Promise.resolve(); }

        return fetch('/api/blog/' + postId + '/translate', {
            method: 'POST',
            headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ lang: lang }),
        })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function (d) {
            toggle.setAttribute(attr, d.html);
            applyHtml(d.html);
        });
    }

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var lang = btn.getAttribute('data-lang');
            if (btn.classList.contains('active')) return;

            btns.forEach(function (b) { b.disabled = true; });
            hint.style.display = 'inline';

            load(lang)
                .then(function () {
                    btns.forEach(function (b) {
                        b.disabled = false;
                        b.classList.toggle('active', b === btn);
                    });
                    hint.style.display = 'none';
                })
                .catch(function () {
                    btns.forEach(function (b) { b.disabled = false; });
                    hint.textContent = 'Translation failed.';
                    setTimeout(function () { hint.style.display = 'none'; hint.textContent = '⏳ Translating…'; }, 3000);
                });
        });
    });
}());
</script>
@endpush
