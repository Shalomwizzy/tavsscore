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
// 1X2, Over/Under, Double Chance, GG/NG, Draw No Bet, European Handicap,
// Asian Handicap, Odd/Even, 1st-5-min 1X2 (900069), 1st-10-min 1X2 (105)
const MARKET_IDS = '1,18,10,29,11,14,16,26,900069,105';
const TENNIS_MARKET_IDS = '1';
const MAX_PAGES = 12;
// All tickets in one worker run use the same upcoming-fixture board. Reusing
// it avoids 12 SportyBet requests per ticket and prevents a late ticket (such
// as High Risk) being throttled after the earlier tickets have already loaded.
const eventsByPage = new WeakMap();

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
  'Player One Win':         { marketId: '1', outcomeId: '1' },
  'Player Two Win':         { marketId: '1', outcomeId: '3' },

  // First-minutes draw (1X2 → Draw). Near-certain, near-1.0 odds; used for the
  // unlimited-leg minute-draw tickets.
  'First 5 Min Draw':       { marketId: '900069', outcomeId: '2', specifier: 'minute=5' },
  'First 10 Min Draw':      { marketId: '105', outcomeId: '2', specifier: 'from=1|to=10' },
};

// European Handicap is dynamic: "European Handicap 0:2 - Away" →
// marketId 14, specifier "hcp=0:2", outcome 1711/1712/1713 (Home/Draw/Away).
const EH_OUTCOME = { Home: '1711', Draw: '1712', Away: '1713' };
function resolveMarket(label) {
  if (MARKET_MAP[label]) return MARKET_MAP[label];

  // European Handicap: "European Handicap 0:2 - Away" → market 14, hcp=0:2.
  const eh = /^European Handicap (\d+):(\d+) - (Home|Draw|Away)$/.exec(label || '');
  if (eh) return { marketId: '14', specifier: `hcp=${eh[1]}:${eh[2]}`, outcomeId: EH_OUTCOME[eh[3]] };

  // Asian Handicap: "Home -1.5 (Handicap)" / "Away +4.5 (Handicap)" → market 16.
  // SportyBet's specifier is the HOME line (away line negated); 1714=Home, 1715=Away.
  const ah = /^(Home|Away) ([+-]\d+(?:\.\d+)?) \(Handicap\)$/.exec(label || '');
  if (ah) {
    const value = parseFloat(ah[2]);
    const homeLine = ah[1] === 'Home' ? value : -value;
    return { marketId: '16', specifier: `hcp=${homeLine}`, outcomeId: ah[1] === 'Home' ? '1714' : '1715' };
  }
  return null;
}

