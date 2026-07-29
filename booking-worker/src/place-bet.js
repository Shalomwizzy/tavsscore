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

const PROFILE = process.env.SPORTY_PROFILE || '.sporty-profile';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

export async function placeBet(code, stake, { headless = process.env.HEADLESS === 'true' } = {}) {
  code = String(code || '').trim();
  stake = Number(stake);
  if (!code) throw new Error('a booking/share code is required');
  if (!(stake >= 10)) throw new Error('stake must be at least ₦10');

  const ctx = await chromium.launchPersistentContext(PROFILE, { headless, userAgent: UA, args: ['--disable-blink-features=AutomationControlled'] });
  const page = ctx.pages()[0] || (await ctx.newPage());
  try {
    // Load the code into the betslip via its share URL.
    await page.goto(`https://www.sportybet.com/ng/?shareCode=${code}`, { waitUntil: 'domcontentloaded' });

    // Confirm we're actually logged in (a balance is visible), else abort — we
    // must never fall through to a login screen.
    await page.waitForTimeout(3500);
    const balance = (await page.evaluate(() => (document.body.innerText.match(/(?:NGN|₦)\s?[\d,]+\.?\d*/) || [])[0])) || null;
    if (!balance) throw new Error('not logged in (no balance visible) — run: npm run login-setup');

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
