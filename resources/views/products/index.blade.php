@extends('layouts.app')

@section('title', 'Gestion des Produits : '.config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0">Catalogue</p>
            <h1>Mes Produits</h1>
        </div>
        <div class="dash-head-actions">
            <a href="{{ route('categories.index') }}" class="btn btn-ghost dash-head-btn">
                <span class="btn-full-text">Gérer les catégories</span>
                <span class="btn-short-text">Catégories</span>
            </a>
            <a id="btn-add-product" href="{{ route('products.create') }}" class="btn btn-primary dash-head-btn">
                <span class="btn-full-text">+ Ajouter un produit</span>
                <span class="btn-short-text">+ Produit</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif

    @php
        $userShop = auth()->user()->shop;
        $activePlan = $userShop?->currentPlan();
        $prodCount = $userShop?->products()->count() ?? 0;
        $maxProd = $userShop?->maxProducts() ?? 50;
        $prodPct = min(100, round(($prodCount / max(1, $maxProd)) * 100));
    @endphp

    @if ($userShop && $activePlan)
        <div style="margin-bottom:1.25rem;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:.85rem 1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
            <div style="display:flex;align-items:center;gap:.75rem;flex:1;min-width:240px">
                <div style="font-size:.9rem">
                    Formule <strong>{{ $activePlan->name }}</strong> :
                    <span style="color:{{ $prodCount >= $maxProd ? 'var(--danger)' : 'var(--text-soft)' }}">
                        <strong>{{ $prodCount }}</strong> / {{ $maxProd }} articles utilisés
                    </span>
                </div>
                <div style="flex:1;max-width:160px;height:7px;background:var(--bg-soft);border-radius:99px;overflow:hidden;border:1px solid var(--border)">
                    <div style="height:100%;width:{{ $prodPct }}%;background:{{ $prodCount >= $maxProd ? 'var(--danger)' : 'var(--brand-primary)' }};border-radius:99px"></div>
                </div>
            </div>
            @if ($activePlan->isEssentiel() || $activePlan->isPro())
                <a href="{{ route('home') }}#tarifs" target="_blank" style="font-size:.82rem;font-weight:600;color:var(--brand-primary);text-decoration:none;display:inline-flex;align-items:center;gap:.3rem">
                    Agrandir mon catalogue (Changer de formule) →
                </a>
            @endif
        </div>
    @endif

    <!-- Barre de filtres et recherche responsive (C-05) -->
    <div class="card panel product-filters-card" style="margin-bottom: 1.25rem; padding: 1rem 1.15rem;">
        <form method="GET" action="{{ route('products.index') }}" class="product-filters-form">
            <div class="product-filters-inputs">
                <input type="text" name="search" class="input product-filter-search" placeholder="Rechercher par nom, réf..." value="{{ request('search') }}">
                
                <select name="category_id" class="select product-filter-select" onchange="this.form.submit()">
                    <option value="">Toutes les catégories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>

                <select name="status" class="select product-filter-select" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <option value="active" @selected(request('status') === 'active')>Actifs</option>
                    <option value="hidden" @selected(request('status') === 'hidden')>Masqués</option>
                    <option value="archived" @selected(request('status') === 'archived')>Archivés</option>
                </select>

                <select name="filter" class="select product-filter-select" onchange="this.form.submit()">
                    <option value="">Tous les stocks</option>
                    <option value="in_stock" @selected(request('filter') === 'in_stock')>En stock uniquement</option>
                    <option value="low_stock" @selected(request('filter') === 'low_stock')>Stock bas (≤ seuil)</option>
                    <option value="out_of_stock" @selected(request('filter') === 'out_of_stock')>Épuisé (Rupture)</option>
                </select>
            </div>

            <div class="product-filters-actions">
                <button type="submit" class="btn btn-ghost btn-sm">Filtrer</button>
                @if (request()->hasAny(['search', 'category_id', 'status', 'filter']))
                    <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm" style="color:var(--text-faint)">Effacer</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Catalogue des produits -->
    @if ($products->isEmpty())
        <div class="card panel empty" style="text-align:center;padding:3.5rem 1rem">
            <div style="display:flex;justify-content:center;margin-bottom:1rem;color:var(--text-soft)" aria-hidden="true">
                <x-icon name="tag" size="48" />
            </div>
            <p style="margin:0 0 1rem;font-size:1.05rem">Aucun produit ne correspond à votre recherche.</p>
            @if (request()->hasAny(['search', 'category_id', 'status', 'filter']))
                <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm">Réinitialiser les filtres</a>
            @else
                <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm">+ Créer votre premier produit</a>
            @endif
        </div>
    @else
        <!-- Vue Cartes Mobiles pour Produits (visible sur mobile <= 768px uniquement) -->
        <div class="product-mobile-cards">
                @foreach ($products as $prod)
                    <div class="product-mobile-card">
                        @php $thumb = $prod->primaryImage?->thumbnail_url ?? $prod->images->first()?->thumbnail_url; @endphp
                        <div class="product-mobile-thumb-wrap">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="{{ $prod->title }}" class="product-mobile-card-img" loading="lazy" onerror="this.onerror=null; this.src='/api/placeholder-image?id={{ $prod->id }}'">
                            @else
                                <div class="product-mobile-card-img placeholder">
                                    <x-icon name="camera" size="22" />
                                </div>
                            @endif
                            @if ($prod->compare_at_price && $prod->compare_at_price > $prod->price)
                                @php $discountPct = round((($prod->compare_at_price - $prod->price) / $prod->compare_at_price) * 100); @endphp
                                <span class="product-mobile-badge-promo">-{{ $discountPct }}%</span>
                            @endif
                        </div>

                        <div class="product-mobile-card-info">
                            <!-- Titre & Statut -->
                            <div class="product-mobile-card-head">
                                <a href="{{ route('products.edit', $prod) }}" class="product-mobile-card-title">
                                    {{ $prod->title }}
                                </a>
                                <span class="badge-status {{ $prod->status === 'active' ? 'badge-active' : ($prod->status === 'hidden' ? 'badge-hidden' : 'badge-archived') }}" style="flex-shrink:0;font-size:.7rem;padding:.2rem .5rem">
                                    {{ $prod->status === 'active' ? 'Actif' : ($prod->status === 'hidden' ? 'Masqué' : 'Archivé') }}
                                </span>
                            </div>

                            <!-- Catégorie & Stock -->
                            <div class="product-mobile-card-meta">
                                <span class="product-mobile-card-cat">
                                    @if ($prod->categories->isNotEmpty())
                                        {{ $prod->categories->pluck('name')->join(', ') }}
                                    @else
                                        <em>Non classé</em>
                                    @endif
                                </span>
                                <span class="product-mobile-card-dot">•</span>
                                <span class="product-mobile-card-stock">
                                    @if (! $prod->track_stock)
                                        <span style="color:var(--text-soft)">Illimité</span>
                                    @elseif ($prod->isOutOfStock())
                                        <span style="color:#ef4444;font-weight:600">Épuisé (0)</span>
                                    @elseif ($prod->isLowStock())
                                        <span style="color:#f59e0b;font-weight:600">Bas ({{ $prod->stock }})</span>
                                    @else
                                        <span style="color:#10b981;font-weight:600">{{ $prod->stock }} en stock</span>
                                    @endif
                                </span>
                            </div>

                            <!-- Prix & Actions -->
                            <div class="product-mobile-card-bottom">
                                <div class="product-mobile-card-price-wrap">
                                    <span class="product-mobile-card-price">
                                        {{ number_format($prod->price, 0, ',', ' ') }} FCFA
                                    </span>
                                    @if ($prod->compare_at_price && $prod->compare_at_price > $prod->price)
                                        <span class="product-mobile-card-compare">
                                            {{ number_format($prod->compare_at_price, 0, ',', ' ') }} FCFA
                                        </span>
                                    @endif
                                </div>

                                <div class="product-mobile-card-actions">
                                    @if ($userShop && $prod->status === 'active')
                                        <a href="{{ url('/b/' . $userShop->subdomain . '/produits/' . $prod->slug) }}" target="_blank" class="btn btn-ghost btn-sm product-mobile-action-btn" title="Voir sur ma vitrine">
                                            <x-icon name="external-link" size="13" />
                                        </a>
                                    @endif
                                    <a href="{{ route('ads.flyers', ['produit_id' => $prod->id]) }}" class="btn btn-ghost btn-sm product-mobile-action-btn" title="Créer une affiche promo">
                                        <x-icon name="image" size="13" />
                                        <span>Affiche</span>
                                    </a>
                                    <a href="{{ route('products.edit', $prod) }}" class="btn btn-ghost btn-sm product-mobile-action-btn primary" title="Modifier">
                                        Modifier
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

        <!-- Vue Tableau Tablette / Ordinateur (visible sur desktop > 768px uniquement) -->
        <div class="card panel table-wrap products-desktop-table" style="margin-bottom:1.5rem">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:60px">Photo</th>
                        <th>Titre & Catégorie</th>
                        <th>Prix (FCFA)</th>
                        <th>Stock</th>
                        <th>Statut</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $prod)
                        <tr>
                            <td style="width:60px">
                                @php $thumb = $prod->primaryImage?->thumbnail_url ?? $prod->images->first()?->thumbnail_url; @endphp
                                @if ($thumb)
                                    <img src="{{ $thumb }}" alt="{{ $prod->title }}" class="thumb-preview" loading="lazy" style="width:52px;height:52px;min-width:52px;min-height:52px;max-width:52px;max-height:52px;border-radius:10px;object-fit:cover;display:block;" onerror="this.onerror=null; this.src='/api/placeholder-image?id={{ $prod->id }}'">
                                @else
                                    <div class="thumb-preview" style="width:52px;height:52px;min-width:52px;min-height:52px;max-width:52px;max-height:52px;border-radius:10px;display:grid;place-items:center;color:var(--text-faint)">
                                        <x-icon name="camera" size="18" />
                                    </div>
                                @endif
                            </td>
                            <td>
                                <strong><a href="{{ route('products.edit', $prod) }}">{{ $prod->title }}</a></strong>
                                <div style="font-size:.82rem;color:var(--text-soft)">
                                    @if ($prod->categories->isNotEmpty())
                                        {{ $prod->categories->pluck('name')->join(', ') }}
                                    @else
                                        <em>Non classé</em>
                                    @endif
                                    @if ($prod->sku) • Réf: {{ $prod->sku }} @endif
                                </div>
                            </td>
                            <td>
                                <strong>{{ number_format($prod->price, 0, ',', ' ') }} FCFA</strong>
                                @if ($prod->compare_at_price)
                                    <div style="font-size:.8rem;color:var(--text-faint);text-decoration:line-through;">
                                        {{ number_format($prod->compare_at_price, 0, ',', ' ') }} FCFA
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if (! $prod->track_stock)
                                    <span class="badge-status" style="background:hsl(240 20% 50%/.15);color:var(--text-soft)">Illimité</span>
                                @elseif ($prod->isOutOfStock())
                                    <span class="badge-status badge-out-of-stock">Épuisé (0)</span>
                                @elseif ($prod->isLowStock())
                                    <span class="badge-status badge-low-stock">Bas ({{ $prod->stock }})</span>
                                @else
                                    <span style="font-weight:600">{{ $prod->stock }} en stock</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge-status {{ $prod->status === 'active' ? 'badge-active' : 'badge-hidden' }}">
                                    {{ $prod->status === 'active' ? 'Actif' : ($prod->status === 'hidden' ? 'Masqué' : 'Archivé') }}
                                </span>
                            </td>
                            <td style="text-align:right">
                                <div style="display:inline-flex;gap:.35rem;align-items:center">
                                    <a href="{{ route('ads.flyers', ['produit_id' => $prod->id]) }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem" title="Créer une affiche promo 1-clic pour ce produit">
                                        <x-icon name="image" size="13" /> <span>Affiche</span>
                                    </a>
                                    <a href="{{ route('products.edit', $prod) }}" class="btn btn-ghost btn-sm">Modifier</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination (affichée uniquement s'il y a plusieurs pages) -->
        @if ($products->hasPages())
            <div class="products-pagination" style="margin-top:1.25rem;margin-bottom:1rem;display:flex;justify-content:center">
                {{ $products->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
