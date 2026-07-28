<?php

namespace App\Services\Football;

use App\Models\FootballMatch;
use App\Support\LeagueCoverage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Free fallback for match RESULTS when the API-Football quota is exhausted.
 *
 * Pulls finished scores from football-data.org (free tier: the top European
 * competitions) and writes them onto our pending fixtures, matched by team name
 * + date. The normal settler then grades outcomes — so predictions still resolve
 * the same day even when API-Football is down.
 */
class ResultsFallbackService
{
    private const NON_FINAL = ['FT', 'AET', 'PEN', 'CANC', 'PST', 'ABD', 'AWD', 'WO'];

    /** @return array{configured:bool, pending?:int, results?:int, updated?:int, error?:mixed} */
    public function settlePending(int $days = 3): array
    {
        if (blank(config('services.football_data.key')) && blank(config('services.thesportsdb.key'))) {
            return ['configured' => false, 'updated' => 0];
        }

        $tz = config('app.timezone');

        // Fixtures that should be finished by wall-clock but aren't final yet.
        // TOP PRIORITY: matches we actually predicted (so their prediction can be
        // graded) — always included, even outside the usual covered set. We also
        // settle other covered fixtures, but predicted ones are processed first.
        $pending = FootballMatch::query()
            ->with('prediction')
            ->where('match_time', '<', now()->subMinutes(150))
            ->where('match_time', '>=', now()->subDays($days + 1))
            ->whereNotIn('status', self::NON_FINAL)
            ->where(fn ($q) => $q
                ->whereHas('prediction')
                ->orWhere(fn ($w) => LeagueCoverage::scopeCovered($w)))
            ->get()
            ->sortByDesc(fn (FootballMatch $m) => $m->prediction ? 1 : 0)
            ->values();

        if ($pending->isEmpty()) {
            return ['configured' => true, 'pending' => 0, 'predicted' => 0, 'updated' => 0, 'predicted_updated' => 0,
                'fd_rows' => null, 'tsdb_rows' => null];
        }

        $predicted = $pending->filter(fn (FootballMatch $m) => (bool) $m->prediction)->count();
        $unsettled = $pending->all();
        $updated = 0;
        $predictedUpdated = 0;

        // Source 1: football-data.org (top competitions). null = not configured.
        $fd = $this->fetchFootballData($days, $tz);
        $fdRows = $fd === null ? null : count($fd);
        if ($fd !== null) {
            [$unsettled, $u, $pu] = $this->apply($unsettled, $fd, $tz);
            $updated += $u; $predictedUpdated += $pu;
        }

        // Source 2: TheSportsDB (broad) — always checked for whatever is STILL
        // unsettled, so predicted matches football-data doesn't carry get graded.
        $tsdbRows = null;
        if (! empty($unsettled)) {
            $tsdb = $this->fetchTheSportsDb($days, $tz);
            $tsdbRows = $tsdb === null ? null : count($tsdb);
            if ($tsdb !== null) {
                [$unsettled, $u, $pu] = $this->apply($unsettled, $tsdb, $tz);
                $updated += $u; $predictedUpdated += $pu;
            }
        }

        if ($updated > 0) {
            Log::info("ResultsFallbackService: filled {$updated} result(s) ({$predictedUpdated} predicted) from free fallback sources.");
        }

        return [
            'configured'        => true,
            'pending'           => $pending->count(),
            'predicted'         => $predicted,
            'updated'           => $updated,
            'predicted_updated' => $predictedUpdated,
            'fd_rows'           => $fdRows,   // null = football-data key not set
            'tsdb_rows'         => $tsdbRows, // null = not reached / key not set
        ];
    }

    /**
     * Apply a result index to the unsettled matches. Returns [remaining, updated,
     * predictedUpdated] so the next source only re-checks what's still open.
     *
     * @param  array<int, FootballMatch>  $matches
     * @return array{0: array<int, FootballMatch>, 1: int, 2: int}
     */
    private function apply(array $matches, array $index, string $tz): array
    {
        $remaining = [];
        $updated = 0;
        $predictedUpdated = 0;

        foreach ($matches as $match) {
            $fixtureDate = $match->match_time?->timezone($tz)->toDateString();
            $result = $this->lookup($index, $match->home_team, $match->away_team, $fixtureDate);
            if ($result === null) {
                $remaining[] = $match;
                continue;
            }

            $match->update([
                'home_score'    => $result['home'],
                'away_score'    => $result['away'],
                'home_score_ht' => $result['ht_home'],
                'away_score_ht' => $result['ht_away'],
                'status'        => 'FT',
            ]);
            $updated++;
            if ($match->prediction) {
                $predictedUpdated++;
            }
        }

        return [$remaining, $updated, $predictedUpdated];
    }

