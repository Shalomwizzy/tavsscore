<?php

/*
|--------------------------------------------------------------------------
| Prediction engine feature flags
|--------------------------------------------------------------------------
| Which markets Dixon-Coles owns for which leagues. Any (league, market)
| combination NOT listed here falls back to the existing Groq+Poisson
| blend. Ship-gate discipline — DC only ships where the backtest proved
| it beats baseline (see /docs/backtests, or /admin/model-metrics).
|
| Ship-gate results (2026-07-12 backtest, 9,691 held-out 1X2 predictions):
|   1X2:    DC wins on all 9 priority leagues (+5.7pp avg hit rate).
|   O2.5:   DC wins on La Liga, Scot Prem, Primeira only. Ties/loses elsewhere.
|   BTTS:   DC does not beat baseline in any league — stays on hybrid.
|
*/

return [

    // Master switch — turn all DC off with one env flag if something goes wrong live.
    'dc_enabled' => env('DC_ENABLED', true),

    'model_version' => env('DC_MODEL_VERSION', 'dc-v1.0'),

    /*
    | Leagues where DC owns the 1X2 probabilities. Everywhere else keeps the
    | existing Groq numbers. Backtested and proven for all 9 priority leagues.
    */
    'dc_1x2_leagues' => [
        39,  // Premier League
        140, // La Liga
        135, // Serie A
        78,  // Bundesliga
        179, // Scottish Premiership
        94,  // Primeira Liga
        61,  // Ligue 1
        88,  // Eredivisie
        144, // Belgian Pro League
    ],

    /*
    | Leagues where DC additionally owns Over 2.5. Kept narrow because the
    | backtest showed no advantage in most leagues.
    */
    'dc_over25_leagues' => [
        140, // La Liga
        179, // Scottish Premiership
        94,  // Primeira Liga
    ],

    /*
    | BTTS never uses DC — the backtest showed no advantage in any league.
    | Kept as an explicit empty list so future ship-gate wins can be enabled
    | one line at a time.
    */
    'dc_btts_leagues' => [],

];
