// Orchestrator: fetch today's spec → for each platform, for each slip, build a
// booking code in the browser → post the code back. Adapters isolate the
// site-specific browser steps (the only part that needs real DOM selectors).

import { chromium } from 'playwright';
import { fetchSpec, postCode } from './api.js';
import { sportybet } from './adapters/sportybet.js';
import { onexbet } from './adapters/onexbet.js';

const ADAPTERS = { sportybet, '1xbet': onexbet };

async function run() {
  const spec = await fetchSpec();
  const slips = spec.slips || [];
  const platforms = (process.env.PLATFORMS || spec.platforms?.join(',') || 'sportybet')
    .split(',').map((p) => p.trim()).filter(Boolean);

  if (!slips.length) {
    console.log('No slips in today\'s spec — nothing to build.');
    return;
  }

  const browser = await chromium.launch({ headless: process.env.HEADLESS !== 'false' });

  for (const platform of platforms) {
    const adapter = ADAPTERS[platform];
    if (!adapter) {
      console.warn(`No adapter for platform "${platform}" — skipping.`);
      continue;
    }

    const context = await browser.newContext();
    const page = await context.newPage();

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

    await context.close();
  }

  await browser.close();
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
