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

    'shop_id' => env('FEEXPAY_SHOP_ID', '698dd650a7ffb88696f5id86'),

    'token' => env('FEEXPAY_TOKEN', 'test_Hg7Kjl3ZAM63UuIUpuudD9nKuu3ZAM67Kjl3Uuhn'),

    'mode' => env('FEEXPAY_MODE', 'SANDBOX'), // 'SANDBOX' ou 'LIVE'

    'base_url' => env('FEEXPAY_BASE_URL', 'https://api-v2.feexpay.me'),

    'currency' => env('FEEXPAY_CURRENCY', 'XOF'),
];
