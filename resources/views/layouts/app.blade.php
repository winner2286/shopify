<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('platform.name').' : votre boutique en ligne créée par l\'IA')</title>
    <meta name="description" content="@yield('description', 'Créez, promouvez et gérez votre boutique en ligne en quelques minutes grâce à l\'intelligence artificielle. Paiement Mobile Money, carte et à la livraison.')">
    <meta name="theme-color" content="#09090b">
    <script>document.documentElement.setAttribute('data-theme','dark');try{localStorage.setItem('theme','dark');}catch(e){}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/brand/02_icone_application.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    @auth
        <script src="{{ asset('js/order-notifications.js') }}" defer></script>
    @endauth
</head>
<body>
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
                    <img src="{{ asset('images/brand/02_icone_application.jpg') }}" alt="Zanika" style="height:34px;width:34px;border-radius:9px;object-fit:cover;box-shadow:0 2px 10px rgba(255,45,109,0.35)">
                    <span style="font-family:'Montserrat',var(--font-display),sans-serif;font-weight:800;font-size:1.35rem;letter-spacing:-0.02em;color:#fff">ZANIKA</span>
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
