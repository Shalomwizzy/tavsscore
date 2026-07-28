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
    public function settlePending(int $days = 2): array
    {
        $key = config('services.football_data.key');
        if (blank($key)) {
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
            return ['configured' => true, 'pending' => 0, 'predicted' => 0, 'results' => 0, 'updated' => 0, 'predicted_updated' => 0];
        }

        $predicted = $pending->filter(fn (FootballMatch $m) => (bool) $m->prediction)->count();

        $index = $this->fetchResults($key, $days, $tz);
        if ($index === null) {
            return ['configured' => true, 'pending' => $pending->count(), 'predicted' => $predicted, 'error' => 'fetch_failed', 'updated' => 0, 'predicted_updated' => 0];
        }

        $updated = 0;
        $predictedUpdated = 0;
        foreach ($pending as $match) {
            $fixtureDate = $match->match_time?->timezone($tz)->toDateString();
            $result = $this->lookup($index, $match->home_team, $match->away_team, $fixtureDate);
            if ($result === null) {
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

        if ($updated > 0) {
            Log::info("ResultsFallbackService: filled {$updated} result(s) from football-data.org ({$predictedUpdated} predicted).");
        }

        return [
            'configured'        => true,
            'pending'           => $pending->count(),
            'predicted'         => $predicted,
            'results'           => count($index),
            'updated'           => $updated,
            'predicted_updated' => $predictedUpdated,
        ];
    }

    /**
     * Fetch FINISHED matches for the window, indexed by "home|away" (and by the
     * short-name / TLA variants) so we can match our free-form fixture names.
     *
     * @return array<string, array{home:int, away:int, ht_home:?int, ht_away:?int, date:string}>|null
     */
    private function fetchResults(string $key, int $days, string $tz): ?array
    {
        $from = now($tz)->subDays($days)->toDateString();
        $to   = now($tz)->toDateString();

        try {
            $resp = Http::withHeaders(['X-Auth-Token' => $key])
                ->timeout(30)
                ->get(rtrim(config('services.football_data.url'), '/') . '/matches', [
                    'dateFrom' => $from,
                    'dateTo'   => $to,
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
                    $h = $this->norm((string) data_get($m, "homeTeam.$hk"));
                    $a = $this->norm((string) data_get($m, "awayTeam.$ak"));
                    if ($h !== '' && $a !== '') {
                        $index["$h|$a"] = $row;
                    }
                }
            }
        }

        return $index;
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
