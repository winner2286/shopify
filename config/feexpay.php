<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FeexPay Configuration (Passerelle de paiement panafricaine)
    |--------------------------------------------------------------------------
    |
    | Supporte Mobile Money (T-Money, Moov Money, MTN, Orange, Wave, Celtiis)
    | et cartes bancaires (Visa, Mastercard).
    |
    */

    'shop_id' => env('GENIUSPAY_SHOP_ID', env('FEEXPAY_SHOP_ID')),

    'token' => env('GENIUSPAY_TOKEN', env('FEEXPAY_TOKEN')),

    'mode' => env('GENIUSPAY_MODE', env('FEEXPAY_MODE', 'SANDBOX')), // 'SANDBOX' ou 'LIVE'

    'base_url' => env('GENIUSPAY_BASE_URL', env('FEEXPAY_BASE_URL', 'https://api-v2.feexpay.me')),

    'currency' => env('GENIUSPAY_CURRENCY', env('FEEXPAY_CURRENCY', 'XOF')),
];
