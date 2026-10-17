<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand & Compliance
    |--------------------------------------------------------------------------
    |
    | The brand name itself comes from APP_NAME. The licence line is shown in
    | the site footer and must match the operator's BCLB licence exactly
    | before launch; leave it empty until a licence is held.
    |
    */

    'licence_text' => env('SPORTSBOOK_LICENCE_TEXT'),

    'minimum_age' => 18,

    'timezone' => 'Africa/Nairobi',

    /*
    |--------------------------------------------------------------------------
    | Admin Panel
    |--------------------------------------------------------------------------
    |
    | The Filament back office only answers on this host, so its login page
    | never exists on the player-facing domain.
    |
    */

    'admin_domain' => env('ADMIN_DOMAIN', 'admin.localhost'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Money is stored as integers in the currency's minor unit (cents), never
    | as floats. Limits below are expressed in whole shillings.
    |
    */

    'currency' => [
        'code' => 'KES',
        'minor_units' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Betting Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'min_stake' => (int) env('SPORTSBOOK_MIN_STAKE', 10),
        'max_stake' => (int) env('SPORTSBOOK_MAX_STAKE', 20_000),
        'max_payout' => (int) env('SPORTSBOOK_MAX_PAYOUT', 1_000_000),
        'max_selections' => (int) env('SPORTSBOOK_MAX_SELECTIONS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | Margin is applied on top of the de-margined consensus price. Model-priced
    | markets (derived from 1X2 / totals) carry an additional margin because
    | the risk is entirely ours.
    |
    */

    'odds' => [
        'margin' => (float) env('SPORTSBOOK_ODDS_MARGIN', 0.06),
        'derived_market_margin' => (float) env('SPORTSBOOK_DERIVED_MARGIN', 0.10),
        'minimum_bookmakers' => (int) env('SPORTSBOOK_MINIMUM_BOOKMAKERS', 3),
        'stale_after_hours' => (int) env('SPORTSBOOK_ODDS_STALE_AFTER_HOURS', 26),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax
    |--------------------------------------------------------------------------
    |
    | UNVERIFIED: these rates must be confirmed against current Kenyan law
    | before launch (plan item B). They are settings, never hard-coded.
    |
    */

    'tax' => [
        'deposit_excise_rate' => (float) env('SPORTSBOOK_DEPOSIT_EXCISE_RATE', 0.05),
        'winnings_withholding_rate' => (float) env('SPORTSBOOK_WINNINGS_WHT_RATE', 0.20),
    ],

    /*
    |--------------------------------------------------------------------------
    | The Odds API Budget
    |--------------------------------------------------------------------------
    |
    | Credentials live in config/services.php. These settings keep usage
    | inside the plan's monthly quota: calls are refused once the remaining
    | credits would fall below the reserve.
    |
    */

    'odds_api' => [
        // "live" calls the API; "replay" serves recordings and never spends credits.
        'driver' => env('ODDS_API_DRIVER', 'replay'),
        'recordings_path' => database_path('odds-api-recordings'),
        'region' => env('ODDS_API_REGION', 'uk'),
        // Comma-separated market keys pulled by the scheduled odds sync.
        'markets' => env('ODDS_API_MARKETS', 'h2h'),
        // Odds are only bought for competitions with a fixture kicking off within this many days.
        'lookahead_days' => (int) env('ODDS_API_LOOKAHEAD_DAYS', 7),
        // Feed sport groups imported as sports, mapped to the name players see.
        'sport_groups' => ['Soccer' => 'Football'],
        // Competitions synced out of the box (CompetitionSeeder) and recorded for replay.
        'default_competitions' => [
            'soccer_epl',
            'soccer_spain_la_liga',
            'soccer_italy_serie_a',
            'soccer_germany_bundesliga',
            'soccer_france_ligue_one',
            'soccer_uefa_champs_league',
        ],
        'odds_format' => 'decimal',
        'monthly_credit_cap' => (int) env('ODDS_API_MONTHLY_CREDIT_CAP', 500),
        'credit_reserve' => (int) env('ODDS_API_CREDIT_RESERVE', 50),
    ],

];
