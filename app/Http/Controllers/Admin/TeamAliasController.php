<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamAlias;
use App\Services\DixonColes\TeamNameNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin queue for reviewing unmapped team aliases (Phase 1.5.2).
 *
 * TeamCanonicalizer creates a fresh alias every time a new team-name string
 * arrives from the provider. This UI lets an operator:
 *   - see all unreviewed aliases with likely-duplicate suggestions
 *   - merge a duplicate into an existing canonical team
 *   - mark a legitimate new team as reviewed (keeps its own canonical row)
 *
 * Suggestions use TeamNameNormalizer::key() to surface aliases whose
 * normalised form matches an existing team — that's where the real
 * duplicates live (Bayern München / Munich style).
 */
class TeamAliasController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'pending');

        $q = TeamAlias::with('team')->orderBy('first_seen_at', 'desc');
        if ($filter === 'pending') $q->where('reviewed', false);
        if ($filter === 'reviewed') $q->where('reviewed', true);

        $aliases = $q->paginate(self::PER_PAGE)->withQueryString();

        // Build suggestion map: alias_id -> [candidate Teams] whose canonical
        // name normalises to the same key as this alias.
        $suggestions = $this->buildSuggestions($aliases->items());

        $stats = [
            'pending'  => TeamAlias::where('reviewed', false)->count(),
            'reviewed' => TeamAlias::where('reviewed', true)->count(),
            'teams'    => Team::count(),
        ];

        return view('admin.team-aliases.index', compact('aliases', 'suggestions', 'stats', 'filter'));
    }

    /**
     * Merge the alias into another team (kills the source team + retargets
     * the alias). Used when the source team is a duplicate spelling.
     */
    public function merge(Request $request, TeamAlias $alias): RedirectResponse
    {
        $request->validate([
            'target_team_id' => 'required|integer|exists:teams,id',
        ]);
        $targetId = (int) $request->input('target_team_id');
        $sourceTeamId = $alias->team_id;

        DB::transaction(function () use ($alias, $targetId, $sourceTeamId) {
            // Retarget every alias for the source team to the merge target.
            TeamAlias::where('team_id', $sourceTeamId)->update(['team_id' => $targetId]);
            // Mark the newly-retargeted aliases reviewed.
            TeamAlias::where('team_id', $targetId)->where('reviewed', false)->update(['reviewed' => true]);
            // Drop the abandoned source team if no aliases still point at it
            // (updateOrCreate could have left it orphaned in edge cases).
            if ($sourceTeamId !== $targetId && ! TeamAlias::where('team_id', $sourceTeamId)->exists()) {
                Team::where('id', $sourceTeamId)->delete();
            }
        });

        return back()->with('success', "Alias merged into team #{$targetId}.");
    }

    /**
     * Mark the alias reviewed without changing its canonical team — used
     * when the alias is genuinely a new team, not a duplicate.
     */
    public function approve(TeamAlias $alias): RedirectResponse
    {
        $alias->update(['reviewed' => true]);
        return back()->with('success', "Alias \"{$alias->alias}\" approved as a distinct team.");
    }

    /**
     * Bulk approve — mark every alias whose normalised key doesn't collide
     * with any other team as reviewed. Fast path for the initial 877-alias
     * seed after teams:seed.
     */
    public function bulkApproveUnique(): RedirectResponse
    {
        $updated = 0;
        TeamAlias::where('reviewed', false)->chunk(200, function ($chunk) use (&$updated) {
            foreach ($chunk as $alias) {
                $key = TeamNameNormalizer::key($alias->alias);
                $collision = Team::where('id', '!=', $alias->team_id)
                    ->get(['id', 'canonical_name'])
                    ->contains(fn ($t) => TeamNameNormalizer::key($t->canonical_name) === $key);
                if (! $collision) {
                    $alias->update(['reviewed' => true]);
                    $updated++;
                }
            }
        });
        return back()->with('success', "Auto-approved {$updated} aliases with no likely duplicates.");
    }

    /**
     * @param  TeamAlias[]  $aliases
     * @return array<int, array<int, Team>>  keyed by alias id
     */
    private function buildSuggestions(array $aliases): array
    {
        $keyToTeams = [];
        foreach (Team::all(['id', 'canonical_name']) as $team) {
            $key = TeamNameNormalizer::key($team->canonical_name);
            $keyToTeams[$key][] = $team;
        }

        $out = [];
        foreach ($aliases as $alias) {
            $key = TeamNameNormalizer::key($alias->alias);
            $candidates = collect($keyToTeams[$key] ?? [])
                ->reject(fn ($t) => $t->id === $alias->team_id)
                ->values();
            if ($candidates->isNotEmpty()) {
                $out[$alias->id] = $candidates->all();
            }
        }
        return $out;
    }
}
