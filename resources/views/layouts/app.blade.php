<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('platform.name').' : votre boutique en ligne créée par l\'IA')</title>
    <meta name="description" content="@yield('description', 'Créez, promouvez et gérez votre boutique en ligne en quelques minutes grâce à l\'intelligence artificielle. Paiement Mobile Money, carte et à la livraison.')">
    <script>
        (function() {
            var savedTheme = 'dark';
            try {
                savedTheme = localStorage.getItem('theme') || 'dark';
            } catch(e) {}
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Icônes officielles Zanika pour Apple iOS (iPhone/iPad) & Android -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2">
    <link rel="apple-touch-icon-precomposed" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=2">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Zanika">
    <!-- Preload Splash Screen Assets pour fluidité maximale sans saccade -->
    <link rel="preload" as="image" href="{{ asset('images/brand/splash_icon.webp') }}" type="image/webp">
    <link rel="preload" as="image" href="{{ asset('images/brand/splash_wordmark.webp') }}" type="image/webp">
    <link rel="preload" as="image" href="{{ asset('images/brand/splash_bg.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ file_exists(public_path('css/app.css')) ? filemtime(public_path('css/app.css')) : '1.1' }}">
    <script src="{{ asset('js/app.js') }}?v={{ file_exists(public_path('js/app.js')) ? filemtime(public_path('js/app.js')) : '1.1' }}" defer></script>
    @auth
        <script src="{{ asset('js/order-notifications.js') }}?v={{ file_exists(public_path('js/order-notifications.js')) ? filemtime(public_path('js/order-notifications.js')) : '1.1' }}" defer></script>
    @endauth
</head>
<body>
    @include('partials.splash-screen')
    <div id="scroll-progress-bar" class="scroll-progress-bar" aria-hidden="true"></div>
    @guest
        <div class="top-announcement">
            <span>Plateforme e-commerce tout-en-un pour l'Afrique de l'Ouest • Paiements T-Money & Flooz intégrés • Essai gratuit 14 jours</span>
            <a href="{{ route('register') }}">Commencer maintenant →</a>
        </div>
    @endguest

    <header class="nav">
        <div class="container nav-inner">
            <div style="display:flex;align-items:center;gap:2rem">
                <a href="{{ route('home') }}" class="logo" aria-label="Accueil {{ config('platform.name') }}" style="display:inline-flex;align-items:center;gap:.65rem;text-decoration:none">
                    <img src="{{ asset('images/brand/02_icone_application.jpg') }}" alt="Zanika" style="height:34px;width:34px;border-radius:9px;object-fit:cover;box-shadow:0 2px 10px rgba(158,27,50,0.35)">
                    <span class="logo-text" style="font-family:'Montserrat',var(--font-display),sans-serif;font-weight:800;font-size:1.35rem;letter-spacing:-0.02em;">ZANIKA</span>
                </a>

                @guest
                    <ul class="nav-links">
                        <li><a href="{{ route('home') }}#solutions">Solutions</a></li>
                        <li><a href="{{ route('home') }}#fonctionnalites">Fonctionnalités</a></li>
                        <li><a href="{{ route('home') }}#tarifs">Tarifs</a></li>
                        <li><a href="{{ route('home') }}#temoignages">Témoignages</a></li>
                        <li><a href="{{ route('home') }}#faq">FAQ</a></li>
                    </ul>
                @endguest
            </div>

            <nav class="nav-actions" aria-label="Navigation principale">
                <!-- Bouton de bascule Mode Sombre / Mode Clair -->
                <button type="button" class="theme-toggle-btn" id="theme-toggle" aria-label="Changer de thème" title="Changer de thème">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                </button>
                @auth
                    @if (auth()->user()->shop && auth()->user()->shop->subdomain)
                        <a id="nav-preview-store" href="{{ url('/b/' . auth()->user()->shop->subdomain) }}" target="_blank" class="btn btn-ghost btn-sm" style="color:var(--brand-1);display:inline-flex;align-items:center;gap:.35rem" title="Voir ma boutique en direct">
                            <x-icon name="eye" :size="15" />
                            <span>Ma boutique</span>
                            <x-icon name="external-link" :size="12" />
                        </a>
                    @endif
                    <a id="nav-dashboard" href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('dashboard') ? 'btn-primary' : '' }}">Accueil</a>
                    <a id="nav-orders" href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('orders.*') ? 'btn-primary' : '' }}">Commandes</a>
                    <a id="nav-products" href="{{ route('products.index') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('products.*') ? 'btn-primary' : '' }}">Produits</a>
                    <a id="nav-categories" href="{{ route('categories.index') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('categories.*') ? 'btn-primary' : '' }}">Catégories</a>
                    <a id="nav-ads" href="{{ route('ads.index') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('ads.*') ? 'btn-primary' : '' }}">Publicité</a>
                    <a id="nav-analytics" href="{{ route('analytics.index') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('analytics.*') ? 'btn-primary' : '' }}">Statistiques</a>
                    <a id="nav-shop" href="{{ route('shop.settings') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('shop.*') ? 'btn-primary' : '' }}">Boutique</a>
                    @if (auth()->user()->isAdmin())
                        <a id="nav-admin" href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('admin.*') ? 'btn-primary' : '' }}" style="color:var(--brand-1);font-weight:600;display:inline-flex;align-items:center;gap:.35rem">
                            <x-icon name="shield" :size="15" />
                            <span>Admin</span>
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf
                        <button id="nav-logout" class="btn btn-ghost btn-sm" type="submit">Déconnexion</button>
                    </form>
                @else
                    <a id="nav-login" href="{{ route('login') }}" class="btn btn-ghost btn-sm">Connexion</a>
                    <a id="nav-register" href="{{ route('register') }}" class="btn btn-primary btn-sm">
                        <span class="btn-full-text">Démarrer l'essai gratuit</span>
                        <span class="btn-short-text">Essai gratuit</span>
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    @auth
        <!-- Navigation mobile inférieure (Section 9 du CDC : Accueil, Commandes, Produits, Publicité, Plus) -->
        <nav class="bottom-nav" aria-label="Navigation mobile">
            <div class="bottom-nav-inner">
                <a href="{{ route('dashboard') }}" class="bottom-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="icon" aria-hidden="true"><x-icon name="home" :size="20" /></span>
                    <span>Accueil</span>
                </a>
                <a href="{{ route('orders.index') }}" class="bottom-nav-item {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                    <span class="icon" aria-hidden="true"><x-icon name="orders" :size="20" /></span>
                    <span>Commandes</span>
                </a>
                <a href="{{ route('products.index') }}" class="bottom-nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}">
                    <span class="icon" aria-hidden="true"><x-icon name="products" :size="20" /></span>
                    <span>Produits</span>
                </a>
                <a href="{{ route('ads.index') }}" class="bottom-nav-item {{ request()->routeIs('ads.*') ? 'active' : '' }}">
                    <span class="icon" aria-hidden="true"><x-icon name="ads" :size="20" /></span>
                    <span>Publicité</span>
                </a>
                <a href="{{ route('categories.index') }}" class="bottom-nav-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                    <span class="icon" aria-hidden="true"><x-icon name="categories" :size="20" /></span>
                    <span>Plus</span>
                </a>
            </div>
        </nav>
    @endauth

    @hasSection('footer')
        @yield('footer')
    @endif
</body>
</html>
