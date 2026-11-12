<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FeexPay Configuration (Passerelle de paiement panafricaine)
    |--------------------------------------------------------------------------
    |
    | Supporte Mobile Money (T-Money, Flooz, MTN, Moov, Orange, Wave, Celtiis)
    | et cartes bancaires (Visa, Mastercard).
    |
    */

    'shop_id' => env('FEEXPAY_SHOP_ID'),

    'token' => env('FEEXPAY_TOKEN'),

    'mode' => env('FEEXPAY_MODE', 'SANDBOX'), // 'SANDBOX' ou 'LIVE'

    'base_url' => env('FEEXPAY_BASE_URL', 'https://api-v2.feexpay.me'),

    'currency' => env('FEEXPAY_CURRENCY', 'XOF'),
];
