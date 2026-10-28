@extends('storefront.layout')

@section('title', 'Suivi de commande - ' . $shop->name)

@section('content')
<div class="container" style="max-width:760px;padding-top:2.5rem;padding-bottom:4rem">
    <div style="text-align:center;margin-bottom:2rem">
        <h1 style="font-size:2rem;margin:0 0 .5rem">Suivre ma commande (D-06)</h1>
        <p style="color:var(--text-soft);margin:0">Saisissez votre numéro de commande et votre numéro de téléphone pour vérifier son avancement.</p>
    </div>

    <!-- Formulaire de recherche -->
    <div class="card" style="margin-bottom:2rem">
        <form method="GET" action="{{ route('storefront.tracking', $shop->subdomain) }}">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.2rem">
                <div>
                    <label for="numero" class="label">Numéro de commande *</label>
                    <input id="numero" type="text" name="numero" value="{{ old('numero', $orderNumber) }}" required placeholder="Ex. CMD-20261026-0001" class="input" style="font-family:monospace">
                </div>

                <div>
                    <label for="telephone" class="label">Numéro de téléphone *</label>
                    <input id="telephone" type="tel" name="telephone" value="{{ old('telephone', $phone) }}" required placeholder="Ex. 90 12 34 56" class="input">
                </div>
            </div>

            <button type="submit" class="btn-store btn-block" style="display:inline-flex;align-items:center;justify-content:center;gap:.5rem">
                <x-icon name="search" size="18" /> Rechercher ma commande
            </button>
        </form>
    </div>

    @if ($order)
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;border-bottom:1px solid var(--border);padding-bottom:1rem">
                <div>
                    <div style="font-size:.85rem;color:var(--text-soft)">Commande</div>
                    <strong style="font-size:1.3rem;font-family:monospace">{{ $order->order_number }}</strong>
                </div>

                <div style="text-align:right">
                    <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.2rem">Statut actuel :</div>
                    <span class="badge {{ $order->status === 'delivered' ? 'badge-ok' : ($order->status === 'cancelled' ? 'badge-draft' : 'badge-warn') }}" style="font-size:.95rem">
                        {{ $order->status_label }}
                    </span>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
                <div>
                    <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.2rem">Destinataire :</div>
                    <div style="font-weight:600">{{ $order->customer_name }}</div>
                    <div style="color:var(--text-soft);font-size:.9rem">{{ $order->customer_phone }}</div>
                </div>

                <div>
                    <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.2rem">Livraison :</div>
                    <div style="font-weight:600">{{ $order->delivery_zone_name }}</div>
                    <div style="color:var(--text-soft);font-size:.9rem">{{ $order->delivery_address }}</div>
                </div>
            </div>

            <!-- Historique des étapes -->
            <div style="border-top:1px solid var(--border);padding-top:1.5rem">
                <h3 style="margin-top:0;font-size:1.15rem;margin-bottom:1rem">Historique d'avancement</h3>
                <div style="display:flex;flex-direction:column;gap:1rem">
                    @foreach ($order->statusHistories as $h)
                        <div style="display:flex;gap:1rem;align-items:start">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:var(--store-primary);color:#fff;font-size:.8rem;flex-shrink:0">
                                <x-icon name="check" size="14" />
                            </span>
                            <div style="flex:1">
                                <div style="font-weight:600;font-size:.95rem">{{ $h->comment ?: 'Changement de statut' }}</div>
                                <div style="font-size:.8rem;color:var(--text-faint)">{{ $h->created_at->format('d/m/Y à H:i') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
