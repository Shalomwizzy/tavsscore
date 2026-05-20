@extends('layouts.admin')
@section('title', 'Affiliate Links')
@section('page-title', '💰 Affiliate Links')

@section('content')

<div class="page-hd">
    <span class="page-hd-title">💰 Affiliate Links</span>
</div>

@if(session('success'))
<div class="alert alert-green">✓ {{ session('success') }}</div>
@endif

{{-- Add new affiliate link --}}
<div class="a-card" style="margin-bottom:1.25rem;">
    <div class="page-hd" style="margin-bottom:1rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">➕ Add Platform</span>
    </div>
    <form method="POST" action="{{ route('admin.affiliate-links.store') }}">
        @csrf
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:.75rem; margin-bottom:.75rem;">
            <div>
                <label class="form-label">Platform Name</label>
                <input type="text" name="platform" value="{{ old('platform') }}" required maxlength="60"
                    placeholder="e.g. 1xBet"
                    class="form-input" style="width:100%; box-sizing:border-box;">
                @error('platform') <span class="form-hint" style="color:#fca5a5;">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="form-label">Slug</label>
                <input type="text" name="slug" value="{{ old('slug') }}" required maxlength="60"
                    placeholder="e.g. 1xbet"
                    class="form-input" style="width:100%; box-sizing:border-box;"
                    oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9\-]/g,'')">
                <span class="form-hint">Lowercase letters, numbers, hyphens only</span>
                @error('slug') <span class="form-hint" style="color:#fca5a5;">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="99"
                    class="form-input" style="width:100%; box-sizing:border-box;">
                @error('sort_order') <span class="form-hint" style="color:#fca5a5;">{{ $message }}</span> @enderror
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:.75rem;">
            <div>
                <label class="form-label">Register URL (Affiliate)</label>
                <input type="url" name="register_url" value="{{ old('register_url') }}" required maxlength="500"
                    placeholder="https://..."
                    class="form-input" style="width:100%; box-sizing:border-box;">
                @error('register_url') <span class="form-hint" style="color:#fca5a5;">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="form-label">Website URL <span style="opacity:.5;">(optional)</span></label>
                <input type="url" name="website_url" value="{{ old('website_url') }}" maxlength="500"
                    placeholder="https://..."
                    class="form-input" style="width:100%; box-sizing:border-box;">
                @error('website_url') <span class="form-hint" style="color:#fca5a5;">{{ $message }}</span> @enderror
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:.75rem; margin-bottom:1rem;">
            <div>
                <label class="form-label">Promo Text <span style="opacity:.5;">(optional)</span></label>
                <input type="text" name="promo_text" value="{{ old('promo_text') }}" maxlength="200"
                    placeholder="e.g. Get a 200% welcome bonus"
                    class="form-input" style="width:100%; box-sizing:border-box;">
                @error('promo_text') <span class="form-hint" style="color:#fca5a5;">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="form-label">Color (hex)</label>
                <input type="text" name="color" value="{{ old('color', '#f59e0b') }}" maxlength="7"
                    placeholder="#f59e0b"
                    class="form-input" style="width:100%; box-sizing:border-box;">
                @error('color') <span class="form-hint" style="color:#fca5a5;">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="form-label">Logo Emoji</label>
                <input type="text" name="logo_emoji" value="{{ old('logo_emoji', '🎯') }}" maxlength="10"
                    placeholder="🎯"
                    class="form-input" style="width:100%; box-sizing:border-box;">
                @error('logo_emoji') <span class="form-hint" style="color:#fca5a5;">{{ $message }}</span> @enderror
            </div>
        </div>
        <button type="submit" class="btn-a btn-green">➕ Add Platform</button>
    </form>
</div>

