@extends('storefront.layout')

@section('title', $shop->name . ' : Boutique officielle')
@section('body_class', 'has-live-filter')

@section('content')
<!-- Dynamic Hero Banner with Ambient Glow & Live Search -->
<section class="store-hero">
    <div class="hero-ambient-glow"></div>
    <div class="container">
        <div class="hero-live-badge">
            <span class="live-dot"></span>
            <span>Ouvert aux commandes • Boutique Officielle</span>
        </div>

        <h1>{{ $shop->name }}</h1>
        <p>{{ $shop->description ?: 'Bienvenue sur notre boutique officielle. Découvrez nos sélections exclusives avec livraison rapide et paiement Mobile Money sécurisé.' }}</p>

        <!-- Barre de recherche interactive instantanée -->
        <div class="hero-search-wrap">
            <span class="hero-search-icon"><x-icon name="search" size="18" /></span>
            <input id="hero-live-search" type="search" placeholder="Rechercher un plat, un article, un produit..." class="hero-search-input" autocomplete="off">
            <button id="hero-search-clear" type="button" class="hero-search-clear" style="display:none" title="Effacer">&times;</button>
        </div>

        <!-- Boutons d'action -->
        <div style="display:flex;justify-content:center;gap:.75rem;flex-wrap:wrap">
            <a href="#catalogue-section" class="btn-store" style="display:inline-flex;align-items:center;gap:.5rem" onclick="document.getElementById('catalogue-section')?.scrollIntoView({behavior:'smooth'}); return false;">
                <x-icon name="shopping-bag" size="18" /> Explorer le catalogue
            </a>
            @if ($shop->whatsapp_formatted)
                <a href="https://wa.me/{{ $shop->whatsapp_formatted }}?text={{ urlencode('Bonjour ' . $shop->name . ', je souhaite passer une commande !') }}" target="_blank" class="btn-whatsapp" style="display:inline-flex;align-items:center;gap:.5rem">
                    <x-icon name="whatsapp" size="18" /> Commander sur WhatsApp
                </a>
            @endif
        </div>

        <!-- Invitation visuelle au défilement interactif -->
        <div style="margin-top:1.4rem">
            <a href="#catalogue-section" class="hero-scroll-cue" onclick="document.getElementById('catalogue-section')?.scrollIntoView({behavior:'smooth'}); return false;">
                <span>Découvrir la sélection & les tarifs</span>
                <span class="hero-scroll-arrow">↓</span>
            </a>
        </div>

        <!-- Micro-puces de réassurance dans le Hero -->
        <div class="hero-trust-bar">
            <span class="hero-trust-item"><x-icon name="truck" size="15" /> Livraison rapide à Lomé</span>
            <span class="hero-trust-item"><x-icon name="wallet" size="15" /> T-Money & Flooz acceptés</span>
            <span class="hero-trust-item"><x-icon name="sparkles" size="15" /> Articles 100% garantis</span>
        </div>
    </div>
</section>

<!-- Bandeau Défilant Infini (Marquee Ticker) -->
<div class="marquee-wrap" aria-hidden="true">
    <div class="marquee-track">
        <span class="marquee-item"><x-icon name="flame" size="15" /> Sélections & Nouveautés en direct</span>
        <span class="marquee-item"><x-icon name="truck" size="15" /> Livraison rapide et soignée</span>
        <span class="marquee-item"><x-icon name="whatsapp" size="15" /> Commande WhatsApp en 1 clic</span>
        <span class="marquee-item"><x-icon name="wallet" size="15" /> Paiement Mobile Money & Espèces</span>
        <span class="marquee-item"><x-icon name="sparkles" size="15" /> Qualité & Fraîcheur garanties</span>
        <!-- Duplication pour boucle infinie fluide -->
        <span class="marquee-item"><x-icon name="flame" size="15" /> Sélections & Nouveautés en direct</span>
        <span class="marquee-item"><x-icon name="truck" size="15" /> Livraison rapide et soignée</span>
        <span class="marquee-item"><x-icon name="whatsapp" size="15" /> Commande WhatsApp en 1 clic</span>
        <span class="marquee-item"><x-icon name="wallet" size="15" /> Paiement Mobile Money & Espèces</span>
        <span class="marquee-item"><x-icon name="sparkles" size="15" /> Qualité & Fraîcheur garanties</span>
    </div>
