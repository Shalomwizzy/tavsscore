// One-off: scan live events for which market IDs SportyBet exposes, and dump a
// full example of Asian Handicap (16) if present. Run on a NG IP.
import { chromium } from 'playwright';
const BASE = 'https://www.sportybet.com';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
const browser = await chromium.launch({ headless: true });
const page = await (await browser.newContext({ userAgent: UA })).newPage();
await page.goto(`${BASE}/ng/sport/football`, { waitUntil: 'domcontentloaded' });
const url = `${BASE}/api/ng/factsCenter/pcUpcomingEvents?sportId=sr:sport:1&marketId=1,10,11,14,16,18,19,20,26,29,36&pageSize=100&pageNum=1&option=1&_t=${Date.now()}`;
const resp = await page.request.get(url, { headers: { accept: 'application/json', referer: `${BASE}/ng/sport/football` } });
const body = await resp.json().catch(() => null);
const events = (body?.data?.tournaments || []).flatMap((t) => t.events || []);
console.log('events:', events.length);

const seen = {};
let ahExample = null;
for (const ev of events) {
  for (const m of ev.markets || []) {
    seen[`${m.id} ${m.desc}`] = (seen[`${m.id} ${m.desc}`] || 0) + 1;
    if (String(m.id) === '16' && !ahExample) ahExample = { ev, m };
  }
}
console.log('\nmarket ids seen (id desc → #events):');
Object.entries(seen).sort().forEach(([k, v]) => console.log(`  ${k} → ${v}`));

if (ahExample) {
  console.log('\n=== Asian Handicap example:', ahExample.ev.homeTeamName, 'vs', ahExample.ev.awayTeamName, '===');
  for (const m of ahExample.ev.markets.filter((x) => String(x.id) === '16')) {
    const outs = (m.outcomes || []).map((o) => `${o.id}:${o.desc}${o.odds ? '@' + o.odds : ''}`).join('  ');
    console.log(`   spec=${m.specifier || '-'}  ${outs}`);
  }
} else {
  console.log('\nNo market 16 (Asian Handicap) exposed on any event.');
}
await browser.close();
