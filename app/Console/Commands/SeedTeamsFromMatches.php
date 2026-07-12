<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\TeamCanonicalizer;
use Illuminate\Console\Command;

/**
 * One-shot: walk every distinct team name that has appeared in `matches`
 * and register it in `teams` / `team_aliases`. Idempotent — TeamCanonicalizer
 * skips already-registered aliases.
 *
 * Aliases created this way are marked reviewed=false; the admin queue is
 * where duplicates like "Man Utd" vs "Manchester United" get merged.
 */
class SeedTeamsFromMatches extends Command
{
    protected $signature   = 'teams:seed';
    protected $description = 'Backfill teams / team_aliases from distinct home_team / away_team values in matches.';

    public function handle(TeamCanonicalizer $canon): int
    {
        $names = collect()
            ->concat(FootballMatch::query()->distinct()->pluck('home_team'))
            ->concat(FootballMatch::query()->distinct()->pluck('away_team'))
            ->filter()
            ->unique()
            ->values();

        $registered = 0;
        foreach ($names as $name) {
            $before = $canon->pendingReviewCount();
            $canon->resolve((string) $name);
            $after = $canon->pendingReviewCount();
            if ($after > $before) $registered++;
        }

        $this->info("Registered {$registered} new team alias(es) from {$names->count()} distinct name(s).");
        return self::SUCCESS;
    }
}
