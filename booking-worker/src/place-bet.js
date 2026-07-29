// Place a REAL bet on YOUR OWN SportyBet account — personal use only.
//
// SportyBet encrypts its place-bet API, so we can't call it directly. Instead we
// drive the real UI with your logged-in session (.sporty-profile): load a
// booking code into the betslip, type the stake, click Place Bet. SportyBet's
// own JS does the encryption. No password is stored or typed by automation.
//
//   npm run place-bet -- <shareCode> <stake>
//
// ⚠️ Real money, your account, your risk. Minimum stake is ₦10.
import { chromium } from 'playwright';
import { fetchAutobetConfig } from './api.js';

const PROFILE = process.env.SPORTY_PROFILE || '.sporty-profile';

/**
 * Ensure a live betting session. If SportyBet shows a login state, log in with
 * the owner's own credentials (fetched from the token-authed admin config) and
 * tick "Keep me signed in" so the session persists. No password is stored here.
 */
async function ensureLoggedIn(page) {
  await page.goto('https://www.sportybet.com/ng/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);
  const loggedIn = await page.evaluate(() => /NGN|₦/.test(document.body.innerText) && !/>\s*Log ?in\s*</i.test(document.querySelector('header')?.innerHTML || ''));
  if (loggedIn) return;

  let creds = {};
  try { creds = await fetchAutobetConfig(); } catch { /* offline / no config */ }
  if (!creds.phone || !creds.password) {
    throw new Error('session expired and no stored credentials — set them in Admin → Auto-Bet, or run npm run login-setup');
  }

  const loginLink = page.locator('text=/^Log ?in$/i').first();
  if (await loginLink.count()) { await loginLink.click().catch(() => {}); await page.waitForTimeout(1500); }
  await page.locator('input[type="tel"], input[placeholder*="Mobile" i], input[name*="phone" i]').first().fill(String(creds.phone));
  await page.locator('input[type="password"]').first().fill(String(creds.password));
  const keep = page.locator('text=/keep me signed in/i').first();
  if (await keep.count()) await keep.click().catch(() => {});
  await page.locator('button:has-text("Log in"), button:has-text("Login"), .af-button--primary:has-text("Log in")').first().click().catch(() => {});
  await page.waitForTimeout(4500);

  const ok = await page.evaluate(() => /NGN|₦/.test(document.body.innerText) && !/>\s*Log ?in\s*</i.test(document.querySelector('header')?.innerHTML || ''));
  if (!ok) throw new Error('auto-login failed — check the credentials, or SportyBet may have shown a captcha');
}
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

export async function placeBet(code, stake, { headless = process.env.HEADLESS === 'true' } = {}) {
  code = String(code || '').trim();
  stake = Number(stake);
  if (!code) throw new Error('a booking/share code is required');
  if (!(stake >= 10)) throw new Error('stake must be at least ₦10');

  const ctx = await chromium.launchPersistentContext(PROFILE, { headless, userAgent: UA, args: ['--disable-blink-features=AutomationControlled'] });
  const page = ctx.pages()[0] || (await ctx.newPage());
  try {
    // Make sure we have a live betting session (auto-login if credentials given).
    await ensureLoggedIn(page);

    // Load the code into the betslip via its share URL.
    await page.goto(`https://www.sportybet.com/ng/?shareCode=${code}`, { waitUntil: 'domcontentloaded' });

    // Confirm we're actually logged in (a balance is visible), else abort — we
    // must never fall through to a login screen.
    await page.waitForTimeout(3500);
    const balance = (await page.evaluate(() => (document.body.innerText.match(/(?:NGN|₦)\s?[\d,]+\.?\d*/) || [])[0])) || null;
    if (!balance) throw new Error('not logged in (no balance visible) — run: npm run login-setup, or set credentials in Admin → Auto-Bet');

    // Type the stake into the betslip stake box.
    const stakeInput = page.locator('input.m-input[placeholder*="min"], input[placeholder*="min. 10"]').first();
    await stakeInput.waitFor({ state: 'visible', timeout: 15000 });
    await stakeInput.fill(String(stake));
    await page.waitForTimeout(500);

    // Place Bet — then confirm on the dialog SportyBet shows.
    await page.locator('.m-btn-wrapper:has-text("Place Bet"), button:has-text("Place Bet")').first().click();
    await page.waitForTimeout(1500);
    const confirm = page.locator('.af-button--primary:has-text("Place Bet"), button:has-text("Confirm"), .af-button--primary:has-text("Confirm")');
    if (await confirm.count()) await confirm.first().click().catch(() => {});

    // Detect acceptance.
    await page.waitForTimeout(3500);
    const text = await page.evaluate(() => document.body.innerText);
    const placed = /bet (placed|accepted)|successfully|order.*success|ticket.*id/i.test(text);
    const insufficient = /insufficient|top up|low balance/i.test(text);
    if (insufficient) throw new Error('insufficient balance to place this stake');

    return { placed, balance, code, stake };
  } finally {
    await ctx.close();
  }
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const [, , code, stake] = process.argv;
  placeBet(code, stake)
    .then((r) => { console.log(r.placed ? `✓ bet placed: ${r.code} @ ₦${r.stake} (balance ${r.balance})` : `⚠ submitted but could not confirm acceptance — check your SportyBet bet history`); })
    .catch((e) => { console.error('✗', e.message); process.exit(1); });
}
