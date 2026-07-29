@extends('layouts.app')

@section('title', 'Football News & Analysis | TavsScore Blog')
@section('meta_description', 'Latest football news, match previews, transfer updates and analysis covering the Premier League, Champions League, La Liga, Serie A, Bundesliga and more.')
@section('og_title', 'Football News & Analysis | TavsScore')

@push('styles')
<style>
    .news-hero { position:relative;overflow:hidden;margin:1.35rem 0 1.1rem;padding:2.3rem 2.1rem;border:1px solid rgba(96,165,250,.24);border-radius:20px;background:radial-gradient(circle at 88% 15%,rgba(59,130,246,.22),transparent 30%),radial-gradient(circle at 12% 95%,rgba(16,185,129,.15),transparent 34%),linear-gradient(135deg,#111d33,#0b1220 68%); }
    .news-hero::after { content:'TAVSSCORE';position:absolute;right:-.05rem;bottom:-1.35rem;color:rgba(255,255,255,.035);font-size:clamp(3rem,10vw,7rem);font-weight:900;letter-spacing:-.08em;pointer-events:none; }
    .news-kicker { color:#86efac;font-size:.64rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase; }
    .news-title { position:relative;z-index:1;max-width:650px;margin:.45rem 0;color:#fff;font-size:clamp(1.7rem,4vw,3rem);font-weight:900;line-height:1.02;letter-spacing:-.055em; }
    .news-sub { position:relative;z-index:1;max-width:580px;color:#aab8cb;font-size:.83rem;line-height:1.65; }
    .news-count { position:relative;z-index:1;display:inline-flex;margin-top:1rem;padding:.35rem .6rem;border:1px solid rgba(255,255,255,.12);border-radius:999px;background:rgba(255,255,255,.055);color:#dbeafe;font-size:.68rem;font-weight:800; }
    .news-filter-shell { display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:1rem 0 1.35rem; }
    .news-filter-label { color:var(--text-dim);font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap; }
    .cat-bar { display:flex;flex-wrap:wrap;gap:.38rem; }
    .cat-pill { display:inline-flex;align-items:center;padding:.42rem .7rem;border-radius:999px;font-size:.7rem;font-weight:750;text-decoration:none;background:rgba(255,255,255,.035);border:1px solid var(--border);color:#9aa9bd;transition:all 160ms; }
    .cat-pill:hover,.cat-pill.active { transform:translateY(-1px);color:#ecfdf5;background:rgba(16,185,129,.12);border-color:rgba(52,211,153,.4); }
    .story-feature { display:grid;grid-template-columns:1.2fr .8fr;min-height:340px;margin-bottom:1rem;overflow:hidden;border:1px solid rgba(148,163,184,.16);border-radius:18px;background:var(--card);text-decoration:none;transition:transform 180ms,border-color 180ms,box-shadow 180ms; }
    .story-feature:hover { transform:translateY(-3px);border-color:rgba(96,165,250,.4);box-shadow:0 18px 42px rgba(0,0,0,.23); }
    .story-feature-media { position:relative;min-height:250px;background:linear-gradient(135deg,#12233d,#101827); }
    .story-feature-media img { width:100%;height:100%;object-fit:cover; }
    .story-feature-media::after { content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent 55%,rgba(19,29,48,.8)); }
    .story-feature-body { display:flex;flex-direction:column;justify-content:center;padding:2rem 2rem 1.6rem; }
    .story-kicker { color:#6ee7b7;font-size:.63rem;font-weight:900;text-transform:uppercase;letter-spacing:.1em; }
    .story-feature-title { color:#fff;font-size:clamp(1.2rem,2.5vw,1.85rem);line-height:1.14;font-weight:900;letter-spacing:-.035em;margin:.55rem 0; }
    .story-feature-excerpt { color:#9aa9bd;font-size:.78rem;line-height:1.65;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden; }
    .story-meta { display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;margin-top:1rem;color:#718096;font-size:.66rem;font-weight:650; }
    .story-read { display:inline-flex;align-items:center;gap:.35rem;margin-top:1.1rem;color:#6ee7b7;font-size:.72rem;font-weight:850; }
    .news-section-head { display:flex;align-items:end;justify-content:space-between;gap:.75rem;margin:1.65rem 0 .8rem; }
    .news-section-title { color:#fff;font-size:1rem;font-weight:900;letter-spacing:-.025em; }
    .news-section-sub { color:var(--text-dim);font-size:.7rem; }
    .post-grid { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem; }
    .post-card { position:relative;display:flex;flex-direction:column;overflow:hidden;border:1px solid var(--border);border-radius:15px;background:linear-gradient(180deg,rgba(255,255,255,.025),rgba(255,255,255,.01));text-decoration:none;transition:transform 180ms,border-color 180ms,box-shadow 180ms; }
    .post-card:hover { transform:translateY(-4px);border-color:rgba(52,211,153,.35);box-shadow:0 14px 30px rgba(0,0,0,.22); }
    .post-img,.post-img-placeholder { width:100%;aspect-ratio:16/9;object-fit:cover;background:linear-gradient(135deg,#14223a,#0d1422); }
    .post-img-placeholder { display:grid;place-items:center;font-size:2.3rem; }
    .post-body { display:flex;flex:1;flex-direction:column;padding:1rem; }
    .post-cat { color:#6ee7b7;font-size:.6rem;font-weight:900;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.45rem; }
    .post-title { color:#fff;font-size:.9rem;font-weight:850;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
    .post-excerpt { flex:1;margin:.55rem 0 .75rem;color:#8796aa;font-size:.72rem;line-height:1.62;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden; }
    .post-meta { display:flex;align-items:center;justify-content:space-between;gap:.5rem;color:#64748b;font-size:.62rem; }
    .post-meta-left { display:flex;align-items:center;gap:.3rem;flex-wrap:wrap; }
    .read-more { color:#6ee7b7;font-weight:850;white-space:nowrap; }
    .pagination-wrap { display:flex;justify-content:center;margin:2rem 0; }
    @media(max-width:760px) { .news-hero { padding:1.65rem 1.2rem;border-radius:16px; } .news-filter-shell { align-items:flex-start;flex-direction:column;gap:.55rem; } .story-feature { grid-template-columns:1fr; } .story-feature-media { min-height:210px; } .story-feature-media::after { background:linear-gradient(180deg,transparent,rgba(19,29,48,.65)); } .story-feature-body { padding:1.25rem; } .post-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:470px) { .post-grid { grid-template-columns:1fr; } .news-title { font-size:1.8rem; } }
</style>
@endpush

@section('content')
<div class="wrap">
    <header class="news-hero">
        <div class="news-kicker">TavsScore editorial</div>
        <h1 class="news-title">Football stories worth staying for.</h1>
        <p class="news-sub">Sharp previews, transfer context and match intelligence—built for supporters who want more than a headline.</p>
        <span class="news-count">{{ $posts->total() }} stories published</span>
    </header>

    @if($categories->isNotEmpty())
    <div class="news-filter-shell">
        <span class="news-filter-label">Explore stories</span>
        <nav class="cat-bar" aria-label="Blog categories">
            <a href="{{ route('blog.index') }}" class="cat-pill {{ !$category ? 'active' : '' }}">All stories</a>
            @foreach($categories as $cat)<a href="{{ route('blog.index', ['category' => $cat]) }}" class="cat-pill {{ $category === $cat ? 'active' : '' }}">{{ $cat }}</a>@endforeach
        </nav>
    </div>
    @endif

    @if($posts->isNotEmpty())
        @php($featured = $posts->first())
        <a href="{{ route('blog.show', $featured->slug) }}" class="story-feature">
            <div class="story-feature-media">
                @if($featured->image_url)<img src="{{ $featured->image_url }}" alt="{{ $featured->title }}">@else<div class="post-img-placeholder">⚽</div>@endif
            </div>
            <article class="story-feature-body">
                <div class="story-kicker">Featured · {{ $featured->category }}</div>
                <h2 class="story-feature-title">{{ $featured->title }}</h2>
                <p class="story-feature-excerpt">{{ $featured->excerpt_or_generated }}</p>
                <div class="story-meta"><span>{{ $featured->published_at?->format('M d, Y') ?? $featured->created_at->format('M d, Y') }}</span><span>•</span><span>{{ $featured->reading_time }} min read</span>@if($featured->is_ai_generated)<span>• AI assisted</span>@endif</div>
                <span class="story-read">Read the full story <span>→</span></span>
            </article>
        </a>

        @if($posts->count() > 1)
        <div class="news-section-head"><div><h2 class="news-section-title">Latest from the newsroom</h2><p class="news-section-sub">Fresh context from football’s biggest conversations.</p></div></div>
        <div class="post-grid">
            @foreach($posts->slice(1) as $post)
            <a href="{{ route('blog.show', $post->slug) }}" class="post-card">
                @if($post->image_url)<img src="{{ $post->image_url }}" alt="{{ $post->title }}" class="post-img" loading="lazy">@else<div class="post-img-placeholder">{{ ['⚽','🏆','🎯','📊','🌍'][crc32($post->slug) % 5] }}</div>@endif
                <article class="post-body"><div class="post-cat">{{ $post->category }}</div><h2 class="post-title">{{ $post->title }}</h2><p class="post-excerpt">{{ $post->excerpt_or_generated }}</p><div class="post-meta"><span class="post-meta-left"><span>{{ $post->published_at?->format('M d') ?? $post->created_at->format('M d') }}</span><span>•</span><span>{{ $post->reading_time }} min</span></span><span class="read-more">Read →</span></div></article>
            </a>
            @endforeach
        </div>
        @endif
        <div class="pagination-wrap">{{ $posts->links() }}</div>
    @else
        <div class="state-box"><span class="state-icon">📰</span><div class="state-title">The newsroom is warming up</div><p class="state-sub">Check back soon for football news and analysis.</p></div>
    @endif
</div>
@endsection