    /**
     * Fetch FINISHED matches for the window, indexed by "home|away" (and by the
     * short-name / TLA variants) so we can match our free-form fixture names.
     *
     * @return array<string, array{home:int, away:int, ht_home:?int, ht_away:?int, date:string}>|null
     */
    private function fetchFootballData(int $days, string $tz): ?array
    {
        $key = config('services.football_data.key');
        if (blank($key)) {
            return null;
        }

        try {
            $resp = Http::withHeaders(['X-Auth-Token' => $key])
                ->timeout(30)
                ->get(rtrim(config('services.football_data.url'), '/') . '/matches', [
                    'dateFrom' => now($tz)->subDays($days)->toDateString(),
                    'dateTo'   => now($tz)->toDateString(),
                    'status'   => 'FINISHED',
                ]);
        } catch (\Throwable $e) {
            Log::warning('ResultsFallbackService: football-data.org request failed — ' . $e->getMessage());
            return null;
        }

        if ($resp->failed()) {
            Log::warning('ResultsFallbackService: football-data.org HTTP ' . $resp->status());
            return null;
        }

        $index = [];
        foreach ($resp->json('matches') ?? [] as $m) {
            $home = data_get($m, 'score.fullTime.home');
            $away = data_get($m, 'score.fullTime.away');
            if ($home === null || $away === null) {
                continue;
            }

            $row = [
                'home'    => (int) $home,
                'away'    => (int) $away,
                'ht_home' => $this->intOrNull(data_get($m, 'score.halfTime.home')),
                'ht_away' => $this->intOrNull(data_get($m, 'score.halfTime.away')),
                'date'    => substr((string) data_get($m, 'utcDate'), 0, 10),
            ];

            foreach (['name', 'shortName', 'tla'] as $hk) {
                foreach (['name', 'shortName', 'tla'] as $ak) {
                    $this->addToIndex($index, data_get($m, "homeTeam.$hk"), data_get($m, "awayTeam.$ak"), $row);
                }
            }
        }

        return $index;
    }

    /**
     * TheSportsDB "events on a day" — one call per date in the window covers a
     * huge range of leagues football-data doesn't. Free test key works.
     */
    private function fetchTheSportsDb(int $days, string $tz): ?array
    {
        $key = config('services.thesportsdb.key');
        if (blank($key)) {
            return null;
        }

        $base = rtrim(config('services.thesportsdb.url'), '/') . '/' . $key . '/eventsday.php';
        $index = [];

        for ($i = 0; $i <= $days; $i++) {
            $date = now($tz)->subDays($i)->toDateString();
            try {
                $resp = Http::timeout(30)->get($base, ['d' => $date, 's' => 'Soccer']);
            } catch (\Throwable $e) {
                Log::warning('ResultsFallbackService: TheSportsDB request failed — ' . $e->getMessage());
                continue;
            }
            if ($resp->failed()) {
                continue;
            }

            foreach ($resp->json('events') ?? [] as $e) {
                $home = data_get($e, 'intHomeScore');
                $away = data_get($e, 'intAwayScore');
                if ($home === null || $away === null || $home === '' || $away === '') {
                    continue; // not finished / no score yet
                }

                $row = [
                    'home'    => (int) $home,
                    'away'    => (int) $away,
                    'ht_home' => null,
                    'ht_away' => null,
                    'date'    => (string) data_get($e, 'dateEvent', $date),
                ];
                $this->addToIndex($index, data_get($e, 'strHomeTeam'), data_get($e, 'strAwayTeam'), $row);
            }
        }

        return $index;
    }

    /** Index a result under the normalised "home|away" key (if both are present). */
    private function addToIndex(array &$index, ?string $home, ?string $away, array $row): void
    {
        $h = $this->norm((string) $home);
        $a = $this->norm((string) $away);
        if ($h !== '' && $a !== '') {
            $index["$h|$a"] = $row;
        }
    }

    /** Find a result for a fixture, requiring the date to be within a day. */
    private function lookup(array $index, string $home, string $away, ?string $fixtureDate): ?array
    {
        $row = $index[$this->norm($home) . '|' . $this->norm($away)] ?? null;
        if ($row === null) {
            return null;
        }
        if ($fixtureDate && $row['date'] !== '' && abs(strtotime($row['date']) - strtotime($fixtureDate)) > 86400) {
            return null; // a different meeting of the same two teams
        }
        return $row;
    }

    /**
     * Normalise a club name for cross-provider matching: lowercase, strip
     * accents/punctuation, and drop common club tokens (FC, CF, AFC, SC…) so
     * "Manchester United FC" and "Manchester United" collapse to the same key.
     */
    private function norm(string $name): string
    {
        $s = Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9 ]/', ' ')->squish();
        $stop = ['fc', 'cf', 'afc', 'sc', 'ac', 'ss', 'ssc', 'us', 'rc', 'cd', 'ca', 'ud', 'sd', 'cp',
            'club', 'de', 'the', 'football', 'calcio', 'if', 'bk', 'fk', 'sk'];
        $words = array_filter(explode(' ', (string) $s), fn ($w) => $w !== '' && ! in_array($w, $stop, true));
        return implode(' ', $words);
    }

    private function intOrNull(mixed $v): ?int
    {
        return $v === null ? null : (int) $v;
    }
}
