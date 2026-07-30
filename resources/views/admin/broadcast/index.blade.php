@extends('layouts.admin')
@section('title', 'Broadcast Message')
@section('page-title', 'Broadcast Message')

@section('content')
<div style="max-width:680px;">

    @if(session('success'))
        <div class="alert alert-green" style="margin-bottom:1.25rem;">✅ {{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-red" style="margin-bottom:1.25rem;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <div class="a-card">
        <p style="font-size:.78rem; color:var(--dim); margin-bottom:1.25rem; line-height:1.6;">
            Write a custom message and send it to your Telegram channel, all push-notification subscribers, or both at once.
        </p>

        <form method="POST" action="{{ route('admin.broadcast.send') }}">
            @csrf

            {{-- Channel selector --}}
            <div style="margin-bottom:1.1rem;">
                <label style="font-size:.75rem; font-weight:700; color:#fff; display:block; margin-bottom:.5rem;">Send to</label>
                <div style="display:flex; gap:.6rem; flex-wrap:wrap;">
                    @foreach([
                        ['telegram', '📢 Telegram only',       'rgba(56,189,248,.15)', 'rgba(56,189,248,.4)'],
                        ['push',     '🔔 Push notifications only', 'rgba(167,139,250,.15)', 'rgba(167,139,250,.4)'],
                        ['both',     '🚀 Both together',        'rgba(52,211,153,.15)', 'rgba(52,211,153,.4)'],
                    ] as [$val, $lbl, $bg, $border])
                    <label style="cursor:pointer; flex:1; min-width:160px;">
                        <input type="radio" name="channel" value="{{ $val }}" {{ old('channel', 'both') === $val ? 'checked' : '' }}
                               style="display:none;" class="ch-radio">
                        <span class="ch-pill" data-val="{{ $val }}"
                              style="display:flex; align-items:center; justify-content:center; gap:.4rem;
                                     background:{{ $bg }}; border:1px solid {{ $border }};
                                     border-radius:10px; padding:.6rem .8rem; font-size:.76rem; font-weight:700;
                                     color:#fff; transition:box-shadow .15s; white-space:nowrap;">
                            {{ $lbl }}
                        </span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Title --}}
            <div style="margin-bottom:.85rem;">
                <label style="font-size:.75rem; font-weight:700; color:#fff; display:block; margin-bottom:.35rem;">
                    Title <span style="color:var(--dim); font-weight:400;">(push notification headline / Telegram bold header)</span>
                </label>
                <input type="text" name="title" value="{{ old('title') }}" maxlength="120" placeholder="e.g. 🔥 Big match tonight!"
                       style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text);
                              border-radius:8px; padding:.55rem .75rem; font-size:.82rem; box-sizing:border-box;">
            </div>

            {{-- Message --}}
            <div style="margin-bottom:.85rem;">
                <label style="font-size:.75rem; font-weight:700; color:#fff; display:block; margin-bottom:.35rem;">
                    Message
                </label>
                <textarea name="message" rows="5" maxlength="1000" placeholder="Type your message here…"
                          style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text);
                                 border-radius:8px; padding:.55rem .75rem; font-size:.82rem; resize:vertical; box-sizing:border-box; line-height:1.55;">{{ old('message') }}</textarea>
                <div style="font-size:.68rem; color:var(--dim); margin-top:.25rem;">
                    Telegram supports *bold* and _italic_ markdown. Push notifications are plain text.
                </div>
            </div>

            {{-- URL path (push only) --}}
            <div id="path-row" style="margin-bottom:1.1rem;">
                <label style="font-size:.75rem; font-weight:700; color:#fff; display:block; margin-bottom:.35rem;">
                    Link path <span style="color:var(--dim); font-weight:400;">(push only, where tapping takes users)</span>
                </label>
                <div style="display:flex; align-items:center; gap:.4rem;">
                    <span style="font-size:.8rem; color:var(--dim);">{{ config('app.url') }}</span>
                    <input type="text" name="path" value="{{ old('path', '/picks') }}"
                           placeholder="/picks"
                           style="flex:1; background:var(--bg); border:1px solid var(--border); color:var(--text);
                                  border-radius:8px; padding:.5rem .65rem; font-size:.8rem; box-sizing:border-box;">
                </div>
            </div>

            <button type="submit" class="btn-a btn-green" style="width:100%; justify-content:center; font-size:.85rem; padding:.65rem 1rem;">
                📤 Send Message
            </button>
        </form>
    </div>

    {{-- Preview card --}}
    <div class="a-card" style="margin-top:1.25rem; border-color:rgba(255,255,255,.08);">
        <div style="font-size:.75rem; font-weight:700; color:#fff; margin-bottom:.75rem;">Preview</div>
        <div style="background:rgba(0,0,0,.3); border-radius:10px; padding:.9rem 1rem;">
            <div id="preview-title" style="font-weight:800; color:#fff; font-size:.88rem; margin-bottom:.3rem;">Your title here</div>
            <div id="preview-body"  style="font-size:.78rem; color:var(--dim); line-height:1.55; white-space:pre-wrap;">Your message here…</div>
        </div>
    </div>
</div>

<style>
.ch-radio:checked + .ch-pill { box-shadow: 0 0 0 2px #10b981; opacity:1; }
.ch-pill { opacity:.65; transition:opacity .15s, box-shadow .15s; }
.ch-radio:checked + .ch-pill { opacity:1; }
</style>

<script>
// Radio pill highlight
document.querySelectorAll('.ch-radio').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.ch-pill').forEach(function(p) {
            p.style.boxShadow = '';
            p.style.opacity = '.65';
        });
        if (this.checked) {
            var pill = this.nextElementSibling;
            pill.style.boxShadow = '0 0 0 2px #10b981';
            pill.style.opacity   = '1';
        }
        // Toggle path row visibility
        var ch = document.querySelector('.ch-radio:checked');
        document.getElementById('path-row').style.display = (ch && ch.value === 'telegram') ? 'none' : '';
    });
});

// Init pill states on load
(function() {
    var checked = document.querySelector('.ch-radio:checked');
    if (checked) {
        checked.nextElementSibling.style.boxShadow = '0 0 0 2px #10b981';
        checked.nextElementSibling.style.opacity   = '1';
        document.getElementById('path-row').style.display = checked.value === 'telegram' ? 'none' : '';
    }
})();

// Live preview
function updatePreview() {
    var t = document.querySelector('[name=title]').value   || 'Your title here';
    var m = document.querySelector('[name=message]').value || 'Your message here…';
    document.getElementById('preview-title').textContent = t;
    document.getElementById('preview-body').textContent  = m;
}
document.querySelector('[name=title]').addEventListener('input', updatePreview);
document.querySelector('[name=message]').addEventListener('input', updatePreview);
</script>
@endsection
