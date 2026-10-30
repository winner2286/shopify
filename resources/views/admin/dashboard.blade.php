@extends('layouts.app')

@section('title', 'Super-Administration Plateforme : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1160px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <x-icon name="zap" size="24" style="color:var(--brand-1)" />
                <h1 style="font-size:1.8rem;margin:0">Super-Administration Zanika</h1>
                <span class="badge badge-ok">Fondateur</span>
            </div>
            <p style="margin:0;color:var(--text-soft)">Tour de contrôle globale de l'écosystème Zanika : commerçants, boutiques, abonnements et ventes.</p>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <a href="{{ route('admin.shops') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="shop" size="15" /> Boutiques
            </a>
            <a href="{{ route('admin.subscriptions') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="credit-card" size="15" /> Abonnements & CA
            </a>
            <a href="{{ route('admin.ai_usage') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="sparkles" size="15" /> Quotas IA
            </a>
            <a href="{{ route('admin.plans') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="diamond" size="15" /> Formules & Tarifs
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    <!-- Indicateurs clés de la plateforme (KPIs) -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;margin-bottom:2rem">
        <div class="card" style="padding:1.25rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Commerçants Inscrits</div>
            <div style="font-size:1.85rem;font-weight:700;font-family:var(--font-display);margin:.2rem 0">{{ $totalMerchants }}</div>
            <div style="font-size:.8rem;color:var(--ok)">Utilisateurs marchands</div>
        </div>

        <div class="card" style="padding:1.25rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Boutiques en Ligne</div>
            <div style="font-size:1.85rem;font-weight:700;font-family:var(--font-display);margin:.2rem 0">{{ $publishedShops }} / {{ $totalShops }}</div>
            <div style="font-size:.8rem;color:var(--text-soft)">Publiées / Total créées</div>
        </div>

        <div class="card" style="padding:1.25rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Volume d'Affaires Global (GMV)</div>
            <div style="font-size:1.65rem;font-weight:700;font-family:var(--font-display);margin:.2rem 0" class="gradient-text">
                {{ number_format($totalGmv, 0, ',', ' ') }} FCFA
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)">{{ $totalOrders }} commandes sur le réseau</div>
        </div>

        <div class="card" style="padding:1.25rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Abonnements & Essais</div>
            <div style="font-size:1.85rem;font-weight:700;font-family:var(--font-display);color:#f59e0b;margin:.2rem 0">
                {{ $activeSubsCount }} <span style="font-size:1.1rem;color:var(--text-soft);font-weight:500">/ {{ $trialSubsCount }} essai(s)</span>
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)"><a href="{{ route('admin.subscriptions') }}" style="color:var(--brand-1);text-decoration:underline">Gérer les abonnements →</a></div>
        </div>

        <div class="card" style="padding:1.25rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Consommation IA (OpenAI)</div>
            <div style="font-size:1.85rem;font-weight:700;font-family:var(--font-display);color:var(--brand-2);margin:.2rem 0">
                {{ number_format($aiCallsCount, 0, ',', ' ') }}
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)">Coût estimé : {{ number_format($aiTotalCostFcfa, 0, ',', ' ') }} FCFA</div>
        </div>
    </div>

    <!-- Section 1 : Flux en direct des Dernières Commandes sur le Réseau Zanika -->
    <div class="card" style="padding:0;overflow:hidden;margin-bottom:2rem">
        <div style="padding:1rem 1.5rem;background:var(--bg-soft);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:.5rem">
                <x-icon name="shopping-bag" size="18" style="color:var(--ok)" />
                <h3 style="margin:0;font-size:1.15rem">Dernières Ventes Réalisées sur le Réseau</h3>
            </div>
            <span style="font-size:.82rem;color:var(--text-soft)">Toutes boutiques confondues</span>
        </div>

        <div class="table-wrap">
            <table class="table" style="width:100%;border-collapse:collapse;font-size:.92rem">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);text-align:left;background:var(--surface)">
                        <th style="padding:1rem">N° Commande</th>
                        <th style="padding:1rem">Boutique</th>
                        <th style="padding:1rem">Client</th>
                        <th style="padding:1rem">Montant</th>
                        <th style="padding:1rem">Paiement</th>
                        <th style="padding:1rem">Statut</th>
                        <th style="padding:1rem">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentOrders as $order)
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:1rem;font-weight:700;font-family:monospace">
                                {{ $order->order_number }}
                            </td>
                            <td style="padding:1rem">
                                <span style="font-weight:600">{{ $order->shop?->name ?: 'Boutique' }}</span>
                            </td>
                            <td style="padding:1rem">
                                <div>{{ $order->customer_name }}</div>
                                <div style="font-size:.8rem;color:var(--text-soft)">{{ $order->customer_phone }}</div>
                            </td>
                            <td style="padding:1rem;font-weight:700;color:var(--text)">
                                {{ number_format($order->total, 0, ',', ' ') }} FCFA
                            </td>
                            <td style="padding:1rem;font-size:.85rem">
                                <span class="badge" style="background:rgba(255,255,255,0.08);font-size:.78rem">
                                    {{ $order->payment_method_label }}
                                </span>
                            </td>
                            <td style="padding:1rem">
                                @if ($order->status === 'delivered')
                                    <span class="badge badge-ok" style="font-size:.75rem">Livrée</span>
                                @elseif ($order->status === 'cancelled')
                                    <span class="badge" style="background:rgba(239,68,68,0.15);color:#ef4444;font-size:.75rem">Annulée</span>
                                @elseif ($order->status === 'confirmed')
                                    <span class="badge badge-ok" style="font-size:.75rem">Confirmée</span>
                                @else
                                    <span class="badge badge-warn" style="font-size:.75rem">En attente</span>
                                @endif
                            </td>
                            <td style="padding:1rem;font-size:.82rem;color:var(--text-soft)">
                                {{ $order->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;padding:2rem;color:var(--text-soft)">
                                Aucune commande enregistrée pour le moment.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2 : Dernières Boutiques Inscrites & Accès Support Impersonation -->
    <div class="card" style="padding:0;overflow:hidden">
        <div style="padding:1rem 1.5rem;background:var(--bg-soft);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:.5rem">
                <x-icon name="shop" size="18" style="color:var(--brand-1)" />
                <h3 style="margin:0;font-size:1.15rem">Dernières Boutiques Inscrites</h3>
            </div>
            <a href="{{ route('admin.shops') }}" style="font-size:.85rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem">
                Voir tout l'annuaire ({{ $totalShops }}) <x-icon name="arrow-right" size="12" />
            </a>
        </div>

        <div class="table-wrap">
            <table class="table" style="width:100%;border-collapse:collapse;font-size:.92rem">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);text-align:left;background:var(--surface)">
                        <th style="padding:1rem">Boutique</th>
                        <th style="padding:1rem">Commerçant</th>
                        <th style="padding:1rem">Sous-domaine</th>
                        <th style="padding:1rem">Statut</th>
                        <th style="padding:1rem">Date création</th>
                        <th style="padding:1rem;text-align:right">Actions Fondateur</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentShops as $s)
                        <tr style="border-bottom:1px solid var(--border)">
                            <td style="padding:1rem;font-weight:600">
                                {{ $s->name ?: 'Sans nom' }}
                            </td>
                            <td style="padding:1rem">
                                <div>{{ $s->owner?->name }}</div>
                                <div style="font-size:.8rem;color:var(--text-soft)">{{ $s->owner?->email }}</div>
                            </td>
                            <td style="padding:1rem;font-family:monospace">
                                /b/{{ $s->subdomain }}
                            </td>
                            <td style="padding:1rem">
                                @if ($s->status === 'published')
                                    <span class="badge badge-ok" style="font-size:.75rem">En ligne</span>
                                @elseif ($s->status === 'suspended')
                                    <span class="badge badge-warn" style="font-size:.75rem;color:var(--danger)">Suspendue</span>
                                @else
                                    <span class="badge badge-draft" style="font-size:.75rem">{{ ucfirst($s->status) }}</span>
                                @endif
                            </td>
                            <td style="padding:1rem;font-size:.85rem;color:var(--text-soft)">
                                {{ $s->created_at->format('d/m/Y') }}
                            </td>
                            <td style="padding:1rem;text-align:right">
                                <div style="display:flex;gap:.5rem;justify-content:flex-end;align-items:center">
                                    <a href="{{ url('/b/' . $s->subdomain) }}" target="_blank" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                                        <x-icon name="eye" size="14" /> Visiter
                                    </a>

                                    <form method="POST" action="{{ route('admin.shops.impersonate', $s) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm" title="Prendre le contrôle (Mode Support)" style="display:inline-flex;align-items:center;gap:.3rem;color:var(--brand-1)">
                                            <x-icon name="user" size="14" />
                                            <span>Gérer</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
