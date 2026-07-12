<?php

namespace App\Services\DixonColes;

/**
 * Normalises team names so Dixon-Coles training doesn't fragment across
 * cosmetic spelling variants. Handles:
 *
 * - Diacritic collisions ("Bayern München" and "Bayern Munchen" → same key)
 * - Case-only variants ("VfL Bochum" vs "Vfl Bochum")
 * - Common prefix noise ("1. FC Heidenheim" vs "FC Heidenheim")
 *
 * True cross-language canonicalisation ("Bayern Munich" ↔ "Bayern München")
 * still needs a proper alias map — that's the TeamCanonicalizer / admin
 * queue work. This class just kills the obvious computable collisions.
 *
 * The MySQL utf8mb4_unicode_ci collation on dc_team_params.team_name would
 * otherwise treat "München" and "Munchen" as the same key at insert time,
 * throwing a unique-constraint violation from separately-trained rows.
 */
class TeamNameNormalizer
{
    /**
     * Return a stable key for a team name suitable for training + storage.
     * Original display name is kept elsewhere; this key is just the merge
     * pivot. Idempotent.
     */
    public static function key(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';

        // Strip common numeric/club prefixes that appear inconsistently across
        // API-Football payloads.
        $name = preg_replace('/^(\d+\.\s*)+/', '', $name);

        // Diacritic strip via iconv. Falls back to the untouched name if the
        // conversion errors (very rare on modern PHP builds).
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($ascii !== false && $ascii !== null) {
            $name = $ascii;
        }

        // Collapse whitespace + lowercase.
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($name)));
    }
}
