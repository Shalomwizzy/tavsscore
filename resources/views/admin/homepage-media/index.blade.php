@extends('layouts.admin')

@section('title', 'Homepage Images')
@section('page-title', 'Homepage Images')

@section('content')
<div style="max-width:980px;">
    <div style="margin-bottom:1.25rem;padding:1.25rem;border-radius:12px;border:1px solid rgba(16,185,129,.28);background:radial-gradient(circle at 90% 0%,rgba(16,185,129,.15),transparent 36%),linear-gradient(135deg,#101b2d,#0a111e);">
        <div style="font-size:.65rem;font-weight:900;letter-spacing:.09em;text-transform:uppercase;color:#6ee7b7;">TavsScore homepage</div>
        <h1 style="font-size:1.35rem;font-weight:900;color:#fff;margin:.35rem 0 .45rem;">Upload Homepage Images</h1>
        <p style="font-size:.78rem;line-height:1.55;color:var(--dim);max-width:680px;">Choose the three images that power the cinematic homepage. Upload a JPG, PNG or WebP up to 5 MB, then press Save Homepage Images.</p>
    </div>

    @if(session('success'))
        <div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.35);color:#6ee7b7;padding:.8rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.8rem;font-weight:700;">✓ {{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem;">
            @foreach([
                'homepage_hero_image' => ['Hero Stadium Image', 'Main image behind the homepage hero', 'Wide 16:9 · 1920 × 1080 recommended'],
                'homepage_feature_image' => ['Football Feature Image', 'Football story and prediction feature image', 'Landscape or portrait · 1400 × 1000 recommended'],
                'homepage_tennis_image' => ['Tennis Banner Image', 'Tennis prediction section image', 'Wide 16:9 · 1400 × 700 recommended'],
            ] as $field => [$label, $description, $hint])
                @php($path = $settings[$field]->value ?? null)
                <section style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;">
                    <div style="aspect-ratio:16/9;background:linear-gradient(135deg,#17243a,#0d1421);display:grid;place-items:center;border-bottom:1px solid var(--border);">
                        @if($path)
                            <img src="{{ asset($path) }}" alt="{{ $label }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            <div style="text-align:center;color:var(--dim);"><div style="font-size:2rem;margin-bottom:.3rem;">🖼️</div><div style="font-size:.7rem;font-weight:700;">No image uploaded yet</div></div>
                        @endif
                    </div>
                    <div style="padding:1rem;">
                        <h2 style="font-size:.9rem;color:#fff;margin:0 0 .3rem;font-weight:850;">{{ $label }}</h2>
                        <p style="font-size:.7rem;color:var(--dim);line-height:1.45;margin:0 0 .75rem;">{{ $description }}<br>{{ $hint }}</p>
                        <input type="file" name="{{ $field }}" accept="image/jpeg,image/png,image/webp" class="form-input" style="font-size:.72rem;width:100%;">
                        @error($field)<p style="color:#f87171;font-size:.72rem;margin:.45rem 0 0;">{{ $message }}</p>@enderror
                    </div>
                </section>
            @endforeach
        </div>

        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:1.25rem;">
            <button type="submit" class="btn-a btn-green" style="padding:.65rem 1rem;">🖼️ Save Homepage Images</button>
            <a href="{{ route('home.index') }}" target="_blank" rel="noopener" class="btn-a btn-gray">View homepage ↗</a>
            <span style="font-size:.7rem;color:var(--dim);">Leave a field empty to keep its current image.</span>
        </div>
    </form>
</div>
@endsection
