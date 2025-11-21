<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Paymob Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for Paymob API. Same URL for both test and live modes.
    |
    */
    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),

    /*
    |--------------------------------------------------------------------------
    | Paymob Secret Key
    |--------------------------------------------------------------------------
    |
    | Your Paymob secret key used for API authentication.
    | Used in Authorization header: Token <SECRET_KEY>
    |
    */
    'secret_key' => env('PAYMOB_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Paymob Public Key
    |--------------------------------------------------------------------------
    |
    | Your Paymob public key used for client-side checkout.
    |
    */
    'public_key' => env('PAYMOB_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Paymob Integration IDs
    |--------------------------------------------------------------------------
    |
    | Array of integration IDs for different payment methods.
    | You can specify multiple integration IDs separated by comma.
    |
    */
    'integration_ids' => array_map(fn ($id) => (int) $id, array_filter(
        explode(',', env('PAYMOB_INTEGRATION_IDS', ''))
    )),

    /*
    |--------------------------------------------------------------------------
    | Paymob HMAC Secret
    |--------------------------------------------------------------------------
    |
    | Your HMAC secret key used for webhook signature verification.
    |
    */
    'hmac_secret' => env('PAYMOB_HMAC_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for transactions (ISO 4217 code).
    |
    */
    'currency' => env('PAYMOB_CURRENCY', 'EGP'),

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | Payment mode: test or live
    |
    */
    'mode' => env('PAYMOB_MODE', 'test'),
];
