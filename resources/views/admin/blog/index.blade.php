@extends('layouts.admin')
@section('title', 'Blog Posts')
@section('page-title', 'Blog Posts')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">📝 Blog Posts</span>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.blog.auto-generate') }}">
            @csrf
            <button type="submit" class="btn-a" style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.28);color:#fcd34d;">
                🤖 AI Auto-Generate
            </button>
        </form>
        <a href="{{ route('admin.blog.create') }}" class="btn-a btn-green">✏️ New Post</a>
    </div>
</div>

<div class="a-card">
    <div style="overflow-x:auto">
        <table class="a-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $post)
                <tr>
                    <td>
                        <div style="color:#fff; font-weight:600; max-width:280px;">
                            {{ Str::limit($post->title, 55) }}
                            @if($post->is_ai_generated)
                                <span class="badge badge-blue" style="font-size:.6rem; margin-left:4px;">AI</span>
                            @endif
                        </div>
                    </td>
                    <td><span class="badge badge-gray">{{ $post->category }}</span></td>
                    <td style="color:var(--dim)">{{ $post->author }}</td>
                    <td>
                        @if($post->is_published)
                            <span class="badge badge-green">Published</span>
                        @else
                            <span class="badge badge-gray">Draft</span>
                        @endif
                    </td>
                    <td style="color:var(--dim); white-space:nowrap; font-size:.75rem;">{{ $post->created_at->format('M d, Y') }}</td>
                    <td>
                        <div style="display:flex; gap:.4rem; align-items:center;">
                            <a href="{{ route('blog.show', $post->slug) }}" target="_blank" class="btn-a btn-gray" style="padding:.28rem .65rem; font-size:.72rem;">View</a>
                            <a href="{{ route('admin.blog.edit', $post) }}" class="btn-a btn-blue" style="padding:.28rem .65rem; font-size:.72rem;">Edit</a>
                            <form method="POST" action="{{ route('admin.blog.regenerate-image', $post) }}" onsubmit="return confirm('Generate a new Tavs Score watermarked image for this post?')">
                                @csrf
                                <button type="submit" class="btn-a btn-gray" style="padding:.28rem .5rem; font-size:.72rem;">🖼️</button>
                            </form>
                            <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" onsubmit="return confirm('Delete this post?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-a btn-red" style="padding:.28rem .65rem; font-size:.72rem;">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="color:var(--dim); text-align:center; padding:2rem;">
                        No blog posts yet. Create your first post or use AI Auto-Generate.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($posts->hasPages())
    @include('admin.partials.pagination', ['paginator' => $posts])
    @endif
</div>

@endsection
