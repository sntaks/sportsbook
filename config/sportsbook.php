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
    | Phone Codes
    |--------------------------------------------------------------------------
    |
    | SMS codes for sign-up, password reset and changing number. Every SMS
    | costs money, so sends are capped per number and per IP address.
    |
    */

    'phone_codes' => [
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'resend_seconds' => 60,
        'sends_per_hour_phone' => 5,
        'sends_per_hour_ip' => 20,
    ],

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
    | Wallet
    |--------------------------------------------------------------------------
    |
    | Deposit limits are in whole shillings: M-Pesa only moves whole shillings.
    | The deposit tax rate is a fraction (0.05 = 5%) and stays 0 until the
    | current Kenyan rate is confirmed; when set, it posts as its own entry.
    |
    */

    'wallet' => [
        'min_deposit' => (int) env('SPORTSBOOK_MIN_DEPOSIT', 10),
        'max_deposit' => (int) env('SPORTSBOOK_MAX_DEPOSIT', 150_000),
        'deposit_tax_rate' => (float) env('SPORTSBOOK_DEPOSIT_TAX_RATE', 0),
        // STK pushes a player can start in a rolling window, so nobody's phone can be spammed with prompts.
        'stk_pushes_per_window' => 5,
        'stk_push_window_minutes' => 10,
        // A new push is refused while the last one is still pending and younger than this (the prompt on the phone
        // lasts about a minute).
        'stk_prompt_seconds' => 90,
        // A pending STK deposit with no callback is checked with STK Push Query after this long, at most once
        // per interval, and given up on (expired) after the last value.
        'stk_query_after_seconds' => 60,
        'stk_expire_after_minutes' => 30,

        // Withdrawals, in whole shillings. At or above the approval threshold, finance approves before paying.
        'min_withdrawal' => (int) env('SPORTSBOOK_MIN_WITHDRAWAL', 50),
        'max_withdrawal' => (int) env('SPORTSBOOK_MAX_WITHDRAWAL', 150_000),
        'withdrawal_approval_threshold' => (int) env('SPORTSBOOK_WITHDRAWAL_APPROVAL_THRESHOLD', 20_000),
        'withdrawals_per_hour' => 5,
        // A payout with no definite answer from M-Pesa after this long is shown to finance to resolve by hand.
        'payout_stuck_after_minutes' => 30,

        // Raising or removing a deposit limit waits this long; cutting one applies at once.
        'limit_raise_delay_hours' => 24,
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
        // Expected goals per match when a competition has no feed Over 2.5 prices to learn from. Recorded top-league
        // prices implied about 2.75.
        'default_total_goals' => (float) env('SPORTSBOOK_DEFAULT_TOTAL_GOALS', 2.7),
        // Share of each team's goals expected in the first half, for half-time markets. UNVERIFIED rule of thumb.
        'first_half_goal_share' => (float) env('SPORTSBOOK_FIRST_HALF_GOAL_SHARE', 0.45),
        // Model-priced markets (e.g. correct score) never offer more than this.
        'derived_maximum_price' => (float) env('SPORTSBOOK_DERIVED_MAXIMUM_PRICE', 500),
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

    /*
    |--------------------------------------------------------------------------
    | Settlement
    |--------------------------------------------------------------------------
    |
    | Results come from the paid scores feed (2 credits a call), fetched only
    | for competitions with open bets on matches that should have finished,
    | and at most once per competition per check interval (decision 33).
    | Competitions listed as manual are settled from the admin panel.
    |
    */

    'settlement' => [
        'result_after_minutes' => 135,
        'scores_check_interval_hours' => 12,
        'scores_days_from' => 3,
        // A leg is void when its match moves more than this from the kick-off it was bet on.
        'postponement_void_hours' => 48,
        // Settled by hand until it is known whether the feed's scores include extra time.
        'manual_competitions' => ['soccer_uefa_champs_league'],
        // Withholding tax on net winnings, as a fraction; 0 until the current Kenyan rate is confirmed.
        'winnings_tax_rate' => (float) env('SPORTSBOOK_WINNINGS_TAX_RATE', 0),
    ],

];
