<?php

namespace App\Services\Tennis;

use Illuminate\Support\Str;

/**
 * Reconciles tennis player names across sources so a scheduled fixture links to
 * that player's history. Live fixtures arrive as "Frances Tiafoe"; historical
 * Tennis-Data.co.uk rows arrive as "Tiafoe F.". Both fold to one canonical
 * "Surname I." form used everywhere names are compared (ratings, form, H2H).
 */
class TennisNameNormalizer
{
    public static function canonical(?string $name): string
    {
        $name = trim(Str::ascii((string) $name));
        $name = preg_replace('/[^A-Za-z .\'-]/', ' ', $name) ?? '';
        $tokens = array_values(array_filter(preg_split('/\s+/', $name) ?: []));
        if ($tokens === []) return '';
        if (count($tokens) === 1) return self::title($tokens[0]);

        $last = end($tokens);
        if (self::isInitial($last)) {
            // Already "Surname I." (or "Surname I.J.") — keep surname + first initial.
            $surname = array_slice($tokens, 0, -1);
            $initial = strtoupper($last[0]);
        } else {
            // Full "First [Middle] Surname…" — the first token is the given name.
            $initial = strtoupper($tokens[0][0]);
            $surname = array_slice($tokens, 1);
        }

        $surname = array_filter($surname, fn ($t) => ! self::isInitial($t));
        if ($surname === []) return self::title($last);

        return implode(' ', array_map([self::class, 'title'], $surname)) . " {$initial}.";
    }

    /** A single letter, optionally chained ("J.W."), or a lone initial. */
    private static function isInitial(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z](\.[A-Za-z])*\.$/', $token)
            || strlen(str_replace('.', '', $token)) === 1;
    }

    private static function title(string $token): string
    {
        return ucwords(strtolower($token), " -'");
    }
}
