@extends('storefront.layout')

@section('title', 'Catalogue : ' . ($activeCategory ? $activeCategory->name . ' - ' : '') . $shop->name)

@section('content')
<div class="container" style="padding-top:2rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">
                {{ $activeCategory ? $activeCategory->name : 'Tous les produits' }}
            </h1>
            <p style="margin:0;color:var(--text-soft);font-size:.95rem">
                {{ $products->total() }} article(s) trouvé(s)
            </p>
        </div>

        <!-- Filtres et Tri -->
        <form method="GET" action="{{ url('/b/' . $shop->subdomain . '/produits') }}" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            @if ($activeCategory)
                <input type="hidden" name="categorie" value="{{ $activeCategory->slug }}">
            @endif

            <input type="text" name="q" value="{{ $search }}" placeholder="Rechercher un article..." class="input" style="padding:.5rem .8rem;font-size:.9rem;min-width:180px">

            <select name="tri" class="input" style="padding:.5rem .8rem;font-size:.9rem;width:auto" onchange="this.form.submit()">
                <option value="recent" {{ $sort === 'recent' ? 'selected' : '' }}>Nouveautés</option>
                <option value="prix_croissant" {{ $sort === 'prix_croissant' ? 'selected' : '' }}>Prix croissant</option>
                <option value="prix_decroissant" {{ $sort === 'prix_decroissant' ? 'selected' : '' }}>Prix décroissant</option>
            </select>

            <button type="submit" class="btn btn-ghost btn-sm">Filtrer</button>
        </form>
    </div>

    <!-- Catégories Chips -->
    @if ($categories->isNotEmpty())
        <div class="cat-scroll" style="margin-bottom:1.5rem">
            <a href="{{ url('/b/' . $shop->subdomain . '/produits' . ($search ? '?q='.$search : '')) }}" class="cat-chip {{ ! $activeCategory ? 'active' : '' }}" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="sparkles" size="14" /> Tous
            </a>
            @foreach ($categories as $cat)
                <a href="{{ url('/b/' . $shop->subdomain . '/produits?categorie=' . $cat->slug . ($search ? '&q='.$search : '')) }}" class="cat-chip {{ $activeCategory && $activeCategory->id === $cat->id ? 'active' : '' }}">
                    {{ $cat->name }}
                </a>
            @endforeach
        </div>
    @endif

    <!-- Grille de Produits -->
    @if ($products->isEmpty())
        <div class="card" style="text-align:center;padding:4rem 1rem;margin:2rem 0">
            <div style="margin-bottom:1rem;color:var(--text-soft);display:flex;justify-content:center">
                <x-icon name="search" size="40" />
            </div>
            <h3 style="font-size:1.3rem;margin-bottom:.5rem">Aucun article ne correspond à votre recherche</h3>
            <p style="color:var(--text-soft);margin-bottom:1.5rem">Essayez d'autres mots-clés ou consultez l'ensemble du catalogue.</p>
            <a href="{{ url('/b/' . $shop->subdomain . '/produits') }}" class="btn btn-primary btn-sm">
                Réinitialiser les filtres
            </a>
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
                <article class="product-card"
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

                        <button type="button" class="btn-quick-view" onclick="openQuickView(this)" title="Aperçu rapide" aria-label="Aperçu rapide de {{ $product->title }}">
                            <x-icon name="eye" size="18" />
                        </button>
                    </div>

                    <div class="product-card-body">
                        @if ($product->categories->isNotEmpty())
                            <div style="font-size:.75rem;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem">
                                {{ $product->categories->first()->name }}
                            </div>
                        @endif

                        <h3 class="product-card-title">
                            <a href="{{ url('/b/' . $shop->subdomain . '/produits/' . $product->slug) }}">
                                {{ $product->title }}
                            </a>
                        </h3>

                        <div class="product-price-box">
                            <span class="product-price">{{ number_format($product->price, 0, ',', ' ') }} FCFA</span>
                            @if ($hasDiscount)
                                <span class="product-old-price">{{ number_format($product->compare_at_price, 0, ',', ' ') }} FCFA</span>
                            @endif
                        </div>

                        <div style="margin-top:.85rem;display:flex;gap:.4rem;align-items:center">
                            <form class="ajax-add-cart" method="POST" action="{{ url('/b/' . $shop->subdomain . '/panier/ajouter') }}" style="flex:1">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-add-cart btn-block" style="width:100%">
                                    <x-icon name="shopping-cart" size="15" /> + Ajouter
                                </button>
                            </form>
                            <a href="{{ url('/b/' . $shop->subdomain . '/produits/' . $product->slug) }}" class="btn btn-ghost btn-sm" aria-label="Voir la fiche détaillée" style="display:inline-flex;align-items:center;padding:.5rem .7rem">
                                <x-icon name="arrow-right" size="16" />
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div style="margin:2.5rem 0;display:flex;justify-content:center">
            {{ $products->links() }}
        </div>
    @endif
</div>
@endsection
