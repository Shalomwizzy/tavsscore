// Capture helper — run this ONCE on a machine that can reach SportyBet
// (Nigerian residential IP, not a datacenter/VPN), so we learn the exact
// request SportyBet fires when a booking code is created. Then the adapter can
// be implemented against that real request instead of guessed DOM selectors.
//
//   cd booking-worker
//   npm install
//   node src/capture.js
//
// A visible Chromium opens on the SportyBet football page. Log in if you want,
// add 2-3 selections to your betslip, then click "Book Bet" / the share icon.
// Every network call that looks bet-related is printed here AND written to
// capture.log.json. Send that file back and the adapter gets wired up exactly.

import { chromium } from 'playwright';
import { writeFileSync } from 'node:fs';

const START_URL = process.env.CAPTURE_URL || 'https://www.sportybet.com/ng/sport/football';
const KEYWORDS = ['order', 'share', 'book', 'betslip', 'bet-slip', 'selection', 'outcome', 'coupon', 'ticket', 'factscenter', 'event', 'upcoming', 'match', 'wapconfigurable', 'sportlist'];
const isInteresting = (url) => KEYWORDS.some((k) => url.toLowerCase().includes(k));

const captured = [];

async function main() {
  const browser = await chromium.launch({
    headless: false,
    args: ['--disable-blink-features=AutomationControlled'],
  });
  const context = await browser.newContext({
    userAgent:
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
  });
  const page = await context.newPage();

  page.on('request', (req) => {
    if (!isInteresting(req.url())) return;
    const entry = {
      phase: 'request',
      method: req.method(),
      url: req.url(),
      headers: req.headers(),
      postData: safeJson(req.postData()),
      at: new Date().toISOString(),
    };
    captured.push(entry);
    console.log(`\n→ ${entry.method} ${entry.url}`);
    if (entry.postData) console.log('  body:', JSON.stringify(entry.postData).slice(0, 800));
  });

  page.on('response', async (res) => {
    if (!isInteresting(res.url())) return;
    let body = null;
    try { body = safeJson(await res.text()); } catch { /* non-text */ }
    captured.push({ phase: 'response', status: res.status(), url: res.url(), body, at: new Date().toISOString() });
    console.log(`← ${res.status()} ${res.url()}`);
  });

  await page.goto(START_URL, { waitUntil: 'domcontentloaded' });
  console.log('\n=== Capture running ===');
  console.log('Add 2-3 selections, then click "Book Bet"/share. Close the window when done.\n');

  await page.waitForEvent('close', { timeout: 0 }).catch(() => {});
  await browser.close();
}

function safeJson(text) {
  if (!text) return null;
  try { return JSON.parse(text); } catch { return text.length > 2000 ? text.slice(0, 2000) + '…' : text; }
}

main()
  .catch((e) => console.error('capture error:', e))
  .finally(() => {
    writeFileSync('capture.log.json', JSON.stringify(captured, null, 2));
    console.log(`\nSaved ${captured.length} network events to booking-worker/capture.log.json — send that file back.`);
  });
