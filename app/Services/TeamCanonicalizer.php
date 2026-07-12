<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamAlias;
use Illuminate\Support\Facades\Log;

/**
 * Maps provider-supplied team name strings to canonical Team records.
 *
 * Permissive: unknown names auto-register as unreviewed aliases pointing at
 * a fresh canonical Team, warned via Log::info so the admin queue picks
 * them up. This avoids the failure mode where a new team name silently
 * blocks fixture ingestion — silent gaps in match data corrupt every
 * downstream model far more than a duplicate canonical team ever would.
 *
 * Downstream code should call `resolve()` for the tracking side effects;
 * the caller can keep using the original provider string as the display name
 * (matches.home_team) until a proper migration replaces it with the FK.
 */
class TeamCanonicalizer
{
    /**
     * Look up (or create) the canonical Team for a provider-supplied alias.
     * Returns the Team; a fresh alias row is written on first sight.
     */
    public function resolve(string $alias, string $provider = TeamAlias::PROVIDER_API_FOOTBALL): Team
    {
        $alias = trim($alias);

        $existing = TeamAlias::where('alias', $alias)->where('provider', $provider)->first();
        if ($existing) {
            return $existing->team;
        }

        // First sighting — create canonical team + unreviewed alias in one go.
        $team = Team::create(['canonical_name' => $alias]);
        TeamAlias::create([
            'team_id'  => $team->id,
            'alias'    => $alias,
            'provider' => $provider,
            'reviewed' => false,
        ]);

        Log::info('TeamCanonicalizer: new unmapped team registered', [
            'alias'    => $alias,
            'provider' => $provider,
            'team_id'  => $team->id,
        ]);

        return $team;
    }

    /**
     * Count of aliases awaiting human review. Surfaced on the admin dashboard
     * so operators know when to reconcile duplicates.
     */
    public function pendingReviewCount(): int
    {
        return TeamAlias::where('reviewed', false)->count();
    }
}
