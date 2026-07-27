// 1xBet adapter — same contract as the SportyBet adapter. Add this once
// SportyBet is working end-to-end (start with one book, per the plan).
//
// buildCode(page, slip) must return { code, link, total_odds, booked }.

export const onexbet = {
  async buildCode(page, slip) {
    void page; void slip;
    throw new Error('1xbet adapter not implemented yet — enable after SportyBet is live');
  },
};
