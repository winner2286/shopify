<?php

return [
    // Nom officiel de la plateforme
    'name' => env('APP_NAME', 'Zanika'),
    'tagline' => 'Le commerce à votre manière.',
    'brand_color' => '#FF2D6D',
    'logo_path' => '/images/brand/01_logo_principal.jpg',
    'icon_path' => '/images/brand/02_icone_application.jpg',

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
        'zanika', 'boutiq', 'shopify', 'billing', 'login', 'register', 'dashboard', 'auth', 'root',
        'test', 'dev', 'status', 'account', 'shop', 'store', 'cart', 'checkout', 'pay',
    ],
];
