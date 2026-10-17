<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // The Odds API (the-odds-api.com): fixtures and bookmaker prices. Budget settings live in config/sportsbook.php.
    'odds_api' => [
        'key' => env('ODDS_API_KEY'),
        'base_url' => env('ODDS_API_BASE_URL', 'https://api.the-odds-api.com/v4'),
    ],

    // TextSMS (sms.textsms.co.ke): phone verification codes and notifications. The API key lives in .env only.
    'textsms' => [
        'url' => env('TEXTSMS_URL', 'https://sms.textsms.co.ke/api/services/sendsms/'),
        'api_key' => env('TEXTSMS_API_KEY'),
        'partner_id' => env('TEXTSMS_PARTNER_ID'),
        'shortcode' => env('TEXTSMS_SHORTCODE', 'TextSMS'),
    ],

];
