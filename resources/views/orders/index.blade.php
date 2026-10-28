@extends('layouts.app')

@section('title', 'Gestion des Commandes : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1120px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">Gestion des Commandes</h1>
            <p style="margin:0;color:var(--text-soft)">Suivez, traitez et expédiez les commandes de vos clients en temps réel (Module E).</p>
        </div>

        <div class="header-actions">
            <a href="{{ route('orders.export_csv') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="download" size="16" /> Exporter en CSV
            </a>
            <a href="{{ route('shipping.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="truck" size="16" /> Frais livraison
            </a>
            <a href="{{ route('customers.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="users" size="16" /> Clients
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    <!-- Onglets de statut (CDC E-01) -->
    <div class="tabs-scroll" style="display:flex;gap:.5rem;padding-bottom:.5rem;margin-bottom:1.5rem">
        <a href="{{ route('orders.index') }}" class="btn btn-sm {{ ! $status ? 'btn-primary' : 'btn-ghost' }}">
            Toutes ({{ $counts['all'] }})
        </a>
        <a href="{{ route('orders.index', ['statut' => 'pending']) }}" class="btn btn-sm {{ $status === 'pending' ? 'btn-primary' : 'btn-ghost' }}">
            En attente ({{ $counts['pending'] }})
        </a>
        <a href="{{ route('orders.index', ['statut' => 'confirmed']) }}" class="btn btn-sm {{ $status === 'confirmed' ? 'btn-primary' : 'btn-ghost' }}">
            Confirmées ({{ $counts['confirmed'] }})
        </a>
        <a href="{{ route('orders.index', ['statut' => 'shipped']) }}" class="btn btn-sm {{ $status === 'shipped' ? 'btn-primary' : 'btn-ghost' }}">
            Expédiées ({{ $counts['shipped'] }})
        </a>
        <a href="{{ route('orders.index', ['statut' => 'delivered']) }}" class="btn btn-sm {{ $status === 'delivered' ? 'btn-primary' : 'btn-ghost' }}">
            Livrées ({{ $counts['delivered'] }})
        </a>
        <a href="{{ route('orders.index', ['statut' => 'cancelled']) }}" class="btn btn-sm {{ $status === 'cancelled' ? 'btn-primary' : 'btn-ghost' }}">
            Annulées ({{ $counts['cancelled'] }})
        </a>
    </div>

    <!-- Recherche -->
    <div class="card" style="margin-bottom:1.5rem;padding:1rem">
        <form method="GET" action="{{ route('orders.index') }}" style="display:flex;gap:.75rem;flex-wrap:wrap">
            @if ($status)
                <input type="hidden" name="statut" value="{{ $status }}">
            @endif
            <input type="text" name="q" value="{{ $search }}" placeholder="Rechercher par N° commande, nom ou téléphone..." class="input" style="flex:1;min-width:240px">
            <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
            @if ($search)
                <a href="{{ route('orders.index', $status ? ['statut' => $status] : []) }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
            @endif
        </form>
    </div>

    <!-- Tableau des commandes -->
    @if ($orders->isEmpty())
        <div class="card" style="text-align:center;padding:4rem 1rem">
            <div style="display:flex;justify-content:center;margin-bottom:1rem;color:var(--text-soft)" aria-hidden="true">
                <x-icon name="package" size="48" />
            </div>
            <h3 style="font-size:1.3rem;margin-bottom:.5rem">Aucune commande trouvée</h3>
            <p style="color:var(--text-soft);max-width:400px;margin:0 auto">
                Les nouvelles commandes passées par vos clients sur votre vitrine s'afficheront automatiquement ici.
            </p>
        </div>
    @else
        <div class="table-wrap" style="margin-bottom:1.5rem">
            <table class="table" style="width:100%;min-width:680px;border-collapse:collapse;font-size:.95rem">
                <thead>
                    <tr style="background:var(--bg-soft);border-bottom:1px solid var(--border);text-align:left">
                        <th style="padding:1rem">Numéro</th>
                        <th style="padding:1rem">Date</th>
                        <th style="padding:1rem">Client</th>
                        <th style="padding:1rem">Zone</th>
                        <th style="padding:1rem">Montant</th>
                        <th style="padding:1rem">Paiement</th>
                        <th style="padding:1rem">Statut</th>
                        <th style="padding:1rem;text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:1rem;font-family:monospace;font-weight:700">
                                <a href="{{ route('orders.show', $order) }}" style="color:var(--brand-2)">
                                    {{ $order->order_number }}
                                </a>
                            </td>
                            <td style="padding:1rem;color:var(--text-soft);font-size:.85rem">
                                {{ $order->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td style="padding:1rem">
                                <div style="font-weight:600">{{ $order->customer_name }}</div>
                                <div style="font-size:.85rem;color:var(--text-soft)">{{ $order->customer_phone }}</div>
                            </td>
                            <td style="padding:1rem;font-size:.9rem;color:var(--text-soft)">
                                {{ $order->delivery_zone_name }}
                            </td>
                            <td style="padding:1rem;font-weight:700;font-family:var(--font-display)">
                                {{ number_format($order->total, 0, ',', ' ') }} FCFA
                            </td>
                            <td style="padding:1rem">
                                <div style="font-size:.85rem;font-weight:600">{{ $order->payment_method_label }}</div>
                                <span class="badge {{ $order->payment_status === 'paid' ? 'badge-ok' : 'badge-draft' }}" style="font-size:.75rem;padding:.15rem .5rem;margin:0">
                                    {{ $order->payment_status_label }}
                                </span>
                            </td>
                            <td style="padding:1rem">
                                <span class="badge {{ $order->status === 'delivered' ? 'badge-ok' : ($order->status === 'cancelled' ? 'badge-draft' : 'badge-warn') }}" style="font-size:.8rem;padding:.2rem .6rem;margin:0">
                                    {{ $order->status_label }}
                                </span>
                            </td>
                            <td style="padding:1rem;text-align:right">
                                <a href="{{ route('orders.show', $order) }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                                    Gérer <x-icon name="arrow-right" size="14" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:2rem;display:flex;justify-content:center">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
