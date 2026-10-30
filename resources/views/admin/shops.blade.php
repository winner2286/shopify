@extends('layouts.app')

@section('title', 'Annuaire des Boutiques : Super-Admin - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1120px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <x-icon name="shop" size="24" style="color:var(--brand-1)" />
                <h1 style="font-size:1.8rem;margin:0">Annuaire des Boutiques</h1>
            </div>
            <p style="margin:0;color:var(--text-soft)">Gestion, modération et suivi de toutes les boutiques hébergées sur Zanika.</p>
        </div>

        <div class="admin-nav-pills">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="zap" size="15" /> Dashboard
            </a>
            <a href="{{ route('admin.subscriptions') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="credit-card" size="15" /> Abonnements & CA
            </a>
            <a href="{{ route('admin.ai_usage') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="sparkles" size="15" /> Quotas IA
            </a>
            <a href="{{ route('admin.plans') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="diamond" size="15" /> Formules
            </a>
            <a href="{{ route('admin.maintenance') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="wrench" size="15" /> Maintenance
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    <!-- Recherche -->
    <div class="card" style="margin-bottom:1.5rem;padding:1rem">
        <form method="GET" action="{{ route('admin.shops') }}" style="display:flex;gap:.75rem">
            <input type="text" name="q" value="{{ $search }}" placeholder="Rechercher par nom de boutique, sous-domaine ou commerçant..." class="input" style="flex:1">
            <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
            @if ($search)
                <a href="{{ route('admin.shops') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
            @endif
        </form>
    </div>

    <div class="table-wrap" style="margin-bottom:1.5rem">
        <table class="table" style="width:100%;min-width:700px;border-collapse:collapse;font-size:.95rem">
            <thead>
                <tr style="background:var(--bg-soft);border-bottom:1px solid var(--border);text-align:left">
                    <th style="padding:1rem">Boutique</th>
                    <th style="padding:1rem">Commerçant</th>
                    <th style="padding:1rem">Sous-domaine</th>
                    <th style="padding:1rem;text-align:center">Produits</th>
                    <th style="padding:1rem;text-align:center">Commandes</th>
                    <th style="padding:1rem">Statut</th>
                    <th style="padding:1rem;text-align:right">Modération</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($shops as $shop)
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:1rem;font-weight:600">
                            {{ $shop->name ?: 'Sans nom' }}
                        </td>
                        <td style="padding:1rem">
                            <div>{{ $shop->owner?->name }}</div>
                            <div style="font-size:.8rem;color:var(--text-soft)">{{ $shop->owner?->email }}</div>
                        </td>
                        <td style="padding:1rem;font-family:monospace">
                            /b/{{ $shop->subdomain }}
                        </td>
                        <td style="padding:1rem;text-align:center;font-weight:600">
                            {{ $shop->products_count }}
                        </td>
                        <td style="padding:1rem;text-align:center;font-weight:600">
                            {{ $shop->orders_count }}
                        </td>
                        <td style="padding:1rem">
                            @if ($shop->status === 'published')
                                <span class="badge badge-ok" style="font-size:.75rem">En ligne</span>
                            @elseif ($shop->status === 'suspended')
                                <span class="badge badge-warn" style="font-size:.75rem;color:var(--danger)">Suspendue</span>
                            @elseif ($shop->status === 'paused')
                                <span class="badge badge-warn" style="font-size:.75rem">En pause</span>
                            @else
                                <span class="badge badge-draft" style="font-size:.75rem">Brouillon</span>
                            @endif
                        </td>
                        <td style="padding:1rem;text-align:right">
                            <div style="display:flex;gap:.5rem;justify-content:flex-end;align-items:center">
                                <a href="{{ url('/b/' . $shop->subdomain) }}" target="_blank" class="btn btn-ghost btn-sm" title="Voir la vitrine" style="display:inline-flex;align-items:center">
                                    <x-icon name="eye" size="16" />
                                </a>

                                <form method="POST" action="{{ route('admin.shops.impersonate', $shop) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Prendre la main sur la boutique (Mode Support)" style="display:inline-flex;align-items:center;gap:.3rem;color:var(--brand-1)">
                                        <x-icon name="user" size="14" />
                                        <span>Gérer</span>
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.shops.toggle_suspend', $shop) }}" data-confirm="Confirmer l'action de modération pour {{ $shop->name }} ?">
                                    @csrf
                                    @if ($shop->status === 'suspended')
                                        <button type="submit" class="btn btn-primary btn-sm" style="background:var(--ok)">
                                            Rétablir
                                        </button>
                                    @else
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">
                                            Suspendre
                                        </button>
                                    @endif
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top:2rem;display:flex;justify-content:center">
        {{ $shops->links() }}
    </div>
</div>
@endsection
