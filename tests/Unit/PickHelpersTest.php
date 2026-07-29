<?php

namespace Tests\Unit;

use App\Models\FootballMatch;
use App\Support\PickHelpers;
use Tests\TestCase;

class PickHelpersTest extends TestCase
{
    private function match(int $home, int $away, ?int $htHome = null, ?int $htAway = null): FootballMatch
    {
        $m                = new FootballMatch();
        $m->home_score    = $home;
        $m->away_score    = $away;
        $m->home_score_ht = $htHome;
        $m->away_score_ht = $htAway;
        $m->status        = 'FT';
        return $m;
    }

    // ── 1X2 ─────────────────────────────────────────────────────────

    public function test_home_win_correct(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 0), 'Home Win'));
    }

    public function test_home_win_wrong_when_draw(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(1, 1), 'Home Win'));
    }

    public function test_home_win_wrong_when_away_wins(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(0, 2), 'Home Win'));
    }

    public function test_away_win_correct(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 1), 'Away Win'));
    }

    public function test_draw_correct(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 1), 'Draw'));
    }

    public function test_draw_wrong_when_home_wins(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(2, 0), 'Draw'));
    }

    // ── Double chance ────────────────────────────────────────────────

    public function test_1x_correct_when_home_wins(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1), 'Home or Draw (1X)'));
    }

    public function test_1x_correct_when_draw(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 0), 'Home or Draw (1X)'));
    }

    public function test_1x_wrong_when_away_wins(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(0, 2), 'Home or Draw (1X)'));
    }

    public function test_12_correct_when_no_draw(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(3, 1), 'Home or Away (12)'));
    }

    public function test_12_wrong_when_draw(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(1, 1), 'Home or Away (12)'));
    }

    // ── Goal lines ───────────────────────────────────────────────────

    public function test_over_25_correct(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1), 'Over 2.5 Goals'));
    }

    public function test_over_25_wrong_on_two_goals(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(1, 1), 'Over 2.5 Goals'));
    }

    public function test_under_25_correct_on_two_goals(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 1), 'Under 2.5 Goals'));
    }

    public function test_over_35_correct(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(3, 1), 'Over 3.5 Goals'));
    }

    public function test_over_35_wrong_on_three_goals(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(2, 1), 'Over 3.5 Goals'));
    }

    // ── BTTS ─────────────────────────────────────────────────────────

    public function test_btts_correct(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 1), 'Both Teams Score'));
    }

    public function test_btts_correct_gg_alias(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1), 'Both Teams Score (GG)'));
    }

    public function test_btts_wrong_when_only_home_scores(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(2, 0), 'Both Teams Score'));
    }

    public function test_no_btts_correct_when_clean_sheet(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 0), 'No Both Teams Score (NG)'));
    }

    // ── Clean sheets & to-nil ────────────────────────────────────────

    public function test_home_win_to_nil_correct(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 0), 'Home Win to Nil'));
    }

    public function test_home_win_to_nil_wrong_when_away_scores(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(2, 1), 'Home Win to Nil'));
    }

    public function test_home_clean_sheet_correct(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 0), 'Home Clean Sheet'));
    }

    // ── Null cases ───────────────────────────────────────────────────

    public function test_returns_null_when_match_is_null(): void
    {
        $this->assertNull(PickHelpers::resolveForMatch(null, 'Home Win'));
    }

    public function test_returns_null_when_score_is_null(): void
    {
        $m             = new FootballMatch();
        $m->home_score = null;
        $m->away_score = null;
        $this->assertNull(PickHelpers::resolveForMatch($m, 'Home Win'));
    }

    public function test_returns_null_for_competitive_match(): void
    {
        $this->assertNull(PickHelpers::resolveForMatch($this->match(1, 0), 'Competitive Match'));
    }

    public function test_returns_null_for_unknown_outcome(): void
    {
        $this->assertNull(PickHelpers::resolveForMatch($this->match(1, 0), 'Some Made Up Market'));
    }

    // ── Half-time outcomes (require HT score) ───────────────────────

    public function test_home_lead_at_ht_correct(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1, 1, 0), 'Home Lead at HT'));
    }

    public function test_home_lead_at_ht_wrong_when_ht_draw(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(2, 1, 0, 0), 'Home Lead at HT'));
    }

    public function test_ht_outcome_returns_null_when_no_ht_score(): void
    {
        $this->assertNull(PickHelpers::resolveForMatch($this->match(2, 1), 'Home Lead at HT'));
    }

    // ── Handicaps ────────────────────────────────────────────────────

    public function test_home_minus1_handicap_correct(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(3, 1), 'Home -1 Handicap'));
    }

    public function test_home_minus1_handicap_wrong_on_one_goal_lead(): void
    {
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(2, 1), 'Home -1 Handicap'));
    }

    // ── Full-board markets (added so the grader drops nothing) ───────

    public function test_over_15_and_under_15(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 1), 'Over 1.5 Goals'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 0), 'Under 1.5 Goals'));
    }

    public function test_over_under_45_55(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(3, 2), 'Over 4.5 Goals'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 2), 'Under 4.5 Goals'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(3, 2), 'Over 5.5 Goals'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(3, 2), 'Under 5.5 Goals'));
    }

    public function test_total_goals_odd_even(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1), 'Total Goals Odd'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 1), 'Total Goals Even'));
    }

    public function test_exact_and_range_totals(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 1), 'Exactly 2 Goals'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(3, 1), '4+ Goals'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1), '2-3 Goals'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(3, 2), '2-3 Goals'));
    }

    public function test_team_totals(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 0), 'Home Over 1.5'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(1, 0), 'Home Over 1.5'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 3), 'Away 3+ Goals'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 0), 'Home Exactly 0'));
    }

    public function test_dot5_handicaps(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(3, 1), 'Home -1.5 (Handicap)'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(2, 1), 'Home -1.5 (Handicap)'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 1), 'Home +1.5 (Handicap)'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 2), 'Away -1.5 (Handicap)'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 4), 'Home +4.5 (Handicap)'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(0, 5), 'Home +4.5 (Handicap)'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(4, 0), 'Away +4.5 (Handicap)'));
    }

    public function test_every_published_asian_handicap_line_is_resolved_without_a_push(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 0), 'Home -0.5 (Handicap)'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(0, 0), 'Home -0.5 (Handicap)'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 5), 'Home +5.5 (Handicap)'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(0, 6), 'Home +5.5 (Handicap)'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(5, 0), 'Away +5.5 (Handicap)'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(4, 1), 'Home -2.5 (Handicap)'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(3, 1), 'Home -2.5 (Handicap)'));
    }

    public function test_european_handicap_uses_the_virtual_score_and_has_a_draw_selection(): void
    {
        // 0:1 gives the away side one virtual goal.
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 0), 'European Handicap 0:1 - Home'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 0), 'European Handicap 0:1 - Draw'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 0), 'European Handicap 0:1 - Away'));
        // 3:0 gives the home side three virtual goals.
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 3), 'European Handicap 3:0 - Draw'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 4), 'European Handicap 3:0 - Away'));
    }

    public function test_winning_margin(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1), 'Home to win by 1'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(3, 1), 'Home to win by 1'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(4, 1), 'Home to win by 3+'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(0, 2), 'Away to win by 2'));
    }

    public function test_result_and_goals_combos(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1), 'Home & Over 2.5'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 0), 'Home & Under 2.5'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1), 'Home & BTTS'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1), 'BTTS & Over 2.5'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(2, 0), 'Home & BTTS'));
    }

    public function test_ht_markets(): void
    {
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1, 1, 0), 'HT Home Win'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(1, 1, 0, 0), 'HT Under 0.5'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 2, 1, 1), 'HT Both Teams Score'));
        $this->assertNull(PickHelpers::resolveForMatch($this->match(2, 1), 'HT Home Win'));
    }

    public function test_halves_and_htft(): void
    {
        // HT 1-0, FT 2-1 → 2nd half also scored → goal in both halves
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1, 1, 0), 'Goal in Both Halves'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1, 1, 0), 'HT/FT Home/Home'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1, 0, 0), 'HT/FT Draw/Home'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(2, 1, 1, 0), 'HT/FT Draw/Home'));
    }

    public function test_dnb_voids_on_draw(): void
    {
        $this->assertNull(PickHelpers::resolveForMatch($this->match(1, 1), 'Draw No Bet - Home'));
        $this->assertTrue(PickHelpers::resolveForMatch($this->match(2, 1), 'Draw No Bet - Home'));
        $this->assertFalse(PickHelpers::resolveForMatch($this->match(0, 1), 'Draw No Bet - Home'));
    }

    // ── safestBoardMarket() — whole-board selection ──────────────────

    public function test_safest_board_market_picks_highest_over_floor(): void
    {
        $board = ['Home Win' => 60, 'Over 1.5 Goals' => 91, 'Over 2.5 Goals' => 72];
        $safe  = PickHelpers::safestBoardMarket($board, 90.0, []);
        $this->assertSame('Over 1.5 Goals', $safe['market']);
    }

    public function test_safest_board_market_returns_null_when_none_clear_floor(): void
    {
        $board = ['Home Win' => 60, 'Over 2.5 Goals' => 72];
        $this->assertNull(PickHelpers::safestBoardMarket($board, 90.0, []));
    }

    public function test_headline_block_excludes_trivial_certainties(): void
    {
        $board = ['Over 0.5 Goals' => 97, 'Under 5.5 Goals' => 99, 'Over 1.5 Goals' => 91];
        $safe  = PickHelpers::safestBoardMarket($board, 88.0, PickHelpers::headlineBlock());
        $this->assertSame('Over 1.5 Goals', $safe['market']);
    }

    public function test_safest_board_market_null_on_empty_board(): void
    {
        $this->assertNull(PickHelpers::safestBoardMarket(null, 90.0, []));
        $this->assertNull(PickHelpers::safestBoardMarket([], 90.0, []));
    }

    // ── confidenceBand() ────────────────────────────────────────────

    public function test_confidence_band_high(): void
    {
        $band = PickHelpers::confidenceBand(80);
        $this->assertSame('High', $band['label']);
        $this->assertSame('🟢', $band['emoji']);
    }

    public function test_confidence_band_medium(): void
    {
        $band = PickHelpers::confidenceBand(62);
        $this->assertSame('Medium', $band['label']);
    }

    public function test_confidence_band_risky(): void
    {
        $band = PickHelpers::confidenceBand(45);
        $this->assertSame('Risky', $band['label']);
    }

    public function test_confidence_band_null_returns_unknown(): void
    {
        $band = PickHelpers::confidenceBand(null);
        $this->assertSame('Unknown', $band['label']);
    }
}
