// One-off end-to-end test: build a real SportyBet booking code from live
// fixtures using the adapter. Run on a SportyBet-reachable (Nigerian) IP:
//   node src/test-sporty.js
import { chromium } from 'playwright';
import { sportybet } from './adapters/sportybet.js';

const BASE = 'https://www.sportybet.com';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

const browser = await chromium.launch({ headless: true });
const page = await (await browser.newContext({ userAgent: UA })).newPage();
await page.goto(`${BASE}/ng/sport/football`, { waitUntil: 'domcontentloaded' });

const url = `${BASE}/api/ng/factsCenter/pcUpcomingEvents?sportId=sr:sport:1&marketId=1,18&pageSize=100&pageNum=1&option=1&_t=${Date.now()}`;
const resp = await page.request.get(url, { headers: { accept: 'application/json', referer: `${BASE}/ng/sport/football` } });
const body = await resp.json().catch(() => null);
const events = (body?.data?.tournaments || []).flatMap((t) => t.events || []);
console.log('live events pulled:', events.length, '(http', resp.status() + ')');

const legs = events.slice(0, 4).map((e) => ({
  home: e.homeTeamName, away: e.awayTeamName,
  kickoff: new Date(e.estimateStartTime).toISOString(),
  market: 'Over 1.5 Goals', est_odds: 1.2,
}));
console.log('slip legs:', legs.map((l) => `${l.home} v ${l.away}`).join(' | '));

try {
  const result = await sportybet.buildCode(page, { selections: legs, min_total_odds: 1.5, max_total_odds: 500 });
  console.log('\n✓ BOOKING CODE:', result.code);
  console.log('  link:', result.link);
  console.log('  total odds:', result.total_odds);
  console.log('  booked:', result.booked.map((b) => `${b.home} v ${b.away} (${b.market} @${b.est_odds})`).join(' | '));
} catch (e) {
  console.error('\n✗ failed:', e.message);
}
await browser.close();
