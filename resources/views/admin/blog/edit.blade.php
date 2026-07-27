@extends('layouts.admin')
@section('title', 'Edit Post')
@section('page-title', 'Edit Post')

@section('content')
<div style="max-width:920px">
    <div class="page-hd">
        <span class="page-hd-title">✏️ Edit: {{ Str::limit($blog->title, 50) }}</span>
        <div style="display:flex; gap:.5rem;">
            <a href="{{ route('blog.show', $blog->slug) }}" target="_blank" class="btn-a btn-gray">🔗 View Live</a>
            <a href="{{ route('admin.blog.index') }}" class="btn-a btn-gray">← Back</a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-red">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.blog.update', $blog) }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div style="display:grid; grid-template-columns:1fr 310px; gap:1rem; align-items:start;">

            <div style="display:flex; flex-direction:column; gap:.75rem;">

                <div class="a-card">
                    <div class="form-group">
                        <label class="form-label" for="title">Title <span style="color:#ef4444">*</span></label>
                        <input id="title" type="text" name="title" class="form-input"
                               value="{{ old('title', $blog->title) }}" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" for="slug">
                            Slug
                            <span style="font-size:.68rem; font-weight:400; color:var(--dim); margin-left:.4rem;">changing this breaks existing links</span>
                        </label>
                        <div style="display:flex; align-items:center; gap:.5rem;">
                            <span style="font-size:.78rem; color:var(--dim); white-space:nowrap; flex-shrink:0;">tavsscore.com/blog/</span>
                            <input id="slug" type="text" name="slug" class="form-input"
                                   value="{{ old('slug', $blog->slug) }}" style="font-family:monospace; font-size:.82rem;" required>
                        </div>
                    </div>
                </div>

                <div class="a-card">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" for="excerpt">Excerpt <span style="font-size:.68rem; font-weight:400; color:var(--dim);">- shown on cards and used as meta description</span></label>
                        <textarea id="excerpt" name="excerpt" class="form-textarea" style="min-height:72px;"
                                  placeholder="130–160 characters is ideal for SEO…">{{ old('excerpt', $blog->excerpt) }}</textarea>
                        <div class="form-hint" id="excerpt-count"></div>
                    </div>
                </div>

                <div class="a-card">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" for="content">Content (HTML) <span style="color:#ef4444">*</span></label>
                        <textarea id="content" name="content" class="form-textarea" style="min-height:420px;" required>{{ old('content', $blog->content) }}</textarea>
                        <div class="form-hint">
                            <span id="word-count">0 words</span>
                        </div>
                    </div>
                </div>

            </div>

            <div style="display:flex; flex-direction:column; gap:.75rem; position:sticky; top:70px;">

                <div class="a-card">
                    <div style="font-size:.78rem; font-weight:700; color:#fff; margin-bottom:.875rem;">📤 Publish</div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer;">
                            <input type="checkbox" name="is_published" value="1"
                                   {{ $blog->is_published ? 'checked' : '' }}
                                   style="accent-color:#10b981; width:14px; height:14px;">
                            <span class="form-label" style="margin:0;">Published</span>
                        </label>
                    </div>
                    <div style="font-size:.7rem; color:var(--dim); margin-bottom:.875rem; line-height:1.6;">
                        Created {{ $blog->created_at->format('M d, Y g:i A') }}<br>
                        Updated {{ $blog->updated_at->format('M d, Y g:i A') }}<br>
                        @if($blog->is_ai_generated) <span style="color:#93c5fd;">🤖 AI-generated</span> @endif
                    </div>
                    <button type="submit" class="btn-a btn-green" style="width:100%; justify-content:center;">💾 Update Post</button>
                </div>

                <div class="a-card">
                    <div style="font-size:.78rem; font-weight:700; color:#fff; margin-bottom:.875rem;">⚙️ Details</div>
                    <div class="form-group">
                        <label class="form-label" for="category">Category</label>
                        <select id="category" name="category" class="form-select">
                            @foreach(['Football News','Match Previews','Match Reports','Premier League','Champions League','La Liga','Serie A','Bundesliga','Ligue 1','Transfer News','Football Analysis','Tactics & Data'] as $cat)
                                <option value="{{ $cat }}" {{ old('category', $blog->category) === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" for="author">Author</label>
                        <input id="author" type="text" name="author" class="form-input"
                               value="{{ old('author', $blog->author) }}" required>
                    </div>
                </div>

                <div class="a-card">
                    <div style="font-size:.78rem; font-weight:700; color:#fff; margin-bottom:.875rem;">✨ AI Regenerate</div>
                    <p style="font-size:.72rem; color:var(--dim); line-height:1.5; margin:0 0 .75rem;">Replace only the part you choose. The other part stays unchanged.</p>
                    <form method="POST" action="{{ route('admin.blog.regenerate-article', $blog) }}" onsubmit="return confirm('Replace this article with a fresh AI version? The current image will stay.');" style="margin-bottom:.5rem;">
                        @csrf
                        <button type="submit" class="btn-a" style="width:100%;justify-content:center;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:#93c5fd;">📝 Regenerate News</button>
                    </form>
                    <form method="POST" action="{{ route('admin.blog.regenerate-image', $blog) }}" onsubmit="return confirm('Replace the current featured image with a new Tavs Score watermarked image?');">
                        @csrf
                        <button type="submit" class="btn-a" style="width:100%;justify-content:center;background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#6ee7b7;">🖼️ Regenerate Image</button>
                    </form>
                </div>

                <div class="a-card">
                    <div style="font-size:.78rem; font-weight:700; color:#fff; margin-bottom:.875rem;">🖼️ Featured Image</div>
                    <div style="font-size:.68rem;font-weight:700;color:var(--dim);margin-bottom:.4rem;">CURRENT IMAGE</div>
                    @if($blog->image_url)
                        <img src="{{ $blog->image_url }}" alt="Current image"
                             style="width:100%; border-radius:6px; aspect-ratio:16/9; object-fit:cover; border:1px solid var(--border); margin-bottom:.75rem;"
                             id="img-preview-el" onerror="this.style.display='none'">
                    @else
                        <div id="img-preview" style="display:none; margin-bottom:.75rem;">
                            <img id="img-preview-el" src="" alt="Preview"
                                 style="width:100%; border-radius:6px; aspect-ratio:16/9; object-fit:cover; border:1px solid var(--border);">
                        </div>
                    @endif
                    @if($imagePreview)
                        <div style="border-top:1px solid var(--border);margin:1rem 0 .75rem;padding-top:.875rem;">
                            <div style="font-size:.68rem;font-weight:700;color:#6ee7b7;margin-bottom:.4rem;">NEW AI IMAGE PREVIEW</div>
                            <img src="{{ $imagePreview }}" alt="New generated preview"
                                 style="width:100%;border-radius:6px;aspect-ratio:16/9;object-fit:cover;border:1px solid rgba(16,185,129,.55);margin-bottom:.6rem;">
                            <form method="POST" action="{{ route('admin.blog.apply-image-preview', $blog) }}" style="margin-bottom:.4rem;">@csrf<button type="submit" class="btn-a btn-green" style="width:100%;justify-content:center;">✓ Use New Image</button></form>
                            <form method="POST" action="{{ route('admin.blog.discard-image-preview', $blog) }}">@csrf<button type="submit" class="btn-a btn-gray" style="width:100%;justify-content:center;">Keep Current Image</button></form>
                        </div>
                    @endif
                    <div class="form-group">
                        <label class="form-label" for="image_file">Replace Image (Upload)</label>
                        <input id="image_file" type="file" name="image_file" class="form-input"
                               accept="image/jpeg,image/png,image/webp,image/gif"
                               onchange="previewFile(this)">
                        <div class="form-hint">JPG, PNG, WebP or GIF, up to 4 MB. Leave empty to keep current image.</div>
                    </div>
                </div>

            </div>
        </div>
    </form>

    <div style="max-width:310px; margin-top:.75rem;">
        <div class="a-card">
            <div style="font-size:.78rem; font-weight:700; color:#ef4444; margin-bottom:.75rem;">🗑 Danger Zone</div>
            <form method="POST" action="{{ route('admin.blog.destroy', $blog) }}"
                  onsubmit="return confirm('Permanently delete this post? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="btn-a btn-red" style="width:100%; justify-content:center;">Delete Post</button>
            </form>
        </div>
    </div>
</div>

<script>
function previewFile(input) {
    var el = document.getElementById('img-preview-el');
    var wrap = document.getElementById('img-preview');
    var file = input.files && input.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
        if (el) { el.src = e.target.result; el.style.display = 'block'; }
        if (wrap) wrap.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

(function() {
    var content = document.getElementById('content');
    var excerpt = document.getElementById('excerpt');

    function countWords() {
        var words = content.value.replace(/<[^>]+>/g,'').trim().split(/\s+/).filter(Boolean).length;
        document.getElementById('word-count').textContent = words + ' word' + (words !== 1 ? 's' : '');
    }

    function countChars() {
        var n = excerpt.value.length;
        var el = document.getElementById('excerpt-count');
        el.textContent = n + ' chars' + (n < 130 ? ' - add more for SEO' : n > 160 ? ' - slightly long' : ' - perfect');
        el.style.color = n >= 130 && n <= 160 ? '#6ee7b7' : 'var(--dim)';
    }

    content.addEventListener('input', countWords);
    excerpt.addEventListener('input', countChars);
    countWords(); countChars();
}());
</script>
@endsection
