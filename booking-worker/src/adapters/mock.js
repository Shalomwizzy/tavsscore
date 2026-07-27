// Mock adapter — no browser, no bookmaker. It "books" the safest legs of a slip
// and returns a fake code so you can validate the WHOLE pipeline end-to-end
// (fetch spec → post code → DB → public /booking-codes page) before the real
// SportyBet selectors exist. Enable with PLATFORMS=mock or DRY_RUN=true.

export const mock = {
  usesBrowser: false,

  async buildCode(_page, slip) {
    const legs = (slip.selections || []).slice(0, Math.max(3, Math.min(slip.selections.length, 10)));

    let total = 1.0;
    for (const l of legs) total *= l.est_odds || 1.0;

    const code = `MOCK-${slip.ref.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8)}`;
    return {
      code,
      link: null,
      total_odds: Math.round(total * 100) / 100,
      booked: legs,
    };
  },
};
