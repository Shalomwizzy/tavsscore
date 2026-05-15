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
     * IDs we treat as "top European / global" - used to order the daily-pick selector.
     */
    public static function topEuropean(): array
    {
        return (array) config('leagues.top_european', []);
    }

    /**
     * IDs of pan-African continental competitions (CAF Champions League, etc.).
     */
    public static function africaContinental(): array
    {
        return (array) config('leagues.africa_continental', []);
    }

    /**
     * Country names treated as African coverage (case-insensitive match against
     * `league.country` from API-Football).
     */
    public static function africaCountries(): array
    {
        return array_map('strtolower', (array) config('leagues.africa_countries', []));
    }

    /**
     * Should this match be ingested? True if it's in our top set, an African
     * continental competition, or any league whose country is on our Africa list.
     */
    public static function shouldIngest(array $match): bool
    {
        $id      = (int) ($match['league_id'] ?? 0);
        $country = strtolower((string) ($match['league_country'] ?? ''));

        if (in_array($id, self::topEuropean(), true)) return true;
        if (in_array($id, self::africaContinental(), true)) return true;
        if ($country !== '' && in_array($country, self::africaCountries(), true)) return true;

        return false;
    }

    /**
     * Eloquent constraint for "anything in our coverage set".
     * Pass to ->where(fn ($q) => LeagueCoverage::scopeCovered($q)).
     */
    public static function scopeCovered($query): void
    {
        $query->where(function ($q) {
            $q->whereIn('league_id', array_merge(self::topEuropean(), self::africaContinental()))
              ->orWhereIn('league_country', config('leagues.africa_countries', []));
        });
    }
}
