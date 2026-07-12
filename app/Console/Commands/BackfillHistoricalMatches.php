<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\FixtureIntegrityService;
use App\Services\FootballService;
use App\Services\TeamCanonicalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Pull historical fixtures per league × season into the matches table so
 * Dixon-Coles has real training data. API-Football returns a full ~380-match
 * season in a single `/fixtures?league={id}&season={year}` call, so the
 * quota cost is minimal (~2 calls per league × ~10 leagues = 20 requests).
 *
 * Runs the same TeamCanonicalizer + FixtureIntegrityService hooks the live
 * ingestion path uses, so backfilled matches enter the system with the same
 * hygiene as fresh fixtures.
 *
 * Idempotent — `updateOrCreate` keyed by api_id.
 */
class BackfillHistoricalMatches extends Command
{
    protected $signature   = 'matches:backfill
                              {--leagues= : Comma-separated league IDs (default: config leagues.season_priority)}
                              {--seasons=2024,2025 : Comma-separated season start-years (2025 = 2025-26 season)}';
    protected $description = 'Backfill historical fixtures per league × season for Dixon-Coles training.';

    public function handle(
        FootballService $football,
        TeamCanonicalizer $canon,
        FixtureIntegrityService $integrity,
    ): int {
        if (Cache::get('api_football_quota_exhausted')) {
            $this->error('API-Football quota exhausted — try again after reset.');
            return self::FAILURE;
        }

        $leagues = $this->parseIntCsv($this->option('leagues'))
            ?: (array) config('leagues.season_priority', []);
        $seasons = $this->parseIntCsv($this->option('seasons')) ?: [2024, 2025];

        if (empty($leagues)) {
            $this->error('No leagues specified and config leagues.season_priority is empty.');
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Backfilling %d league(s) × %d season(s) = %d requests.',
            count($leagues), count($seasons), count($leagues) * count($seasons),
        ));

        $totalIngested = 0;
        $totalHeld     = 0;
        $totalFlagged  = 0;

        foreach ($leagues as $leagueId) {
            foreach ($seasons as $season) {
                $label = "league {$leagueId}, season {$season}-" . ($season + 1);
                $this->line("→ {$label}…");

                try {
                    $fixtures = $football->fetchFixturesByLeagueSeason($leagueId, $season);
                } catch (\Throwable $e) {
                    $this->warn("  ✗ {$label}: {$e->getMessage()}");
                    continue;
                }

                if (empty($fixtures)) {
                    $this->warn("  ⚠ {$label}: no fixtures returned (quota / league not covered by provider?).");
                    continue;
                }

                $ingested = 0;
                $held     = 0;
                $flagged  = 0;

                foreach ($fixtures as $fx) {
                    $canon->resolve($fx['home_team']);
                    $canon->resolve($fx['away_team']);

                    $match = FootballMatch::query()->updateOrCreate(
                        ['api_id' => $fx['api_id']],
                        $this->matchData($fx),
                    );

                    $flags = $integrity->evaluate($match);
                    if (! empty($flags))            $flagged++;
                    if ($match->fresh()->held_for_review) $held++;
                    $ingested++;
                }

                $this->info("  ✓ {$label}: {$ingested} ingested ({$flagged} flagged, {$held} held).");
                $totalIngested += $ingested;
                $totalHeld     += $held;
                $totalFlagged  += $flagged;

                // Politeness pause — API-Football's fair-use rate is 300 req/min on
                // most plans; 500ms between calls keeps us well under.
                usleep(500_000);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Backfill complete: %d matches ingested across %d league × season combinations. Flagged: %d. Held: %d.',
            $totalIngested, count($leagues) * count($seasons), $totalFlagged, $totalHeld,
        ));
        $this->info('Next: run `php artisan coverage:report --days=730` to verify per-league coverage.');

        return self::SUCCESS;
    }

    private function parseIntCsv(?string $csv): array
    {
        if (blank($csv)) return [];
        return collect(explode(',', $csv))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->values()
            ->all();
    }

    private function matchData(array $fx): array
    {
        return [
            'league'         => $fx['league'],
            'league_id'      => $fx['league_id'],
            'league_country' => $fx['league_country'],
            'home_team'      => $fx['home_team'],
            'away_team'      => $fx['away_team'],
            'home_score'     => $fx['home_score'],
            'away_score'     => $fx['away_score'],
            'home_score_ht'  => $fx['home_score_ht'] ?? null,
            'away_score_ht'  => $fx['away_score_ht'] ?? null,
            'status'         => $fx['status'],
            'elapsed'        => $fx['elapsed'],
            'match_time'     => $fx['match_time'],
        ];
    }
}
