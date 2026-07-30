<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\PublicationQualityRevalidator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationQualityRevalidatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep the fixture safely within the same Lagos calendar day. Without
        // a fixed clock this test crosses midnight after 21:00 WAT and the
        // revalidator correctly excludes it from its "today" query.
        Carbon::setTestNow(Carbon::parse('2026-07-29 12:00:00', 'Africa/Lagos'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-29 12:00:00', 'Africa/Lagos'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_withdraws_a_published_pick_when_late_data_is_stale(): void
    {
        $match = FootballMatch::create([
            'api_id' => 881204,
            'home_team' => 'Home FC',
            'away_team' => 'Away FC',
            'league' => 'Test League',
            'league_id' => 999,
            'league_country' => 'Test',
            'status' => 'NS',
            'match_time' => now('Africa/Lagos')->addHours(3),
            'fixture_data_checked_at' => now()->subMinutes(95),
            'intel_checked_at' => now()->subHours(9),
        ]);
        $prediction = Prediction::create([
            'match_id' => $match->id,
            'home_win_prob' => 75,
            'draw_prob' => 15,
            'away_win_prob' => 10,
            'predicted_outcome' => 'Home Win',
            'confidence' => 75,
            'analysis' => 'Verified pre-match analysis.',
            'is_daily_pick' => true,
            'pick_rank' => 1,
        ]);

        $result = app(PublicationQualityRevalidator::class)->revalidateToday();

        $this->assertCount(1, $result['withdrawn']);
        $this->assertFalse($prediction->fresh()->is_daily_pick);
    }
}
