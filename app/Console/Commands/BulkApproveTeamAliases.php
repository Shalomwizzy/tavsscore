<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\TeamAlias;
use App\Services\DixonColes\TeamNameNormalizer;
use Illuminate\Console\Command;

/**
 * CLI twin of the admin "Bulk-approve non-colliding" button — approves every
 * pending alias whose normalised name doesn't collide with any other team.
 * The remaining (colliding) aliases are the real duplicates that need a
 * human decision in /admin/team-aliases.
 *
 * Safe and idempotent: only flips reviewed=false → true, never merges.
 */
class BulkApproveTeamAliases extends Command
{
    protected $signature   = 'teams:bulk-approve {--dry-run : Report counts without writing}';
    protected $description = 'Auto-approve pending team aliases with no likely-duplicate collision.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Build the normalised-key → team-ids index once.
        $keyToTeamIds = [];
        foreach (Team::all(['id', 'canonical_name']) as $team) {
            $keyToTeamIds[TeamNameNormalizer::key($team->canonical_name)][] = $team->id;
        }

        $approved  = 0;
        $colliding = 0;

        TeamAlias::where('reviewed', false)->chunkById(200, function ($chunk) use ($keyToTeamIds, $dryRun, &$approved, &$colliding) {
            foreach ($chunk as $alias) {
                $key       = TeamNameNormalizer::key($alias->alias);
                $others    = array_diff($keyToTeamIds[$key] ?? [], [$alias->team_id]);
                if (! empty($others)) {
                    $colliding++;
                    continue;
                }
                if (! $dryRun) {
                    $alias->update(['reviewed' => true]);
                }
                $approved++;
            }
        });

        $verb = $dryRun ? 'Would approve' : 'Approved';
        $this->info("{$verb} {$approved} alias(es). {$colliding} colliding alias(es) left for manual review at /admin/team-aliases.");
        return self::SUCCESS;
    }
}
