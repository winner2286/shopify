@extends('layouts.app')

@section('title', 'Frais & Zones de Livraison : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1000px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">Zones & Frais de Livraison (G-01)</h1>
            <p style="margin:0;color:var(--text-soft)">Définissez où vous livrez, vos délais et vos tarifs appliqués au checkout.</p>
        </div>

        <div class="header-actions">
            <a href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="arrow-left" size="14" />
                Voir les commandes
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert" style="margin-bottom:1.5rem">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:2rem;align-items:start">
        <!-- Liste des zones existantes -->
        <div>
            <h2 style="font-size:1.2rem;margin-top:0;margin-bottom:1rem">Zones Actives</h2>

            <div style="display:flex;flex-direction:column;gap:1rem">
                @forelse ($zones as $zone)
                    <div class="card" style="padding:1.2rem">
                        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:.5rem">
                            <div>
                                <h3 style="margin:0;font-size:1.05rem">{{ $zone->name }}</h3>
                                <div style="font-size:.85rem;color:var(--text-soft)">{{ $zone->delivery_estimate }}</div>
                            </div>

                            <div style="font-size:1.2rem;font-weight:700;font-family:var(--font-display);color:{{ $zone->price === 0 ? 'var(--ok)' : 'var(--text)' }}">
                                {{ $zone->price === 0 ? 'Gratuit' : number_format($zone->price, 0, ',', ' ') . ' FCFA' }}
                            </div>
                        </div>

                        @if ($zone->free_shipping_threshold)
                            <div style="font-size:.8rem;color:var(--ok);margin-bottom:.8rem;display:inline-flex;align-items:center;gap:.3rem">
                                <x-icon name="check" size="14" /> Livraison gratuite dès {{ number_format($zone->free_shipping_threshold, 0, ',', ' ') }} FCFA d'achats
                            </div>
                        @endif

                        <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border);padding-top:.8rem;margin-top:.4rem">
                            <span class="badge {{ $zone->is_active ? 'badge-ok' : 'badge-draft' }}" style="font-size:.75rem">
                                {{ $zone->is_active ? 'Active' : 'Désactivée' }}
                            </span>

                            <form method="POST" action="{{ route('shipping.destroy', $zone) }}" onsubmit="return confirm('Supprimer cette zone ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);padding:.2rem .6rem;font-size:.85rem">
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="card" style="text-align:center;padding:2rem">
                        Aucune zone de livraison configurée.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Formulaire d'ajout -->
        <div class="card">
            <h2 style="font-size:1.2rem;margin-top:0;margin-bottom:1rem">Ajouter une Zone de Livraison</h2>

            <form method="POST" action="{{ route('shipping.store') }}">
                @csrf

                <div style="margin-bottom:1rem">
                    <label for="name" class="label">Nom de la zone ou du secteur *</label>
                    <input id="name" type="text" name="name" required placeholder="Ex. Lomé Commune, Adidogomé..." class="input">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
                    <div>
                        <label for="price" class="label">Tarif en FCFA *</label>
                        <input id="price" type="number" name="price" value="1000" min="0" required class="input">
                    </div>

                    <div>
                        <label for="free_shipping_threshold" class="label">Gratuit dès (FCFA)</label>
                        <input id="free_shipping_threshold" type="number" name="free_shipping_threshold" min="0" placeholder="Ex. 25000" class="input">
                    </div>
                </div>

                <div style="margin-bottom:1rem">
                    <label for="delivery_estimate" class="label">Délai estimé affiché au client *</label>
                    <input id="delivery_estimate" type="text" name="delivery_estimate" value="Livré en 24h" required class="input">
                </div>

                <div style="margin-bottom:1.5rem;background:var(--bg-soft);padding:.8rem;border-radius:var(--radius-sm)">
                    <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer">
                        <input type="checkbox" name="is_pickup" value="1" style="width:18px;height:18px;accent-color:var(--brand-1)">
                        <span style="font-weight:600;font-size:.9rem">Option Retrait en boutique (Gratuit)</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    + Enregistrer la zone
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
