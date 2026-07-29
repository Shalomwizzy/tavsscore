@extends('layouts.admin')

@section('title', 'Tennis Page Image')
@section('page-title', 'Tennis Page Image')

@section('content')
@php($path = $settings['tennis_page_hero_image']->value ?? null)
<div style="max-width:900px;">
    <div style="margin-bottom:1.25rem;padding:1.35rem;border-radius:14px;border:1px solid rgba(74,222,128,.28);background:radial-gradient(circle at 90% 0%,rgba(251,191,36,.16),transparent 30%),linear-gradient(135deg,#11261c,#08130f);">
        <div style="font-size:.66rem;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:#bbf7d0;">TavsScore Tennis</div>
        <h1 style="font-size:1.45rem;font-weight:900;color:#fff;margin:.4rem 0 .45rem;">Tennis Page Hero Image</h1>
        <p style="font-size:.8rem;line-height:1.6;color:var(--dim);max-width:660px;margin:0;">This image appears behind the tennis page’s premium header. A dark overlay is applied automatically so the text always stays easy to read.</p>
    </div>
    @if(session('success'))<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.35);color:#6ee7b7;padding:.8rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.8rem;font-weight:700;">✓ {{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
        @csrf
        <section style="background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
            <div style="aspect-ratio:21/9;background:linear-gradient(135deg,#173929,#0b1711);display:grid;place-items:center;border-bottom:1px solid var(--border);">
                @if($path)<img src="{{ asset($path) }}" alt="Tennis page hero" style="width:100%;height:100%;object-fit:cover;">@else<div style="text-align:center;color:var(--dim);"><div style="font-size:2.25rem;margin-bottom:.35rem;">🎾</div><strong style="font-size:.78rem;">No tennis hero image uploaded</strong></div>@endif
            </div>
            <div style="padding:1.1rem;"><h2 style="font-size:.95rem;color:#fff;margin:0 0 .35rem;font-weight:850;">Upload new hero image</h2><p style="font-size:.75rem;color:var(--dim);line-height:1.5;margin:0 0 .8rem;">Use JPG, PNG or WebP, up to 5 MB. Best result: 1920 × 900 or wider, with the players positioned to the right or edges.</p><input type="file" name="tennis_page_hero_image" accept="image/jpeg,image/png,image/webp" class="form-input" style="font-size:.75rem;width:100%;">@error('tennis_page_hero_image')<p style="color:#f87171;font-size:.72rem;margin:.45rem 0 0;">{{ $message }}</p>@enderror</div>
        </section>
        <div style="display:flex;gap:.7rem;align-items:center;flex-wrap:wrap;margin-top:1rem;"><button class="btn-a btn-green">Save Tennis Image</button><a href="{{ route('tennis.index') }}" target="_blank" rel="noopener" class="btn-a btn-gray">Preview tennis page ↗</a></div>
    </form>
</div>
@endsection
