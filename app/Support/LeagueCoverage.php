<?php

namespace App\Support;

class LeagueCoverage
{
    /**
     * Format a league for display: "England · Premier League".
     * Drops the prefix for international competitions (country = "World"
     * or blank) since "World · UEFA Champions League" reads awkwardly.
     *
     * Disambiguates multiple "Premier League" entries (England/Egypt/Kenya/etc.)
     */
    public static function formatName(?string $league, ?string $country): string
    {
        $league  = trim((string) $league);
        $country = trim((string) $country);

        if ($league === '') {
            return $country !== '' ? $country : '-';
        }

        if ($country === '' || strtolower($country) === 'world' || strtolower($country) === 'unknown') {
            return $league;
        }

        return $country . ' · ' . $league;
    }

    /**
     * The football season START YEAR that currently has data, Lagos time.
     *
     * API-Football labels seasons by their starting year (2025 = the 2025-26
     * season). European leagues run Aug-May, so from January through July the
     * live/most-recent season still started LAST year. Defaulting to the raw
     * calendar year (e.g. 2026 in mid-2026) asks the API for a season that
     * hasn't kicked off yet — it returns nothing, wasting a request per league
     * and leaving features (Top Scorers, Fantasy) empty. The rollover to the
     * new season happens in August.
     */
    public static function currentSeason(): int
    {
        $now = now('Africa/Lagos');
        return $now->month >= 8 ? $now->year : $now->year - 1;
    }

    /**
     * IDs we treat as "top European / global" - used to order the daily-pick selector.
     */
    public static function topEuropean(): array
    {
        return (array) config('leagues.top_european', []);
    }

    /** Should this match be ingested into the football prediction pipeline? */
    public static function shouldIngest(array $match): bool
    {
        $id      = (int) ($match['league_id'] ?? 0);

        return in_array($id, self::topEuropean(), true);
    }

    /**
     * Distinct league IDs we've actually ingested that fall inside coverage.
     * Reads the matches table so stat fetchers enumerate every real league we
     * track.
     *
     * @return array<int, int>
     */
    public static function coveredLeagueIds(): array
    {
        return \App\Models\FootballMatch::query()
            ->where(fn ($q) => self::scopeCovered($q))
            ->whereNotNull('league_id')
            ->distinct()
            ->pluck('league_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Eloquent constraint for "anything in our coverage set".
     * Pass to ->where(fn ($q) => LeagueCoverage::scopeCovered($q)).
     */
    public static function scopeCovered($query): void
    {
        $query->whereIn('league_id', self::topEuropean());
    }
}
