{{-- Shared language-toggle JS using event delegation, so it works for both
     server-rendered cards AND cards rendered later via fetch (predictions list).

     Pages opt in by structuring a section with these data attributes:

       <section data-pred-id="{{ $id }}"
                data-en="{{ $english }}"
                data-pidgin="{{ $pidgin ?? '' }}"
                data-swahili="{{ $swahili ?? '' }}">
         <div class="lang-toggle">
           <button class="lang-btn active" data-lang="en">🇬🇧 EN</button>
           <button class="lang-btn"        data-lang="pidgin">🇳🇬 Pidgin</button>
           <button class="lang-btn"        data-lang="swahili">🇰🇪 Swahili</button>
           <button class="lang-btn"        data-lang="french">🇫🇷 French</button>
         </div>
         <div data-role="body">…</div>
         <div data-role="tip">…</div>   (optional)
       </section>
--}}
@once
@push('scripts')
<script>
(function () {
    'use strict';
    if (window.__tsLangToggleBound) return;
    window.__tsLangToggleBound = true;

    var CSRF   = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var LABELS = { 'en': '🇬🇧 EN', 'pidgin': '🇳🇬 Pidgin', 'swahili': '🇰🇪 Swahili', 'french': '🇫🇷 French' };

    function splitTip(text) {
        if (!text) return { body: '', tip: '' };
        var m = text.match(/💡\s*Tip:\s*(.+?)(?:\.|$)/i);
        if (!m) return { body: text, tip: '' };
        return {
            body: text.replace(m[0], '').trim(),
            tip:  m[1].trim().charAt(0).toUpperCase() + m[1].trim().slice(1),
        };
    }

    function setText(section, text) {
        var bodyEl = section.querySelector('[data-role="body"]');
        var tipEl  = section.querySelector('[data-role="tip"]');
        var s      = splitTip(text);
        if (bodyEl) bodyEl.textContent = s.body || text || '';
        if (tipEl && s.tip) tipEl.textContent = s.tip;
    }

    function loadLang(section, lang) {
        var saved = section.getAttribute('data-' + lang);
        if (saved) { setText(section, saved); return Promise.resolve(); }
        if (lang === 'en') { setText(section, section.getAttribute('data-en')); return Promise.resolve(); }

        var id = section.getAttribute('data-pred-id');
        if (!id) return Promise.reject(new Error('no pred-id'));

        return fetch('/api/predictions/' + id + '/translate', {
            method: 'POST',
            headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ lang: lang }),
        })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function (d) {
            section.setAttribute('data-' + lang, d.text);
            setText(section, d.text);
        });
    }

    // Single document-level click listener - covers both server-rendered and
    // dynamically-rendered (fetch-injected) cards
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest && e.target.closest('.lang-btn');
        if (!btn) return;
        var section = btn.closest('[data-pred-id]');
        if (!section) return;

        var lang = btn.getAttribute('data-lang');
        if (btn.classList.contains('active')) return;

        var btns = section.querySelectorAll('.lang-btn');
        btns.forEach(function (b) { b.disabled = true; });
        btn.textContent = (LABELS[lang] || '').split(' ')[0] + ' …';

        loadLang(section, lang)
            .then(function () {
                btns.forEach(function (b) {
                    b.disabled = false;
                    b.classList.toggle('active', b === btn);
                    b.textContent = LABELS[b.getAttribute('data-lang')] || b.textContent;
                });
            })
            .catch(function () {
                btns.forEach(function (b) {
                    b.disabled = false;
                    b.textContent = LABELS[b.getAttribute('data-lang')] || b.textContent;
                });
                alert('Translation failed - try again in a moment.');
            });
    });
}());
</script>
@endpush
@endonce