{{-- Existing affiliate links --}}
<div class="a-card">
    <div class="page-hd" style="margin-bottom:.875rem;">
        <span style="font-weight:700; font-size:.9rem; color:#fff;">📋 All Affiliate Links</span>
    </div>

    @if($links->isEmpty())
        <div style="text-align:center; padding:1.75rem; color:var(--dim); font-size:.85rem;">No affiliate links added yet.</div>
    @else
        <div style="overflow-x:auto;">
            <table class="a-table">
                <thead>
                    <tr>
                        <th>Platform</th>
                        <th>Slug</th>
                        <th>Register URL</th>
                        <th>Promo Text</th>
                        <th>Active</th>
                        <th>Sort</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($links as $link)
                    <tr>
                        <td>
                            <span style="font-size:1rem;">{{ $link->logo_emoji }}</span>
                            <strong style="color:#fff; margin-left:.35rem;">{{ $link->platform }}</strong>
                        </td>
                        <td>
                            <span style="font-family:monospace; font-size:.78rem; color:var(--dim);">{{ $link->slug }}</span>
                        </td>
                        <td>
                            <a href="{{ $link->register_url }}" target="_blank" rel="noopener"
                               style="color:#a5b4fc; font-size:.75rem; text-decoration:underline; word-break:break-all;">
                                {{ Str::limit($link->register_url, 45) }}
                            </a>
                        </td>
                        <td style="font-size:.75rem; color:var(--dim); max-width:160px;">
                            {{ $link->promo_text ?: '—' }}
                        </td>
                        <td>
                            @if($link->is_active)
                                <span class="badge badge-green">Active</span>
                            @else
                                <span class="badge badge-red">Inactive</span>
                            @endif
                        </td>
                        <td style="font-size:.82rem; color:var(--dim);">{{ $link->sort_order }}</td>
                        <td>
                            <div style="display:flex; gap:.4rem; align-items:center; flex-wrap:wrap;">
                                {{-- Toggle active --}}
                                <form method="POST" action="{{ route('admin.affiliate-links.toggle', $link) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-a btn-gray" style="font-size:.72rem; padding:.28rem .65rem;">
                                        {{ $link->is_active ? '⏸ Pause' : '▶ Enable' }}
                                    </button>
                                </form>
                                {{-- Delete --}}
                                <form method="POST" action="{{ route('admin.affiliate-links.destroy', $link) }}" style="display:inline;" onsubmit="return confirm('Delete {{ addslashes($link->platform) }} affiliate link?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-a btn-red" style="font-size:.72rem; padding:.28rem .65rem;">🗑 Delete</button>
                                </form>
                            </div>
                            {{-- Inline edit via <details> --}}
                            <details style="margin-top:.5rem;">
                                <summary style="font-size:.72rem; color:#a5b4fc; cursor:pointer; list-style:none; user-select:none;">✏️ Edit</summary>
                                <div style="margin-top:.6rem; padding:.75rem; background:rgba(255,255,255,.03); border:1px solid var(--border); border-radius:8px;">
                                    <form method="POST" action="{{ route('admin.affiliate-links.update', $link) }}">
                                        @csrf
                                        @method('PUT')
                                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:.5rem; margin-bottom:.5rem;">
                                            <div>
                                                <label class="form-label" style="font-size:.7rem;">Platform</label>
                                                <input type="text" name="platform" value="{{ $link->platform }}" required maxlength="60" class="form-input" style="width:100%; box-sizing:border-box; font-size:.78rem;">
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size:.7rem;">Slug</label>
                                                <input type="text" name="slug" value="{{ $link->slug }}" required maxlength="60" class="form-input" style="width:100%; box-sizing:border-box; font-size:.78rem;" oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9\-]/g,'')">
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size:.7rem;">Sort</label>
                                                <input type="number" name="sort_order" value="{{ $link->sort_order }}" min="0" max="99" class="form-input" style="width:100%; box-sizing:border-box; font-size:.78rem;">
                                            </div>
                                        </div>
                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.5rem;">
                                            <div>
                                                <label class="form-label" style="font-size:.7rem;">Register URL</label>
                                                <input type="url" name="register_url" value="{{ $link->register_url }}" required maxlength="500" class="form-input" style="width:100%; box-sizing:border-box; font-size:.78rem;">
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size:.7rem;">Website URL</label>
                                                <input type="url" name="website_url" value="{{ $link->website_url }}" maxlength="500" class="form-input" style="width:100%; box-sizing:border-box; font-size:.78rem;">
                                            </div>
                                        </div>
                                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:.5rem; margin-bottom:.75rem;">
                                            <div>
                                                <label class="form-label" style="font-size:.7rem;">Promo Text</label>
                                                <input type="text" name="promo_text" value="{{ $link->promo_text }}" maxlength="200" class="form-input" style="width:100%; box-sizing:border-box; font-size:.78rem;">
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size:.7rem;">Color</label>
                                                <input type="text" name="color" value="{{ $link->color }}" maxlength="7" class="form-input" style="width:100%; box-sizing:border-box; font-size:.78rem;">
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size:.7rem;">Logo Emoji</label>
                                                <input type="text" name="logo_emoji" value="{{ $link->logo_emoji }}" maxlength="10" class="form-input" style="width:100%; box-sizing:border-box; font-size:.78rem;">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn-a btn-blue" style="font-size:.75rem;">💾 Save Changes</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
