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
        'region' => env('ODDS_API_REGION', 'uk'),
        'markets' => env('ODDS_API_MARKETS', 'h2h'),
        'odds_format' => 'decimal',
        'monthly_credit_cap' => (int) env('ODDS_API_MONTHLY_CREDIT_CAP', 500),
        'credit_reserve' => (int) env('ODDS_API_CREDIT_RESERVE', 50),
    ],

];
