@extends('layouts.app')

@section('title', 'Gestion des Produits : '.config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0">Catalogue</p>
            <h1>Mes Produits</h1>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            <a href="{{ route('categories.index') }}" class="btn btn-ghost">Gérer les catégories</a>
            <a id="btn-add-product" href="{{ route('products.create') }}" class="btn btn-primary">+ Ajouter un produit</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif

    <!-- Barre de filtres et recherche (C-05) -->
    <div class="card panel" style="margin-bottom: 1.5rem; padding: 1.2rem;">
        <form method="GET" action="{{ route('products.index') }}" class="toolbar">
            <div class="filters">
                <input type="text" name="search" class="input" style="min-width:220px;" placeholder="Rechercher par nom, réf..." value="{{ request('search') }}">
                
                <select name="category_id" class="select" style="min-width:160px;" onchange="this.form.submit()">
                    <option value="">Toutes les catégories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>

                <select name="status" class="select" style="min-width:140px;" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <option value="active" @selected(request('status') === 'active')>Actifs</option>
                    <option value="hidden" @selected(request('status') === 'hidden')>Masqués</option>
                    <option value="archived" @selected(request('status') === 'archived')>Archivés</option>
                </select>

                <select name="filter" class="select" style="min-width:160px;" onchange="this.form.submit()">
                    <option value="">Tous les stocks</option>
                    <option value="low_stock" @selected(request('filter') === 'low_stock')>Stock bas (≤ seuil)</option>
                    <option value="out_of_stock" @selected(request('filter') === 'out_of_stock')>Épuisé (0)</option>
                </select>
            </div>

            <div style="display:flex;gap:.5rem">
                <button type="submit" class="btn btn-ghost btn-sm">Filtrer</button>
                @if (request()->hasAny(['search', 'category_id', 'status', 'filter']))
                    <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm" style="color:var(--text-faint)">Effacer</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Tableau des produits -->
    <section class="card panel">
        @if ($products->isEmpty())
            <div class="empty">
                <div style="display:flex;justify-content:center;margin-bottom:1rem;color:var(--text-soft)" aria-hidden="true">
                    <x-icon name="tag" size="48" />
                </div>
                Aucun produit ne correspond à votre recherche.<br>
                <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm" style="margin-top:1rem">+ Créer votre premier produit</a>
            </div>
        @else
            <div class="table-wrap">
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
                                    @if ($prod->primaryImage)
                                        <img src="{{ $prod->primaryImage->thumbnail_url }}" alt="{{ $prod->title }}" class="thumb-preview" loading="lazy" style="width:52px;height:52px;min-width:52px;min-height:52px;max-width:52px;max-height:52px;border-radius:10px;object-fit:cover;display:block;">
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
                                    <a href="{{ route('products.edit', $prod) }}" class="btn btn-ghost btn-sm">Modifier</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div style="margin-top:1.5rem">
                {{ $products->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
