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

    const context = adapter.usesBrowser === false ? null : await browser.newContext();
    const page = context ? await context.newPage() : null;

    for (const slip of slips) {
      const legs = slip.selections || [];
      if (legs.length < 3) continue; // spec already filters, but be safe

      try {
        // adapter.buildCode returns { code, link, total_odds, booked } where
        // `booked` is the subset of legs it actually managed to add (some legs
        // may be missing on the bookmaker). It must respect the odds band:
        // slip.min_total_odds .. slip.max_total_odds.
        const result = await adapter.buildCode(page, slip);

        if (!result || !result.code) {
          await postFailure(platform, slip, 'no code produced');
          continue;
        }

        await postCode({
          platform,
          code: result.code,
          link: result.link || null,
          slip_ref: slip.ref,
          total_odds: result.total_odds || slip.est_total_odds || null,
          fixtures: (result.booked || legs).map((l) => ({
            match: `${l.home} vs ${l.away}`,
            market: l.market,
            est_odds: l.est_odds ?? null,
          })),
          status: 'published',
          note: slip.title,
          pick_date: spec.pick_date,
        });
        console.log(`✓ ${platform}/${slip.ref}: ${result.code} @ ${result.total_odds || '?'}`);
      } catch (err) {
        console.error(`✗ ${platform}/${slip.ref}: ${err.message}`);
        await postFailure(platform, slip, err.message);
      }
    }

    if (context) await context.close();
  }

  if (browser) await browser.close();
}

async function postFailure(platform, slip, reason) {
  try {
    await postCode({
      platform,
      code: `FAILED-${slip.ref}`,
      slip_ref: slip.ref,
      status: 'failed',
      note: `${slip.title}: ${reason}`.slice(0, 500),
    });
  } catch (e) {
    console.error(`could not report failure for ${slip.ref}: ${e.message}`);
  }
}

run().catch((e) => {
  console.error('worker crashed:', e);
  process.exit(1);
});
