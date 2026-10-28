@extends('layouts.app')

@section('title', 'Fichier Clients : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1040px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">Fichier Clients (E-04)</h1>
            <p style="margin:0;color:var(--text-soft)">Consultez l'historique et la valeur de vos acheteurs. Données strictement isolées par boutique.</p>
        </div>

        <div class="header-actions">
            <a href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="arrow-left" size="14" />
                Voir les commandes
            </a>
        </div>
    </div>

    <!-- Recherche -->
    <div class="card" style="margin-bottom:1.5rem;padding:1rem">
        <form method="GET" action="{{ route('customers.index') }}" style="display:flex;gap:.75rem">
            <input type="text" name="q" value="{{ $search }}" placeholder="Rechercher par nom, téléphone ou email..." class="input" style="flex:1">
            <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
            @if ($search)
                <a href="{{ route('customers.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
            @endif
        </form>
    </div>

    @if ($customers->isEmpty())
        <div class="card" style="text-align:center;padding:4rem 1rem">
            <div style="display:flex;justify-content:center;margin-bottom:1rem;color:var(--text-soft)" aria-hidden="true">
                <x-icon name="users" size="48" />
            </div>
            <h3 style="font-size:1.3rem;margin-bottom:.5rem">Aucun client pour le moment</h3>
            <p style="color:var(--text-soft);max-width:400px;margin:0 auto">
                Les coordonnées des clients qui commandent sur votre vitrine s'enregistreront automatiquement ici.
            </p>
        </div>
    @else
        <div class="table-wrap" style="margin-bottom:1.5rem">
            <table class="table" style="width:100%;min-width:640px;border-collapse:collapse;font-size:.95rem">
                <thead>
                    <tr style="background:var(--bg-soft);border-bottom:1px solid var(--border);text-align:left">
                        <th style="padding:1rem">Client</th>
                        <th style="padding:1rem">Téléphone</th>
                        <th style="padding:1rem">Zone / Ville</th>
                        <th style="padding:1rem;text-align:center">Commandes</th>
                        <th style="padding:1rem">Total dépensé</th>
                        <th style="padding:1rem">Dernier achat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $c)
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:1rem;font-weight:600">
                                {{ $c->name }}
                                @if ($c->email)
                                    <div style="font-size:.8rem;color:var(--text-soft);font-weight:normal">{{ $c->email }}</div>
                                @endif
                            </td>
                            <td style="padding:1rem;font-family:monospace">
                                {{ $c->phone }}
                            </td>
                            <td style="padding:1rem;color:var(--text-soft)">
                                {{ $c->city ?: 'Lomé' }}
                            </td>
                            <td style="padding:1rem;text-align:center;font-weight:700">
                                {{ $c->total_orders_count }}
                            </td>
                            <td style="padding:1rem;font-weight:700;font-family:var(--font-display)">
                                {{ number_format($c->total_spent_fcfa, 0, ',', ' ') }} FCFA
                            </td>
                            <td style="padding:1rem;color:var(--text-soft);font-size:.85rem">
                                {{ $c->last_order_at ? $c->last_order_at->format('d/m/Y') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:2rem;display:flex;justify-content:center">
            {{ $customers->links() }}
        </div>
    @endif
</div>
@endsection