</div>

<!-- Catégories Interactives (Filtre sans rechargement) -->
@if ($categories->isNotEmpty())
    <div class="container" style="margin-top:2rem">
        <div class="cat-scroll">
            <button type="button" data-category-filter="all" class="cat-chip active">
                <x-icon name="sparkles" size="14" /> Tout afficher
            </button>
            @foreach ($categories as $cat)
                <button type="button" data-category-filter="{{ $cat->slug }}" class="cat-chip">
                    {{ $cat->name }}
                </button>
            @endforeach
        </div>
    </div>
@endif

<!-- Grille des Produits Vedettes avec Filtre Dynamique -->
<div class="container" id="catalogue-section">
    <div style="display:flex;justify-content:space-between;align-items:baseline;margin:2.5rem 0 1rem;flex-wrap:wrap;gap:.5rem">
        <div>
            <h2 style="font-size:1.6rem;margin:0">Sélection du moment</h2>
            <div id="live-filter-count" style="font-size:.85rem;color:var(--text-soft);margin-top:.2rem">
                {{ $products->count() }} article(s) affiché(s)
            </div>
        </div>
        <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" style="font-size:.9rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem;color:var(--store-primary)">
            Voir tout le catalogue <x-icon name="arrow-right" size="14" />
        </a>
    </div>

    <!-- Message si aucun article après recherche/filtre -->
    <div id="live-filter-empty" class="card" style="display:none;text-align:center;padding:3rem 1.5rem;margin:2rem 0">
        <div style="margin-bottom:1rem;color:var(--text-soft);display:flex;justify-content:center">
            <x-icon name="search" size="42" />
        </div>
        <h3 style="font-size:1.25rem;margin-bottom:.5rem">Aucun article ne correspond à votre sélection</h3>
        <p style="color:var(--text-soft);margin-bottom:1.5rem">Essayez un autre mot-clé ou réinitialisez le filtre de catégorie.</p>
        <button type="button" class="btn btn-store btn-sm" onclick="document.getElementById('hero-live-search').value = ''; document.querySelector('[data-category-filter=all]').click();">
            Tout réafficher
        </button>
    </div>

    @if ($products->isEmpty())
        <div class="card" style="text-align:center;padding:3.5rem 1.5rem">
            <p style="font-size:1.15rem;margin-bottom:1rem;font-weight:600">Aucun article disponible pour le moment.</p>
            <p style="color:var(--text-soft);margin:0">Revenez très bientôt pour découvrir nos nouveautés !</p>
        </div>
    @else
        <div class="store-grid">
            @foreach ($products as $product)
                @php
                    $firstImg = $product->images->first();
                    $hasDiscount = $product->compare_at_price && $product->compare_at_price > $product->price;
                    $discountPct = $hasDiscount ? round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100) : 0;
                    $categoriesList = $product->categories->pluck('slug')->join(' ');
                    $categoryName = $product->categories->first()?->name ?? '';
                    $waProductMsg = urlencode("Bonjour {$shop->name}, je souhaite commander cet article :\n- {$product->title}\n- Prix : " . number_format($product->price, 0, ',', ' ') . " FCFA\nLien : " . url('/b/' . $shop->subdomain . '/produits/' . $product->slug));
                    $waProductUrl = $shop->whatsapp_formatted ? "https://wa.me/{$shop->whatsapp_formatted}?text={$waProductMsg}" : '';
                @endphp
                <article class="product-card reveal-on-scroll" style="--delay: {{ $loop->index % 8 }}"
                    data-product-id="{{ $product->id }}"
                    data-product-title="{{ $product->title }}"
                    data-product-price="{{ number_format($product->price, 0, ',', ' ') }} FCFA"
                    data-product-old-price="{{ $hasDiscount ? number_format($product->compare_at_price, 0, ',', ' ') . ' FCFA' : '' }}"
                    data-product-discount="{{ $discountPct }}"
                    data-product-description="{{ e($product->description) }}"
                    data-product-category="{{ $categoryName }}"
                    data-product-categories="{{ $categoriesList }}"
                    data-product-url="{{ url('/b/' . $shop->subdomain . '/produits/' . $product->slug) }}"
                    data-product-wa="{{ $waProductUrl }}"
                    data-product-color="{{ $shop->brand_color ?: '#10b981' }}"
                    data-product-images='@json($product->images->map(fn($i) => ["url" => $i->url, "thumb" => $i->thumbnail_url]))'
                >
                    <div class="product-img-wrap">
                        <a href="{{ url('/b/' . $shop->subdomain . '/produits/' . $product->slug) }}">
                            @if ($firstImg)
                                <img src="{{ $firstImg->thumbnail_url }}" alt="{{ $product->title }}" loading="lazy" onerror="this.onerror=null; this.src='/api/placeholder-image?id={{ $product->id }}&color={{ urlencode($shop->brand_color ?: '#10b981') }}'">
                            @else
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--bg-soft);color:var(--text-faint)">
                                    <x-icon name="shopping-bag" size="40" />
                                </div>
                            @endif
                        </a>

                        @if ($hasDiscount)
                            <span class="product-discount-badge">-{{ $discountPct }}%</span>
                        @endif

                        <span class="product-card-badge-top">
                            <span class="card-stock-dot"></span> Prêt à livrer
                        </span>

                        <!-- Boutons d'Action Flottants (Aperçu + WhatsApp direct) -->
                        <div class="product-card-actions-float">
                            <button type="button" class="btn-card-action btn-card-qv" onclick="openQuickView(this)" title="Aperçu rapide" aria-label="Aperçu rapide de {{ $product->title }}">
                                <x-icon name="eye" size="16" />
                            </button>
                            @if ($waProductUrl)
                                <a href="{{ $waProductUrl }}" target="_blank" class="btn-card-action btn-card-wa" title="Commander sur WhatsApp" aria-label="Commander sur WhatsApp">
                                    <x-icon name="whatsapp" size="16" />
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="product-card-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:.4rem;margin-bottom:.3rem">
                            @if ($product->categories->isNotEmpty())
                                <span style="font-size:.72rem;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;font-weight:600">
                                    {{ $product->categories->first()->name }}
                                </span>
                            @else
                                <span style="font-size:.72rem;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;font-weight:600">
                                    Sélection
                                </span>
                            @endif
                            <span class="card-stock-pill">
                                <span class="card-stock-dot"></span> Dispo
                            </span>
                        </div>

                        <div class="product-rating-row">
                            <span class="product-stars">★★★★★</span>
                            <span class="product-rating-text">4.9 • Choix populaire</span>
                        </div>

                        <h3 class="product-card-title">
                            <a href="{{ url('/b/' . $shop->subdomain . '/produits/' . $product->slug) }}">
                                {{ $product->title }}
                            </a>
                        </h3>

                        <div class="product-price-box">
                            <span class="product-price">{{ number_format($product->price, 0, ',', ' ') }} <small style="font-size:.65em;font-weight:700">FCFA</small></span>
                            @if ($hasDiscount)
                                <span class="product-old-price">{{ number_format($product->compare_at_price, 0, ',', ' ') }} FCFA</span>
                            @endif
                        </div>

                        <!-- Actions Carte Produit (Ajout AJAX fluide + WhatsApp) -->
                        <div style="margin-top:.85rem;display:flex;gap:.45rem;align-items:center">
                            <form class="ajax-add-cart" method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/ajouter') }}" style="flex:1">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-add-cart btn-block" style="width:100%" aria-label="Ajouter {{ $product->title }} au panier">
                                    <x-icon name="shopping-bag" size="15" /> + Ajouter
                                </button>
                            </form>
                            @if ($waProductUrl)
                                <a href="{{ $waProductUrl }}" target="_blank" class="btn-card-wa-icon" title="Commander en 1 clic sur WhatsApp" aria-label="Commander sur WhatsApp">
                                    <x-icon name="whatsapp" size="18" />
                                </a>
                            @endif
                            <a href="{{ url('/b/' . $shop->subdomain . '/produits/' . $product->slug) }}" class="btn btn-ghost btn-sm" aria-label="Voir la fiche détaillée" style="display:inline-flex;align-items:center;padding:.5rem .7rem">
                                <x-icon name="arrow-right" size="16" />
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>

