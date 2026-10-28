@extends('layouts.app')

@section('title', 'Nouveau Produit : '.config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('products.index') }}">← Retour au catalogue</a></p>
            <h1>Ajouter un produit</h1>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-err" role="alert">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" data-once>
        @csrf
        <div class="dash-grid" style="grid-template-columns: minmax(320px, 2fr) minmax(300px, 1fr);">
            
            <!-- Informations principales -->
            <div style="display:flex;flex-direction:column;gap:1.5rem">
                <section class="card panel">
                    <h3>Détails du produit</h3>
                    
                    <div class="field">
                        <label for="title">Titre du produit <span style="color:var(--danger)">*</span></label>
                        <input id="title" name="title" type="text" class="input" value="{{ old('title') }}" placeholder="Ex: Chemise en lin blanc" maxlength="120" required autofocus>
                        <div class="hint">120 caractères maximum.</div>
                    </div>

                    <div class="field">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="input" rows="5" placeholder="Décrivez les atouts, matières, conseils d'entretien...">{{ old('description') }}</textarea>
                    </div>

                    <div class="field">
                        <label for="sku">Référence interne (SKU)</label>
                        <input id="sku" name="sku" type="text" class="input" value="{{ old('sku') }}" placeholder="Ex: CH-BLANC-01">
                    </div>
                </section>

                <!-- Photos (1 à 8 photos) -->
                <section class="card panel">
                    <h3>Photos du produit (jusqu'à 8 photos)</h3>
                    <p style="font-size:.9rem;color:var(--text-soft)">La première photo sera la photo principale de votre fiche produit.</p>
                    
                    <div class="field">
                        <input type="file" name="photos[]" class="input" multiple accept="image/png,image/jpeg,image/webp">
                        <div class="hint">Formats acceptés : JPG, PNG, WebP. 5 Mo maximum par photo.</div>
                    </div>
                </section>

                <!-- Stock et inventaire (C-04) -->
                <section class="card panel">
                    <h3>Stock & Disponibilité</h3>
                    
                    <div class="field">
                        <label class="check" style="margin-bottom:1rem">
                            <input type="checkbox" name="track_stock" id="track_stock" value="1" @checked(old('track_stock', true)) onchange="document.getElementById('stock-settings').style.display = this.checked ? 'block' : 'none'">
                            <span>Suivre la quantité en stock pour ce produit</span>
                        </label>
                    </div>

                    <div id="stock-settings" style="display: {{ old('track_stock', true) ? 'block' : 'none' }}">
                        <div class="dash-grid" style="grid-template-columns: 1fr 1fr; margin-bottom:0">
                            <div class="field">
                                <label for="stock">Quantité disponible</label>
                                <input id="stock" name="stock" type="number" class="input" value="{{ old('stock', 0) }}" min="0">
                            </div>
                            <div class="field">
                                <label for="low_stock_threshold">Seuil d'alerte stock bas</label>
                                <input id="low_stock_threshold" name="low_stock_threshold" type="number" class="input" value="{{ old('low_stock_threshold', 3) }}" min="1">
                                <div class="hint">Par défaut : 3 unités.</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Colonne latérale : Prix, Catégories, Statut -->
            <div style="display:flex;flex-direction:column;gap:1.5rem">
                <!-- Prix en FCFA -->
                <section class="card panel">
                    <h3>Tarification (FCFA)</h3>
                    
                    <div class="field">
                        <label for="price">Prix de vente (FCFA) <span style="color:var(--danger)">*</span></label>
                        <input id="price" name="price" type="number" class="input" value="{{ old('price') }}" placeholder="Ex: 15000" min="1" step="1" required>
                    </div>

                    <div class="field">
                        <label for="compare_at_price">Prix barré / promotion (FCFA)</label>
                        <input id="compare_at_price" name="compare_at_price" type="number" class="input" value="{{ old('compare_at_price') }}" placeholder="Ex: 18000" min="1" step="1">
                        <div class="hint">Laissez vide si le produit n'est pas en promotion.</div>
                    </div>
                </section>

                <!-- Organisation & Catégories (1 à 3) -->
                <section class="card panel">
                    <h3>Organisation</h3>
                    
                    <div class="field">
                        <label>Catégories (1 à 3 max)</label>
                        @if ($categories->isEmpty())
                            <div style="font-size:.9rem;color:var(--text-faint);margin-bottom:.5rem">
                                Aucune catégorie créée. <a href="{{ route('categories.index') }}">Créer une catégorie</a>
                            </div>
                        @else
                            <div style="display:flex;flex-direction:column;gap:.4rem;max-height:160px;overflow-y:auto">
                                @foreach ($categories as $cat)
                                    <label class="check">
                                        <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked(is_array(old('category_ids')) && in_array($cat->id, old('category_ids')))>
                                        <span>{{ $cat->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="field" style="margin-top:1.2rem">
                        <label for="status">Statut du produit</label>
                        <select id="status" name="status" class="select">
                            <option value="active" @selected(old('status', 'active') === 'active')>Actif (visible sur la vitrine)</option>
                            <option value="hidden" @selected(old('status') === 'hidden')>Masqué (brouillon)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:1.5rem">Enregistrer le produit</button>
                </section>
            </div>

        </div>
    </form>
</div>
@endsection
