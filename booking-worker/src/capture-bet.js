// Capture the authenticated PLACE-BET request, using the logged-in profile from
// login-setup.js. You place ONE small real bet by hand; we record the exact API
// call so the worker can place bets the same way.
//
//   cd booking-worker && npm run capture-bet
//
// ⚠️ This places a REAL bet with REAL money. Use the SMALLEST stake SportyBet
// allows (e.g. ₦10–100) on a single selection — we only need the request shape.
import { chromium } from 'playwright';
import { writeFileSync } from 'node:fs';

const PROFILE = process.env.SPORTY_PROFILE || '.sporty-profile';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
const KEYWORDS = ['order', 'bet', 'place', 'stake', 'ticket', 'coupon', 'wager', 'balance', 'cutbet'];
const isInteresting = (url) => KEYWORDS.some((k) => url.toLowerCase().includes(k));

const captured = [];

const ctx = await chromium.launchPersistentContext(PROFILE, {
  headless: false,
  userAgent: UA,
  args: ['--disable-blink-features=AutomationControlled'],
});
const page = ctx.pages()[0] || (await ctx.newPage());

page.on('request', (req) => {
  if (!isInteresting(req.url())) return;
  captured.push({ phase: 'request', method: req.method(), url: req.url(), headers: req.headers(), postData: safe(req.postData()), at: new Date().toISOString() });
  console.log(`\n→ ${req.method()} ${req.url()}`);
  if (req.postData()) console.log('  body:', req.postData().slice(0, 700));
});
page.on('response', async (res) => {
  if (!isInteresting(res.url())) return;
  let body = null;
  try { body = safe(await res.text()); } catch { /* non-text */ }
  captured.push({ phase: 'response', status: res.status(), url: res.url(), body, at: new Date().toISOString() });
  console.log(`← ${res.status()} ${res.url()}`);
});

await page.goto('https://www.sportybet.com/ng/sport/football', { waitUntil: 'domcontentloaded' }).catch(() => {});
console.log('\n=== Place-bet capture ===');
console.log('Add ONE selection, enter the SMALLEST real stake, click "Place Bet" and confirm.');
console.log('When the bet is accepted, close the window.\n');

await page.waitForEvent('close', { timeout: 0 }).catch(() => {});
await ctx.close();

writeFileSync('capture-bet.log.json', JSON.stringify(captured, null, 2));
console.log(`\nSaved ${captured.length} network events to booking-worker/capture-bet.log.json — send that file back.`);

function safe(text) {
  if (!text) return null;
  try { return JSON.parse(text); } catch { return text.length > 2000 ? text.slice(0, 2000) + '…' : text; }
}
