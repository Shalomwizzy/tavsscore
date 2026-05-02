@extends('layouts.admin')
@section('title', 'New Post')
@section('page-title', 'New Blog Post')

@section('content')
<div style="max-width:920px">
    <div class="page-hd">
        <span class="page-hd-title">✏️ Create Post</span>
        <a href="{{ route('admin.blog.index') }}" class="btn-a btn-gray">← Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-red">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.blog.store') }}" enctype="multipart/form-data">
        @csrf

        <div style="display:grid; grid-template-columns:1fr 310px; gap:1rem; align-items:start;">

            {{-- Main content --}}
            <div style="display:flex; flex-direction:column; gap:.75rem;">

                <div class="a-card">
                    <div class="form-group">
                        <label class="form-label" for="title">Title <span style="color:#ef4444">*</span></label>
                        <input id="title" type="text" name="title" class="form-input"
                               value="{{ old('title') }}" placeholder="Compelling, SEO-friendly title…"
                               required oninput="autoSlug(this.value)">
                    </div>

                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" for="slug">
                            Slug
                            <span style="font-size:.68rem; font-weight:400; color:var(--dim); margin-left:.4rem;">auto-generated — edit if needed</span>
                        </label>
                        <div style="display:flex; align-items:center; gap:.5rem;">
                            <span style="font-size:.78rem; color:var(--dim); white-space:nowrap; flex-shrink:0;">tavsscore.com/blog/</span>
                            <input id="slug" type="text" name="slug" class="form-input"
                                   value="{{ old('slug') }}" placeholder="url-friendly-slug" style="font-family:monospace; font-size:.82rem;">
                        </div>
                    </div>
                </div>

                <div class="a-card">
                    <div class="form-group">
                        <label class="form-label" for="excerpt">Excerpt <span style="font-size:.68rem; font-weight:400; color:var(--dim);">— shown on cards and used as meta description</span></label>
                        <textarea id="excerpt" name="excerpt" class="form-textarea" style="min-height:72px;"
                                  placeholder="1–2 sentences summarising the article. 130–160 characters is ideal for SEO.">{{ old('excerpt') }}</textarea>
                        <div class="form-hint" id="excerpt-count"></div>
                    </div>
                </div>

                <div class="a-card">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" for="content">Content (HTML) <span style="color:#ef4444">*</span></label>
                        <textarea id="content" name="content" class="form-textarea" style="min-height:420px;"
                                  placeholder="Write your article. Supported tags: &lt;p&gt; &lt;h2&gt; &lt;h3&gt; &lt;ul&gt; &lt;li&gt; &lt;strong&gt; &lt;em&gt; &lt;a&gt; &lt;blockquote&gt;"
                                  required>{{ old('content') }}</textarea>
                        <div class="form-hint">
                            <span id="word-count">0 words</span> &mdash; aim for 600+ for best SEO
                        </div>
                    </div>
                </div>

            </div>

            {{-- Sidebar --}}
            <div style="display:flex; flex-direction:column; gap:.75rem; position:sticky; top:70px;">

                <div class="a-card">
                    <div style="font-size:.78rem; font-weight:700; color:#fff; margin-bottom:.875rem;">📤 Publish</div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer;">
                            <input type="checkbox" name="is_published" value="1"
                                   {{ old('is_published') ? 'checked' : '' }}
                                   style="accent-color:#10b981; width:14px; height:14px;">
                            <span class="form-label" style="margin:0;">Publish immediately</span>
                        </label>
                        <div class="form-hint">Uncheck to save as draft.</div>
                    </div>
                    <button type="submit" class="btn-a btn-green" style="width:100%; justify-content:center;">💾 Save Post</button>
                </div>

                <div class="a-card">
                    <div style="font-size:.78rem; font-weight:700; color:#fff; margin-bottom:.875rem;">⚙️ Details</div>
                    <div class="form-group">
                        <label class="form-label" for="category">Category</label>
                        <select id="category" name="category" class="form-select">
                            @foreach(['Football News','Match Previews','Match Reports','Premier League','Champions League','La Liga','Serie A','Bundesliga','Ligue 1','Transfer News','Football Analysis','Tactics & Data'] as $cat)
                                <option value="{{ $cat }}" {{ old('category','Football News') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label" for="author">Author</label>
                        <input id="author" type="text" name="author" class="form-input"
                               value="{{ old('author', 'TavsScore Editorial') }}" required>
                    </div>
                </div>

                <div class="a-card">
                    <div style="font-size:.78rem; font-weight:700; color:#fff; margin-bottom:.875rem;">🖼️ Featured Image</div>
                    <div class="form-group">
                        <label class="form-label" for="image_file">Upload Image</label>
                        <input id="image_file" type="file" name="image_file" class="form-input"
                               accept="image/jpeg,image/png,image/webp,image/gif"
                               onchange="previewFile(this)">
                        <div class="form-hint">JPG, PNG, WebP or GIF. Up to 4 MB. 1200×630 (16:9) is recommended.</div>
                    </div>
                    <div id="img-preview" style="display:none; margin-top:.5rem;">
                        <img id="img-preview-el" src="" alt="Preview"
                             style="width:100%; border-radius:6px; aspect-ratio:16/9; object-fit:cover; border:1px solid var(--border);">
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

<script>
function autoSlug(val) {
    var s = val.toLowerCase()
        .replace(/[^a-z0-9\s-]/g,'')
        .trim().replace(/\s+/g,'-').replace(/-+/g,'-').substring(0,80);
    document.getElementById('slug').value = s;
}

function previewFile(input) {
    var wrap = document.getElementById('img-preview');
    var img  = document.getElementById('img-preview-el');
    var file = input.files && input.files[0];
    if (!file) { wrap.style.display = 'none'; return; }
    var reader = new FileReader();
    reader.onload = function (e) {
        img.src = e.target.result;
        wrap.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

document.getElementById('content').addEventListener('input', function() {
    var words = this.value.trim().split(/\s+/).filter(Boolean).length;
    document.getElementById('word-count').textContent = words + ' word' + (words !== 1 ? 's' : '');
});

document.getElementById('excerpt').addEventListener('input', function() {
    var n = this.value.length;
    var el = document.getElementById('excerpt-count');
    el.textContent = n + ' chars' + (n < 130 ? ' — add more for best SEO' : n > 160 ? ' — slightly long' : ' — perfect');
    el.style.color = n >= 130 && n <= 160 ? '#6ee7b7' : 'var(--dim)';
});
</script>
@endsection
