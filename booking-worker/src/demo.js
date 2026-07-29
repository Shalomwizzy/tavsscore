// A built-in demo ticket, used only in test mode when the site has no fixtures
// today (pre-season lull). It lets you watch a code travel the full pipeline —
// worker → API → database → /booking-codes page — even with no real matches.
// The codes are clearly marked DEMO and can be deleted from /admin/booking-code.

export function demoSlips(pickDate) {
  const leg = (home, away, market, odds) => ({
    home, away, league: 'Demo League', country: 'World',
    kickoff: `${pickDate}T18:00:00+01:00`, market, est_odds: odds,
  });

  return [
    {
      ref: 'demo-over-2-5',
      title: 'DEMO — Over 2.5 Goals (test, delete me)',
      market: 'Over 2.5 Goals',
      min_total_odds: 2,
      max_total_odds: 500,
      selections: [
        leg('Manchester City', 'Brighton', 'Over 2.5 Goals', 1.4),
        leg('Bayern Munich', 'Werder Bremen', 'Over 2.5 Goals', 1.45),
        leg('PSG', 'Nantes', 'Over 2.5 Goals', 1.5),
      ],
    },
    {
      ref: 'demo-safe-builder',
      title: 'DEMO — Safe Builder (test, delete me)',
      market: 'Mixed — safest per game',
      min_total_odds: 2,
      max_total_odds: 500,
      selections: [
        leg('Real Madrid', 'Getafe', 'Home or Draw (1X)', 1.1),
        leg('Liverpool', 'Luton', 'Over 1.5 Goals', 1.12),
        leg('Inter', 'Empoli', 'Home or Draw (1X)', 1.14),
      ],
    },
  ];
}
