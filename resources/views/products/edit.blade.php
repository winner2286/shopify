@extends('layouts.app')

@section('title', 'Modifier : '.$product->title.' - '.config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('products.index') }}">← Retour au catalogue</a></p>
            <h1>Modifier le produit</h1>
        </div>
        <div>
            <form method="POST" action="{{ route('products.destroy', $product) }}" style="display:inline" onsubmit="return confirm('Archiver ce produit ? Il ne sera plus proposé sur la boutique.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">Archiver le produit</button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-err" role="alert">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data" data-once>
        @csrf
        @method('PUT')
        
        <div class="dash-grid" style="grid-template-columns: minmax(320px, 2fr) minmax(300px, 1fr);">
            
            <div style="display:flex;flex-direction:column;gap:1.5rem">
                <!-- Détails produit -->
                <section class="card panel">
                    <h3>Détails du produit</h3>
                    
                    <div class="field">
                        <label for="title">Titre du produit <span style="color:var(--danger)">*</span></label>
                        <input id="title" name="title" type="text" class="input" value="{{ old('title', $product->title) }}" maxlength="120" required>
                    </div>

                    <div class="field">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="input" rows="5">{{ old('description', $product->description) }}</textarea>
                    </div>

                    <div class="field">
                        <label for="sku">Référence interne (SKU)</label>
                        <input id="sku" name="sku" type="text" class="input" value="{{ old('sku', $product->sku) }}">
                    </div>
                </section>

                <!-- Photos actuelles & Ajout de photos -->
                <section class="card panel">
                    <h3>Photos du produit ({{ $product->images->count() }} / 8)</h3>
                    
                    @if ($product->images->isNotEmpty())
                        <div class="gallery-grid">
                            @foreach ($product->images as $img)
                                <div class="gallery-item">
                                    <img src="{{ $img->url }}" alt="">
                                    <button type="button" onclick="if(confirm('Supprimer cette photo ?')) document.getElementById('delete-img-{{ $img->id }}').submit();" title="Supprimer" style="display:grid;place-items:center"><x-icon name="x" size="14" /></button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($product->images->count() < 8)
                        <div class="field" style="margin-top:1rem">
                            <label>Ajouter d'autres photos</label>
                            <input type="file" name="photos[]" class="input" multiple accept="image/png,image/jpeg,image/webp">
                        </div>
                    @else
                        <div class="hint">Vous avez atteint le maximum de 8 photos.</div>
                    @endif
                </section>

                <!-- Stock et Ajustements (C-04) -->
                <section class="card panel">
                    <h3>Gestion du stock</h3>
                    
                    <div class="field">
                        <label class="check">
                            <input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product->track_stock))>
                            <span>Suivre la quantité en stock pour ce produit</span>
                        </label>
                    </div>

                    <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;margin:1rem 0">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span>Stock actuel :</span>
                            <strong style="font-size:1.3rem;">{{ $product->stock }} unité(s)</strong>
                        </div>
                    </div>

                    <h4>Ajustement manuel du stock</h4>
                    <div class="dash-grid" style="grid-template-columns: 1fr 2fr; margin-bottom:1rem">
                        <div class="field">
                            <label for="stock_adjustment">Variation (+ ou -)</label>
                            <input id="stock_adjustment" name="stock_adjustment" type="number" class="input" placeholder="Ex: +5 ou -2">
                        </div>
                        <div class="field">
                            <label for="adjustment_reason">Motif de l'ajustement</label>
                            <input id="adjustment_reason" name="adjustment_reason" type="text" class="input" placeholder="Ex: Réassort, Perte, Erreur comptage">
                        </div>
                    </div>

                    <div class="field">
                        <label for="low_stock_threshold">Seuil d'alerte stock bas</label>
                        <input id="low_stock_threshold" name="low_stock_threshold" type="number" class="input" value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}" min="1">
                    </div>

                    @if ($product->stockMovements->isNotEmpty())
                        <h4 style="margin-top:1.5rem">Historique des mouvements récents</h4>
                        <div class="table-wrap">
                            <table class="table" style="font-size:.85rem">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Variation</th>
                                        <th>Nouveau stock</th>
                                        <th>Motif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($product->stockMovements->take(5) as $mvt)
                                        <tr>
                                            <td>{{ $mvt->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <strong style="color: {{ $mvt->quantity_change > 0 ? 'var(--ok)' : 'var(--danger)' }}">
                                                    {{ $mvt->quantity_change > 0 ? '+'.$mvt->quantity_change : $mvt->quantity_change }}
                                                </strong>
                                            </td>
                                            <td>{{ $mvt->new_stock }}</td>
                                            <td>{{ $mvt->comment ?? $mvt->reason }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <!-- Colonne latérale -->
            <div style="display:flex;flex-direction:column;gap:1.5rem">
                <!-- Tarification -->
                <section class="card panel">
                    <h3>Tarification (FCFA)</h3>
                    
                    <div class="field">
                        <label for="price">Prix de vente (FCFA) <span style="color:var(--danger)">*</span></label>
                        <input id="price" name="price" type="number" class="input" value="{{ old('price', $product->price) }}" min="1" step="1" required>
                    </div>

                    <div class="field">
                        <label for="compare_at_price">Prix barré (FCFA)</label>
                        <input id="compare_at_price" name="compare_at_price" type="number" class="input" value="{{ old('compare_at_price', $product->compare_at_price) }}" min="1" step="1">
                    </div>
                </section>

                <!-- Organisation & Catégories -->
                <section class="card panel">
                    <h3>Organisation</h3>
                    
                    <div class="field">
                        <label>Catégories (1 à 3 max)</label>
                        @php $selectedCats = old('category_ids', $product->categories->pluck('id')->toArray()); @endphp
                        <div style="display:flex;flex-direction:column;gap:.4rem;max-height:160px;overflow-y:auto">
                            @foreach ($categories as $cat)
                                <label class="check">
                                    <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked(in_array($cat->id, $selectedCats))>
                                    <span>{{ $cat->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="field" style="margin-top:1.2rem">
                        <label for="status">Statut du produit</label>
                        <select id="status" name="status" class="select">
                            <option value="active" @selected(old('status', $product->status) === 'active')>Actif</option>
                            <option value="hidden" @selected(old('status', $product->status) === 'hidden')>Masqué</option>
                            <option value="archived" @selected(old('status', $product->status) === 'archived')>Archivé</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:1.5rem">Mettre à jour le produit</button>
                </section>
            </div>

        </div>
    </form>

    <!-- Formulaires cachés de suppression d'image -->
    @foreach ($product->images as $img)
        <form id="delete-img-{{ $img->id }}" method="POST" action="{{ route('products.images.destroy', [$product, $img]) }}" style="display:none">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</div>
@endsection
