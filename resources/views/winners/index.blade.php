@extends('layouts.app')

@section('title', 'Winners Wall | TavsScore')
@section('meta_description', 'Real wins from real TavsScore users. Submit your winning screenshot and join the wall.')

@push('styles')
<style>
    .winners-header { padding:2rem 0 1.5rem; border-bottom:1px solid var(--border); margin-bottom:2rem; }
    .winners-title  { font-size:1.5rem; font-weight:900; color:#fff; letter-spacing:-.02em; margin-bottom:.3rem; }
    .winners-sub    { font-size:.82rem; color:var(--text-dim); }

    .winners-grid {
        display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:2rem;
    }

    .win-card {
        background:var(--card); border:1px solid var(--border); border-radius:14px;
        overflow:hidden; transition:border-color 200ms, transform 200ms;
    }
    .win-card:hover { border-color:rgba(16,185,129,.3); transform:translateY(-2px); }

    .win-img { width:100%; aspect-ratio:4/3; object-fit:cover; cursor:pointer; }

    .win-body { padding:.875rem 1rem; }
    .win-user { font-size:.82rem; font-weight:800; color:#fff; margin-bottom:.25rem; }
    .win-pick { font-size:.72rem; color:var(--text-dim); margin-bottom:.4rem; line-height:1.5; }
    .win-amount {
        display:inline-flex; align-items:center; gap:.3rem;
        font-size:.75rem; font-weight:800; color:#10b981;
        background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.2);
        padding:3px 10px; border-radius:999px;
    }

    /* Submit form */
    .submit-card {
        background:var(--card); border:1px solid var(--border); border-radius:14px;
        padding:1.5rem; margin-bottom:2rem;
    }
    .submit-title { font-size:1rem; font-weight:800; color:#fff; margin-bottom:1rem; }

    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
    .f-group { display:flex; flex-direction:column; gap:.35rem; margin-bottom:.75rem; }
    .f-label { font-size:.72rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:.05em; }
    .f-input, .f-select {
        background:var(--surface); border:1px solid var(--border); color:#fff;
        border-radius:8px; padding:.55rem .75rem; font-size:.82rem; font-family:inherit;
        width:100%; transition:border-color 160ms;
    }
    .f-input:focus, .f-select:focus { outline:none; border-color:rgba(16,185,129,.5); }
    .f-hint { font-size:.68rem; color:var(--text-muted); }

    /* Lightbox */
    .lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,.9); z-index:9999; align-items:center; justify-content:center; }
    .lightbox.open { display:flex; }
    .lightbox img { max-width:90vw; max-height:90vh; border-radius:10px; }
    .lightbox-close { position:absolute; top:1rem; right:1.25rem; font-size:2rem; color:#fff; cursor:pointer; background:none; border:none; }

    @media(max-width:900px){ .winners-grid{ grid-template-columns:repeat(2,1fr); } }
    @media(max-width:560px){ .winners-grid{ grid-template-columns:1fr; } .form-row{ grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="wrap">

    <div class="winners-header">
        <h1 class="winners-title">🏆 Winners Wall</h1>
        <p class="winners-sub">Real wins from real TavsScore users - verified and approved before publishing.</p>
    </div>

    @if(session('success'))
        <div style="background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.3); border-radius:10px; padding:1rem 1.25rem; margin-bottom:1.5rem; color:#6ee7b7; font-size:.85rem; font-weight:600;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Submit form --}}
    <div class="submit-card">
        <div class="submit-title">🎉 Share Your Win</div>
        <form method="POST" action="{{ route('winners.submit') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-row">
                <div class="f-group">
                    <label class="f-label">Username *</label>
                    <input type="text" name="username" id="username-input" class="f-input"
                           placeholder="e.g. BigWinnerNg" maxlength="60" required
                           value="{{ old('username') }}" autocomplete="off">
                    <div id="username-status" style="font-size:.7rem; margin-top:.25rem; min-height:1rem;"></div>
                    @error('username') <span style="color:#ef4444;font-size:.7rem;">{{ $message }}</span> @enderror
                </div>
                <div class="f-group">
                    <label class="f-label">Your Email * <span style="font-weight:400; color:var(--text-dim);">(private, never shown publicly)</span></label>
                    <input type="email" name="email" id="email-input" class="f-input"
                           placeholder="you@example.com" maxlength="150" required
                           value="{{ old('email') }}">
                    <span class="f-hint">Used only to verify your identity on future submissions.</span>
                    @error('email') <span style="color:#ef4444;font-size:.7rem;">{{ $message }}</span> @enderror
                </div>
                <div class="f-group">
                    <label class="f-label">Winning Screenshots * (up to 5)</label>
                    <input type="file" name="screenshots[]" id="screenshots" class="f-input"
                           accept="image/*" multiple required
                           onchange="showPreview(this)">
                    <span class="f-hint">JPG, PNG or WebP · max 5MB each · select up to 5 images</span>
                    <div id="preview-row" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem;"></div>
                    @error('screenshots') <span style="color:#ef4444;font-size:.7rem;">{{ $message }}</span> @enderror
                    @error('screenshots.*') <span style="color:#ef4444;font-size:.7rem;">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="form-row">
                <div class="f-group">
                    <label class="f-label">Which TavsScore pick did you follow? <span style="font-weight:400;color:var(--text-dim);">(optional)</span></label>
                    <input type="text" name="pick_description" class="f-input" placeholder="e.g. Arsenal Home Win, May 13" maxlength="255" value="{{ old('pick_description') }}">
                </div>
                <div class="f-group">
                    <label class="f-label">Match details <span style="font-weight:400;color:var(--text-dim);">(optional)</span></label>
                    <input type="text" name="match_details" class="f-input" placeholder="e.g. Arsenal vs Chelsea, 2:1" maxlength="255" value="{{ old('match_details') }}">
                    <span class="f-hint">The match the prediction was for.</span>
                </div>
                <div class="f-group">
                    <label class="f-label">Betting platform <span style="font-weight:400;color:var(--text-dim);">(optional)</span></label>
                    <select name="platform" class="f-select">
                        <option value=""> Select platform </option>
                        <option value="Bet9ja"    {{ old('platform') === 'Bet9ja'    ? 'selected' : '' }}>Bet9ja</option>
                        <option value="SportyBet" {{ old('platform') === 'SportyBet' ? 'selected' : '' }}>SportyBet</option>
                        <option value="1xBet"     {{ old('platform') === '1xBet'     ? 'selected' : '' }}>1xBet</option>
                        <option value="Betway"    {{ old('platform') === 'Betway'    ? 'selected' : '' }}>Betway</option>
                        <option value="BetKing"   {{ old('platform') === 'BetKing'   ? 'selected' : '' }}>BetKing</option>
                        <option value="MSport"    {{ old('platform') === 'MSport'    ? 'selected' : '' }}>MSport</option>
                        <option value="Parimatch" {{ old('platform') === 'Parimatch' ? 'selected' : '' }}>Parimatch</option>
                        <option value="Betfair"   {{ old('platform') === 'Betfair'   ? 'selected' : '' }}>Betfair</option>
                        <option value="Other"     {{ old('platform') === 'Other'     ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
                <div class="f-group">
                    <label class="f-label">Amount won <span style="font-weight:400;color:var(--text-dim);">(optional)</span></label>
                    <div style="display:flex; gap:.5rem;">
                        <select name="currency" class="f-select" style="width:90px; flex-shrink:0;">
                            <option value="USD">USD</option>
                            <option value="NGN">NGN</option>
                            <option value="GBP">GBP</option>
                            <option value="EUR">EUR</option>
                            <option value="KES">KES</option>
                            <option value="GHS">GHS</option>
                            <option value="ZAR">ZAR</option>
                        </select>
                        <input type="number" name="winning_amount" class="f-input" placeholder="0.00" min="0" step="0.01" value="{{ old('winning_amount') }}">
                    </div>
                    @error('winning_amount') <span style="color:#ef4444;font-size:.7rem;">{{ $message }}</span> @enderror
                </div>
            </div>
            <button type="submit" class="btn-ts btn-green" style="margin-top:.25rem;">Submit My Win →</button>
        </form>
    </div>

    {{-- Winners grid --}}
    @if($winners->isNotEmpty())
    <div class="winners-grid">
        @foreach($winners as $win)
        @php $urls = $win->screenshot_urls; @endphp
        <div class="win-card">
            <div style="position:relative;">
                <img src="{{ $urls[0] }}" alt="{{ $win->username }} winning screenshot"
                     class="win-img" onclick='openLightbox(@json($urls), 0)' loading="lazy">
                @if(count($urls) > 1)
                    <span style="position:absolute;top:.5rem;right:.5rem;background:rgba(0,0,0,.7);color:#fff;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:999px;">📷 {{ count($urls) }}</span>
                @endif
            </div>
            <div class="win-body">
                <div class="win-user">🏆 {{ $win->username }}</div>
                @if($win->pick_description)
                    <div class="win-pick">📌 {{ $win->pick_description }}</div>
                @endif
                @if($win->winning_amount)
                    <span class="win-amount">💰 {{ $win->currency }} {{ number_format($win->winning_amount, 2) }}</span>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    <div style="display:flex; justify-content:center; margin-top:1rem;">
        {{ $winners->links() }}
    </div>

    @else
    <div class="state-box">
        <span class="state-icon">🏆</span>
        <div class="state-title">No wins published yet</div>
        <p class="state-sub">Be the first to share your winning screenshot above!</p>
    </div>
    @endif

    <div style="height:2rem"></div>
</div>

{{-- Lightbox --}}
<div class="lightbox" id="lightbox">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <button id="lb-prev" onclick="lbNav(-1)" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;font-size:1.6rem;border-radius:50%;width:44px;height:44px;cursor:pointer;display:none;">‹</button>
    <img id="lightbox-img" src="" alt="Winning screenshot">
    <button id="lb-next" onclick="lbNav(1)" style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;font-size:1.6rem;border-radius:50%;width:44px;height:44px;cursor:pointer;display:none;">›</button>
    <div id="lb-counter" style="position:absolute;bottom:1rem;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.7);font-size:.78rem;display:none;"></div>
</div>
@endsection

@push('scripts')
<script>
let lbUrls = [], lbIdx = 0;

function showPreview(input) {
    const row = document.getElementById('preview-row');
    row.innerHTML = '';
    Array.from(input.files).slice(0, 5).forEach(file => {
        const img = document.createElement('img');
        img.style = 'width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,.1);';
        img.src = URL.createObjectURL(file);
        row.appendChild(img);
    });
}

function openLightbox(urls, idx) {
    lbUrls = urls; lbIdx = idx;
    document.getElementById('lightbox-img').src = lbUrls[lbIdx];
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
    const multi = lbUrls.length > 1;
    document.getElementById('lb-prev').style.display    = multi ? 'block' : 'none';
    document.getElementById('lb-next').style.display    = multi ? 'block' : 'none';
    document.getElementById('lb-counter').style.display = multi ? 'block' : 'none';
    if (multi) document.getElementById('lb-counter').textContent = `${lbIdx + 1} / ${lbUrls.length}`;
}

function lbNav(dir) {
    lbIdx = (lbIdx + dir + lbUrls.length) % lbUrls.length;
    document.getElementById('lightbox-img').src = lbUrls[lbIdx];
    document.getElementById('lb-counter').textContent = `${lbIdx + 1} / ${lbUrls.length}`;
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}

document.getElementById('lightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowRight') lbNav(1);
    if (e.key === 'ArrowLeft')  lbNav(-1);
});

// Real-time username check
(function () {
    const usernameInput = document.getElementById('username-input');
    const emailInput    = document.getElementById('email-input');
    const status        = document.getElementById('username-status');
    if (!usernameInput) return;

    let timer = null;

    function check() {
        clearTimeout(timer);
        const username = usernameInput.value.trim();
        const email    = emailInput ? emailInput.value.trim() : '';
        status.textContent = '';
        if (username.length < 2) return;

        status.innerHTML = '<span style="color:var(--text-dim);">Checking...</span>';

        timer = setTimeout(function () {
            fetch('{{ route('winners.check-username') }}?username=' + encodeURIComponent(username) + '&email=' + encodeURIComponent(email))
                .then(r => r.json())
                .then(data => {
                    if (!data.returning) {
                        status.innerHTML = '<span style="color:#10b981;">✓ Looks good!</span>';
                    } else if (data.email_match) {
                        status.innerHTML = '<span style="color:#10b981;">✓ Welcome back! Email matches your previous submission.</span>';
                    } else if (email.length > 0) {
                        status.innerHTML = '<span style="color:#ef4444;">⚠️ This username belongs to someone else, please use a different one, or check your email.</span>';
                    } else {
                        status.innerHTML = '<span style="color:#fbbf24;">⚠️ This username has been used before. Enter your email above to verify it\'s you.</span>';
                    }
                })
                .catch(() => { status.textContent = ''; });
        }, 500);
    }

    usernameInput.addEventListener('input', check);
    if (emailInput) emailInput.addEventListener('input', check);
})();
</script>
@endpush