export const sportybet = {
  async buildCode(page, slip) {
    // Establish the SportyBet session (cookies) that page.request reuses.
    if (!page.url().includes('sportybet.com')) {
      await page.goto(`${BASE}/ng/sport/football`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    }
    const events = await loadEvents(page, slip.sport || 'football');
    if (!events.length) throw new Error('SportyBet returned no upcoming events');

    const selections = [];
    const booked = [];
    const skipped = { unsupported: [], fixture: [], market: [], odds: [] };
    let combined = 1.0;
    const maxOdds = slip.max_total_odds ?? 500;
    const minOdds = slip.min_total_odds ?? 2;
    // Unlimited-leg tickets (e.g. minute-draw): take every matching fixture up
    // to SportyBet's selection limit, no odds band and no early stop.
    const allLegs = slip.all_legs === true;
    const legCap = allLegs ? 40 : Infinity;

    if (allLegs) {
      // Unlimited minute-draw ticket: ignore the (prediction-limited) slip legs
      // and book EVERY upcoming SportyBet fixture that offers this one market, so
      // it packs as many games as possible (the user's intent) and clears the
      // 2.0 floor naturally rather than settling for ~7 legs.
      const mapped = resolveMarket(slip.market);
      if (!mapped) {
        const error = new Error(`unsupported all-legs market: ${slip.market}`);
        error.permanent = true;
        throw error;
      }
      for (const ev of events) {
        if (selections.length >= legCap) break;
        const hit = findOutcome(ev, mapped);
        if (!hit) continue;
        const odds = parseFloat(hit.odds) || 1.0;
        selections.push({ eventId: ev.eventId, marketId: mapped.marketId, specifier: hit.specifier || null, outcomeId: mapped.outcomeId });
        booked.push({ match_id: null, sport: slip.sport || 'football', home: ev.homeTeamName, away: ev.awayTeamName, market: slip.market, model_prob: null, est_odds: odds });
        combined *= odds;
      }
    } else {
      for (const leg of slip.selections) {
        if (selections.length >= legCap) break;
        const mapped = resolveMarket(leg.market);
        if (!mapped) {
          skipped.unsupported.push(describeLeg(leg));
          continue; // market we don't book on SportyBet — skip the leg
        }

        const ev = matchEvent(events, leg);
        if (!ev) {
          skipped.fixture.push(describeLeg(leg));
          continue;
        }

        const hit = findOutcome(ev, mapped);
        if (!hit) {
          skipped.market.push(describeLeg(leg));
          continue;
        }

        const odds = parseFloat(hit.odds) || leg.est_odds || 1.0;
        if (combined * odds > maxOdds) {
          skipped.odds.push(describeLeg(leg));
          continue; // would blow the band — try the next leg
        }

        selections.push({ eventId: ev.eventId, marketId: mapped.marketId, specifier: hit.specifier || null, outcomeId: mapped.outcomeId });
        booked.push({ match_id: leg.match_id ?? null, sport: leg.sport || slip.sport || 'football', home: leg.home, away: leg.away, market: leg.market, model_prob: leg.model_prob ?? null, est_odds: odds });
        combined *= odds;

        if (booked.length >= 3 && combined >= minOdds) break;
      }
    }

    if (selections.length < 3) {
      const error = new Error(`only ${selections.length} legs matched on SportyBet (need 3). ${formatCoverage(events.length, skipped)}`);
      // A missing fixture/market is not a transient network or odds-refresh
      // problem. The orchestrator should report it once instead of blindly
      // doing the same browser request eight times.
      error.permanent = true;
      throw error;
    }
    if (!allLegs && combined < minOdds) {
      throw new Error(`combined odds ${combined.toFixed(2)} below ${minOdds} minimum`);
    }

    const data = await shareBooking(page, selections);
    if (!data || !data.shareCode) throw new Error('share call returned no shareCode');

    // Prefer proof from the real SportyBet shared-ticket page. If SportyBet
    // delays or blocks that page, render a branded ticket card in Playwright
    // with the exact generated code, actual odds and Lagos timestamp. Either
    // way, every automated booking-code Telegram post has a truthful image.
    const totalOdds = Math.round(combined * 100) / 100;
    const ticketScreenshot = await captureTicketScreenshot(page, data.shareURL, data.shareCode)
      || await captureTavsScoreTicketCard(page, {
        platform: 'SportyBet', code: data.shareCode, totalOdds,
      });
    if (!ticketScreenshot) throw new Error('could not create the required booking-ticket image');

    return {
      code: data.shareCode,
      link: data.shareURL || `${BASE}/ng/?shareCode=${data.shareCode}`,
      total_odds: totalOdds,
      booked,
      ticket_screenshot: ticketScreenshot,
    };
  },
};

// ── SportyBet calls, run inside the page so they carry the real session ──

// page.request runs the HTTP call from Node using the browser context's cookies
// — it shares the session established by the initial page.goto but bypasses
// SportyBet's in-page fetch wrapper (which rejects programmatic fetch()).
const apiHeaders = { accept: 'application/json', referer: `${BASE}/ng/sport/football`, 'x-requested-with': 'XMLHttpRequest' };

async function loadEvents(page, sport = 'football') {
  const cachedBySport = eventsByPage.get(page);
  const cached = cachedBySport?.get(sport);
  if (cached) return cached;

  const all = [];
  const sportId = sport === 'tennis' ? 'sr:sport:5' : 'sr:sport:1';
  const marketIds = sport === 'tennis' ? TENNIS_MARKET_IDS : MARKET_IDS;
  for (let pageNum = 1; pageNum <= MAX_PAGES; pageNum++) {
    const url = `${BASE}${EVENTS_PATH}?sportId=${sportId}&marketId=${marketIds}&pageSize=100&pageNum=${pageNum}&option=1&_t=${Date.now()}`;
    const resp = await page.request.get(url, { headers: apiHeaders });
    if (!resp.ok()) break;
    const body = await resp.json().catch(() => null);
    const tournaments = body?.data?.tournaments || (Array.isArray(body?.data) ? body.data : []);
    const events = tournaments.flatMap((t) => t.events || []);
    if (!events.length) break;
    all.push(...events);
    // SportyBet groups this endpoint by tournament. A page can contain fewer
    // than pageSize events while many later pages still exist (e.g. page 1
    // currently has 59 events but the API advertises 1,100+). Stopping on the
    // event count made the worker search only the first page and falsely say
    // every TavsScore ticket had zero matching legs.
    const total = Number(body?.data?.totalNum || 0);
    if (total > 0 && pageNum * 100 >= total) break;
  }
  if (all.length) {
    const cache = cachedBySport || new Map();
    cache.set(sport, all);
    eventsByPage.set(page, cache);
  }
  return all;
}

async function captureTicketScreenshot(page, shareUrl, shareCode) {
  if (!shareUrl || !shareCode) return null;

  let ticketPage = null;
  try {
    ticketPage = await page.context().newPage();
    await ticketPage.goto(shareUrl, { waitUntil: 'domcontentloaded', timeout: 25000 });
    await ticketPage.waitForTimeout(1800);
    const visibleText = await ticketPage.locator('body').innerText({ timeout: 6000 });
    if (!visibleText.toUpperCase().includes(String(shareCode).toUpperCase())) {
      return null;
    }

    const image = await ticketPage.screenshot({ type: 'jpeg', quality: 84, fullPage: false });
    return `data:image/jpeg;base64,${image.toString('base64')}`;
  } catch (error) {
    console.warn(`Could not capture the shared SportyBet ticket: ${error.message}`);
    return null;
  } finally {
    if (ticketPage) await ticketPage.close().catch(() => {});
  }
}

/**
 * A JPEG fallback for when SportyBet's share page does not visibly render the
 * ticket. It is explicitly TavsScore-branded rather than imitating SportyBet,
 * but every number is copied from the just-created real ticket.
 */
export async function captureTavsScoreTicketCard(page, { platform, code, totalOdds }) {
  let cardPage = null;
  try {
    const now = new Intl.DateTimeFormat('en-GB', {
      timeZone: 'Africa/Lagos', day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit', hour12: false,
    }).format(new Date()).replace(',', '');
    const safeCode = escapeHtml(String(code).toUpperCase());
    const safePlatform = escapeHtml(platform);
    const odds = Number(totalOdds).toFixed(2);

    cardPage = await page.context().newPage();
    await cardPage.setViewportSize({ width: 1080, height: 720 });
    await cardPage.setContent(`<!doctype html><html><head><style>
      *{box-sizing:border-box} body{margin:0;background:#0d1520;font-family:Arial,sans-serif;color:#fff}
      .ticket{height:720px;padding:42px;background:radial-gradient(circle at 85% 10%,#164e63 0,transparent 30%),linear-gradient(135deg,#0b1320,#121d2d 60%,#0d2928)}
      .top{display:flex;justify-content:space-between;align-items:center;padding-bottom:28px;border-bottom:1px solid rgba(255,255,255,.13)}
      .brand{font-size:31px;font-weight:900;letter-spacing:-1px}.brand span{color:#2dd4bf}.meta{text-align:right;color:#b9cad6;font-size:17px;line-height:1.45}.meta b{display:block;color:#fff;font-size:22px}
      .eyebrow{margin:58px 0 12px;text-align:center;color:#75f3e5;font-size:16px;font-weight:800;letter-spacing:3px}.code{text-align:center;font-size:82px;line-height:1;font-weight:900;letter-spacing:7px;color:#36dfbe;text-shadow:0 10px 32px rgba(45,212,191,.23)}
      .bar{height:5px;width:180px;border-radius:99px;background:#2dd4bf;margin:26px auto 35px}.panel{max-width:770px;margin:auto;border:1px solid rgba(255,255,255,.15);border-radius:18px;background:rgba(255,255,255,.08);padding:25px 31px;display:flex;justify-content:space-between;align-items:center}.panel small{display:block;color:#a9bec9;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;font-size:13px}.panel b{font-size:42px;color:#fff}.footer{max-width:770px;margin:32px auto 0;text-align:center;color:#b8c9d4;font-size:16px}.footer strong{color:#fff}
    </style></head><body><main class="ticket"><div class="top"><div class="brand">Tavs<span>Score</span></div><div class="meta"><b>${safePlatform} booking ticket</b>${now} · Lagos</div></div><div class="eyebrow">YOUR BOOKING CODE</div><div class="code">${safeCode}</div><div class="bar"></div><div class="panel"><div><small>Combined odds</small><b>${odds}</b></div><div style="text-align:right"><small>Ticket status</small><b style="font-size:25px;color:#75f3e5">READY TO LOAD</b></div></div><div class="footer">Use <strong>Copy Code</strong> below, then verify the selections and final odds in the sportsbook before placing a bet.</div></main></body></html>`);
    const image = await cardPage.screenshot({ type: 'jpeg', quality: 88, fullPage: false });
    return `data:image/jpeg;base64,${image.toString('base64')}`;
  } catch (error) {
    console.warn(`Could not render the TavsScore ticket card: ${error.message}`);
    return null;
  } finally {
    if (cardPage) await cardPage.close().catch(() => {});
  }
}

function escapeHtml(value) {
  return value.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
}

async function shareBooking(page, selections) {
  // Retry transient failures (a code is only minted on a clean 200, so a thrown
  // attempt never leaves a stray booking behind).
  let lastErr;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
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
    } catch (e) {
      lastErr = e;
      if (attempt < 3) await page.waitForTimeout(1500 * attempt);
    }
  }
  throw lastErr;
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

function describeLeg(leg) {
  return `${leg.home} vs ${leg.away} (${leg.market})`;
}

function formatCoverage(eventCount, skipped) {
  const parts = [`SportyBet scan: ${eventCount} upcoming fixtures`];
  for (const [kind, label] of [
    ['fixture', 'fixture not listed'],
    ['market', 'market unavailable'],
    ['unsupported', 'unsupported TavsScore market'],
    ['odds', 'outside ticket odds limit'],
  ]) {
    const legs = skipped[kind];
    if (!legs.length) continue;
    const sample = legs.slice(0, 3).join('; ');
    parts.push(`${label}: ${legs.length}${sample ? ` [${sample}${legs.length > 3 ? '; …' : ''}]` : ''}`);
  }
  return parts.join('. ');
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
