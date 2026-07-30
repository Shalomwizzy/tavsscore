// Orchestrator: fetch today's spec → for each platform, for each slip, build a
// booking code in the browser → post the code back. Adapters isolate the
// site-specific browser steps (the only part that needs real DOM selectors).

import { fetchSpec, postCode } from './api.js';
import { sportybet } from './adapters/sportybet.js';
import { onexbet } from './adapters/onexbet.js';
import { mock } from './adapters/mock.js';
import { demoSlips } from './demo.js';

const ADAPTERS = { sportybet, '1xbet': onexbet, mock };

async function run() {
  const spec = await fetchSpec();
  let slips = spec.slips || [];
  const dryRun = process.env.DRY_RUN === 'true';
  // A bookmaker can briefly reject a valid ticket while odds or its page are
  // refreshing. Retry the ticket before letting run.sh retry the whole batch.
  // Unsuccessful attempts are never saved as public booking codes.
  const maxSlipAttempts = Math.max(
    1,
    Number.parseInt(process.env.BOOKING_SLIP_MAX_ATTEMPTS || '8', 10) || 8,
  );
  const unresolvedSlips = [];
  let publishedCodes = 0;
  const platforms = dryRun
    ? ['mock']
    : (process.env.PLATFORMS || spec.platforms?.join(',') || 'sportybet')
        .split(',').map((p) => p.trim()).filter(Boolean);

  // In test mode with no fixtures today (pre-season), fall back to a built-in
  // demo ticket so you can see a code go all the way through to /booking-codes.
  if (dryRun && slips.length === 0) {
    console.log('DRY_RUN: no fixtures today — using a built-in DEMO ticket so you can see a code end-to-end.');
    slips = demoSlips(spec.pick_date);
  }

  if (!slips.length) {
    console.log('No slips in today\'s spec — nothing to build.');
    if (process.env.REQUIRE_BOOKING_CODE === 'true') {
      throw new Error('No qualified tickets are available yet; keeping the admin request queued.');
    }
    return;
  }

  // Only spin up a real browser if an adapter actually needs one.
  const needsBrowser = platforms.some((p) => ADAPTERS[p] && ADAPTERS[p].usesBrowser !== false);
  let browser = null;
  if (needsBrowser) {
    const { chromium } = await import('playwright');
    browser = await chromium.launch({ headless: process.env.HEADLESS !== 'false' });
  }

  for (const platform of platforms) {
    const adapter = ADAPTERS[platform];
    if (!adapter) {
      console.warn(`No adapter for platform "${platform}" — skipping.`);
      continue;
    }

    // A real desktop UA — SportyBet blocks the default HeadlessChrome UA.
    const context = adapter.usesBrowser === false ? null : await browser.newContext({
      userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
    });
    const page = context ? await context.newPage() : null;

    for (const slip of slips) {
      const legs = slip.selections || [];
      if (legs.length < 3) continue; // spec already filters, but be safe
      const completedPlatforms = (slip.completed_platforms || []).map((value) => String(value).toLowerCase());
      if (completedPlatforms.includes(platform.toLowerCase())) {
        console.log(`↷ ${platform}/${slip.ref}: already published today — skipping.`);
        continue;
      }

      try {
        // adapter.buildCode returns { code, link, total_odds, booked } where
        // `booked` is the subset of legs it actually managed to add (some legs
        // may be missing on the bookmaker). It must respect the odds band:
        // slip.min_total_odds .. slip.max_total_odds. Retrying protects against
        // transient odds changes or a briefly blocked bookmaker page.
        let result = null;
        let lastErr = null;
        for (let attempt = 1; attempt <= maxSlipAttempts; attempt++) {
          try {
            result = await adapter.buildCode(page, slip);
            if (result && result.code) break;
          } catch (err) {
            lastErr = err;
            // Retrying can help an odds refresh or a transient SportyBet
            // response, but cannot make a fixture that is absent from the
            // sportsbook appear eight times in a row.
            if (err?.permanent) break;
          }
          if (attempt < maxSlipAttempts && page) {
            await page.waitForTimeout(Math.min(15000, 2000 * attempt));
          }
        }

        if (!result || !result.code) {
          const reason = lastErr ? lastErr.message : 'no code produced';
          console.error(`✗ ${platform}/${slip.ref}: no code after ${maxSlipAttempts} attempts (${reason}). No failed code was saved; the worker run will retry it.`);
          unresolvedSlips.push(`${platform}/${slip.ref}`);
          continue;
        }

        await postCode({
          platform,
          code: result.code,
          link: result.link || null,
          ticket_screenshot: result.ticket_screenshot || null,
          slip_ref: slip.ref,
          total_odds: result.total_odds || slip.est_total_odds || null,
          fixtures: (result.booked || legs).map((l) => ({
            match_id: l.match_id ?? null,
            match: `${l.home} vs ${l.away}`,
            market: l.market,
            model_prob: l.model_prob ?? null,
            est_odds: l.est_odds ?? null,
          })),
          status: 'published',
          note: slip.title,
          pick_date: spec.pick_date,
        });
        publishedCodes++;
        console.log(`✓ ${platform}/${slip.ref}: ${result.code} @ ${result.total_odds || '?'}`);
      } catch (err) {
        console.error(`✗ ${platform}/${slip.ref}: ${err.message}`);
        // Never create a FAILED-* placeholder. The next run retries this
        // ticket, while users only ever see real, usable booking codes.
        unresolvedSlips.push(`${platform}/${slip.ref}`);
      }
    }

    if (context) await context.close();
  }

  if (browser) await browser.close();

  // run.sh sees the non-zero exit and retries the complete run on this Mac.
  // Successfully-created tickets are idempotent, so users are not spammed.
  if (unresolvedSlips.length) {
    throw new Error(`Booking codes still unresolved: ${unresolvedSlips.join(', ')}`);
  }
  if (process.env.REQUIRE_BOOKING_CODE === 'true' && publishedCodes === 0) {
    throw new Error('No real booking code was created; keeping the admin request queued.');
  }
}

run().catch((e) => {
  console.error('worker crashed:', e);
  process.exit(1);
});
