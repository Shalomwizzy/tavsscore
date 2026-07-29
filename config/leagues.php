<?php

/*
|--------------------------------------------------------------------------
| League coverage
|--------------------------------------------------------------------------
| API-Football identifies competitions by integer ID. We track:
|   • the canonical Euro/global IDs we know (top European + UCL/UEL etc.)
|
| The Predictions/AutoBlog pipelines also use `top_european` so that the
| daily-pick selector leans toward marquee matches.
|
*/

return [

    // Top European + global (UCL/UEL/CONMEBOL/MLS/etc.). These remain the
    // canonical "top" set for the daily-pick picker.
    'top_european' => [
        2,   // UEFA Champions League
        3,   // UEFA Europa League
        39,  // Premier League (Eng)
        40,  // EFL Championship (Eng)
        45,  // FA Cup (Eng)
        48,  // EFL Cup (Eng)
        61,  // Ligue 1 (Fra)
        66,  // Ligue 2 (Fra)
        71,  // Brasileirão Série A
        78,  // Bundesliga (Ger)
        79,  // 2. Bundesliga (Ger)
        88,  // Eredivisie (Ned)
        94,  // Primeira Liga (Por)
        135, // Serie A (Ita)
        136, // Serie B (Ita)
        140, // La Liga (Esp)
        143, // Copa del Rey (Esp)
        144, // Belgian Pro League
        179, // Scottish Premiership
        203, // Süper Lig (Tur)
        235, // Russian Premier League
        253, // MLS (USA)
        262, // Liga MX (Mex)
        292, // K League 1 (Kor)
        307, // Saudi Pro League
        848, // UEFA Conference League
    ],

    // Season-priority leagues for Dixon-Coles training (Phase 2). Ordered
    // by focus for the 2026-27 launch: user emphasis is the marquee
    // European leagues where we have the most history and cleanest data.
    // Others in `top_european` above will get DC coverage in a later
    // pass once the model is proven here.
    'season_priority' => [
        39,  // Premier League (Eng)
        140, // La Liga (Esp)
        135, // Serie A (Ita)
        78,  // Bundesliga (Ger)
        179, // Scottish Premiership
        94,  // Primeira Liga (Por)
        61,  // Ligue 1 (Fra)
        88,  // Eredivisie (Ned)
        144, // Belgian Pro League
    ],

];
