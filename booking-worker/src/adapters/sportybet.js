// SportyBet adapter — API-based (no DOM clicking, no login).
//
// Booking a code on SportyBet is two calls, both captured from the live site:
//   1. GET  /api/ng/factsCenter/pcUpcomingEvents?sportId=sr:sport:1&marketId=…
//        → upcoming events, each with eventId, team names, kickoff and markets
//          (marketId + specifier + outcomes[{id,odds}]).
//   2. POST /api/ng/orders/share  { selections:[{eventId,marketId,specifier,outcomeId}] }
//        → { data:{ shareCode, shareURL } }   ← the booking code.
//
// We run both through page.evaluate(fetch) so they use the real browser origin,
// cookies and TLS fingerprint from a SportyBet-reachable (Nigerian) IP.

const BASE = 'https://www.sportybet.com';
const EVENTS_PATH = '/api/ng/factsCenter/pcUpcomingEvents';
const SHARE_PATH = '/api/ng/orders/share';
// 1X2, Over/Under, Double Chance, GG/NG, Draw No Bet, European Handicap, Odd/Even
const MARKET_IDS = '1,18,10,29,11,14,26';
const MAX_PAGES = 12;

// Our internal market label → SportyBet { marketId, outcomeId, specifier? }.
// specifier is the exact on-wire specifier (e.g. "total=2.5", "hcp=0:2").
const MARKET_MAP = {
  'Home Win':               { marketId: '1', outcomeId: '1' },
  'Draw':                   { marketId: '1', outcomeId: '2' },
  'Away Win':               { marketId: '1', outcomeId: '3' },

  'Over 0.5 Goals':         { marketId: '18', outcomeId: '12', specifier: 'total=0.5' },
  'Over 1.5 Goals':         { marketId: '18', outcomeId: '12', specifier: 'total=1.5' },
  'Over 2.5 Goals':         { marketId: '18', outcomeId: '12', specifier: 'total=2.5' },
  'Over 3.5 Goals':         { marketId: '18', outcomeId: '12', specifier: 'total=3.5' },
  'Over 4.5 Goals':         { marketId: '18', outcomeId: '12', specifier: 'total=4.5' },
  'Under 1.5 Goals':        { marketId: '18', outcomeId: '13', specifier: 'total=1.5' },
  'Under 2.5 Goals':        { marketId: '18', outcomeId: '13', specifier: 'total=2.5' },
  'Under 3.5 Goals':        { marketId: '18', outcomeId: '13', specifier: 'total=3.5' },
  'Under 4.5 Goals':        { marketId: '18', outcomeId: '13', specifier: 'total=4.5' },
  'Under 5.5 Goals':        { marketId: '18', outcomeId: '13', specifier: 'total=5.5' },

  'Both Teams Score (GG)':  { marketId: '29', outcomeId: '74' },
  'Both Teams Score':       { marketId: '29', outcomeId: '74' },
  'No Goal (NG)':           { marketId: '29', outcomeId: '76' },

  'Home or Draw (1X)':      { marketId: '10', outcomeId: '9' },
  'Home or Away (12)':      { marketId: '10', outcomeId: '10' },
  'Draw or Away (X2)':      { marketId: '10', outcomeId: '11' },

  'Draw No Bet - Home':     { marketId: '11', outcomeId: '4' },
  'Draw No Bet - Away':     { marketId: '11', outcomeId: '5' },

  'Total Goals Odd':        { marketId: '26', outcomeId: '70' },
  'Total Goals Even':       { marketId: '26', outcomeId: '72' },
};

// European Handicap is dynamic: "European Handicap 0:2 - Away" →
// marketId 14, specifier "hcp=0:2", outcome 1711/1712/1713 (Home/Draw/Away).
const EH_OUTCOME = { Home: '1711', Draw: '1712', Away: '1713' };
function resolveMarket(label) {
  if (MARKET_MAP[label]) return MARKET_MAP[label];
  const eh = /^European Handicap (\d+):(\d+) - (Home|Draw|Away)$/.exec(label || '');
  if (eh) return { marketId: '14', specifier: `hcp=${eh[1]}:${eh[2]}`, outcomeId: EH_OUTCOME[eh[3]] };
  return null;
}

