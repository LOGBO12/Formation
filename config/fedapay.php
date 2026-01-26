<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FedaPay API Configuration
    |--------------------------------------------------------------------------
    */
    'api_key' => env('FEDAPAY_API_KEY'),
    'public_key' => env('FEDAPAY_PUBLIC_KEY'),
    'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'), // sandbox ou live
    'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => 'XOF', // Franc CFA

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    */
    'callback_url' => env('APP_URL') . '/api/fedapay/callback',
    'webhook_url'  => env('APP_URL') . '/api/fedapay/webhook',

];
