@extends('layouts.app')

@section('title', 'Football News & Analysis | TavsScore Blog')
@section('meta_description', 'Latest football news, match previews, transfer updates and analysis covering the Premier League, Champions League, La Liga, Serie A, Bundesliga and more.')
@section('og_title', 'Football News & Analysis | TavsScore')

@push('styles')
<style>
    .blog-header {
        padding: 2rem 0 1.5rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 1.5rem;
    }

    .blog-title  { font-size:1.35rem; font-weight:800; color:#fff; letter-spacing:-.02em; margin:0 0 .25rem; }
    .blog-sub    { font-size:.78rem; color:var(--text-dim); }

    /* Category filter */
    .cat-bar { display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:1.5rem; }

    .cat-pill {
        display:inline-flex; align-items:center;
        padding:4px 12px; border-radius:999px;
        font-size:.72rem; font-weight:700; text-decoration:none;
        background:var(--card); border:1px solid var(--border); color:var(--text-dim);
        transition:all 160ms;
    }
    .cat-pill:hover, .cat-pill.active {
        background:var(--green-dim); border-color:var(--green-border); color:#6ee7b7;
    }

    /* Post grid */
    .post-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .85rem;
    }

    .post-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        transition: border-color 200ms, transform 200ms;
        text-decoration: none;
        display: flex; flex-direction: column;
    }

    .post-card:hover { border-color:rgba(16,185,129,.22); transform:translateY(-2px); }

    .post-img {
        width:100%; aspect-ratio:16/9; object-fit:cover;
        background: linear-gradient(135deg, var(--surface), var(--card));
    }

    .post-img-placeholder {
        width:100%; aspect-ratio:16/9;
        background: linear-gradient(135deg, var(--surface), var(--card-hover));
        display:flex; align-items:center; justify-content:center;
        font-size:2.5rem;
    }

    .post-body { padding:.875rem 1rem; flex:1; display:flex; flex-direction:column; }

    .post-cat {
        font-size:.65rem; font-weight:700; color:var(--green);
        text-transform:uppercase; letter-spacing:.06em; margin-bottom:.35rem;
    }

    .post-title {
        font-size:.9rem; font-weight:700; color:#fff; line-height:1.35;
        margin-bottom:.45rem;
        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
    }

    .post-excerpt {
        font-size:.75rem; color:var(--text-dim); line-height:1.6; flex:1;
        display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;
        margin-bottom:.65rem;
    }

    .post-meta {
        display:flex; align-items:center; justify-content:space-between;
        font-size:.68rem; color:var(--text-muted);
    }

    .post-meta-left { display:flex; align-items:center; gap:.4rem; }

    .read-more {
        font-size:.72rem; font-weight:700; color:var(--green);
        display:inline-flex; align-items:center; gap:.2rem;
    }

    /* Pagination */
    .pagination-wrap { display:flex; justify-content:center; margin-top:2rem; gap:.35rem; flex-wrap:wrap; }

    @media (max-width:900px) { .post-grid { grid-template-columns:repeat(2,1fr); } }
    @media (max-width:560px) { .post-grid { grid-template-columns:1fr; } .blog-header { padding:1.5rem 0 1rem; } }
</style>
@endpush

@section('content')
<div class="wrap">

    <div class="blog-header">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:.75rem;">
            <div>
                <h1 class="blog-title">📰 Football News &amp; Analysis</h1>
                <p class="blog-sub">Match previews, transfer news, and in-depth football analysis</p>
            </div>
            <span style="font-size:.75rem; color:var(--text-dim);">{{ $posts->total() }} article{{ $posts->total() !== 1 ? 's' : '' }}</span>
        </div>
    </div>

    @if($categories->isNotEmpty())
    <div class="cat-bar">
        <a href="{{ route('blog.index') }}" class="cat-pill {{ !$category ? 'active' : '' }}">All</a>
        @foreach($categories as $cat)
            <a href="{{ route('blog.index', ['category' => $cat]) }}"
               class="cat-pill {{ $category === $cat ? 'active' : '' }}">{{ $cat }}</a>
        @endforeach
    </div>
    @endif

    @if($posts->isNotEmpty())
    <div class="post-grid">
        @foreach($posts as $post)
        <a href="{{ route('blog.show', $post->slug) }}" class="post-card">
            @if($post->image_url)
                <img src="{{ $post->image_url }}" alt="{{ $post->title }}" class="post-img" loading="lazy">
            @else
                <div class="post-img-placeholder">
                    @php $emojis = ['⚽','🏆','🎯','🏃','🥅','📊','🌍']; @endphp
                    {{ $emojis[crc32($post->slug) % count($emojis)] }}
                </div>
            @endif

            <div class="post-body">
                <div class="post-cat">{{ $post->category }}</div>
                <div class="post-title">{{ $post->title }}</div>
                <p class="post-excerpt">{{ $post->excerpt_or_generated }}</p>
                <div class="post-meta">
                    <div class="post-meta-left">
                        <span>{{ $post->published_at?->format('M d, Y') ?? $post->created_at->format('M d, Y') }}</span>
                        <span>·</span>
                        <span>{{ $post->reading_time }} min read</span>
                        @if($post->is_ai_generated)
                            <span class="chip chip-blue" style="font-size:.58rem; padding:1px 5px;">AI</span>
                        @endif
                    </div>
                    <span class="read-more">Read →</span>
                </div>
            </div>
        </a>
        @endforeach
    </div>

    <div class="pagination-wrap">
        {{ $posts->links() }}
    </div>

    @else
    <div class="state-box">
        <span class="state-icon">📰</span>
        <div class="state-title">No articles yet</div>
        <p class="state-sub">Check back soon for football news and analysis.</p>
    </div>
    @endif

    <div style="height:2rem"></div>
</div>
@endsection
