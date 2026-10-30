<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $shop->name . ' : Boutique en ligne')</title>
    <meta name="description" content="@yield('description', $shop->description ?: 'Découvrez notre sélection d\'articles de qualité sur notre boutique en ligne. Livraison rapide et paiement sécurisé.')">
    <meta name="theme-color" content="{{ $shop->brand_color ?: '#6d3df2' }}">

    <!-- Balises Open Graph pour WhatsApp, Facebook & Réseaux sociaux -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ $shop->name }}">
    <meta property="og:title" content="@yield('title', $shop->name . ' : Boutique officielle')">
    <meta property="og:description" content="@yield('description', $shop->description ?: 'Découvrez notre sélection de produits sur notre boutique en ligne. Livraison rapide et paiement sécurisé.')">
    @if ($shop->logo_path)
        <meta property="og:image" content="{{ asset('storage/' . $shop->logo_path) }}">
    @else
        <meta property="og:image" content="{{ url('/api/placeholder-image?title=' . urlencode($shop->name) . '&color=' . urlencode($shop->brand_color ?: '#10b981')) }}">
    @endif

    <!-- Icônes pour Apple iOS (iPhone/iPad) & Android -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $shop->logo_path ? asset('storage/' . $shop->logo_path) : asset('apple-touch-icon.png') }}?v=3">
    <link rel="apple-touch-icon-precomposed" sizes="180x180" href="{{ $shop->logo_path ? asset('storage/' . $shop->logo_path) : asset('apple-touch-icon.png') }}?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=3">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ file_exists(public_path('css/app.css')) ? filemtime(public_path('css/app.css')) : '1.1' }}">
    <script src="{{ asset('js/storefront.js') }}?v={{ file_exists(public_path('js/storefront.js')) ? filemtime(public_path('js/storefront.js')) : '1.1' }}" defer></script>
    <script src="{{ asset('js/app.js') }}?v={{ file_exists(public_path('js/app.js')) ? filemtime(public_path('js/app.js')) : '1.1' }}" defer></script>

    <style>
        :root {
            --store-primary: {{ $shop->brand_color ?: '#6d3df2' }};
            --store-primary-soft: color-mix(in srgb, {{ $shop->brand_color ?: '#6d3df2' }} 15%, transparent);
            --store-primary-glow: color-mix(in srgb, {{ $shop->brand_color ?: '#6d3df2' }} 35%, transparent);
            --store-primary-hover: color-mix(in srgb, {{ $shop->brand_color ?: '#6d3df2' }} 85%, #000);
            --font: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-display: 'Outfit', var(--font);
        }

        /* Animations & Keyframes */
        @keyframes floatGlow {
            0% { transform: translate(-50%, -20%) scale(1); opacity: 0.45; }
            50% { transform: translate(-45%, -12%) scale(1.15); opacity: 0.8; }
            100% { transform: translate(-55%, -22%) scale(0.95); opacity: 0.45; }
        }
        @keyframes pulseLive {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.75); }
            50% { transform: scale(1.1); box-shadow: 0 0 0 7px rgba(34, 197, 94, 0); }
        }
        @keyframes marqueeScroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        @keyframes radarPulse {
            0% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.65); }
            70% { box-shadow: 0 0 0 16px rgba(37, 211, 102, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
        }
        @keyframes badgeBounce {
            0%, 100% { transform: scale(1); }
            40% { transform: scale(1.4); }
        }
        @keyframes toastSlideIn {
            from { transform: translateY(40px) scale(0.94); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        @keyframes discountPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.06); }
        }
        @keyframes spinSlow {
            to { transform: rotate(360deg); }
        }
        @keyframes shimmerBtn {
            0% { transform: translateX(-150%); }
            100% { transform: translateX(250%); }
        }

        .store-header {
            position: sticky; top: 0; z-index: 40;
            backdrop-filter: blur(16px);
            background: color-mix(in srgb, var(--bg) 85%, transparent);
            border-bottom: 1px solid var(--border);
            transition: all .25s ease;
        }
        .store-header.scrolled {
            background: color-mix(in srgb, var(--bg) 95%, transparent);
            border-bottom-color: color-mix(in srgb, var(--store-primary) 30%, var(--border));
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        .store-header-inner {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; height: 68px;
            transition: height .25s ease;
        }
        .store-header.scrolled .store-header-inner {
            height: 58px;
        }
        .store-scroll-progress {
            position: absolute; bottom: -1px; left: 0; height: 2px; width: 0%;
            background: linear-gradient(90deg, var(--store-primary), #25D366);
            box-shadow: 0 0 10px var(--store-primary);
            transition: width .1s ease-out;
            z-index: 41;
            pointer-events: none;
        }
        .store-brand {
            display: flex; align-items: center; gap: .75rem;
            color: var(--text); font-family: var(--font-display);
            font-weight: 700; font-size: 1.25rem; text-decoration: none;
            transition: transform .2s;
        }
        .store-brand:hover {
            transform: scale(1.02);
        }
        .store-logo {
            width: 40px; height: 40px; border-radius: 10px;
            background: var(--store-primary); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.1rem; flex-shrink: 0;
            box-shadow: 0 4px 14px var(--store-primary-glow);
            overflow: hidden;
        }
        .store-logo svg, .store-logo img {
            width: 100% !important; height: 100% !important;
            max-width: 40px !important; max-height: 40px !important;
            object-fit: cover; display: block;
        }
        .store-nav-actions {
            display: flex; align-items: center; gap: .75rem;
        }
        .cart-pill {
            display: inline-flex; align-items: center; gap: .5rem;
            background: var(--store-primary-soft); color: var(--text);
            border: 1px solid color-mix(in srgb, var(--store-primary) 30%, transparent);
            padding: .45rem .9rem; border-radius: 20px; font-weight: 600; font-size: .9rem;
            transition: all .2s;
        }
        .cart-pill:hover {
            background: var(--store-primary); color: #fff;
            box-shadow: 0 4px 14px var(--store-primary-glow);
        }
        .cart-badge {
            background: var(--store-primary); color: #fff;
            width: 20px; height: 20px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700;
            transition: transform .2s;
        }
        .cart-pill:hover .cart-badge {
            background: #fff; color: var(--store-primary);
        }
        .badge-bounce {
            animation: badgeBounce .4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-store {
            background: var(--store-primary); color: #fff; border: none;
            padding: .75rem 1.4rem; border-radius: var(--radius-sm);
            font-weight: 600; font-size: .95rem; cursor: pointer;
            transition: transform .2s, box-shadow .2s;
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            box-shadow: 0 6px 18px var(--store-primary-glow);
            position: relative; overflow: hidden; text-decoration: none;
        }
        .btn-store:hover {
            transform: translateY(-2px); box-shadow: 0 8px 24px var(--store-primary-glow); color: #fff;
        }
        .btn-store::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
            transform: translateX(-150%);
            animation: shimmerBtn 4s infinite ease-in-out;
        }
        .btn-whatsapp {
            background: #25D366; color: #fff; border: none;
            padding: .75rem 1.4rem; border-radius: var(--radius-sm);
            font-weight: 600; font-size: .95rem; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            text-decoration: none; box-shadow: 0 6px 18px rgba(37, 211, 102, 0.35);
            transition: transform .2s, box-shadow .2s;
        }
        .btn-whatsapp:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37, 211, 102, 0.45); color: #fff; }

        /* Dynamic Animated Hero */
        .store-hero {
            position: relative;
            padding: 4.5rem 0 3.5rem;
            text-align: center;
            overflow: hidden;
            border-bottom: 1px solid var(--border);
            background: radial-gradient(circle at 50% 10%, color-mix(in srgb, var(--store-primary) 18%, transparent) 0%, transparent 70%);
        }
        .hero-ambient-glow {
            position: absolute;
            top: -25%;
            left: 50%;
            width: 580px;
            height: 380px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--store-primary-glow) 0%, transparent 70%);
            filter: blur(65px);
            pointer-events: none;
            animation: floatGlow 12s ease-in-out infinite;
            z-index: 0;
        }
        .store-hero .container {
            position: relative;
            z-index: 1;
        }
        .hero-live-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: color-mix(in srgb, var(--surface) 80%, transparent);
            border: 1px solid color-mix(in srgb, var(--store-primary) 35%, var(--border));
            backdrop-filter: blur(10px);
            padding: .35rem .95rem;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            animation: pulseLive 2s infinite;
        }
        .store-hero h1 {
            font-size: clamp(2.3rem, 5.5vw, 4rem);
            font-family: var(--font-display);
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: .75rem;
            background: linear-gradient(180deg, var(--text) 40%, color-mix(in srgb, var(--text) 70%, var(--store-primary)) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .store-hero p {
            font-size: 1.15rem;
            line-height: 1.6;
            max-width: 650px;
            margin: 0 auto 1.6rem;
            color: var(--text-soft);
        }

        /* Hero Search Input */
        .hero-search-wrap {
            max-width: 460px;
            margin: 1.5rem auto 1.75rem;
            position: relative;
        }
        .hero-search-input {
            width: 100%;
            background: var(--surface);
            border: 1px solid color-mix(in srgb, var(--store-primary) 30%, var(--border));
            border-radius: 999px;
            padding: .85rem 2.8rem .85rem 2.85rem;
            color: var(--text);
            font-size: .95rem;
            font-family: var(--font);
            outline: none;
            transition: all .25s ease;
            box-shadow: 0 6px 20px rgba(0,0,0,0.18);
        }
        .hero-search-input:focus {
            border-color: var(--store-primary);
            box-shadow: 0 0 0 3px var(--store-primary-soft), 0 8px 30px rgba(0,0,0,0.25);
        }
        .hero-search-icon {
            position: absolute;
            left: 1.1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-faint);
            pointer-events: none;
        }
        .hero-search-clear {
            position: absolute;
            right: .9rem;
            top: 50%;
            transform: translateY(-50%);
            background: var(--bg-soft);
            border: none;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-soft);
            font-size: .85rem;
            cursor: pointer;
        }

        /* Hero Trust Highlights */
        .hero-trust-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.25rem;
            margin-top: 1.75rem;
            font-size: .82rem;
            color: var(--text-soft);
        }
        .hero-trust-item {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            background: color-mix(in srgb, var(--surface) 60%, transparent);
            padding: .35rem .75rem;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        /* Infinite Marquee Ticker */
        .marquee-wrap {
            background: color-mix(in srgb, var(--store-primary) 10%, var(--surface));
            border-bottom: 1px solid color-mix(in srgb, var(--store-primary) 20%, var(--border));
            padding: .65rem 0;
            overflow: hidden;
            position: relative;
            white-space: nowrap;
        }
        .marquee-track {
            display: inline-flex;
            gap: 2.5rem;
            animation: marqueeScroll 24s linear infinite;
        }
        .marquee-track:hover {
            animation-play-state: paused;
        }
        .marquee-item {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .82rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        /* Category Chips */
        .cat-scroll {
            display: flex; gap: .6rem; overflow-x: auto; padding: .5rem 0 1rem;
            scrollbar-width: none; -ms-overflow-style: none;
        }
        .cat-scroll::-webkit-scrollbar { display: none; }
        .cat-chip {
            padding: .5rem 1rem; border-radius: 20px; font-size: .9rem;
            font-weight: 600; background: var(--surface); border: 1px solid var(--border);
            color: var(--text); white-space: nowrap; transition: all .25s ease;
            cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: .4rem;
        }
        .cat-chip:hover, .cat-chip.active {
            background: var(--store-primary); color: #fff; border-color: var(--store-primary);
            box-shadow: 0 4px 14px var(--store-primary-glow);
            transform: translateY(-2px);
        }

        /* Grid & Cards */
        .store-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 1.5rem; margin: 2rem 0;
        }
        @media (max-width: 640px) {
            .store-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: .75rem;
                margin: 1.2rem 0;
            }
            .product-card-body {
                padding: .75rem .65rem;
            }
            .product-card-title {
                font-size: .88rem;
                margin-bottom: .2rem;
                line-height: 1.3;
            }
            .product-price {
                font-size: 1.05rem;
            }
            .product-old-price {
                font-size: .78rem;
            }
            .product-rating-row {
                font-size: .68rem;
                gap: .25rem;
                margin-bottom: .25rem;
            }
            .product-stars {
                font-size: .72rem;
            }
            .product-rating-text {
                font-size: .68rem;
            }
            .btn-add-cart {
                padding: .45rem .55rem;
                font-size: .76rem;
                gap: .25rem;
                white-space: nowrap;
            }
            .btn-card-wa-icon {
                width: 32px;
                height: 32px;
                min-width: 32px;
                flex-shrink: 0;
            }
            .product-card-badge-top {
                font-size: .65rem;
                padding: .15rem .4rem;
                top: .45rem; right: .45rem;
            }
            .product-discount-badge {
                font-size: .68rem;
                padding: .2rem .45rem;
                top: .45rem; left: .45rem;
            }
            .store-header-inner {
                height: 58px;
                gap: .5rem;
            }
            .store-brand {
                font-size: 1.02rem;
                gap: .5rem;
            }
            .store-logo {
                width: 32px; height: 32px; font-size: .95rem;
            }
            .cart-pill {
                padding: .35rem .75rem; font-size: .82rem;
            }
            .store-hero {
                padding: 2.6rem 0 2rem;
            }
            .store-hero h1 {
                font-size: 1.9rem;
            }
            .store-hero p {
                font-size: .95rem;
            }
        }
        @media (max-width: 360px) {
            .store-grid {
                grid-template-columns: 1fr;
            }
        }
        /* Scroll Reveal Animations Bi-directionnelles Fluides (Haut-Bas et Bas-Haut) */
        .reveal-on-scroll {
            opacity: 0;
            filter: blur(4px);
            transition: opacity .65s cubic-bezier(0.16, 1, 0.3, 1),
                        transform .65s cubic-bezier(0.16, 1, 0.3, 1),
                        filter .55s ease;
            transition-delay: calc(var(--delay, 0) * 70ms);
            will-change: opacity, transform, filter;
        }

        /* Quand on défile vers le bas (ou par défaut) : les éléments montent du bas */
        .reveal-on-scroll.from-bottom,
        .reveal-on-scroll:not(.from-top) {
            transform: translateY(38px) scale(0.96);
        }

        /* Quand on défile vers le haut : les éléments descendent du haut */
        .reveal-on-scroll.from-top {
            transform: translateY(-38px) scale(0.96);
        }

        /* État visible quand l'élément est dans la fenêtre */
        .reveal-on-scroll.in-view {
            opacity: 1;
            transform: translateY(0) scale(1) !important;
            filter: blur(0);
        }

        /* Bouton d'invitation au défilement Hero */
        .hero-scroll-cue {
            display: inline-flex; align-items: center; gap: .6rem;
            padding: .45rem 1.15rem; border-radius: 99px;
            background: color-mix(in srgb, var(--surface) 85%, transparent);
            border: 1px solid color-mix(in srgb, var(--store-primary) 35%, var(--border));
            color: var(--text-soft); font-size: .84rem; font-weight: 600;
            text-decoration: none; transition: all .25s ease;
            box-shadow: 0 4px 14px rgba(0,0,0,0.1);
        }
        .hero-scroll-cue:hover {
            color: var(--text); border-color: var(--store-primary);
            transform: translateY(2px); box-shadow: 0 6px 18px var(--store-primary-glow);
        }
        .hero-scroll-arrow {
            display: inline-block; animation: bounceDown 1.8s infinite ease-in-out;
            color: var(--store-primary); font-weight: 800; font-size: .95rem;
        }
        @keyframes bounceDown {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(4px); }
        }

        /* Cartes Produits Dynamiques & Captivantes */
        .product-card {
            background: linear-gradient(180deg, color-mix(in srgb, var(--surface) 97%, #fff) 0%, var(--surface) 100%);
            border: 1px solid color-mix(in srgb, var(--store-primary) 22%, var(--border));
            border-radius: var(--radius);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: transform .35s cubic-bezier(0.16, 1, 0.3, 1),
                        box-shadow .35s cubic-bezier(0.16, 1, 0.3, 1),
                        border-color .25s ease;
            box-shadow: 0 6px 22px rgba(0, 0, 0, 0.14);
        }
        .product-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 42px -10px var(--store-primary-glow), 0 0 0 1px color-mix(in srgb, var(--store-primary) 60%, transparent);
            border-color: var(--store-primary);
        }

        .product-rating-row {
            display: flex; align-items: center; gap: .4rem;
            margin-bottom: .35rem; font-size: .75rem;
        }
        .product-stars {
            color: #f59e0b; letter-spacing: 1px; font-size: .82rem;
        }
        .product-rating-text {
            color: var(--text-faint); font-weight: 600; font-size: .74rem;
        }
        .product-img-wrap {
            position: relative; width: 100%; aspect-ratio: 1;
            background: var(--bg-soft); overflow: hidden; display: block;
        }
        .product-img-wrap::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 40%, rgba(255, 255, 255, 0.15) 50%, transparent 60%);
            transform: translateX(-100%);
            transition: transform .7s ease;
            pointer-events: none;
            z-index: 2;
        }
        .product-card:hover .product-img-wrap::after {
            transform: translateX(100%);
        }
        .product-img-wrap img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform .6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .product-card:hover .product-img-wrap img {
            transform: scale(1.09);
        }
        .product-discount-badge {
            position: absolute; top: .65rem; left: .65rem;
            background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; font-size: .75rem;
            font-weight: 800; padding: .25rem .55rem; border-radius: 8px;
            box-shadow: 0 4px 10px rgba(220, 38, 38, 0.4);
            animation: discountPulse 2.5s infinite; z-index: 2;
        }
        .product-card-badge-top {
            position: absolute; top: .65rem; right: .65rem;
            background: rgba(18, 18, 22, 0.85); backdrop-filter: blur(8px);
            color: var(--ok); font-size: .7rem; font-weight: 700;
            padding: .2rem .5rem; border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            z-index: 2; display: inline-flex; align-items: center; gap: .25rem;
        }
        .product-card-actions-float {
            position: absolute; bottom: .75rem; right: .75rem;
            display: flex; gap: .45rem; z-index: 3;
            opacity: 0; transform: translateY(8px);
            transition: all .25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .product-card:hover .product-card-actions-float {
            opacity: 1; transform: translateY(0);
        }
        .btn-card-action {
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(18, 18, 22, 0.9); backdrop-filter: blur(8px);
            color: #fff; border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all .2s ease; text-decoration: none;
        }
        .btn-card-action:hover {
            transform: scale(1.12);
        }
        .btn-card-action.btn-card-wa {
            background: #25D366; border-color: #25D366; color: #fff;
        }
        .btn-card-action.btn-card-wa:hover {
            box-shadow: 0 4px 14px rgba(37, 211, 102, 0.5);
        }
        .btn-card-action.btn-card-qv:hover {
            background: var(--store-primary); border-color: var(--store-primary);
        }
        .product-card-body {
            padding: 1rem 1.1rem; display: flex; flex-direction: column; flex: 1;
        }
        .product-card-title {
            font-size: 1.02rem; font-weight: 700; margin: 0 0 .35rem;
            color: var(--text); line-height: 1.35;
        }
        .product-card-title a {
            color: var(--text); text-decoration: none;
            transition: color .2s;
        }
        .product-card-title a:hover {
            color: var(--store-primary);
        }
        .card-stock-pill {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: .74rem; font-weight: 700; color: #10b981;
            margin-bottom: .25rem;
        }
        .card-stock-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: #10b981; box-shadow: 0 0 6px #10b981;
            display: inline-block;
        }
        .product-price-box {
            display: flex; align-items: baseline; gap: .6rem; margin-top: auto; padding-top: .7rem;
        }
        .product-price {
            font-size: 1.25rem; font-weight: 800; color: var(--text);
            font-family: var(--font-display); letter-spacing: -.01em;
        }
        .product-old-price {
            font-size: .85rem; color: var(--text-faint); text-decoration: line-through;
        }

        /* AJAX Cart Button & Micro-Spinner */
        .btn-add-cart {
            background: color-mix(in srgb, var(--store-primary) 18%, var(--surface));
            color: var(--text);
            border: 1px solid color-mix(in srgb, var(--store-primary) 35%, transparent);
            padding: .5rem .85rem; border-radius: var(--radius-sm);
            font-weight: 700; font-size: .88rem; cursor: pointer;
            transition: all .25s ease; display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
        }
        .btn-add-cart:hover {
            background: var(--store-primary); color: #fff;
            border-color: var(--store-primary);
            box-shadow: 0 6px 16px var(--store-primary-glow);
            transform: translateY(-1px);
        }
        .btn-success-pulse {
            background: #16a34a !important; color: #fff !important; border-color: #16a34a !important;
            animation: badgeBounce .35s ease;
        }
        .btn-card-wa-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: var(--radius-sm);
            background: rgba(37, 211, 102, 0.12); color: #25D366;
            border: 1px solid rgba(37, 211, 102, 0.3);
            transition: all .2s ease; text-decoration: none; flex-shrink: 0;
        }
        .btn-card-wa-icon:hover {
            background: #25D366; color: #fff;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
            transform: scale(1.08);
        }
        .store-spinner {
            width: 14px; height: 14px; border: 2px solid currentColor;
            border-right-color: transparent; border-radius: 50%;
            display: inline-block; animation: spinSlow .7s linear infinite;
        }

        /* Live Activity / Social Proof Toast */
        .live-sales-toast {
            position: fixed; bottom: 1.5rem; left: 1.5rem; z-index: 95;
            background: color-mix(in srgb, var(--surface) 95%, #000);
            border: 1px solid color-mix(in srgb, var(--store-primary) 30%, var(--border));
            backdrop-filter: blur(14px);
            border-radius: 14px;
            padding: .65rem .95rem;
            display: flex; align-items: center; gap: .75rem;
            box-shadow: 0 12px 30px rgba(0,0,0,0.35);
            max-width: 340px;
            opacity: 0; transform: translateY(20px);
            transition: opacity .4s ease, transform .4s ease;
            pointer-events: none;
        }
        .live-sales-toast.active {
            opacity: 1; transform: translateY(0);
            pointer-events: auto;
        }
        .live-sales-thumb {
            width: 40px; height: 40px; border-radius: 8px;
            object-fit: cover; border: 1px solid var(--border); flex-shrink: 0;
        }
        .live-sales-text {
            font-size: .8rem; color: var(--text-soft); line-height: 1.35;
        }
        .live-sales-text strong {
            color: var(--text); font-weight: 700;
        }
        .live-sales-badge {
            font-size: .7rem; font-weight: 700; color: #22c55e;
            display: flex; align-items: center; gap: .25rem; margin-top: .15rem;
        }
        @media (max-width: 600px) {
            .live-sales-toast { bottom: 5rem; left: 1rem; right: 1rem; max-width: none; }
        }

        /* Mobile Sticky Product Bar (Fiche Produit) */
        .mobile-sticky-product-bar {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 90;
            background: color-mix(in srgb, var(--surface) 95%, transparent);
            backdrop-filter: blur(16px);
            border-top: 1px solid color-mix(in srgb, var(--store-primary) 25%, var(--border));
            padding: .75rem 1rem;
            display: none; align-items: center; justify-content: space-between; gap: .75rem;
            box-shadow: 0 -8px 25px rgba(0,0,0,0.3);
            transform: translateY(100%);
            transition: transform .3s ease;
        }
        .mobile-sticky-product-bar.visible {
            transform: translateY(0);
        }
        @media (max-width: 768px) {
            .mobile-sticky-product-bar { display: flex; }
        }

        /* Floating WhatsApp Radar Bubble */
        .floating-wa-bubble {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 99;
            display: flex; align-items: center; gap: .75rem;
        }
        .floating-wa-btn {
            width: 56px; height: 56px; border-radius: 50%;
            background: #25D366; color: #fff; display: flex;
            align-items: center; justify-content: center;
            box-shadow: 0 8px 24px rgba(37, 211, 102, 0.4);
            animation: radarPulse 2.8s infinite; transition: transform .2s ease;
            text-decoration: none;
        }
        .floating-wa-btn:hover {
            transform: scale(1.1); color: #fff;
        }
        .floating-wa-tooltip {
            background: var(--surface); color: var(--text);
            border: 1px solid var(--border); padding: .4rem .8rem;
            border-radius: 8px; font-size: .82rem; font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); pointer-events: none;
            opacity: 0; transform: translateX(10px); transition: all .25s ease;
            white-space: nowrap;
        }
        .floating-wa-bubble:hover .floating-wa-tooltip {
            opacity: 1; transform: translateX(0);
        }
        @media (max-width: 600px) {
            .floating-wa-tooltip { display: none; }
            .floating-wa-btn { width: 50px; height: 50px; }
            .floating-wa-bubble { bottom: 1rem; right: 1rem; }
        }

        /* Floating Toast Container */
        .store-toast-container {
            position: fixed; bottom: 2rem; right: 2rem; z-index: 100;
            pointer-events: none; display: none;
        }
        .store-toast-container.active { display: block; }
        .store-toast-card {
            pointer-events: auto;
            background: color-mix(in srgb, var(--surface) 92%, #000);
            border: 1px solid color-mix(in srgb, var(--store-primary) 35%, var(--border));
            backdrop-filter: blur(14px); border-radius: 14px;
            padding: .85rem 1.1rem; display: flex; align-items: center; gap: .85rem;
            box-shadow: 0 16px 40px rgba(0,0,0,0.35); animation: toastSlideIn .35s cubic-bezier(0.16, 1, 0.3, 1);
            max-width: 420px;
        }
        .store-toast-container.hiding .store-toast-card {
            opacity: 0; transform: translateY(20px); transition: all .25s ease;
        }
        .toast-thumb {
            width: 44px; height: 44px; border-radius: 8px;
            object-fit: cover; border: 1px solid var(--border); flex-shrink: 0;
        }
        .toast-content {
            flex: 1; min-width: 0;
        }
        .toast-title {
            font-size: .88rem; font-weight: 700; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .toast-subtitle {
            font-size: .78rem; color: var(--text-soft);
        }
        .toast-btn {
            background: var(--store-primary); color: #fff;
            padding: .35rem .75rem; border-radius: 8px; font-size: .82rem;
            font-weight: 600; text-decoration: none; white-space: nowrap;
            transition: opacity .15s;
        }
        .toast-btn:hover { opacity: .9; color: #fff; }
        .toast-close {
            background: none; border: none; color: var(--text-faint);
            font-size: 1.2rem; cursor: pointer; padding: 0 .2rem; line-height: 1;
        }

        /* Quick View Lightbox Modal */
        .qv-modal {
            position: fixed; inset: 0; z-index: 105; display: none;
            align-items: center; justify-content: center; padding: 1.5rem;
        }
        .qv-modal.open { display: flex; }
        .qv-backdrop {
            position: absolute; inset: 0; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px);
        }
        .qv-dialog {
            position: relative; background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); max-width: 760px; width: 100%; max-height: 90vh;
            overflow-y: auto; box-shadow: 0 24px 60px rgba(0,0,0,0.5); z-index: 1;
            animation: toastSlideIn .3s cubic-bezier(0.16, 1, 0.3, 1); padding: 1.75rem;
        }
        .qv-close-btn {
            position: absolute; top: 1rem; right: 1rem; width: 32px; height: 32px;
            border-radius: 50%; background: var(--bg-soft); border: 1px solid var(--border);
            color: var(--text); display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; cursor: pointer; z-index: 2;
        }
        .qv-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start;
        }
        @media (max-width: 680px) {
            .qv-grid { grid-template-columns: 1fr; gap: 1.25rem; }
            .qv-dialog { padding: 1.25rem; }
        }
        .qv-main-img-wrap {
            position: relative; aspect-ratio: 1; border-radius: var(--radius-sm);
            overflow: hidden; background: var(--bg-soft); border: 1px solid var(--border);
        }
        .qv-main-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .qv-thumbs-wrap { display: flex; gap: .5rem; margin-top: .75rem; overflow-x: auto; }
        .qv-thumb-btn {
            width: 52px; height: 52px; border-radius: 8px; padding: 0;
            border: 2px solid var(--border); overflow: hidden; background: var(--bg-soft);
            cursor: pointer; flex-shrink: 0;
        }
        .qv-thumb-btn.active { border-color: var(--store-primary); }
        .qv-thumb-btn img { width: 100%; height: 100%; object-fit: cover; }

        /* Category Filter Transitions */
        .card-fade-in { animation: toastSlideIn .3s ease forwards; }
        .card-fade-out { opacity: 0; transform: scale(0.96); transition: all .2s ease; }

        .store-footer {
            border-top: 1px solid var(--border);
            background: var(--bg-soft);
            padding: 3rem 0 2rem; margin-top: 4rem;
        }
    </style>
