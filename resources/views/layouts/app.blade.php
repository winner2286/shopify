<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=3">
    <link rel="apple-touch-icon-precomposed" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=3">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=3">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#07080C" media="(prefers-color-scheme: dark)">
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
<body class="@auth has-bottom-nav @endauth">
    @guest
        @include('partials.splash-screen')
    @endguest
    <div id="scroll-progress-bar" class="scroll-progress-bar" aria-hidden="true"></div>
    @if (app()->isDownForMaintenance())
        <div style="background:linear-gradient(90deg, #dc2626, #991b1b);color:#ffffff;padding:.55rem 1rem;font-size:.88rem;font-weight:700;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:99998;box-shadow:0 4px 12px rgba(0,0,0,0.3);flex-wrap:wrap;gap:.5rem">
            <div style="display:flex;align-items:center;gap:.6rem">
                <span style="display:inline-block;width:9px;height:9px;background:#ffffff;border-radius:50%;box-shadow:0 0 8px #ffffff"></span>
                <span>🔴 ATTENTION : Le site est en MODE MAINTENANCE. L'accès public est restreint à la page 503.</span>
            </div>
            <a href="{{ route('admin.maintenance') }}" class="btn btn-sm" style="background:#ffffff;color:#991b1b;font-weight:700;border:none;padding:.25rem .75rem;border-radius:6px;text-decoration:none">
                Gérer la maintenance
            </a>
        </div>
    @endif
    @if (session('admin_impersonator_id'))
        <div style="background:linear-gradient(90deg, #d97706, #b45309);color:#ffffff;padding:.65rem 1rem;font-size:.9rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,0.3);flex-wrap:wrap;gap:.5rem">
            <div style="display:flex;align-items:center;gap:.6rem">
                <x-icon name="shield" size="18" />
                <span>Mode Support Client Actif : Vous administrez la boutique de <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->shop?->name ?: auth()->user()->email }}).</span>
            </div>
            <form method="POST" action="{{ route('admin.impersonate.leave') }}" style="margin:0">
                @csrf
                <button type="submit" class="btn btn-sm" style="background:#ffffff;color:#92400e;font-weight:700;border:none;padding:.3rem .8rem;border-radius:6px;cursor:pointer">
                    ← Quitter le mode support & revenir à l'Admin
                </button>
            </form>
        </div>
    @endif
    @guest
        <div class="top-announcement">
            <span>Plateforme e-commerce tout-en-un pour l'Afrique de l'Ouest • Paiements T-Money & Flooz intégrés • Essai gratuit 14 jours</span>
            <a href="{{ route('register') }}">Commencer maintenant →</a>
        </div>
    @endguest

    <header class="nav">
        <div class="nav-container nav-inner">
            <div class="nav-brand-wrap" style="display:flex;align-items:center;gap:1.5rem;flex-shrink:0">
                <a href="{{ auth()->check() ? (auth()->user()->isAdmin() ? route('admin.dashboard') : route('dashboard')) : route('home') }}" class="logo" aria-label="Accueil {{ config('platform.name') }}" style="display:inline-flex;align-items:center;gap:.65rem;text-decoration:none;flex-shrink:0">
                    <img src="{{ asset('images/brand/02_icone_application.jpg') }}" alt="Zanika" style="height:34px;width:34px;border-radius:9px;object-fit:cover;box-shadow:0 2px 10px rgba(158,27,50,0.35);flex-shrink:0">
                    <span class="logo-text" style="font-family:'Montserrat',var(--font-display),sans-serif;font-weight:800;font-size:1.35rem;letter-spacing:-0.02em;white-space:nowrap">ZANIKA</span>
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

                @auth
                    <nav class="nav-primary-links" aria-label="Navigation principale">
                        <a id="nav-dashboard" href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('dashboard') ? 'btn-primary' : '' }}">Accueil</a>
                        <a id="nav-orders" href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('orders.*') ? 'btn-primary' : '' }}">Commandes</a>
                        <a id="nav-products" href="{{ route('products.index') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('products.*') ? 'btn-primary' : '' }}">Produits</a>
                        <a id="nav-ads" href="{{ route('ads.index') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('ads.*') ? 'btn-primary' : '' }}">Publicité</a>

                        <!-- Liens secondaires (visibles directement sur écran large >= 1240px) -->
                        <a id="nav-categories" href="{{ route('categories.index') }}" class="btn btn-ghost btn-sm nav-desktop-secondary {{ request()->routeIs('categories.*') ? 'btn-primary' : '' }}">Catégories</a>
                        <a id="nav-analytics" href="{{ route('analytics.index') }}" class="btn btn-ghost btn-sm nav-desktop-secondary {{ request()->routeIs('analytics.*') ? 'btn-primary' : '' }}">Statistiques</a>
                        <a id="nav-shop" href="{{ route('shop.settings') }}" class="btn btn-ghost btn-sm nav-desktop-secondary {{ request()->routeIs('shop.*') ? 'btn-primary' : '' }}">Paramètres</a>

                        <!-- Menu déroulant "Plus ▾" (actif sur écran compact 769px à 1239px) -->
                        <div class="nav-more-dropdown" id="nav-more-dropdown">
                            <button type="button" class="btn btn-ghost btn-sm nav-more-trigger {{ (request()->routeIs('categories.*') || request()->routeIs('analytics.*') || request()->routeIs('shop.*')) ? 'active-section' : '' }}" aria-haspopup="true" aria-expanded="false" onclick="toggleNavMoreMenu(event)">
                                <span>Plus</span>
                                <x-icon name="chevron-down" :size="12" />
                            </button>
                            <div class="nav-more-menu" id="nav-more-menu">
                                <a href="{{ route('categories.index') }}" class="nav-more-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                                    <x-icon name="folder" :size="15" />
                                    <span>Catégories</span>
                                </a>
                                <a href="{{ route('analytics.index') }}" class="nav-more-item {{ request()->routeIs('analytics.*') ? 'active' : '' }}">
                                    <x-icon name="trending-up" :size="15" />
                                    <span>Statistiques</span>
                                </a>
                                <a href="{{ route('shop.settings') }}" class="nav-more-item {{ request()->routeIs('shop.*') ? 'active' : '' }}">
                                    <x-icon name="settings" :size="15" />
                                    <span>Paramètres</span>
                                </a>
                                <div style="height:1px;background:var(--border);margin:.2rem 0"></div>
                                <button type="button" class="nav-more-item" onclick="openInstallGuideModal()" style="width:100%;background:transparent;border:none;text-align:left;font-family:inherit;cursor:pointer;color:var(--brand-1)">
                                    <x-icon name="download" :size="15" />
                                    <span>Installer l'application</span>
                                </button>
                            </div>
                        </div>
                    </nav>
                @endauth
            </div>

            <nav class="nav-actions" aria-label="Outils et profil">
                @auth
                    @if (auth()->user()->isAdmin())
                        <a id="nav-admin" href="{{ route('admin.dashboard') }}" class="btn btn-sm {{ request()->routeIs('admin.*') ? 'mobile-hide-on-admin' : '' }}" style="background:linear-gradient(135deg, #ef4444, #dc2626);color:#ffffff;font-weight:700;display:inline-flex;align-items:center;gap:.35rem;border:none;box-shadow:0 2px 8px rgba(239, 68, 68, 0.4);" title="Cockpit Super-Admin">
                            <x-icon name="shield" :size="14" />
                            <span class="nav-admin-full">⭐ Cockpit Admin</span>
                            <span class="nav-admin-short">⭐ Cockpit</span>
                        </a>
                    @endif
                    @if (auth()->user()->shop && auth()->user()->shop->subdomain)
                        <a id="nav-preview-store" href="{{ url('/b/' . auth()->user()->shop->subdomain) }}" target="_blank" class="btn btn-ghost btn-sm" style="color:var(--brand-1);display:inline-flex;align-items:center;gap:.35rem" title="Voir ma boutique en direct">
                            <x-icon name="eye" :size="14" />
                            <span class="nav-btn-text">Ma boutique</span>
                            <x-icon name="external-link" :size="11" />
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" style="display:inline;margin:0">@csrf
                        <button id="nav-logout" class="btn btn-ghost btn-sm" type="submit" title="Déconnexion" aria-label="Déconnexion">
                            <span class="logout-text">Déconnexion</span>
                            <span class="logout-icon" aria-hidden="true"><x-icon name="log-out" :size="16" /></span>
                        </button>
                    </form>
                @else
                    <a id="nav-login" href="{{ route('login') }}" class="btn btn-ghost btn-sm">Connexion</a>
                    <a id="nav-register" href="{{ route('register') }}" class="btn btn-primary btn-sm">
                        <span class="btn-full-text">Démarrer l'essai gratuit</span>
                        <span class="btn-short-text">Essai gratuit</span>
                    </a>
                @endauth

                <!-- Bouton de bascule Mode Sombre / Mode Clair (à l'extrémité droite) -->
                <button type="button" class="theme-toggle-btn" id="theme-toggle" aria-label="Changer de thème" title="Changer de thème">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                </button>
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
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="bottom-nav-item admin-cockpit-link {{ request()->routeIs('admin.*') ? 'active' : '' }}">
                        <span class="icon" aria-hidden="true"><x-icon name="shield" :size="23" /></span>
                        <span>Cockpit</span>
                    </a>
                @endif
                <a href="{{ route('dashboard') }}" class="bottom-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="icon" aria-hidden="true"><x-icon name="home" :size="23" /></span>
                    <span>Accueil</span>
                </a>
                <a href="{{ route('orders.index') }}" class="bottom-nav-item {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                    <span class="icon" aria-hidden="true"><x-icon name="orders" :size="23" /></span>
                    <span>Commandes</span>
                </a>
                <a href="{{ route('products.index') }}" class="bottom-nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}">
                    <span class="icon" aria-hidden="true"><x-icon name="products" :size="23" /></span>
                    <span>Produits</span>
                </a>
                <a href="{{ route('ads.index') }}" class="bottom-nav-item {{ request()->routeIs('ads.*') ? 'active' : '' }}">
                    <span class="icon" aria-hidden="true"><x-icon name="ads" :size="23" /></span>
                    <span>Publicité</span>
                </a>
                <button type="button" class="bottom-nav-item bottom-nav-plus-btn {{ request()->routeIs(['analytics.*', 'shop.*', 'customers.*', 'shipping.*', 'categories.*']) ? 'active' : '' }}" onclick="toggleMobilePlusMenu(event)" aria-label="Menu Plus d'options">
                    <span class="icon" aria-hidden="true"><x-icon name="grid" :size="23" /></span>
                    <span>Plus</span>
                </button>
            </div>
        </nav>

        <!-- Menu Plus Mobile (Slide-Up Sheet pour Statistiques, Paramètres, Clients, Livraisons, etc.) -->
        <div id="mobile-plus-backdrop" class="mobile-plus-backdrop" onclick="closeMobilePlusMenu()" aria-hidden="true"></div>
        <div id="mobile-plus-sheet" class="mobile-plus-sheet" role="dialog" aria-modal="true" aria-labelledby="mobile-plus-title">
            <div class="mobile-plus-handle-wrap" id="mobile-plus-handle-wrap" onclick="closeMobilePlusMenu()" title="Glisser vers le bas pour fermer">
                <div class="mobile-plus-handle"></div>
            </div>
            <div class="mobile-plus-head" id="mobile-plus-head">
                <h3 id="mobile-plus-title" style="margin:0;font-size:1.05rem;font-weight:700">Outils & Gestion</h3>
                <button type="button" class="mobile-plus-close" onclick="closeMobilePlusMenu()" aria-label="Fermer le menu">✕</button>
            </div>
            <div class="mobile-plus-grid">
                <a href="{{ route('analytics.index') }}" class="mobile-plus-item {{ request()->routeIs('analytics.*') ? 'active' : '' }}" onclick="closeMobilePlusMenu()">
                    <div class="mobile-plus-icon" style="background:rgba(158, 27, 50, 0.12);color:var(--brand-1)">
                        <x-icon name="analytics" :size="22" />
                    </div>
                    <div class="mobile-plus-info">
                        <strong>Statistiques & Performance</strong>
                        <span>Chiffre d'affaires, panier moyen & alertes de stock</span>
                    </div>
                    <x-icon name="arrow-right" :size="16" />
                </a>

                <a href="{{ route('shop.settings') }}" class="mobile-plus-item {{ request()->routeIs('shop.*') ? 'active' : '' }}" onclick="closeMobilePlusMenu()">
                    <div class="mobile-plus-icon" style="background:rgba(59, 130, 246, 0.12);color:#3b82f6">
                        <x-icon name="settings" :size="22" />
                    </div>
                    <div class="mobile-plus-info">
                        <strong>Paramètres de la Boutique</strong>
                        <span>Identité, logo, T-Money, Flooz & coordonnées</span>
                    </div>
                    <x-icon name="arrow-right" :size="16" />
                </a>

                <a href="{{ route('customers.index') }}" class="mobile-plus-item {{ request()->routeIs('customers.*') ? 'active' : '' }}" onclick="closeMobilePlusMenu()">
                    <div class="mobile-plus-icon" style="background:rgba(16, 185, 129, 0.12);color:#10b981">
                        <x-icon name="users" :size="22" />
                    </div>
                    <div class="mobile-plus-info">
                        <strong>Fichier Clients</strong>
                        <span>Répertoire des acheteurs et historique d'achats</span>
                    </div>
                    <x-icon name="arrow-right" :size="16" />
                </a>

                <a href="{{ route('shipping.index') }}" class="mobile-plus-item {{ request()->routeIs('shipping.*') ? 'active' : '' }}" onclick="closeMobilePlusMenu()">
                    <div class="mobile-plus-icon" style="background:rgba(245, 158, 11, 0.12);color:#f59e0b">
                        <x-icon name="truck" :size="22" />
                    </div>
                    <div class="mobile-plus-info">
                        <strong>Frais de Livraison</strong>
                        <span>Tarifs par commune & zones de distribution</span>
                    </div>
                    <x-icon name="arrow-right" :size="16" />
                </a>

                <a href="{{ route('categories.index') }}" class="mobile-plus-item {{ request()->routeIs('categories.*') ? 'active' : '' }}" onclick="closeMobilePlusMenu()">
                    <div class="mobile-plus-icon" style="background:rgba(139, 92, 246, 0.12);color:#8b5cf6">
                        <x-icon name="categories" :size="22" />
                    </div>
                    <div class="mobile-plus-info">
                        <strong>Gestion des Catégories</strong>
                        <span>Rayons, organisation et classement des articles</span>
                    </div>
                    <x-icon name="arrow-right" :size="16" />
                </a>

                <button type="button" class="mobile-plus-item" onclick="openInstallGuideModal()" style="width:100%;background:linear-gradient(135deg, rgba(158,27,50,0.12), rgba(244,63,94,0.08));border:1px solid rgba(158,27,50,0.3);text-align:left;font-family:inherit;cursor:pointer">
                    <div class="mobile-plus-icon" style="background:linear-gradient(135deg, var(--brand-1), #dc2626);color:#ffffff;box-shadow:0 3px 10px rgba(158,27,50,0.35)">
                        <x-icon name="download" :size="20" />
                    </div>
                    <div class="mobile-plus-info">
                        <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap">
                            <strong style="color:var(--brand-1)">Installer sur le téléphone</strong>
                            <span style="background:var(--brand-1);color:#fff;font-size:.65rem;font-weight:700;padding:.12rem .45rem;border-radius:999px">App mobile</span>
                        </div>
                        <span>Mettre le site sur votre écran d'accueil en 1 clic</span>
                    </div>
                    <x-icon name="arrow-right" :size="16" style="color:var(--brand-1)" />
                </button>
            </div>
        </div>
        <script>
            var _mobilePlusScrollPos = 0;
            function openMobilePlusMenu(e) {
                if (e) {
                    if (e.preventDefault) e.preventDefault();
                    if (e.stopPropagation) e.stopPropagation();
                }
                var s = document.getElementById('mobile-plus-sheet');
                var b = document.getElementById('mobile-plus-backdrop');
                if (s && b) {
                    s.style.transform = '';
                    s.style.transition = '';
                    b.style.opacity = '';
                    b.style.transition = '';
                    _mobilePlusScrollPos = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
                    document.body.style.position = 'fixed';
                    document.body.style.top = -_mobilePlusScrollPos + 'px';
                    document.body.style.left = '0';
                    document.body.style.right = '0';
                    document.body.style.width = '100%';
                    document.body.style.overflow = 'hidden';
                    document.documentElement.style.overflow = 'hidden';
                    s.classList.add('open');
                    b.classList.add('open');
                }
            }
            function closeMobilePlusMenu() {
                var s = document.getElementById('mobile-plus-sheet');
                var b = document.getElementById('mobile-plus-backdrop');
                if (s && b) {
                    s.classList.remove('open');
                    b.classList.remove('open');
                    s.style.transform = '';
                    s.style.transition = '';
                    b.style.opacity = '';
                    b.style.transition = '';
                    document.body.style.position = '';
                    document.body.style.top = '';
                    document.body.style.left = '';
                    document.body.style.right = '';
                    document.body.style.width = '';
                    document.body.style.overflow = '';
                    document.documentElement.style.overflow = '';
                    window.scrollTo(0, _mobilePlusScrollPos);
                }
            }
            function toggleMobilePlusMenu(e) {
                var s = document.getElementById('mobile-plus-sheet');
                if (s && s.classList.contains('open')) { closeMobilePlusMenu(); } else { openMobilePlusMenu(e); }
            }

            document.addEventListener('DOMContentLoaded', function() {
                var b = document.getElementById('mobile-plus-backdrop');
                var sheet = document.getElementById('mobile-plus-sheet');
                var handleWrap = document.getElementById('mobile-plus-handle-wrap');
                var head = document.getElementById('mobile-plus-head');

                if (b) {
                    b.addEventListener('touchmove', function(ev) { ev.preventDefault(); }, { passive: false });
                }

                if (!sheet) return;

                var startY = 0;
                var currentY = 0;
                var deltaY = 0;
                var isDragging = false;
                var startTime = 0;

                function handleTouchStart(e) {
                    if (!sheet.classList.contains('open')) return;
                    var touch = e.touches[0];
                    startY = touch.clientY;
                    currentY = startY;
                    deltaY = 0;
                    startTime = Date.now();

                    // Si le geste commence sur la poignée (handle) ou l'en-tête (head)
                    if ((handleWrap && (handleWrap.contains(e.target) || e.target === handleWrap)) ||
                        (head && (head.contains(e.target) || e.target === head))) {
                        isDragging = true;
                        sheet.style.transition = 'none';
                        if (b) b.style.transition = 'none';
                    } else {
                        isDragging = false;
                    }
                }

                function handleTouchMove(e) {
                    if (!sheet.classList.contains('open')) return;
                    var touch = e.touches[0];
                    currentY = touch.clientY;
                    var moveY = currentY - startY;

                    // Si on est en haut du défilement interne et qu'on tire vers le bas
                    if (!isDragging && sheet.scrollTop <= 0 && moveY > 10) {
                        isDragging = true;
                        startY = currentY;
                        deltaY = 0;
                        sheet.style.transition = 'none';
                        if (b) b.style.transition = 'none';
                    }

                    if (isDragging) {
                        deltaY = currentY - startY;
                        if (deltaY > 0) {
                            if (e.cancelable) e.preventDefault();
                            sheet.style.transform = 'translateY(' + deltaY + 'px)';
                            if (b) {
                                var opacity = Math.max(0, 1 - (deltaY / 320));
                                b.style.opacity = opacity;
                            }
                        } else {
                            sheet.style.transform = 'translateY(0)';
                        }
                    }
                }

                function handleTouchEnd(e) {
                    if (!isDragging) return;
                    isDragging = false;
                    var elapsed = Date.now() - startTime;
                    var velocity = deltaY / Math.max(1, elapsed);

                    sheet.style.transition = 'transform 0.25s cubic-bezier(0.16, 1, 0.3, 1)';
                    if (b) b.style.transition = 'opacity 0.25s ease';

                    // Si tiré vers le bas de plus de 80px ou swipe dynamique rapide vers le bas
                    if (deltaY > 80 || (deltaY > 35 && velocity > 0.4)) {
                        sheet.style.transform = 'translateY(105%)';
                        if (b) b.style.opacity = '0';
                        setTimeout(function() {
                            closeMobilePlusMenu();
                        }, 250);
                    } else {
                        // Rebond propre à la position ouverte
                        sheet.style.transform = 'translateY(0)';
                        if (b) b.style.opacity = '1';
                        setTimeout(function() {
                            sheet.style.transform = '';
                            sheet.style.transition = '';
                            if (b) { b.style.opacity = ''; b.style.transition = ''; }
                        }, 250);
                    }
                }

                sheet.addEventListener('touchstart', handleTouchStart, { passive: true });
                sheet.addEventListener('touchmove', handleTouchMove, { passive: false });
                sheet.addEventListener('touchend', handleTouchEnd, { passive: true });
                sheet.addEventListener('touchcancel', handleTouchEnd, { passive: true });
            });

            function toggleNavMoreMenu(e) {
                if (e) e.stopPropagation();
                var menu = document.getElementById('nav-more-menu');
                var trigger = document.querySelector('.nav-more-trigger');
                if (menu) {
                    var isOpen = menu.classList.toggle('open');
                    if (trigger) trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }
            }
            document.addEventListener('click', function(e) {
                var wrap = document.getElementById('nav-more-dropdown');
                if (wrap && !wrap.contains(e.target)) {
                    var menu = document.getElementById('nav-more-menu');
                    var trigger = document.querySelector('.nav-more-trigger');
                    if (menu) menu.classList.remove('open');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                }
            });
        </script>

        <!-- Modal Guide Installation Écran d'accueil (PWA / Raccourci Mobile) -->
        <div id="install-guide-modal" class="install-guide-modal" role="dialog" aria-modal="true" aria-labelledby="install-modal-title" style="display:none">
            <div class="install-guide-backdrop" onclick="closeInstallGuideModal()"></div>
            <div class="install-guide-dialog">
                <!-- En-tête -->
                <div class="install-guide-header">
                    <div class="install-app-preview">
                        <img src="{{ asset('apple-touch-icon.png') }}?v=3" alt="Zanika" class="install-app-icon" width="50" height="50">
                        <div>
                            <h3 id="install-modal-title" class="install-modal-title">Installer Zanika</h3>
                            <p class="install-modal-subtitle">Accédez à votre boutique en 1 clic depuis votre écran d'accueil</p>
                        </div>
                    </div>
                    <button type="button" class="install-modal-close" onclick="closeInstallGuideModal()" aria-label="Fermer">✕</button>
                </div>

                <!-- Onglets OS (iOS / Android) -->
                <div class="install-tabs-nav" role="tablist">
                    <button type="button" id="tab-btn-ios" class="install-tab-btn active" role="tab" aria-selected="true" onclick="switchInstallTab('ios')">
                        <span class="tab-emoji">🍎</span>
                        <span>iPhone & iPad</span>
                        <small class="tab-sub">Navigateur Safari</small>
                    </button>
                    <button type="button" id="tab-btn-android" class="install-tab-btn" role="tab" aria-selected="false" onclick="switchInstallTab('android')">
                        <span class="tab-emoji">🤖</span>
                        <span>Android</span>
                        <small class="tab-sub">Chrome / Samsung</small>
                    </button>
                </div>

                <!-- Bouton d'installation native (si supporté par Chrome / Android) -->
                <div id="pwa-native-install-wrap" style="display:none;margin-bottom:1rem">
                    <button type="button" id="pwa-native-install-btn" class="btn btn-primary" style="width:100%;padding:.85rem;font-size:1rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:.5rem;box-shadow:0 4px 16px rgba(158,27,50,0.4)">
                        <x-icon name="download" :size="20" />
                        <span>Installer l'application maintenant</span>
                    </button>
                </div>

                <!-- Contenu Onglet iOS (Safari) -->
                <div id="install-content-ios" class="install-tab-content active" role="tabpanel">
                    <div class="install-steps-list">
                        <div class="install-step-item">
                            <div class="install-step-badge">1</div>
                            <div class="install-step-body">
                                <strong>Appuyez sur le bouton Partager</strong>
                                <p>Dans <strong>Safari</strong>, touchez l'icône de partage 
                                    <span class="ios-share-badge">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;display:inline-block"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                                    </span> 
                                    située dans la barre de menu en bas de votre écran.
                                </p>
                            </div>
                        </div>

                        <div class="install-step-item">
                            <div class="install-step-badge">2</div>
                            <div class="install-step-body">
                                <strong>Sélectionnez « Sur l'écran d'accueil »</strong>
                                <p>Faites défiler le menu vers le bas et touchez l'option 
                                    <span class="ios-action-badge">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;display:inline-block"><rect width="18" height="18" x="3" y="3" rx="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                        Sur l'écran d'accueil
                                    </span>.
                                </p>
                            </div>
                        </div>

                        <div class="install-step-item">
                            <div class="install-step-badge">3</div>
                            <div class="install-step-body">
                                <strong>Touchez « Ajouter »</strong>
                                <p>Confirmez en haut à droite. L'icône Zanika est désormais sur votre téléphone et s'ouvrira en plein écran comme une vraie application !</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenu Onglet Android (Chrome / Samsung) -->
                <div id="install-content-android" class="install-tab-content" role="tabpanel" style="display:none">
                    <div class="install-steps-list">
                        <div class="install-step-item">
                            <div class="install-step-badge">1</div>
                            <div class="install-step-body">
                                <strong>Ouvrez le menu Chrome</strong>
                                <p>Touchez les <strong>3 petits points ⋮</strong> tout en haut à droite du navigateur.</p>
                            </div>
                        </div>

                        <div class="install-step-item">
                            <div class="install-step-badge">2</div>
                            <div class="install-step-body">
                                <strong>Sélectionnez « Installer l'application »</strong>
                                <p>Appuyez sur <strong>« Installer l'application »</strong> (ou <strong>« Ajouter à l'écran d'accueil »</strong> selon votre téléphone).</p>
                            </div>
                        </div>

                        <div class="install-step-item">
                            <div class="install-step-badge">3</div>
                            <div class="install-step-body">
                                <strong>Validez l'installation</strong>
                                <p>Confirmez. L'application Zanika est prête à être utilisée depuis votre écran d'accueil !</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pied de modale -->
                <div class="install-guide-footer">
                    <div class="install-footer-note">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981;flex-shrink:0"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>Ultra-léger, aucune place prise en mémoire, mises à jour automatiques.</span>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="closeInstallGuideModal()" style="font-weight:600">J'ai compris</button>
                </div>
            </div>
        </div>

        <script>
            var _deferredInstallPrompt = null;
            window.addEventListener('beforeinstallprompt', function(e) {
                e.preventDefault();
                _deferredInstallPrompt = e;
                var wrap = document.getElementById('pwa-native-install-wrap');
                if (wrap) wrap.style.display = 'block';
            });

            function openInstallGuideModal() {
                closeMobilePlusMenu();
                var m = document.getElementById('install-guide-modal');
                if (!m) return;
                
                var ua = navigator.userAgent || navigator.vendor || window.opera || '';
                var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                
                switchInstallTab(isIOS ? 'ios' : 'android');
                m.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }

            function closeInstallGuideModal() {
                var m = document.getElementById('install-guide-modal');
                if (m) {
                    m.style.display = 'none';
                    document.body.style.overflow = '';
                }
            }

            function switchInstallTab(platform) {
                var tabIos = document.getElementById('tab-btn-ios');
                var tabAndroid = document.getElementById('tab-btn-android');
                var contentIos = document.getElementById('install-content-ios');
                var contentAndroid = document.getElementById('install-content-android');
                
                if (platform === 'ios') {
                    if (tabIos) { tabIos.classList.add('active'); tabIos.setAttribute('aria-selected', 'true'); }
                    if (tabAndroid) { tabAndroid.classList.remove('active'); tabAndroid.setAttribute('aria-selected', 'false'); }
                    if (contentIos) contentIos.style.display = 'block';
                    if (contentAndroid) contentAndroid.style.display = 'none';
                } else {
                    if (tabAndroid) { tabAndroid.classList.add('active'); tabAndroid.setAttribute('aria-selected', 'true'); }
                    if (tabIos) { tabIos.classList.remove('active'); tabIos.setAttribute('aria-selected', 'false'); }
                    if (contentAndroid) contentAndroid.style.display = 'block';
                    if (contentIos) contentIos.style.display = 'none';
                }
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeInstallGuideModal();
                }
            });

            document.addEventListener('DOMContentLoaded', function() {
                var nativeBtn = document.getElementById('pwa-native-install-btn');
                if (nativeBtn) {
                    nativeBtn.addEventListener('click', function() {
                        if (_deferredInstallPrompt) {
                            _deferredInstallPrompt.prompt();
                            _deferredInstallPrompt.userChoice.then(function(choice) {
                                if (choice.outcome === 'accepted') {
                                    closeInstallGuideModal();
                                }
                                _deferredInstallPrompt = null;
                            });
                        }
                    });
                }
            });
        </script>
    @endauth

    @hasSection('footer')
        @yield('footer')
    @endif
</body>
</html>
