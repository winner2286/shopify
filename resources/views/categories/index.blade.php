@extends('layouts.app')

@section('title', 'Gestion des Catégories : '.config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('products.index') }}" style="display:inline-flex;align-items:center;gap:.3rem"><x-icon name="arrow-left" size="14" /> Retour aux Produits</a></p>
            <h1>Catégories</h1>
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

    <div class="dash-grid" style="grid-template-columns: minmax(300px, 1fr) minmax(300px, 2fr);">
        <!-- Formulaire création de catégorie -->
        <section class="card panel">
            <h3>Ajouter une catégorie</h3>
            <form method="POST" action="{{ route('categories.store') }}">
                @csrf
                <div class="field">
                    <label for="name">Nom de la catégorie</label>
                    <input id="name" name="name" type="text" class="input" placeholder="Ex: Chaussures, Robes..." required>
                </div>

                <div class="field">
                    <label for="display_order">Ordre d'affichage</label>
                    <input id="display_order" name="display_order" type="number" class="input" value="{{ $categories->count() + 1 }}" min="0">
                </div>

                <div class="field">
                    <label class="check">
                        <input type="checkbox" name="is_visible" value="1" checked>
                        <span>Visible sur la vitrine</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Créer la catégorie</button>
            </form>
        </section>

        <!-- Liste des catégories existantes -->
        <section class="card panel">
            <h3>Mes Catégories ({{ $categories->count() }})</h3>
            @if ($categories->isEmpty())
                <div class="empty">
                    <div style="display:flex;justify-content:center;margin-bottom:1rem;color:var(--text-soft)" aria-hidden="true">
                        <x-icon name="folder" size="48" />
                    </div>
                    Aucune catégorie créée pour l'instant.<br>
                    Ajoutez vos catégories pour organiser votre boutique.
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ordre</th>
                                <th>Nom</th>
                                <th>Produits</th>
                                <th>Visibilité</th>
                                <th style="text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $cat)
                                <tr>
                                    <td style="width:50px;">{{ $cat->display_order }}</td>
                                    <td><strong>{{ $cat->name }}</strong></td>
                                    <td>{{ $cat->products_count }} produit(s)</td>
                                    <td>
                                        <span class="badge-status {{ $cat->is_visible ? 'badge-active' : 'badge-hidden' }}">
                                            {{ $cat->is_visible ? 'Visible' : 'Masquée' }}
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <form method="POST" action="{{ route('categories.destroy', $cat) }}" style="display:inline;" data-confirm="Supprimer cette catégorie ? Les produits resteront disponibles.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
