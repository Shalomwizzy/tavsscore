@extends('layouts.app')

@section('title', 'TavsScore | Live Scores')

@push('styles')
<style>
    /* ── Page header ── */
    .scores-header {
        position:relative;overflow:hidden;
        padding:1.75rem 1.5rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: .75rem;
        border:1px solid rgba(96,165,250,.22);
        border-radius:18px;
        background:radial-gradient(circle at 87% 18%,rgba(239,68,68,.17),transparent 25%),radial-gradient(circle at 10% 100%,rgba(59,130,246,.15),transparent 35%),linear-gradient(135deg,#111d33,#0b1220 70%);
        margin:1.25rem 0 1rem;
    }

    .scores-title {
        font-size:clamp(1.55rem,3vw,2.25rem);
        font-weight:900;
        color: #fff;
        letter-spacing: -.02em;
        margin: 0 0 .2rem;
    }

    .scores-sub {
        font-size:.78rem;color:#9fb0c6;
    }

    /* ── Summary tiles ── */
    .summary-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: .5rem;
        margin-bottom: 1.1rem;
    }

    .sum-tile {
        background:linear-gradient(145deg,rgba(19,29,48,.98),rgba(15,23,42,.78));
        border:1px solid rgba(148,163,184,.13);
        border-radius:12px;padding:.85rem .875rem;
        text-align: center;
    }

    .sum-val {
        display: block;
        font-size: 1.4rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
    }

    .sum-lbl {
        display: block;
        font-size: .65rem;
        color: var(--text-dim);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-top: 4px;
    }

    /* ── Controls row ── */
    .controls-row {
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-bottom: 1.1rem;
        flex-wrap: wrap;
    }

    .filter-select {
        flex: 1;
        min-width: 200px;
        max-width: 340px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        padding: .42rem .75rem;
        font-size: .8rem;
        font-family: inherit;
        cursor: pointer;
        outline: none;
        transition: border-color 160ms;
    }

    .filter-select:focus {
        border-color: rgba(16,185,129,.4);
        box-shadow: 0 0 0 3px rgba(16,185,129,.08);
    }

    .filter-label {
        font-size: .75rem;
        font-weight: 600;
        color: var(--text-dim);
        white-space: nowrap;
    }

    .refresh-text {
        margin-left: auto;
        font-size: .7rem;
        color: var(--text-muted);
        white-space: nowrap;
    }

    /* ── League section ── */
    .league-section {
        border:1px solid rgba(148,163,184,.13);
        border-radius:14px;
        overflow: hidden;
        margin-bottom:.8rem;
        box-shadow:0 8px 20px rgba(0,0,0,.08);
    }

    .league-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding:.7rem .9rem;
        background:linear-gradient(90deg,rgba(30,41,59,.8),rgba(15,23,42,.85));
        border-bottom: 1px solid var(--border);
    }

    .league-head-left {
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .lg-flag   { font-size: .95rem; line-height: 1; flex-shrink: 0; }

    .lg-name   { font-size: .75rem; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: .03em; }

    .lg-country{ font-size: .67rem; color: var(--text-dim); margin-top: 1px; }

    .league-head-right {
        display: flex;
        align-items: center;
        gap: .45rem;
        flex-shrink: 0;
    }

    .lg-count { font-size: .68rem; color: var(--text-muted); }

    /* ── Match rows ── */
    .match-list { background: var(--card); }

    /* Desktop layout: status | home | score | away | time */
    .match-row-d {
        display: grid;
        grid-template-columns: 5.25rem 1fr 5rem 1fr 5rem;
        align-items: center;
        gap: .6rem;
        padding:.75rem .9rem;
        border-bottom: 1px solid rgba(255,255,255,.04);
        transition: background 140ms;
        cursor: default;
    }

    .match-row-d:last-child { border-bottom: none; }
    .match-row-d:hover { background: var(--card-hover); }

    /* Mobile layout: status | [home+score / away+score / time] */
    .match-row-m {
        display: none;
        grid-template-columns: 4.5rem 1fr;
        align-items: start;
        gap: .55rem;
        padding: .65rem .875rem;
        border-bottom: 1px solid rgba(255,255,255,.04);
        transition: background 140ms;
    }

    .match-row-m:last-child { border-bottom: none; }
    .match-row-m:hover { background: var(--card-hover); }

    /* Status pill */
    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .28rem;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        width: fit-content;
    }

    .pill-live     { background: var(--red-dim);  border: 1px solid rgba(239,68,68,.3);  color: #fca5a5; }
    .pill-upcoming { background: var(--blue-dim); border: 1px solid rgba(59,130,246,.3); color: #93c5fd; }
    .pill-finished { background: rgba(107,114,128,.1); border: 1px solid rgba(107,114,128,.25); color: #9ca3af; }

    /* Team names */
    .team-name {
        font-size: .85rem;
        font-weight: 600;
        color: #fff;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .live-team { display:flex;align-items:center;gap:.42rem;min-width:0; }
    .live-team.right { justify-content:flex-end; }
    .live-crest { width:22px;height:22px;object-fit:contain;flex-shrink:0; }
    .live-crest-fallback { display:grid;place-items:center;width:22px;height:22px;border-radius:50%;background:#243a63;color:#dbeafe;font-size:.48rem;font-weight:900;flex-shrink:0; }

    .team-name.right { text-align: right; }

    /* Score */
    .score-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        font-size: 1.08rem;
        font-weight: 800;
        color: #fff;
        font-variant-numeric: tabular-nums;
        text-align: center;
    }

    .score-sep { color: var(--text-muted); font-weight: 400; font-size: .85rem; }

    .score-wrap.flash {
        color: var(--green);
        animation: scoreFlash .45s ease;
    }

    @keyframes scoreFlash {
        0%,100% { transform: scale(1); }
        50%      { transform: scale(1.1); }
    }

    /* Kickoff time */
    .kickoff {
        font-size: .75rem;
        font-weight: 600;
        color: var(--text-dim);
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    /* Mobile team stack */
    .m-teams { display: flex; flex-direction: column; gap: .35rem; }

    .m-team-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }

    .m-score {
        font-size: .88rem;
        font-weight: 700;
        color: #fff;
        font-variant-numeric: tabular-nums;
        flex-shrink: 0;
    }

    .m-score.flash { color: var(--green); }

    .m-kickoff {
        font-size: .67rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    /* Responsive */
    @media (max-width: 620px) {
        .scores-header { padding:1.3rem 1rem;align-items:flex-start; }
        .summary-row { grid-template-columns: repeat(2,1fr); }
        .match-row-d { display: none; }
        .match-row-m { display: grid; }
        .filter-select { max-width: 100%; }
        .controls-row { flex-direction: column; align-items: flex-start; }
        .refresh-text { margin-left: 0; }
    }
</style>
@endpush

@section('content')
<div class="wrap">
    {{-- Header --}}
    <div class="scores-header">
        <div>
            <div style="font-size:.62rem;color:#fca5a5;font-weight:900;letter-spacing:.11em;text-transform:uppercase;margin-bottom:.35rem;">● Match centre · updates every 30 seconds</div>
            <h1 class="scores-title">Live football, without the noise.</h1>
            <p class="scores-sub">Scores, match states and competition context in one live command centre.</p>
        </div>
        <div style="text-align:right">
            <div class="scores-sub" id="refresh-status">Updates every 30 s</div>
            <div class="scores-sub" id="last-updated" style="margin-top:2px"></div>
        </div>
    </div>

    {{-- Summary --}}
    <div class="summary-row">
        <div class="sum-tile">
            <span class="sum-val" id="cnt-live">–</span>
            <span class="sum-lbl">🔴 Live</span>
        </div>
        <div class="sum-tile">
            <span class="sum-val" id="cnt-today">–</span>
            <span class="sum-lbl">📅 Upcoming</span>
        </div>
        <div class="sum-tile">
            <span class="sum-val" id="cnt-finished">–</span>
            <span class="sum-lbl">✅ Finished</span>
        </div>
        <div class="sum-tile">
            <span class="sum-val" id="cnt-leagues">–</span>
            <span class="sum-lbl">🏆 Leagues</span>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="ts-tabs mb-3" style="max-width:420px">
        <button class="ts-tab active" data-feed="live"     type="button">🔴 Live</button>
        <button class="ts-tab"        data-feed="today"    type="button">📅 Today</button>
        <button class="ts-tab"        data-feed="finished" type="button">✅ Finished</button>
        <a href="{{ route('predictions.index') }}" class="ts-tab">📊 Tips</a>
    </div>

    {{-- Filter --}}
    <div class="controls-row">
        <span class="filter-label">Competition:</span>
        <select id="league-filter" class="filter-select">
            <option value="">All competitions</option>
        </select>
        <span class="refresh-text" id="refresh-inline"></span>
    </div>

    {{-- Content area --}}
    <div id="loading-state" class="state-box">
        <span class="state-icon"><span class="spin"></span></span>
        <div class="state-title" id="loading-msg">Loading live scores…</div>
    </div>

    <div id="error-state" class="state-box" style="display:none">
        <span class="state-icon">⚠️</span>
        <div class="state-title">Failed to load</div>
        <p class="state-sub">Please refresh the page</p>
    </div>

    <div id="empty-state" class="state-box" style="display:none">
        <span class="state-icon">😴</span>
        <div class="state-title" id="empty-msg">No live matches right now</div>
        <p class="state-sub">Check back during match times or switch tab</p>
    </div>

    <div id="matches-list" style="display:none"></div>

    <div style="height:2rem"></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var API = {
        live:     @json(url('/api/matches/live')),
        today:    @json(url('/api/matches/today')),
        finished: @json(url('/api/matches/finished')),
    };

    var REFRESH_MS = 30000;
    var prevScores = {};
    var activeFeed = 'live';
    var allMatches  = [];
    var selLeague   = '';

    /* ── Country flag map ── */
    var FLAGS = {
        'england':'🏴󠁧󠁢󠁥󠁮󠁧󠁿','scotland':'🏴󠁧󠁢󠁳󠁣󠁴󠁿','spain':'🇪🇸','italy':'🇮🇹',
        'germany':'🇩🇪','france':'🇫🇷','netherlands':'🇳🇱','portugal':'🇵🇹',
        'belgium':'🇧🇪','turkey':'🇹🇷','russia':'🇷🇺','usa':'🇺🇸',
        'mexico':'🇲🇽','brazil':'🇧🇷','argentina':'🇦🇷','south korea':'🇰🇷',
        'japan':'🇯🇵','saudi arabia':'🇸🇦','world':'🌍','europe':'🌍',
    };

    function flag(c) {
        if (!c) return '🌐';
        return FLAGS[c.toLowerCase()] || '🏆';
    }

    /* Top-league sort order (by API-Football ID) */
    var TOP_ORDER = [2,3,39,61,78,135,140,848,88,94,40,48,45,144,179,203,253,71,307,262,292];

    function leagueRank(id) {
        var idx = TOP_ORDER.indexOf(Number(id));
        return idx === -1 ? 999 : idx;
    }

    var TOP_IDS = new Set([2,3,39,61,78,135,140,848,88,94,48,45,144,179,203]);

    /* ── DOM refs ── */
    var elLoading  = document.getElementById('loading-state');
    var elError    = document.getElementById('error-state');
    var elEmpty    = document.getElementById('empty-state');
    var elList     = document.getElementById('matches-list');
    var elLoadMsg  = document.getElementById('loading-msg');
    var elEmptyMsg = document.getElementById('empty-msg');
    var elStatus   = document.getElementById('refresh-status');
    var elUpdated  = document.getElementById('last-updated');
    var elInline   = document.getElementById('refresh-inline');
    var elFilter   = document.getElementById('league-filter');
    var tabs       = document.querySelectorAll('[data-feed]');

    /* ── Helpers ── */
    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function fmtScore(v) { return (v === null || v === undefined) ? '-' : v; }

    function fmtTime(iso) {
        if (!iso) return 'TBC';
        return new Intl.DateTimeFormat(undefined, { hour:'numeric', minute:'2-digit' }).format(new Date(iso));
    }

    function initials(name) {
        return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part.charAt(0).toUpperCase(); }).join('') || 'FC';
    }

    function crest(url, name) {
        return url
            ? '<img class="live-crest" src="' + esc(url) + '" alt="" loading="lazy">'
            : '<span class="live-crest-fallback">' + esc(initials(name)) + '</span>';
    }

    function leagueKey(m) { return (m.league || '') + '|||' + (m.league_country || ''); }

    /* ── State display ── */
    function showState(s) {
        elLoading.style.display = s === 'loading' ? '' : 'none';
        elError.style.display   = s === 'error'   ? '' : 'none';
        elEmpty.style.display   = s === 'empty'   ? '' : 'none';
        elList.style.display    = s === 'ready'   ? 'block' : 'none';
    }

    /* ── Status pill ── */
    function pillClass() {
        if (activeFeed === 'live')     return 'pill-live';
        if (activeFeed === 'today')    return 'pill-upcoming';
        return 'pill-finished';
    }

    function statusText(m) {
        if (activeFeed === 'today')    return fmtTime(m.match_time);
        if (activeFeed === 'finished') return m.display_status || 'FT';
        return m.display_status || m.status || 'LIVE';
    }

    /* ── Render match (desktop row) ── */
    function renderDesktop(m, flash) {
        var st   = esc(statusText(m));
        var pc   = pillClass();
        var dot  = activeFeed === 'live' ? '<span class="live-dot" style="width:5px;height:5px;border-radius:50%;background:var(--red);animation:pulseDot 1.6s infinite;flex-shrink:0;"></span>' : '';
        return '<div class="match-row-d fade-up" data-id="' + esc(m.api_id) + '">'
            + '<div><span class="status-pill ' + pc + '">' + dot + st + '</span></div>'
            + '<div class="team-name live-team">' + crest(m.home_team_logo, m.home_team) + '<span class="team-name">' + esc(m.home_team) + '</span></div>'
            + '<div class="score-wrap' + (flash ? ' flash' : '') + '">'
            +   '<span>' + esc(fmtScore(m.home_score)) + '</span>'
            +   '<span class="score-sep">:</span>'
            +   '<span>' + esc(fmtScore(m.away_score)) + '</span>'
            + '</div>'
            + '<div class="team-name live-team right"><span class="team-name right">' + esc(m.away_team) + '</span>' + crest(m.away_team_logo, m.away_team) + '</div>'
            + '<div class="kickoff">' + esc(fmtTime(m.match_time)) + '</div>'
            + '</div>';
    }

    /* ── Render match (mobile row) ── */
    function renderMobile(m, flash) {
        var st  = esc(statusText(m));
        var pc  = pillClass();
        var dot = activeFeed === 'live' ? '<span class="live-dot" style="width:5px;height:5px;border-radius:50%;background:var(--red);animation:pulseDot 1.6s infinite;flex-shrink:0;display:inline-block;margin-right:3px;"></span>' : '';
        return '<div class="match-row-m fade-up" data-id="' + esc(m.api_id) + '">'
            + '<div><span class="status-pill ' + pc + '" style="font-size:.67rem;padding:2px 6px;">' + dot + st + '</span></div>'
            + '<div class="m-teams">'
            +   '<div class="m-team-row">'
            +     '<span class="live-team"><span class="team-name" style="font-size:.8rem">' + esc(m.home_team) + '</span>' + crest(m.home_team_logo, m.home_team) + '</span>'
            +     '<span class="m-score' + (flash ? ' flash' : '') + '">' + esc(fmtScore(m.home_score)) + '</span>'
            +   '</div>'
            +   '<div class="m-team-row">'
            +     '<span class="live-team"><span class="team-name" style="font-size:.8rem">' + esc(m.away_team) + '</span>' + crest(m.away_team_logo, m.away_team) + '</span>'
            +     '<span class="m-score' + (flash ? ' flash' : '') + '">' + esc(fmtScore(m.away_score)) + '</span>'
            +   '</div>'
            +   '<div class="m-kickoff">' + esc(fmtTime(m.match_time)) + '</div>'
            + '</div>'
            + '</div>';
    }

    function renderMatch(m) {
        var key   = fmtScore(m.home_score) + ':' + fmtScore(m.away_score);
        var prev  = prevScores[m.api_id];
        var flash = !!prev && prev !== key;
        prevScores[m.api_id] = key;
        return renderDesktop(m, flash) + renderMobile(m, flash);
    }

    /* ── Group matches by league ── */
    function groupMatches(matches) {
        var groups = {};
        matches.forEach(function (m) {
            var k = leagueKey(m);
            if (!groups[k]) {
                groups[k] = { league: m.league, country: m.league_country, id: m.league_id, matches: [] };
            }
            groups[k].matches.push(m);
        });
        return groups;
    }

    function sortedGroups(groups) {
        return Object.values(groups).sort(function (a, b) {
            var d = leagueRank(a.id) - leagueRank(b.id);
            if (d !== 0) return d;
            return (a.league || '').localeCompare(b.league || '');
        });
    }

    /* ── Populate filter dropdown ── */
    function populateFilter(groups) {
        var sorted = sortedGroups(groups);
        var prev   = selLeague;
        var html   = '<option value="">All competitions</option>';
        sorted.forEach(function (g) {
            var val   = leagueKey({ league: g.league, league_country: g.country });
            var label = g.league + (g.country ? ' – ' + g.country : '');
            var star  = TOP_IDS.has(Number(g.id)) ? '★ ' : '';
            html += '<option value="' + esc(val) + '">' + esc(star + label) + '</option>';
        });
        elFilter.innerHTML = html;
        var stillValid = sorted.some(function (g) {
            return leagueKey({ league: g.league, league_country: g.country }) === prev;
        });
        selLeague = stillValid ? prev : '';
        elFilter.value = selLeague;
    }

    /* ── Render all match groups ── */
    function render() {
        var visible = selLeague
            ? allMatches.filter(function (m) { return leagueKey(m) === selLeague; })
            : allMatches;

        var sorted = visible.slice().sort(function (a, b) {
            var d = leagueRank(a.league_id) - leagueRank(b.league_id);
            if (d !== 0) return d;
            return new Date(a.match_time || 0) - new Date(b.match_time || 0);
        });

        if (sorted.length === 0) {
            elList.innerHTML = '';
            document.getElementById('cnt-leagues').textContent = '0';
            var msgs = { live: 'No live matches right now', today: 'No upcoming matches today', finished: 'No finished matches today' };
            elEmptyMsg.textContent = selLeague ? 'No matches in this competition' : (msgs[activeFeed] || 'No matches');
            showState('empty');
            return;
        }

        var groups = groupMatches(sorted);
        var glst   = sortedGroups(groups);
        document.getElementById('cnt-leagues').textContent = glst.length;

        var html = '';
        glst.forEach(function (g) {
            var topBadge = TOP_IDS.has(Number(g.id)) ? '<span class="chip chip-green">Top League</span>' : '';
            html += '<div class="league-section">'
                + '<div class="league-head">'
                +   '<div class="league-head-left">'
                +     '<span class="lg-flag">' + esc(flag(g.country)) + '</span>'
                +     '<div>'
                +       '<div class="lg-name">' + esc(g.league) + '</div>'
                +       (g.country ? '<div class="lg-country">' + esc(g.country) + '</div>' : '')
                +     '</div>'
                +   '</div>'
                +   '<div class="league-head-right">'
                +     topBadge
                +     '<span class="lg-count">' + g.matches.length + ' match' + (g.matches.length !== 1 ? 'es' : '') + '</span>'
                +   '</div>'
                + '</div>'
                + '<div class="match-list">'
                + g.matches.map(renderMatch).join('')
                + '</div>'
                + '</div>';
        });

        elList.innerHTML = html;
        showState('ready');
    }

    /* ── Update summary counts ── */
    function updateSummary() {
        return Promise.all([
            fetch(API.live,     { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }),
            fetch(API.today,    { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }),
            fetch(API.finished, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }),
        ]).then(function (results) {
            document.getElementById('cnt-live').textContent     = Array.isArray(results[0].data) ? results[0].data.length : 0;
            document.getElementById('cnt-today').textContent    = Array.isArray(results[1].data) ? results[1].data.length : 0;
            document.getElementById('cnt-finished').textContent = Array.isArray(results[2].data) ? results[2].data.length : 0;
        }).catch(function () {});
    }

    /* ── Main load ── */
    function load(initial) {
        if (initial) showState('loading');
        elStatus.textContent = 'Refreshing…';

        fetch(API[activeFeed], { headers: { Accept: 'application/json' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (payload) {
                allMatches = Array.isArray(payload.data) ? payload.data : [];
                populateFilter(groupMatches(allMatches));
                render();

                var now = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
                elUpdated.textContent  = 'Updated ' + now;
                elInline.textContent   = 'Updated ' + now;
                return updateSummary();
            })
            .catch(function () { showState('error'); })
            .finally(function () { elStatus.textContent = 'Updates every 30 s'; });
    }

    /* ── Tab clicks ── */
    tabs.forEach(function (tab) {
        if (!tab.dataset.feed) return;
        tab.addEventListener('click', function () {
            activeFeed = tab.dataset.feed;
            selLeague  = '';
            tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.feed === activeFeed); });
            var msgs = { live: 'Loading live scores…', today: 'Loading upcoming matches…', finished: 'Loading finished matches…' };
            elLoadMsg.textContent = msgs[activeFeed] || 'Loading…';
            load(true);
        });
    });

    elFilter.addEventListener('change', function (e) {
        selLeague = e.target.value;
        render();
    });

    /* ── Init ── */
    if (location.hash === '#today') {
        activeFeed = 'today';
        tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.feed === 'today'); });
        elLoadMsg.textContent = 'Loading upcoming matches…';
    }

    showState('loading');
    load(true);
    setInterval(function () { load(false); }, REFRESH_MS);
}());
</script>
@endpush
