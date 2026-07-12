<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\TeamAlias;
use App\Services\FixtureIntegrityService;
use App\Services\TeamCanonicalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixtureIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function match(array $extra = []): FootballMatch
    {
        return FootballMatch::create(array_merge([
            'api_id'         => rand(10000, 99999),
            'home_team'      => 'Team A',
            'away_team'      => 'Team B',
            'league'         => 'Test League',
            'league_id'      => 42,
            'league_country' => 'Nigeria',
            'status'         => 'NS',
            'match_time'     => now()->addHours(2),
        ], $extra));
    }

    // ── Canonicalization ─────────────────────────────────────────

    public function test_canonicalizer_creates_team_and_alias_on_first_sight(): void
    {
        $canon = app(TeamCanonicalizer::class);
        $team  = $canon->resolve('Manchester United');

        $this->assertSame('Manchester United', $team->canonical_name);
        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseHas('team_aliases', [
            'team_id'  => $team->id,
            'alias'    => 'Manchester United',
            'provider' => TeamAlias::PROVIDER_API_FOOTBALL,
            'reviewed' => false,
        ]);
    }

    public function test_canonicalizer_is_idempotent(): void
    {
        $canon = app(TeamCanonicalizer::class);
        $a = $canon->resolve('Arsenal');
        $b = $canon->resolve('Arsenal');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Team::count());
        $this->assertSame(1, TeamAlias::count());
    }

    public function test_pending_review_count_reflects_unreviewed_aliases(): void
    {
        $canon = app(TeamCanonicalizer::class);
        $canon->resolve('Barcelona');
        $canon->resolve('Real Madrid');

        $this->assertSame(2, $canon->pendingReviewCount());

        TeamAlias::first()->update(['reviewed' => true]);
        $this->assertSame(1, $canon->pendingReviewCount());
    }

    // ── Duplicate fixture detection ─────────────────────────────

    public function test_duplicate_fixture_within_24h_is_flagged(): void
    {
        $service = app(FixtureIntegrityService::class);
        $this->match(['api_id' => 111, 'match_time' => now()->addHour()]);
        $newer = $this->match(['api_id' => 222, 'match_time' => now()->addHours(6)]);

        $flags = $service->evaluate($newer);

        $this->assertContains(FixtureIntegrityService::FLAG_DUPLICATE, $flags);
    }

    public function test_same_teams_more_than_24h_apart_is_not_duplicate(): void
    {
        $service = app(FixtureIntegrityService::class);
        $this->match(['api_id' => 111, 'match_time' => now()->addHour()]);
        $newer = $this->match(['api_id' => 222, 'match_time' => now()->addDays(3)]);

        $flags = $service->evaluate($newer);

        $this->assertNotContains(FixtureIntegrityService::FLAG_DUPLICATE, $flags);
    }

    // ── Back-to-back detection ──────────────────────────────────

    public function test_back_to_back_flagged_when_team_plays_twice_within_48h(): void
    {
        $service = app(FixtureIntegrityService::class);
        $this->match([
            'api_id'     => 111,
            'home_team'  => 'Chelsea',
            'away_team'  => 'Spurs',
            'match_time' => now()->addHours(1),
        ]);
        $newer = $this->match([
            'api_id'     => 222,
            'home_team'  => 'Chelsea',
            'away_team'  => 'City',
            'match_time' => now()->addHours(30),
        ]);

        $flags = $service->evaluate($newer);

        $this->assertContains(FixtureIntegrityService::FLAG_BACK_TO_BACK, $flags);
    }

    // ── Blowout detection + hold ────────────────────────────────

    public function test_blowout_scoreline_holds_match_for_review(): void
    {
        $service = app(FixtureIntegrityService::class);
        $match = $this->match([
            'status'     => 'FT',
            'home_score' => 9,
            'away_score' => 0,
            'match_time' => now()->subHour(),
        ]);

        $flags = $service->evaluate($match);

        $this->assertContains(FixtureIntegrityService::FLAG_BLOWOUT, $flags);
        $this->assertTrue($match->fresh()->held_for_review);
    }

    public function test_regular_scoreline_is_not_held(): void
    {
        $service = app(FixtureIntegrityService::class);
        $match = $this->match([
            'status'     => 'FT',
            'home_score' => 3,
            'away_score' => 2,
            'match_time' => now()->subHour(),
        ]);

        $flags = $service->evaluate($match);

        $this->assertNotContains(FixtureIntegrityService::FLAG_BLOWOUT, $flags);
        $this->assertFalse($match->fresh()->held_for_review);
    }

    // ── Result-before-kickoff detection ─────────────────────────

    public function test_result_before_kickoff_is_flagged(): void
    {
        $service = app(FixtureIntegrityService::class);
        $match = $this->match([
            'status'     => 'FT',
            'home_score' => 1,
            'away_score' => 1,
            'match_time' => now()->addHours(3), // future kickoff, already has result
        ]);

        $flags = $service->evaluate($match);

        $this->assertContains(FixtureIntegrityService::FLAG_RESULT_BEFORE_KICKOFF, $flags);
    }
}
