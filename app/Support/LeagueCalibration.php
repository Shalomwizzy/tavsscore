<?php

namespace App\Support;

/**
 * Per-league calibration data derived from academic research.
 *
 * Each league has different:
 *   - Draw base rate (how often draws actually occur)
 *   - Predictability tier (how reliable model predictions are)
 *   - Optimal form window (how many recent matches give the best signal)
 *
 * Sources: Foresportia 12,337-match study, Frontiers Bundesliga 2025,
 * penaltyblog 2025 model comparison, Dixon-Coles 1997.
 */
class LeagueCalibration
{
    // league_id → calibration settings
    private static array $data = [
        // ── Champions League / Europa League ──
        2   => ['draw_rate' => 0.22, 'tier' => 'elite',    'form_window' => 8,  'name' => 'Champions League'],
        3   => ['draw_rate' => 0.24, 'tier' => 'elite',    'form_window' => 8,  'name' => 'Europa League'],
        848 => ['draw_rate' => 0.22, 'tier' => 'elite',    'form_window' => 8,  'name' => 'Europa Conference League'],

        // ── England ──
        39  => ['draw_rate' => 0.26, 'tier' => 'high',     'form_window' => 10, 'name' => 'Premier League'],
        40  => ['draw_rate' => 0.28, 'tier' => 'medium',   'form_window' => 8,  'name' => 'Championship'],
        45  => ['draw_rate' => 0.24, 'tier' => 'medium',   'form_window' => 6,  'name' => 'FA Cup'],
        48  => ['draw_rate' => 0.24, 'tier' => 'medium',   'form_window' => 6,  'name' => 'EFL Cup'],

        // ── Germany ──
        78  => ['draw_rate' => 0.23, 'tier' => 'high',     'form_window' => 15, 'name' => 'Bundesliga'],
        79  => ['draw_rate' => 0.26, 'tier' => 'medium',   'form_window' => 12, 'name' => '2. Bundesliga'],

        // ── Spain ──
        140 => ['draw_rate' => 0.26, 'tier' => 'high',     'form_window' => 10, 'name' => 'La Liga'],
        141 => ['draw_rate' => 0.27, 'tier' => 'medium',   'form_window' => 10, 'name' => 'La Liga 2'],
        143 => ['draw_rate' => 0.24, 'tier' => 'medium',   'form_window' => 6,  'name' => 'Copa del Rey'],

        // ── Italy ──
        135 => ['draw_rate' => 0.27, 'tier' => 'high',     'form_window' => 10, 'name' => 'Serie A'],
        136 => ['draw_rate' => 0.28, 'tier' => 'low',      'form_window' => 10, 'name' => 'Serie B'],

        // ── France ──
        61  => ['draw_rate' => 0.25, 'tier' => 'high',     'form_window' => 8,  'name' => 'Ligue 1'],
        62  => ['draw_rate' => 0.27, 'tier' => 'medium',   'form_window' => 8,  'name' => 'Ligue 2'],

        // ── Netherlands ──
        88  => ['draw_rate' => 0.24, 'tier' => 'high',     'form_window' => 10, 'name' => 'Eredivisie'],

        // ── Portugal ──
        94  => ['draw_rate' => 0.25, 'tier' => 'high',     'form_window' => 8,  'name' => 'Primeira Liga'],

        // ── Belgium ──
        144 => ['draw_rate' => 0.25, 'tier' => 'medium',   'form_window' => 8,  'name' => 'Belgian Pro League'],

        // ── Turkey ──
        203 => ['draw_rate' => 0.27, 'tier' => 'medium',   'form_window' => 8,  'name' => 'Süper Lig'],

        // ── Scotland ──
        179 => ['draw_rate' => 0.26, 'tier' => 'medium',   'form_window' => 8,  'name' => 'Scottish Premiership'],

        // ── Brazil ──
        71  => ['draw_rate' => 0.26, 'tier' => 'medium',   'form_window' => 10, 'name' => 'Brasileirão'],

        // ── Argentina ──
        128 => ['draw_rate' => 0.27, 'tier' => 'medium',   'form_window' => 8,  'name' => 'Primera División'],

        // ── MLS ──
        253 => ['draw_rate' => 0.22, 'tier' => 'medium',   'form_window' => 8,  'name' => 'MLS'],

        // ── Scandinavia (most predictable leagues globally) ──
        103 => ['draw_rate' => 0.23, 'tier' => 'high',     'form_window' => 8,  'name' => 'Eliteserien (Norway)'],
        113 => ['draw_rate' => 0.24, 'tier' => 'high',     'form_window' => 8,  'name' => 'Allsvenskan (Sweden)'],
        244 => ['draw_rate' => 0.25, 'tier' => 'high',     'form_window' => 8,  'name' => 'Veikkausliiga (Finland)'],
    ];

    // Fallback for leagues not explicitly listed
    private static array $fallback = [
        'draw_rate'   => 0.26,
        'tier'        => 'medium',
        'form_window' => 8,
        'name'        => 'Unknown',
    ];

    public static function get(int $leagueId): array
    {
        return self::$data[$leagueId] ?? self::$fallback;
    }

    /** Draw base rate for this league (0–1). e.g. 0.26 = 26% of matches are draws. */
    public static function drawRate(int $leagueId): float
    {
        return self::get($leagueId)['draw_rate'];
    }

    /** Predictability tier: 'elite' | 'high' | 'medium' | 'low' */
    public static function tier(int $leagueId): string
    {
        return self::get($leagueId)['tier'];
    }

    /**
     * Confidence modifier based on league predictability.
     * Elite/high leagues → predictions are more reliable → slight boost.
     * Low predictability → slight penalty.
     */
    public static function confidenceModifier(int $leagueId): float
    {
        return match (self::tier($leagueId)) {
            'elite'  => 1.05,
            'high'   => 1.02,
            'medium' => 1.00,
            'low'    => 0.92,
            default  => 1.00,
        };
    }

    /**
     * Human-readable draw rate for the Groq prompt context.
     * e.g. "In the Premier League, ~26% of matches end in a draw."
     */
    public static function drawRateDescription(int $leagueId): string
    {
        $cal  = self::get($leagueId);
        $pct  = (int) round($cal['draw_rate'] * 100);
        return "In the {$cal['name']}, approximately {$pct}% of matches end in a draw.";
    }
}
