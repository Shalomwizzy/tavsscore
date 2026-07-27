// SportyBet adapter.
//
// This is the ONLY file with site-specific browser steps. SportyBet's DOM,
// market names and booking-code flow change often, so the selectors below are
// marked TODO and must be filled in against the live site (run locally with
// HEADLESS=false to inspect). The structure — search fixture → open market →
// add safe selection → respect odds band → read booking code — is stable.
//
// buildCode(page, slip) must return:
//   { code, link, total_odds, booked: [legs actually added] }

// Map our internal market labels to SportyBet's on-site market/outcome wording.
const MARKET_MAP = {
  'Over 1.5 Goals': { group: 'Over/Under', outcome: 'Over 1.5' },
  'Over 2.5 Goals': { group: 'Over/Under', outcome: 'Over 2.5' },
  'Under 3.5 Goals': { group: 'Over/Under', outcome: 'Under 3.5' },
  'Both Teams Score (GG)': { group: 'GG/NG', outcome: 'Yes' },
  'Home or Draw (1X)': { group: 'Double Chance', outcome: '1X' },
  'Draw or Away (X2)': { group: 'Double Chance', outcome: 'X2' },
  'Draw No Bet - Home': { group: 'Draw No Bet', outcome: 'Home' },
  'Draw No Bet - Away': { group: 'Draw No Bet', outcome: 'Away' },
};

export const sportybet = {
  async buildCode(page, slip) {
    await page.goto('https://www.sportybet.com/ng/sport/football', { waitUntil: 'domcontentloaded' });

    const booked = [];
    let combined = 1.0;

    for (const leg of slip.selections) {
      if (combined >= (slip.max_total_odds ?? 500)) break;

      const mapped = MARKET_MAP[leg.market];
      if (!mapped) continue; // market we can't place on this book — skip the leg

      const added = await addSelection(page, leg, mapped);
      if (added) {
        booked.push(leg);
        combined *= leg.est_odds || 1.0;
      }

      // Stop once we're safely inside the odds band with a real accumulator.
      if (booked.length >= 3 && combined >= (slip.min_total_odds ?? 3)) break;
    }

    if (booked.length < 3) {
      throw new Error(`only ${booked.length} legs could be added`);
    }

    const { code, link, total_odds } = await readBookingCode(page);
    return { code, link, total_odds, booked };
  },
};

// ── Site-specific steps — fill these in against the live DOM ──

async function addSelection(page, leg, mapped) {
  // TODO: implement against SportyBet.
  // 1. Search for the fixture: `${leg.home} vs ${leg.away}` (use leg.home_norm /
  //    leg.away_norm for fuzzy matching; confirm kickoff ~= leg.kickoff).
  // 2. Open the fixture's market group `mapped.group`.
  // 3. Click the outcome `mapped.outcome`.
  // 4. Return true if the bet was added to the slip, false otherwise.
  //
  // Example skeleton (selectors are placeholders):
  //   await page.fill('input[type="search"]', `${leg.home} ${leg.away}`);
  //   await page.click(`text=${leg.home}`);
  //   await page.click(`text=${mapped.group}`);
  //   const btn = page.locator(`.m-outcome:has-text("${mapped.outcome}")`);
  //   if (!(await btn.count())) return false;
  //   await btn.first().click();
  //   return true;
  void page; void leg; void mapped;
  throw new Error('sportybet.addSelection not implemented — fill in live selectors');
}

async function readBookingCode(page) {
  // TODO: open the betslip, click "Book bet" / "Share", and read the booking
  // code + shareable link + displayed total odds.
  //   await page.click('text=Book Bet');
  //   const code = await page.locator('.booking-code').innerText();
  //   const link = await page.locator('.share-link').getAttribute('href');
  //   const total = parseFloat(await page.locator('.total-odds').innerText());
  //   return { code, link, total_odds: total };
  void page;
  throw new Error('sportybet.readBookingCode not implemented — fill in live selectors');
}