</head>
<body class="@yield('body_class')">
    @if ($preview ?? false)
        <div style="background:linear-gradient(90deg, #ff8c00, #ff4500);color:#fff;padding:.6rem 1rem;text-align:center;font-size:.9rem;font-weight:600;display:flex;align-items:center;justify-content:center;gap:.75rem">
            <span style="display:inline-flex;align-items:center;gap:.4rem"><x-icon name="eye" :size="16" /> Mode Prévisualisation Commerçant — Votre boutique est actuellement en <strong>{{ $shop->status === 'paused' ? 'pause' : 'brouillon' }}</strong></span>
            <a href="{{ route('shop.settings') }}" style="color:#fff;text-decoration:underline;font-weight:bold;display:inline-flex;align-items:center;gap:.3rem">Gérer / Publier <x-icon name="arrow-right" :size="14" /></a>
        </div>
    @endif

    <header class="store-header">
        <div id="store-scroll-progress" class="store-scroll-progress"></div>
        <div class="container store-header-inner">
            <a href="{{ url('/b/' . $shop->subdomain) }}" class="store-brand">
                <span class="store-logo">
                    @if ($shop->logo_path)
                        <img src="{{ asset('storage/' . $shop->logo_path) }}" alt="{{ $shop->name }}" style="width:100%;height:100%;object-fit:cover;display:block">
                    @elseif (!empty($shop->onboarding_data['chosen_logo']['svg']))
                        {!! str_contains($shop->onboarding_data['chosen_logo']['svg'], 'width=') ? $shop->onboarding_data['chosen_logo']['svg'] : str_replace('<svg ', '<svg width="40" height="40" style="width:100%;height:100%;max-width:40px;max-height:40px;display:block;" ', $shop->onboarding_data['chosen_logo']['svg']) !!}
                    @else
                        {{ strtoupper(substr($shop->name ?: 'B', 0, 1)) }}
                    @endif
                </span>
                <span>{{ $shop->name }}</span>
            </a>

            <div class="store-nav-actions">
                <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" class="btn btn-ghost btn-sm" style="display:none; @media(min-width:600px){display:inline-flex}">
                    Catalogue
                </a>

                @if ($shop->whatsapp_formatted)
                    <a href="https://wa.me/{{ $shop->whatsapp_formatted }}" target="_blank" class="btn btn-ghost btn-sm" title="Contacter sur WhatsApp" style="color:#25D366;display:inline-flex;align-items:center;gap:.4rem">
                        <x-icon name="whatsapp" :size="16" />
                        <span>WhatsApp</span>
                    </a>
                @endif

                <a href="{{ url('/b/' . $shop->subdomain . '/panier') }}" class="cart-pill" aria-label="Voir le panier">
                    <x-icon name="shopping-bag" :size="16" />
                    <span>Panier</span>
                    <span class="cart-badge">{{ $cartCount ?? 0 }}</span>
                </a>
            </div>
        </div>
    </header>

    <main>
        @if (session('status'))
            <div class="container" style="margin-top:1rem">
                <div class="alert alert-ok" role="status">{{ session('status') }}</div>
            </div>
        @endif

        @if (session('error'))
            <div class="container" style="margin-top:1rem">
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="store-footer">
        <div class="container">
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:2rem;margin-bottom:2.5rem">
                <div>
                    <div style="font-weight:700;font-size:1.2rem;margin-bottom:.5rem">{{ $shop->name }}</div>
                    <p style="font-size:.9rem;color:var(--text-soft);margin-bottom:1rem">
                        {{ $shop->description ?: 'Boutique en ligne officielle. Paiement à la livraison et Mobile Money.' }}
                    </p>
                    @if ($shop->city || $shop->address)
                        <div style="margin-top:.75rem">
                            <p style="font-size:.85rem;color:var(--text-soft);margin:0 0 .4rem;display:flex;align-items:flex-start;gap:.4rem">
                                <x-icon name="map-pin" :size="15" style="color:var(--store-primary);flex-shrink:0;margin-top:2px" />
                                <span>{{ $shop->address ? $shop->address . ', ' : '' }}{{ $shop->city ?: 'Lomé, Togo' }}</span>
                            </p>
                            @if ($shop->maps_url)
                                <a href="{{ $shop->maps_url }}" target="_blank" rel="noopener noreferrer" style="font-size:.8rem;color:var(--store-primary);text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;background:color-mix(in srgb, var(--store-primary) 12%, transparent);border-radius:20px;border:1px solid color-mix(in srgb, var(--store-primary) 25%, transparent)">
                                    <x-icon name="map" :size="13" />
                                    <span>Itinéraire Google Maps ↗</span>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                <div>
                    <div style="font-weight:600;margin-bottom:.8rem">Navigation</div>
                    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.5rem;font-size:.9rem">
                        <li><a href="{{ url('/b/' . $shop->subdomain) }}">Accueil</a></li>
                        <li><a href="{{ url('/b/' . $shop->subdomain . '/produits') }}">Tous les produits</a></li>
                        <li><a href="{{ url('/b/' . $shop->subdomain . '/panier') }}">Mon panier ({{ $cartCount ?? 0 }})</a></li>
                    </ul>
                </div>

                <div>
                    <div style="font-weight:600;margin-bottom:.8rem">Informations & Aide</div>
                    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.5rem;font-size:.9rem">
                        <li><a href="{{ url('/b/' . $shop->subdomain . '/p/about') }}">À propos de nous</a></li>
                        <li><a href="{{ url('/b/' . $shop->subdomain . '/p/contact') }}">Contact & Localisation</a></li>
                        <li><a href="{{ url('/b/' . $shop->subdomain . '/p/shipping') }}">Livraison & Paiement</a></li>
                        <li><a href="{{ url('/b/' . $shop->subdomain . '/p/terms') }}">Conditions Générales (CGV)</a></li>
                    </ul>
                </div>

                <div>
                    <div style="font-weight:600;margin-bottom:.8rem">Paiements acceptés</div>
                    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1rem">
                        <span style="background:var(--surface);border:1px solid var(--border);padding:.3rem .6rem;border-radius:6px;font-size:.8rem;font-weight:600">T-Money</span>
                        <span style="background:var(--surface);border:1px solid var(--border);padding:.3rem .6rem;border-radius:6px;font-size:.8rem;font-weight:600">Flooz</span>
                        <span style="background:var(--surface);border:1px solid var(--border);padding:.3rem .6rem;border-radius:6px;font-size:.8rem;font-weight:600">Espèces à la livraison</span>
                    </div>
                    @if ($shop->phone)
                        <div style="font-size:.9rem;color:var(--text-soft);display:flex;align-items:center;gap:.4rem">
                            <x-icon name="phone" :size="14" />
                            <span>{{ $shop->phone }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;padding-top:1.5rem;border-top:1px solid var(--border);font-size:.85rem;color:var(--text-faint)">
                <div>© {{ date('Y') }} {{ $shop->name }}. Tous droits réservés.</div>
                <div>
                    <a href="{{ route('home') }}" style="color:var(--text-faint);display:inline-flex;align-items:center;gap:.35rem" target="_blank">
                        <span>Propulsé par</span>
                        <img src="{{ asset('images/brand/02_icone_application.jpg') }}" alt="Zanika" style="height:17px;width:17px;border-radius:4px;vertical-align:middle">
                        <strong style="color:var(--text);font-family:'Montserrat',sans-serif">Zanika</strong>
                        <x-icon name="sparkles" :size="12" />
                    </a>
                </div>
            </div>
    <!-- Quick View Lightbox Modal -->
    <div id="quick-view-modal" class="qv-modal" aria-hidden="true" role="dialog">
        <div id="qv-backdrop" class="qv-backdrop"></div>
        <div class="qv-dialog">
            <button id="qv-close" type="button" class="qv-close-btn" aria-label="Fermer">&times;</button>
            <div class="qv-grid">
                <div>
                    <div class="qv-main-img-wrap">
                        <img id="qv-main-img" src="" alt="" onerror="this.onerror=null; this.src='/api/placeholder-image?id=' + (document.getElementById('qv-product-id')?.value || '')">
                        <span id="qv-discount" class="product-discount-badge" style="display:none"></span>
                    </div>
                    <div id="qv-thumbs" class="qv-thumbs-wrap" style="display:none"></div>
                </div>
                <div>
                    <span id="qv-category" style="display:none;font-size:.75rem;font-weight:700;color:var(--store-primary);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem"></span>
                    <h2 id="qv-title" style="font-size:1.4rem;margin:0 0 .75rem;line-height:1.3"></h2>

                    <div style="background:var(--bg-soft);border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;margin-bottom:1.25rem">
                        <div style="display:flex;align-items:baseline;gap:.6rem">
                            <span id="qv-price" style="font-size:1.5rem;font-weight:700;font-family:var(--font-display);color:var(--text)"></span>
                            <span id="qv-old-price" style="display:none;font-size:.9rem;color:var(--text-faint);text-decoration:line-through"></span>
                        </div>
                    </div>

                    <div style="margin-bottom:1.25rem">
                        <h4 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-faint);margin-bottom:.4rem">Description</h4>
                        <p id="qv-desc" style="font-size:.92rem;line-height:1.6;color:var(--text-soft);margin:0;max-height:120px;overflow-y:auto"></p>
                    </div>

                    <!-- Formulaire d'ajout rapide -->
                    <form class="ajax-add-cart" method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/ajouter') }}" style="margin-bottom:1rem">
                        @csrf
                        <input id="qv-product-id" type="hidden" name="product_id" value="">
                        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">
                            <label for="qv-qty" style="font-size:.85rem;font-weight:600">Quantité :</label>
                            <input id="qv-qty" type="number" name="quantity" value="1" min="1" max="99" class="input" style="width:70px;text-align:center;padding:.4rem">
                        </div>
                        <button type="submit" class="btn-store btn-block" style="width:100%">
                            <x-icon name="shopping-cart" size="18" /> Ajouter au panier
                        </button>
                    </form>

                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                        <a id="qv-wa-btn" href="#" target="_blank" class="btn-whatsapp btn-sm" style="flex:1;display:none">
                            <x-icon name="whatsapp" size="16" /> Commander par WhatsApp
                        </a>
                        <a id="qv-link" href="#" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                            Page complète <x-icon name="arrow-right" size="14" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Interactive Toast Notification -->
    <div id="store-toast" class="store-toast-container">
        <div class="store-toast-card">
            <img id="toast-img" src="" alt="" class="toast-thumb" style="display:none">
            <div class="toast-content">
                <div id="toast-title" class="toast-title">Article ajouté au panier !</div>
                <div id="toast-subtitle" class="toast-subtitle">Total mis à jour</div>
            </div>
            <a id="toast-btn" href="{{ url('/b/' . $shop->subdomain . '/panier') }}" class="toast-btn">
                Voir le panier &rarr;
            </a>
            <button type="button" class="toast-close" onclick="closeStoreToast()">&times;</button>
        </div>
    </div>

    <!-- Floating WhatsApp Radar Bubble -->
    @if ($shop->whatsapp_formatted)
        <aside class="floating-wa-bubble">
            <span class="floating-wa-tooltip">Une question ? Écrivez-nous sur WhatsApp !</span>
            <a href="https://wa.me/{{ $shop->whatsapp_formatted }}" target="_blank" class="floating-wa-btn" title="Discuter sur WhatsApp" aria-label="Contacter sur WhatsApp">
                <x-icon name="whatsapp" size="28" />
            </a>
        </aside>
    @endif
    <!-- Notification Interactive de Commande en Direct (Preuve Sociale Locale) -->
    <div id="store-live-sale-toast" class="live-sales-toast" aria-live="polite">
        <img id="live-sale-img" src="" alt="" class="live-sales-thumb" style="display:none">
        <div class="live-sales-text">
            <div><strong id="live-sale-product">Article Vedette</strong></div>
            <div style="font-size:.76rem;color:var(--text-soft)">Commandé à <span id="live-sale-city">Lomé</span> • <span id="live-sale-time">Il y a 6 min</span></div>
            <div class="live-sales-badge">
                <x-icon name="check" size="12" /> Commande vérifiée
            </div>
        </div>
        <button type="button" class="toast-close" onclick="document.getElementById('store-live-sale-toast').classList.remove('active')" style="margin-left:auto;font-size:1.1rem">&times;</button>
    </div>
</body>
</html>
