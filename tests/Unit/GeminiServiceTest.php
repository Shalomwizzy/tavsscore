<?php

namespace Tests\Unit;

use App\Services\GeminiService;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    private GeminiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeminiService();
    }

    // ── isConfigured() ────────────────────────────────────────────

    public function test_is_configured_returns_false_without_key(): void
    {
        config(['services.gemini.key' => null]);
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_is_configured_returns_true_with_key(): void
    {
        config(['services.gemini.key' => 'fake-key-for-test']);
        $this->assertTrue($this->service->isConfigured());
    }

    // ── buildStatsBlock() ─────────────────────────────────────────

    private function callBuildStatsBlock(string $team, array $s): string
    {
        $ref = new \ReflectionMethod($this->service, 'buildStatsBlock');
        $ref->setAccessible(true);
        return $ref->invoke($this->service, $team, $s);
    }

    public function test_build_stats_block_handles_empty_stats(): void
    {
        $result = $this->callBuildStatsBlock('Arsenal', []);
        $this->assertStringContainsString('Arsenal', $result);
        $this->assertStringContainsString('No recent data', $result);
    }

    public function test_build_stats_block_contains_team_name(): void
    {
        $stats = [
            'matches_played' => 5,
            'wins' => 3, 'draws' => 1, 'losses' => 1,
            'goals_scored' => 8, 'goals_conceded' => 4,
            'gpg' => 1.6, 'cpg' => 0.8,
            'clean_sheets' => 2, 'failed_to_score' => 1,
            'btts_count' => 2, 'over25_count' => 3,
            'ht_scored' => 4, 'ht_conceded' => 2,
            'ht_gpg' => 0.8, 'ht_cpg' => 0.4,
            'home_matches' => 3, 'home_scored' => 5, 'home_conceded' => 2,
            'away_matches' => 2, 'away_scored' => 3, 'away_conceded' => 2,
            'form_detailed' => ['W(2-0) H 10 May vs Chelsea', 'L(0-1) A 05 May vs Liverpool'],
        ];

        $result = $this->callBuildStatsBlock('Arsenal', $stats);

        $this->assertStringContainsString('Arsenal', $result);
        $this->assertStringContainsString('3W', $result);
        $this->assertStringContainsString('1D', $result);
        $this->assertStringContainsString('1L', $result);
    }

    public function test_build_stats_block_shows_rates(): void
    {
        $stats = [
            'matches_played' => 10,
            'wins' => 6, 'draws' => 2, 'losses' => 2,
            'goals_scored' => 15, 'goals_conceded' => 8,
            'gpg' => 1.5, 'cpg' => 0.8,
            'clean_sheets' => 4, 'failed_to_score' => 1,
            'btts_count' => 6, 'over25_count' => 7,
            'ht_scored' => 7, 'ht_conceded' => 4,
            'ht_gpg' => 0.7, 'ht_cpg' => 0.4,
            'home_matches' => 5, 'home_scored' => 9, 'home_conceded' => 3,
            'away_matches' => 5, 'away_scored' => 6, 'away_conceded' => 5,
            'form_detailed' => [],
        ];

        $result = $this->callBuildStatsBlock('Bayern Munich', $stats);

        // 4/10 clean sheets = 40%
        $this->assertStringContainsString('40%', $result);
        // 6/10 BTTS = 60%
        $this->assertStringContainsString('60%', $result);
    }

    // ── buildH2HBlock() ──────────────────────────────────────────

    private function callBuildH2HBlock(array $h2h): string
    {
        $ref = new \ReflectionMethod($this->service, 'buildH2HBlock');
        $ref->setAccessible(true);
        return $ref->invoke($this->service, $h2h);
    }

    public function test_build_h2h_block_handles_empty(): void
    {
        $result = $this->callBuildH2HBlock(['results' => []]);
        $this->assertStringContainsString('No recent meetings', $result);
    }

    public function test_build_h2h_block_shows_meetings(): void
    {
        $h2h = [
            'results'   => [
                '10 Jan 2025 (at Arsenal): Arsenal 2-1 Chelsea → Arsenal won',
                '05 May 2024 (at Chelsea): Arsenal 0-0 Chelsea → Draw',
            ],
            'home_wins' => 1,
            'draws'     => 1,
            'away_wins' => 0,
            'total'     => 2,
        ];

        $result = $this->callBuildH2HBlock($h2h);

        $this->assertStringContainsString('HEAD-TO-HEAD', $result);
        $this->assertStringContainsString('Arsenal won', $result);
        $this->assertStringContainsString('1W', $result);
    }
}
