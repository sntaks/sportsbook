<?php

/*
|--------------------------------------------------------------------------
| M-Pesa (Safaricom Daraja)
|--------------------------------------------------------------------------
|
| Ported from sites/api config/mpesa.php. Every credential lives in .env only.
|
| The environment is chosen explicitly with MPESA_ENV (sandbox or production)
| rather than following APP_ENV, so staging can be pointed at either.
|
| Callback URLs are built from this app's routes with a long secret in the
| path (Safaricom does not sign callbacks). Safaricom cannot reach a .test
| domain, so set MPESA_CALLBACK_BASE_URL to a public URL (for example from
| `herd share`) when trying the sandbox live.
|
*/

return [

    'environment' => env('MPESA_ENV', 'sandbox'),

    'callback_secret' => env('MPESA_CALLBACK_SECRET'),

    'callback_base_url' => env('MPESA_CALLBACK_BASE_URL'),

    // Safaricom's callback IPs. Empty means the IP check is off; the secret in the URL still applies.
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('MPESA_ALLOWED_IPS', ''))))),

    'apps' => [
        'c2b' => [
            'consumer_key' => env('MPESA_C2B_CONSUMER_KEY'),
            'consumer_secret' => env('MPESA_C2B_CONSUMER_SECRET'),
        ],
        'b2c' => [
            'consumer_key' => env('MPESA_B2C_CONSUMER_KEY'),
            'consumer_secret' => env('MPESA_B2C_CONSUMER_SECRET'),
        ],
    ],

    // Lipa Na M-Pesa Online (STK push).
    'lnmo' => [
        'short_code' => env('MPESA_C2B_SHORTCODE'),
        'passkey' => env('MPESA_LNMO_PASSKEY'),
        'transaction_type' => 'CustomerPayBillOnline',
    ],

    // Paybill payments players make from their own M-Pesa menu.
    'c2b' => [
        'short_code' => env('MPESA_C2B_SHORTCODE'),
        'initiator_name' => env('MPESA_C2B_INITIATOR_NAME'),
        'security_credential' => env('MPESA_C2B_SECURITY_CREDENTIAL'),
        // What M-Pesa does when our validation URL does not answer in time.
        'response_type' => 'Completed',
        'command_id' => 'CustomerPayBillOnline',
    ],

    // Payouts (withdrawals). Used from phase 3c.
    'b2c' => [
        'short_code' => env('MPESA_B2C_SHORTCODE'),
        'initiator_name' => env('MPESA_B2C_INITIATOR_NAME'),
        'security_credential' => env('MPESA_B2C_SECURITY_CREDENTIAL'),
        'command_id' => 'BusinessPayment',
    ],

];
