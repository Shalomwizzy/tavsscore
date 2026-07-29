// ONE-TIME login setup for personal auto-betting (your own SportyBet account).
//
// Opens SportyBet in a PERSISTENT browser profile stored on THIS Mac. Log in
// once; the session (cookies) is saved to that profile and the worker reuses it
// to place bets — so no password is ever stored, typed by automation, or sent
// to the server. Re-run this only if the session expires.
//
//   cd booking-worker && npm run login-setup
//
// The profile lives in booking-worker/.sporty-profile (git-ignored). Keep it
// private — anyone with that folder has your logged-in session.
import { chromium } from 'playwright';

const PROFILE = process.env.SPORTY_PROFILE || '.sporty-profile';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

const ctx = await chromium.launchPersistentContext(PROFILE, {
  headless: false,
  userAgent: UA,
  args: ['--disable-blink-features=AutomationControlled'],
});
const page = ctx.pages()[0] || (await ctx.newPage());
await page.goto('https://www.sportybet.com/ng/', { waitUntil: 'domcontentloaded' }).catch(() => {});

console.log('\n=== SportyBet login setup ===');
console.log('1. Log into YOUR SportyBet account in this window.');
console.log('2. Once you see your balance, close the window.');
console.log(`Your session will be saved to booking-worker/${PROFILE} and reused by the worker.\n`);

await page.waitForEvent('close', { timeout: 0 }).catch(() => {});
await ctx.close();
console.log('Saved. You can now run: npm run capture-bet');
