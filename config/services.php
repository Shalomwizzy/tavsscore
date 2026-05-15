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

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'url' => env('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions'),
        'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
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
    ],

];