<!-- Garanties & Engagements avec Effet de Relief -->
<div class="container" style="margin-top:4rem">
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1.5rem">
        <div class="card" style="text-align:center;padding:2rem 1.4rem;transition:transform .2s;border-radius:14px">
            <div style="margin-bottom:.8rem;display:flex;justify-content:center;color:var(--store-primary)">
                <x-icon name="truck" size="36" />
            </div>
            <h3 style="font-size:1.15rem;margin-bottom:.4rem;font-weight:700">Livraison Rapide</h3>
            <p style="font-size:.9rem;color:var(--text-soft);margin:0;line-height:1.5">Expédition soignée à Lomé et dans toutes les préfectures du Togo.</p>
        </div>

        <div class="card" style="text-align:center;padding:2rem 1.4rem;transition:transform .2s;border-radius:14px">
            <div style="margin-bottom:.8rem;display:flex;justify-content:center;color:var(--store-primary)">
                <x-icon name="wallet" size="36" />
            </div>
            <h3 style="font-size:1.15rem;margin-bottom:.4rem;font-weight:700">Paiement Mobile Money</h3>
            <p style="font-size:.9rem;color:var(--text-soft);margin:0;line-height:1.5">Règlement direct via T-Money, Flooz ou paiement à la livraison en espèces.</p>
        </div>

        <div class="card" style="text-align:center;padding:2rem 1.4rem;transition:transform .2s;border-radius:14px">
            <div style="margin-bottom:.8rem;display:flex;justify-content:center;color:var(--store-primary)">
                <x-icon name="whatsapp" size="36" />
            </div>
            <h3 style="font-size:1.15rem;margin-bottom:.4rem;font-weight:700">Service WhatsApp Direct</h3>
            <p style="font-size:.9rem;color:var(--text-soft);margin:0;line-height:1.5">Une équipe à votre écoute pour vous conseiller et confirmer vos commandes.</p>
        </div>
    </div>
