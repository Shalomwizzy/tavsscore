<?php

namespace Tests\Unit;

use App\Models\FootballMatch;
use App\Services\PredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PredictionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PredictionService::class);
    }

    // ── Poisson internals (via reflection) ───────────────────────

    private function callPoisson(float $homeXg, float $awayXg): array
    {
        $ref = new \ReflectionMethod($this->service, 'poissonProbabilities');
        $ref->setAccessible(true);
        return $ref->invoke($this->service, $homeXg, $awayXg);
    }

    public function test_poisson_probabilities_sum_to_100(): void
    {
        $probs = $this->callPoisson(1.5, 1.0);

        $total = $probs['home_win'] + $probs['draw'] + $probs['away_win'];
        $this->assertEqualsWithDelta(100.0, $total, 0.5);
    }

    public function test_poisson_high_home_xg_favours_home_win(): void
    {
        $probs = $this->callPoisson(3.0, 0.5);
        $this->assertGreaterThan($probs['away_win'], $probs['home_win']);
    }

    public function test_poisson_equal_xg_produces_competitive_probabilities(): void
    {
        $probs = $this->callPoisson(1.2, 1.2);
        // Home and away should be very close (home advantage gives small edge)
        $this->assertEqualsWithDelta($probs['home_win'], $probs['away_win'], 10.0);
    }

    public function test_poisson_contains_expected_keys(): void
    {
        $probs = $this->callPoisson(1.5, 1.0);

        foreach (['home_win','draw','away_win','over_15','over_25','over_35','btts',
                  'home_clean_sheet','away_clean_sheet'] as $key) {
            $this->assertArrayHasKey($key, $probs, "Missing key: {$key}");
        }
    }

    // ── confidenceForOutcome() ────────────────────────────────────
    // Actual signature: (outcome, hw, d, aw, over15, over25, over35, btts, homeClean, awayClean)

    private function callConfidence(string $outcome, array $p): int
    {
        $ref = new \ReflectionMethod($this->service, 'confidenceForOutcome');
        $ref->setAccessible(true);
        return $ref->invoke(
            $this->service,
            $outcome,
            (float) $p['home_win'],
            (float) $p['draw'],
            (float) $p['away_win'],
            (float) $p['over_15'],
            (float) $p['over_25'],
            (float) $p['over_35'],
            (float) $p['btts'],
            (float) ($p['home_clean_sheet'] ?? 0.0),
            (float) ($p['away_clean_sheet'] ?? 0.0),
        );
    }

    public function test_confidence_home_win_is_proportional_to_prob(): void
    {
        $probs = $this->callPoisson(2.5, 0.5);
        $conf  = $this->callConfidence('Home Win', $probs);

        $this->assertGreaterThan(60, $conf);
        $this->assertLessThanOrEqual(97, $conf);
    }

    public function test_confidence_btts_reflects_btts_probability(): void
    {
        $highBtts = $this->callPoisson(2.0, 2.0);
        $lowBtts  = $this->callPoisson(0.5, 0.5);

        $confHigh = $this->callConfidence('Both Teams Score (GG)', $highBtts);
        $confLow  = $this->callConfidence('Both Teams Score (GG)', $lowBtts);

        $this->assertGreaterThan($confLow, $confHigh);
    }

    public function test_confidence_over_25_reflects_total_goals(): void
    {
        $highGoals = $this->callPoisson(2.5, 2.0);
        $lowGoals  = $this->callPoisson(0.4, 0.3);

        $confHigh = $this->callConfidence('Over 2.5 Goals', $highGoals);
        $confLow  = $this->callConfidence('Over 2.5 Goals', $lowGoals);

        $this->assertGreaterThan($confLow, $confHigh);
    }

    public function test_confidence_is_very_high_for_extreme_home_xg(): void
    {
        $probs = $this->callPoisson(10.0, 0.1);
        $conf  = $this->callConfidence('Home Win', $probs);
        // Extreme xG advantage should produce very high confidence
        $this->assertGreaterThanOrEqual(90, $conf);
    }

    public function test_confidence_double_chance_combines_probabilities(): void
    {
        $probs    = $this->callPoisson(1.5, 1.0);
        $homeOnly = $this->callConfidence('Home Win', $probs);
        $dc       = $this->callConfidence('Home or Draw (1X)', $probs);

        // 1X must be higher than Home Win alone
        $this->assertGreaterThan($homeOnly, $dc);
    }

    // ── marketCategory() ─────────────────────────────────────────

    private function callCategory(string $market): string
    {
        $ref = new \ReflectionMethod($this->service, 'marketCategory');
        $ref->setAccessible(true);
        return $ref->invoke($this->service, $market);
    }

    public function test_market_category_1x2_markets(): void
    {
        $this->assertEquals('1x2', $this->callCategory('Home Win'));
        $this->assertEquals('1x2', $this->callCategory('Draw'));
        $this->assertEquals('1x2', $this->callCategory('Away Win'));
    }

    public function test_market_category_btts(): void
    {
        $this->assertEquals('btts', $this->callCategory('Both Teams Score (GG)'));
        $this->assertEquals('btts', $this->callCategory('No Both Teams Score (NG)'));
    }

    public function test_market_category_goals(): void
    {
        $this->assertEquals('goals', $this->callCategory('Over 2.5 Goals'));
        $this->assertEquals('goals', $this->callCategory('Over 1.5 Goals'));
        $this->assertEquals('goals', $this->callCategory('Under 2.5 Goals'));
    }

    public function test_market_category_double_chance(): void
    {
        $this->assertEquals('double_chance', $this->callCategory('Home or Draw (1X)'));
        $this->assertEquals('double_chance', $this->callCategory('Draw or Away (X2)'));
        $this->assertEquals('double_chance', $this->callCategory('Home or Away (12)'));
    }

    public function test_market_category_dnb(): void
    {
        $this->assertEquals('dnb', $this->callCategory('Draw No Bet - Home'));
        $this->assertEquals('dnb', $this->callCategory('Draw No Bet - Away'));
    }

    public function test_market_category_clean_sheet(): void
    {
        $this->assertEquals('clean_sheet', $this->callCategory('Home Clean Sheet'));
        $this->assertEquals('clean_sheet', $this->callCategory('Away Clean Sheet'));
        $this->assertEquals('clean_sheet', $this->callCategory('Home Win to Nil'));
    }

    public function test_market_category_team_score(): void
    {
        $this->assertEquals('team_score', $this->callCategory('Home Team NOT to Score'));
        $this->assertEquals('team_score', $this->callCategory('Away Team to Score'));
    }

    public function test_market_category_corners(): void
    {
        $this->assertEquals('corners', $this->callCategory('Over 9.5 Corners'));
    }

    public function test_market_category_handicap(): void
    {
        $this->assertEquals('handicap', $this->callCategory('Asian Handicap -1'));
        $this->assertEquals('handicap', $this->callCategory('Home -1 Handicap'));
    }

    public function test_market_category_halftime(): void
    {
        $this->assertEquals('halftime', $this->callCategory('Home Win HT'));
        $this->assertEquals('halftime', $this->callCategory('Half-time Draw'));
    }

    public function test_market_category_unknown_returns_other(): void
    {
        $this->assertEquals('other', $this->callCategory('Some unknown market'));
    }

    // ── extendedTeamStats() ─────────────────────────────────────

    public function test_extended_team_stats_returns_zeros_with_no_history(): void
    {
        $stats = $this->service->extendedTeamStats('NoTeamFC', now(), 0);

        $this->assertEquals(0, $stats['matches_played']);
        $this->assertEquals(0, $stats['wins']);
        $this->assertIsArray($stats['form_detailed']);
    }

    public function test_extended_team_stats_counts_correctly(): void
    {
        // 3 wins, 1 draw, 1 loss for Arsenal
        $fixtures = [
            ['home' => 'Arsenal', 'away' => 'Chelsea',   'hs' => 2, 'as' => 0],
            ['home' => 'Arsenal', 'away' => 'Liverpool',  'hs' => 1, 'as' => 1],
            ['home' => 'Arsenal', 'away' => 'Man City',   'hs' => 0, 'as' => 3],
            ['home' => 'Spurs',   'away' => 'Arsenal',   'hs' => 0, 'as' => 2],
            ['home' => 'Everton', 'away' => 'Arsenal',   'hs' => 1, 'as' => 2],
        ];

        foreach ($fixtures as $i => $f) {
            FootballMatch::create([
                'api_id'       => 500 + $i,
                'league'       => 'PL',
                'league_country' => 'England',
                'home_team'    => $f['home'],
                'away_team'    => $f['away'],
                'home_score'   => $f['hs'],
                'away_score'   => $f['as'],
                'status'       => 'FT',
                'match_time'   => now()->subDays($i + 1),
            ]);
        }

        $stats = $this->service->extendedTeamStats('Arsenal', now(), 0);

        $this->assertEquals(5, $stats['matches_played']);
        $this->assertEquals(3, $stats['wins']);
        $this->assertEquals(1, $stats['draws']);
        $this->assertEquals(1, $stats['losses']);
    }

    public function test_extended_team_stats_clean_sheets(): void
    {
        FootballMatch::create([
            'api_id' => 600, 'league' => 'PL', 'league_country' => 'England',
            'home_team' => 'Arsenal', 'away_team' => 'Wolves',
            'home_score' => 3, 'away_score' => 0,
            'status' => 'FT', 'match_time' => now()->subDay(),
        ]);
        FootballMatch::create([
            'api_id' => 601, 'league' => 'PL', 'league_country' => 'England',
            'home_team' => 'Arsenal', 'away_team' => 'Brighton',
            'home_score' => 1, 'away_score' => 1,
            'status' => 'FT', 'match_time' => now()->subDays(2),
        ]);

        $stats = $this->service->extendedTeamStats('Arsenal', now(), 0);

        $this->assertEquals(1, $stats['clean_sheets']);
        $this->assertEquals(0, $stats['failed_to_score']);
    }

    // ── headToHead() ─────────────────────────────────────────────

    public function test_head_to_head_empty_when_no_meetings(): void
    {
        $h2h = $this->service->headToHead('Team A', 'Team B', now(), 0);

        $this->assertEquals(0, $h2h['total']);
        $this->assertEmpty($h2h['results']);
    }

    public function test_head_to_head_counts_home_wins_correctly(): void
    {
        // Home team wins both encounters
        FootballMatch::create([
            'api_id' => 700, 'league' => 'PL', 'league_country' => 'England',
            'home_team' => 'TeamA', 'away_team' => 'TeamB',
            'home_score' => 2, 'away_score' => 0,
            'status' => 'FT', 'match_time' => now()->subDays(30),
        ]);
        FootballMatch::create([
            'api_id' => 701, 'league' => 'PL', 'league_country' => 'England',
            'home_team' => 'TeamB', 'away_team' => 'TeamA',
            'home_score' => 0, 'away_score' => 1,
            'status' => 'FT', 'match_time' => now()->subDays(60),
        ]);

        $h2h = $this->service->headToHead('TeamA', 'TeamB', now(), 0);

        $this->assertEquals(2, $h2h['total']);
        $this->assertEquals(2, $h2h['home_wins']); // TeamA won both
        $this->assertEquals(0, $h2h['away_wins']);
    }

    public function test_head_to_head_capped_at_6_results(): void
    {
        for ($i = 0; $i < 10; $i++) {
            FootballMatch::create([
                'api_id'       => 800 + $i,
                'league'       => 'PL',
                'league_country' => 'England',
                'home_team'    => 'ClubA',
                'away_team'    => 'ClubB',
                'home_score'   => 1,
                'away_score'   => 0,
                'status'       => 'FT',
                'match_time'   => now()->subDays($i + 1),
            ]);
        }

        $h2h = $this->service->headToHead('ClubA', 'ClubB', now(), 0);

        $this->assertCount(6, $h2h['results']);
    }
}
