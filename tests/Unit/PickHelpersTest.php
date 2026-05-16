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
