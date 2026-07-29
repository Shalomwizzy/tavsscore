<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'football' => [
        'key' => env('FOOTBALL_API_KEY'),
        'url' => env('FOOTBALL_API_URL', 'https://v3.football.api-sports.io'),
    ],

    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
    ],

    // Shared secret the external booking-code automation worker uses to pull the
    // betslip spec and post codes back. Set BOOKING_WORKER_TOKEN in .env.
    'booking_worker' => [
        'token' => env('BOOKING_WORKER_TOKEN'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'url' => env('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions'),
        'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
    ],

    'tennis_data' => [
        // Historical ATP/WTA results from Tennis-Data.co.uk (free, updated
        // weekly, carries bookmaker odds). {year} is substituted per season.
        // The importer parses the .xlsx directly. A local file path or a
        // Sackmann-format CSV URL also works — the importer detects the format.
        'atp_url' => env('TENNIS_ATP_SOURCE_URL', 'http://www.tennis-data.co.uk/{year}/{year}.xlsx'),
        'wta_url' => env('TENNIS_WTA_SOURCE_URL', 'http://www.tennis-data.co.uk/{year}w/{year}.xlsx'),
    ],

    'tennis_live' => [
        'key' => env('TENNIS_LIVE_API_KEY'),
        'url' => env('TENNIS_LIVE_API_URL', 'https://api.livetennisapi.com/api/public/v1'),
    ],

    // Public tennis is hidden until the historical data is current enough to
    // trust the predictions. Admin tennis stays available. Flip to true via env.
    'tennis' => [
        'public' => filter_var(env('TENNIS_PUBLIC_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    // Free fallback for match RESULTS when the API-Football quota is exhausted.
    // Covers the top competitions on the free tier (PL, La Liga, Serie A,
    // Bundesliga, Ligue 1, UCL, Championship, Eredivisie, Primeira, etc.).
    'football_data' => [
        'key' => env('FOOTBALL_DATA_KEY'),
        'url' => env('FOOTBALL_DATA_URL', 'https://api.football-data.org/v4'),
    ],

    // Second, broader free results source — ESPN's public scoreboard (no key).
    // Covers the competitions football-data's free tier misses, incl. UEFA
    // qualifiers and non-European leagues. One call per league slug per run.
    'espn' => [
        'url'     => env('ESPN_SOCCER_URL', 'https://site.api.espn.com/apis/site/v2/sports/soccer'),
        'leagues' => env('ESPN_SOCCER_LEAGUES')
            ? array_values(array_filter(array_map('trim', explode(',', (string) env('ESPN_SOCCER_LEAGUES')))))
            : [
                'uefa.champions_qual', 'uefa.europa_qual', 'uefa.europa.conf_qual',
                'uefa.champions', 'uefa.europa', 'uefa.europa.conf', 'uefa.super_cup',
                'usa.1', 'mex.1', 'bra.1', 'arg.1', 'jpn.1', 'kor.1', 'aus.1', 'ksa.1',
                'nor.1', 'swe.1', 'den.1', 'fin.1', 'rus.1', 'tur.1', 'gre.1', 'sco.1',
                'bel.1', 'aut.1', 'sui.1', 'pol.1', 'cze.1', 'rou.1', 'srb.1', 'cro.1',
            ],
    ],

    'gemini' => [
        // Optional second-opinion AI. Leave blank to disable.
        // Get a free key: https://aistudio.google.com/app/apikey
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
    ],

    'mistral' => [
        // Optional third-opinion AI. Leave blank to disable.
        // Get a free key: https://console.mistral.ai
        'key'   => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
        'url'   => env('MISTRAL_API_URL', 'https://api.mistral.ai/v1/chat/completions'),
    ],

    'ga' => [
        'id' => env('GA_MEASUREMENT_ID'),
    ],

    'adsense' => [
        'client' => env('ADSENSE_CLIENT'),
    ],

    'onesignal' => [
        'app_id'       => env('ONESIGNAL_APP_ID'),
        'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
    ],

    'telegram' => [
        'bot_token'  => env('TELEGRAM_BOT_TOKEN', ''),
        'channel_id' => env('TELEGRAM_CHANNEL_ID', ''),
        'card_font'  => env('TELEGRAM_CARD_FONT_PATH'),
    ],

];
