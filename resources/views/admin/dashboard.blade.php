@extends('layouts.app')

@section('title', 'Super-Administration Plateforme : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1120px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <x-icon name="zap" size="24" style="color:var(--brand-primary)" />
                <h1 style="font-size:1.8rem;margin:0">Super-Administration Zanika</h1>
                <span class="badge badge-ok">Super-Admin</span>
            </div>
            <p style="margin:0;color:var(--text-soft)">Supervision globale de l'écosystème Zanika, des commerçants, des ventes et des quotas d'IA.</p>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <a href="{{ route('admin.shops') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="shop" size="16" /> Annuaire Boutiques
            </a>
            <a href="{{ route('admin.ai_usage') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="sparkles" size="16" /> Journal d'usage IA
            </a>
            <a href="{{ route('admin.plans') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="diamond" size="16" /> Formules & Tarifs
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    <!-- Indicateurs clés de la plateforme -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;margin-bottom:2rem">
        <div class="card" style="padding:1.2rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Commerçants Inscrits</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display)">{{ $totalMerchants }}</div>
            <div style="font-size:.8rem;color:var(--ok)">Utilisateurs marchands</div>
        </div>

        <div class="card" style="padding:1.2rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Boutiques en Ligne</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display)">{{ $publishedShops }} / {{ $totalShops }}</div>
            <div style="font-size:.8rem;color:var(--text-soft)">Publiées / Total créées</div>
        </div>

        <div class="card" style="padding:1.2rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Volume d'Affaires Global (GMV)</div>
            <div style="font-size:1.6rem;font-weight:700;font-family:var(--font-display)" class="gradient-text">
                {{ number_format($totalGmv, 0, ',', ' ') }} FCFA
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)">{{ $totalOrders }} commandes passées</div>
        </div>

        <div class="card" style="padding:1.2rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Consommation IA (CDC J-04)</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display);color:var(--brand-2)">
                {{ number_format($aiCallsCount, 0, ',', ' ') }}
            </div>
            <div style="font-size:.8rem;color:var(--text-soft)">Coût estimé : {{ number_format($aiTotalCostFcfa, 0, ',', ' ') }} FCFA</div>
        </div>
    </div>

    <!-- Dernières boutiques enregistrées -->
    <div class="card" style="padding:0;overflow:hidden">
        <div style="padding:1rem 1.5rem;background:var(--bg-soft);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:1.15rem">Dernières Boutiques Inscrites</h3>
            <a href="{{ route('admin.shops') }}" style="font-size:.85rem;font-weight:600;display:inline-flex;align-items:center;gap:.3rem">
                Voir tout l'annuaire <x-icon name="arrow-right" size="12" />
            </a>
        </div>

        <table class="table" style="width:100%;border-collapse:collapse;font-size:.95rem">
            <thead>
                <tr style="border-bottom:1px solid var(--border);text-align:left;background:var(--surface)">
                    <th style="padding:1rem">Boutique</th>
                    <th style="padding:1rem">Commerçant</th>
                    <th style="padding:1rem">Sous-domaine</th>
                    <th style="padding:1rem">Statut</th>
                    <th style="padding:1rem">Date création</th>
                    <th style="padding:1rem;text-align:right">Action</th>
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
                            <a href="{{ url('/b/' . $s->subdomain) }}" target="_blank" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                                <x-icon name="eye" size="14" /> Visiter <x-icon name="external-link" size="12" />
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
