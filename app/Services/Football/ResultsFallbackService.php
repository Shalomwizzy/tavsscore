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
        if (blank(config('services.football_data.key')) && empty(config('services.espn.leagues'))) {
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

        // Source 2: ESPN (broad, no key) — checked for whatever is STILL
        // unsettled, so predicted matches football-data doesn't carry (UEFA
        // qualifiers, non-European leagues) still get graded.
        $espnRows = null;
        if (! empty($unsettled)) {
            $espn = $this->fetchEspn($days, $tz);
            $espnRows = $espn === null ? null : count($espn);
            if ($espn !== null) {
                [$unsettled, $u, $pu] = $this->apply($unsettled, $espn, $tz);
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
            'espn_rows'         => $espnRows, // null = not reached / no leagues configured
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
     * ESPN public scoreboard. The default `all` slug is ESPN's global soccer
     * board for the date range; optional configured league slugs supplement it
     * when a deployment needs a competition-specific feed. No key required.
     */
    private function fetchEspn(int $days, string $tz): ?array
    {
        $slugs = (array) config('services.espn.leagues', []);
        if (empty($slugs)) {
            return null;
        }

        $base  = rtrim(config('services.espn.url'), '/');
        $range = now($tz)->subDays($days)->format('Ymd') . '-' . now($tz)->format('Ymd');
        $index = [];

        foreach ($slugs as $slug) {
            try {
                $resp = Http::timeout(20)->get("{$base}/{$slug}/scoreboard", ['dates' => $range]);
            } catch (\Throwable) {
                continue;
            }
            if ($resp->failed()) {
                continue;
            }

            foreach ($resp->json('events') ?? [] as $ev) {
                $comp = data_get($ev, 'competitions.0');
                if (! $comp || ! data_get($comp, 'status.type.completed')) {
                    continue; // not finished yet
                }

                $home = collect(data_get($comp, 'competitors', []))->firstWhere('homeAway', 'home');
                $away = collect(data_get($comp, 'competitors', []))->firstWhere('homeAway', 'away');
                $hs = $home['score'] ?? null;
                $as = $away['score'] ?? null;
                if ($hs === null || $as === null || $hs === '' || $as === '') {
                    continue;
                }

                [$htHome, $htAway] = $this->espnHalfTime($comp, (string) data_get($home, 'team.id'), (string) data_get($away, 'team.id'), (int) $hs, (int) $as);

                $row = [
                    'home'    => (int) $hs,
                    'away'    => (int) $as,
                    'ht_home' => $htHome,
                    'ht_away' => $htAway,
                    'date'    => substr((string) data_get($ev, 'date'), 0, 10),
                ];

                // Index under every name variant (incl. abbreviation) so free-form
                // fixture names like "KuPS" or "Sabah FA" still match.
                $fields = ['displayName', 'shortDisplayName', 'name', 'location', 'abbreviation'];
                foreach ($fields as $hk) {
                    foreach ($fields as $ak) {
                        $this->addToIndex($index, data_get($home, "team.$hk"), data_get($away, "team.$ak"), $row);
                    }
                }
            }
        }

        return $index;
    }

    /**
     * Derive the half-time score from ESPN's scoring-play "details" (goals carry
     * a minute). Only trusted when the derived full-time total reconciles with
     * the actual FT score — otherwise returns [null, null] so HT markets stay
     * pending rather than being graded on incomplete data.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function espnHalfTime(array $comp, string $homeId, string $awayId, int $ftHome, int $ftAway): array
    {
        $details = data_get($comp, 'details', []);
        if (empty($details) || $homeId === '' || $awayId === '') {
            return [null, null];
        }

        $htHome = $htAway = 0;
        $totHome = $totAway = 0;

        foreach ($details as $d) {
            if (! data_get($d, 'scoringPlay') || data_get($d, 'shootout')) {
                continue;
            }
            $teamId  = (string) data_get($d, 'team.id');
            $ownGoal = (bool) data_get($d, 'ownGoal');

            // An own goal credits the opponent.
            $side = $teamId === $homeId ? ($ownGoal ? 'away' : 'home')
                : ($teamId === $awayId ? ($ownGoal ? 'home' : 'away') : null);
            if ($side === null) {
                continue;
            }

            // Leading minute before any "+" — "45+2'" -> 45 (first half), "52'" -> 52.
            $base = (int) preg_replace('/\D.*$/', '', (string) data_get($d, 'clock.displayValue', ''));
            $firstHalf = $base > 0 && $base <= 45;

            if ($side === 'home') { $totHome++; if ($firstHalf) $htHome++; }
            else                  { $totAway++; if ($firstHalf) $htAway++; }
        }

        // Only trust HT if the play-by-play reconciles with the final score.
        if ($totHome === $ftHome && $totAway === $ftAway) {
            return [$htHome, $htAway];
        }
        return [null, null];
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
        $hk = $this->norm($home);
        $ak = $this->norm($away);
        $row = $index["$hk|$ak"] ?? null;

        // Fuzzy fallback: one provider often adds a city ("Vardar" vs "Vardar
        // Skopje", "Dila" vs "Dila Gori"). Match when both sides' tokens are a
        // subset of the candidate's (or vice versa).
        if ($row === null) {
            $ht = array_values(array_filter(explode(' ', $hk)));
            $at = array_values(array_filter(explode(' ', $ak)));
            foreach ($index as $keyStr => $candidate) {
                [$ch, $ca] = array_pad(explode('|', $keyStr), 2, '');
                if ($this->tokensContain($ht, explode(' ', $ch)) && $this->tokensContain($at, explode(' ', $ca))) {
                    $row = $candidate;
                    break;
                }
            }
        }

        if ($row === null) {
            return null;
        }
        if ($fixtureDate && $row['date'] !== '' && abs(strtotime($row['date']) - strtotime($fixtureDate)) > 86400) {
            return null; // a different meeting of the same two teams
        }
        return $row;
    }

    /** True when the shorter token list is fully contained in the longer one. */
    private function tokensContain(array $a, array $b): bool
    {
        $a = array_values(array_filter($a));
        $b = array_values(array_filter($b));
        if (empty($a) || empty($b)) {
            return false;
        }
        [$short, $long] = count($a) <= count($b) ? [$a, $b] : [$b, $a];
        foreach ($short as $t) {
            if (! in_array($t, $long, true)) {
                return false;
            }
        }
        return true;
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
            'club', 'de', 'the', 'football', 'calcio', 'if', 'bk', 'fk', 'sk', 'fa', 'cfr'];
        $words = array_filter(explode(' ', (string) $s), fn ($w) => $w !== '' && ! in_array($w, $stop, true));
        return implode(' ', $words);
    }

    private function intOrNull(mixed $v): ?int
    {
        return $v === null ? null : (int) $v;
    }
}
