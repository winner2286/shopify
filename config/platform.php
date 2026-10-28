<?php

return [
    // Nom provisoire : le nom définitif est un point ouvert du cahier des charges (section 18, n°1).
    'name' => env('APP_NAME', 'Boutiq'),

    // Pays proposés à l'inscription (Togo en premier : marché de lancement).
    'countries' => [
        'TG' => 'Togo',
        'BJ' => 'Bénin',
        'BF' => 'Burkina Faso',
        'CI' => 'Côte d\'Ivoire',
        'SN' => 'Sénégal',
        'ML' => 'Mali',
        'NE' => 'Niger',
        'GH' => 'Ghana',
        'CM' => 'Cameroun',
        'FR' => 'France',
        'OTHER' => 'Autre',
    ],

    // Noms de sous-domaine interdits (RG-09).
    'reserved_subdomains' => [
        'admin', 'www', 'api', 'support', 'app', 'mail', 'help', 'blog', 'static', 'cdn',
        'boutiq', 'shopify', 'billing', 'login', 'register', 'dashboard', 'auth', 'root',
        'test', 'dev', 'status', 'account', 'shop', 'store', 'cart', 'checkout', 'pay',
    ],
];