export const sportybet = {
  async buildCode(page, slip) {
    // Establish the SportyBet session (cookies) that page.request reuses.
    if (!page.url().includes('sportybet.com')) {
      await page.goto(`${BASE}/ng/sport/football`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    }
    const events = await loadEvents(page);
    if (!events.length) throw new Error('SportyBet returned no upcoming events');

    const selections = [];
    const booked = [];
    let combined = 1.0;
    const maxOdds = slip.max_total_odds ?? 500;
    const minOdds = slip.min_total_odds ?? 3;

    for (const leg of slip.selections) {
      const mapped = resolveMarket(leg.market);
      if (!mapped) continue; // market we don't book on SportyBet — skip the leg

      const ev = matchEvent(events, leg);
      if (!ev) continue;

      const hit = findOutcome(ev, mapped);
      if (!hit) continue;

      const odds = parseFloat(hit.odds) || leg.est_odds || 1.0;
      if (combined * odds > maxOdds) continue; // would blow the band — try the next leg

      selections.push({ eventId: ev.eventId, marketId: mapped.marketId, specifier: hit.specifier || null, outcomeId: mapped.outcomeId });
      booked.push({ home: leg.home, away: leg.away, market: leg.market, est_odds: odds });
      combined *= odds;

      if (booked.length >= 3 && combined >= minOdds) break;
    }

    if (selections.length < 3) {
      throw new Error(`only ${selections.length} legs matched on SportyBet (need 3)`);
    }

    const data = await shareBooking(page, selections);
    if (!data || !data.shareCode) throw new Error('share call returned no shareCode');

    return {
      code: data.shareCode,
      link: data.shareURL || `${BASE}/ng/?shareCode=${data.shareCode}`,
      total_odds: Math.round(combined * 100) / 100,
      booked,
    };
  },
};

// ── SportyBet calls, run inside the page so they carry the real session ──

// page.request runs the HTTP call from Node using the browser context's cookies
// — it shares the session established by the initial page.goto but bypasses
// SportyBet's in-page fetch wrapper (which rejects programmatic fetch()).
const apiHeaders = { accept: 'application/json', referer: `${BASE}/ng/sport/football`, 'x-requested-with': 'XMLHttpRequest' };

async function loadEvents(page) {
  const all = [];
  for (let pageNum = 1; pageNum <= MAX_PAGES; pageNum++) {
    const url = `${BASE}${EVENTS_PATH}?sportId=sr:sport:1&marketId=${MARKET_IDS}&pageSize=100&pageNum=${pageNum}&option=1&_t=${Date.now()}`;
    const resp = await page.request.get(url, { headers: apiHeaders });
    if (!resp.ok()) break;
    const body = await resp.json().catch(() => null);
    const tournaments = body?.data?.tournaments || (Array.isArray(body?.data) ? body.data : []);
    const events = tournaments.flatMap((t) => t.events || []);
    if (!events.length) break;
    all.push(...events);
    if (events.length < 100) break;
  }
  return all;
}

async function shareBooking(page, selections) {
  const resp = await page.request.post(`${BASE}${SHARE_PATH}`, {
    headers: { ...apiHeaders, 'content-type': 'application/json' },
    data: { selections },
  });
  if (!resp.ok()) throw new Error(`share HTTP ${resp.status()}`);
  const body = await resp.json().catch(() => null);
  if (!body) throw new Error('share returned non-JSON');
  if (body.bizCode && body.bizCode !== 10000) {
    throw new Error(`share bizCode ${body.bizCode}: ${body.message || 'rejected'}`);
  }
  return body.data;
}

// ── Matching helpers ──

function findOutcome(event, mapped) {
  for (const m of event.markets || []) {
    if (String(m.id) !== mapped.marketId) continue;
    if (mapped.specifier !== undefined && (m.specifier || '') !== mapped.specifier) continue;
    const outcome = (m.outcomes || []).find((o) => String(o.id) === mapped.outcomeId);
    if (outcome) return { odds: outcome.odds, specifier: m.specifier || null };
  }
  return null;
}

function matchEvent(events, leg) {
  const home = normTeam(leg.home);
  const away = normTeam(leg.away);
  const kickoff = leg.kickoff ? Date.parse(leg.kickoff) : null;

  let best = null;
  let bestScore = 0;
  for (const ev of events) {
    const eh = normTeam(ev.homeTeamName);
    const ea = normTeam(ev.awayTeamName);
    const score = teamScore(home, eh) + teamScore(away, ea);
    if (score < 1.2) continue; // both sides must be a decent match

    let total = score;
    if (kickoff && ev.estimateStartTime) {
      const hours = Math.abs(ev.estimateStartTime - kickoff) / 3.6e6;
      if (hours <= 6) total += 0.3 - hours * 0.02; // small bonus for a close kickoff
    }
    if (total > bestScore) { bestScore = total; best = ev; }
  }
  return best;
}

// Normalise a team name to comparable tokens (drop accents, punctuation and
// generic club words that carry no identity).
const STOP = new Set(['fc', 'afc', 'sc', 'cf', 'ac', 'club', 'the']);
function normTeam(name) {
  return String(name || '')
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .toLowerCase().replace(/[^a-z0-9\s]/g, ' ')
    .split(/\s+/).filter((t) => t && !STOP.has(t));
}

// Similarity of two token lists: shared tokens / longer list, plus a bonus for
// any overlap so single-distinct-token names (e.g. "Arsenal") still match.
function teamScore(a, b) {
  if (!a.length || !b.length) return 0;
  const setB = new Set(b);
  const shared = a.filter((t) => setB.has(t)).length;
  return shared / Math.max(a.length, b.length) + (shared > 0 ? 0.6 : 0);
}
