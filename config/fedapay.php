<?php

return [
    'api_key' => env('FEDAPAY_API_KEY'),
    'public_key' => env('FEDAPAY_PUBLIC_KEY'),
    'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'), // 'sandbox' ou 'live'
    'currency' => env('FEDAPAY_CURRENCY', 'XOF'),
    'callback_url' => env('FEDAPAY_CALLBACK_URL'),
    'webhook_url' => env('FEDAPAY_WEBHOOK_URL'),
    'default_commission' => env('FEDAPAY_COMMISSION', 10),
];  