</div>

<!-- Section Boutique Physique & Localisation Maps -->
@if ($shop->address || $shop->maps_url)
    <div class="container" style="margin-top:3.5rem">
        <div class="card" style="padding:2.2rem 1.8rem;border-radius:18px;background:radial-gradient(circle at 10% 20%, color-mix(in srgb, var(--store-primary) 8%, var(--surface)) 0%, var(--surface) 100%);border:1px solid color-mix(in srgb, var(--store-primary) 25%, var(--border));box-shadow:0 12px 32px rgba(0,0,0,0.06)">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1.5rem">
                <div style="max-width:560px">
                    <span style="display:inline-flex;align-items:center;gap:.4rem;padding:.3rem .75rem;border-radius:999px;background:color-mix(in srgb, var(--store-primary) 15%, transparent);color:var(--store-primary);font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.8rem">
                        <x-icon name="map-pin" :size="13" /> Boutique Physique
                    </span>
                    <h3 style="font-size:1.45rem;font-weight:700;margin:0 0 .6rem;color:var(--text)">
                        Venez nous rendre visite en magasin !
                    </h3>
                    <p style="color:var(--text-soft);font-size:.95rem;line-height:1.6;margin:0 0 1rem">
                        Découvrez nos articles en vrai, bénéficiez de conseils personnalisés et retirez vos commandes directement sur place.
                    </p>
                    <div style="display:flex;align-items:flex-start;gap:.6rem;font-size:.95rem;color:var(--text);font-weight:600">
                        <x-icon name="map-pin" :size="18" style="color:var(--store-primary);flex-shrink:0;margin-top:2px" />
                        <span>{{ $shop->address ? $shop->address . ' — ' : '' }}{{ $shop->city ?: 'Lomé, Togo' }}</span>
                    </div>
                </div>

                @if ($shop->maps_url)
                    <div style="display:flex;flex-direction:column;gap:.75rem;align-items:flex-start">
                        <a href="{{ $shop->maps_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-store" style="padding:.75rem 1.4rem;font-size:.95rem;display:inline-flex;align-items:center;gap:.5rem;border-radius:12px;box-shadow:0 6px 20px color-mix(in srgb, var(--store-primary) 35%, transparent)">
                            <x-icon name="map" :size="17" />
                            <span>Ouvrir l'itinéraire Google Maps ↗</span>
                        </a>
                        <span style="font-size:.78rem;color:var(--text-faint);display:inline-flex;align-items:center;gap:.3rem">
                            <x-icon name="check-circle" :size="13" style="color:var(--ok)" /> Localisation GPS vérifiée
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
@endsection
