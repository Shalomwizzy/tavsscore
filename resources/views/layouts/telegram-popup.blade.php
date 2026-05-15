@if($telegramUrl)
{{-- Telegram slide-in — shown once per browser (cookie-gated, 20 s delay) --}}
<div id="tg-popup" style="
    display:none;
    position:fixed;
    bottom:1.25rem;
    right:1.25rem;
    z-index:9990;
    width:min(320px, calc(100vw - 2rem));
    background:linear-gradient(145deg,#0f1923,#162032);
    border:1px solid rgba(255,255,255,.12);
    border-radius:14px;
    padding:1.25rem 1.1rem 1rem;
    box-shadow:0 16px 48px rgba(0,0,0,.55);
    animation: tgSlideIn .35s cubic-bezier(.22,1,.36,1) both;
">
    <button onclick="tgDismiss()" aria-label="Close" style="
        position:absolute; top:.6rem; right:.7rem;
        background:none; border:none; color:rgba(255,255,255,.4);
        font-size:1.1rem; cursor:pointer; line-height:1; padding:.2rem .4rem;
    ">&times;</button>

    <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:.9rem;">
        <div style="width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#2aabee,#1a8dc4);
                    display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="#fff">
                <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
            </svg>
        </div>
        <div>
            <div style="font-weight:700; font-size:.9rem; color:#fff; line-height:1.3;">Join TavsScore on Telegram</div>
            <div style="font-size:.72rem; color:rgba(255,255,255,.5); margin-top:.15rem;">Free daily picks · 09:00 Lagos</div>
        </div>
    </div>

    <p style="font-size:.78rem; color:rgba(255,255,255,.65); line-height:1.55; margin:0 0 1rem;">
        Get today's 3 AI picks, rollover updates, and live score alerts before everyone else.
    </p>

    <a href="{{ $telegramUrl }}" target="_blank" rel="noopener noreferrer" onclick="tgDismiss()"
       style="display:block; text-align:center; background:linear-gradient(135deg,#2aabee,#1a8dc4);
              color:#fff; text-decoration:none; font-size:.82rem; font-weight:700;
              padding:.6rem 1rem; border-radius:8px; letter-spacing:.01em;">
        Join the Channel &rarr;
    </a>

    <p style="text-align:center; margin:.6rem 0 0; font-size:.68rem; color:rgba(255,255,255,.3);">
        No spam &mdash; unfollow any time
    </p>
</div>

<style>
@keyframes tgSlideIn {
    from { opacity:0; transform:translateY(20px); }
    to   { opacity:1; transform:translateY(0); }
}
</style>

<script>
(function () {
    var COOKIE = 'tg_popup_seen';
    var DELAY  = 20000; // 20 seconds

    function getCookie(name) {
        return document.cookie.split(';').some(function(c) {
            return c.trim().startsWith(name + '=');
        });
    }

    function setCookie(name, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 864e5);
        document.cookie = name + '=1; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
    }

    window.tgDismiss = function () {
        var el = document.getElementById('tg-popup');
        if (el) {
            el.style.transition = 'opacity .2s, transform .2s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(16px)';
            setTimeout(function () { el.style.display = 'none'; }, 220);
        }
        setCookie(COOKIE, 60); // hide for 60 days
    };

    if (!getCookie(COOKIE)) {
        setTimeout(function () {
            var el = document.getElementById('tg-popup');
            if (el) el.style.display = 'block';
        }, DELAY);
    }
})();
</script>
@endif
