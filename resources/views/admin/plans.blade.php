@extends('layouts.app')

@section('title', 'Formules d\'Abonnement : Super-Admin - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1040px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">Formules d'Abonnement (CDC J-05)</h1>
            <p style="margin:0;color:var(--text-soft)">Grille tarifaire, limites de produits et quotas d'intelligence artificielle.</p>
        </div>

        <div class="admin-nav-pills">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="zap" size="15" /> Dashboard
            </a>
            <a href="{{ route('admin.shops') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="shop" size="15" /> Boutiques
            </a>
            <a href="{{ route('admin.subscriptions') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="credit-card" size="15" /> Abonnements & CA
            </a>
            <a href="{{ route('admin.ai_usage') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="sparkles" size="15" /> Quotas IA
            </a>
            <a href="{{ route('admin.maintenance') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="wrench" size="15" /> Maintenance
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1.5rem">
        @foreach ($plans as $p)
            <div class="card" style="padding:2rem;display:flex;flex-direction:column;justify-content:space-between">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
                        <h2 style="font-size:1.4rem;margin:0">{{ $p->name }}</h2>
                        <span class="badge badge-ok" style="font-size:.75rem">{{ $p->subscriptions_count }} souscrit(s)</span>
                    </div>

                    <div style="margin-bottom:1.5rem">
                        <span style="font-size:2rem;font-weight:700;font-family:var(--font-display)">
                            {{ number_format($p->monthly_price, 0, ',', ' ') }}
                        </span>
                        <span style="color:var(--text-soft);font-size:.9rem">FCFA / mois</span>
                    </div>

                    <div style="border-top:1px solid var(--border);padding-top:1rem;display:flex;flex-direction:column;gap:.6rem;font-size:.9rem">
                        <div style="display:flex;align-items:center;gap:.4rem"><x-icon name="package" size="16" /> Limite produits : <strong>{{ $p->max_products }} articles</strong></div>
                        <div style="display:flex;align-items:center;gap:.4rem"><x-icon name="sparkles" size="16" /> Crédits IA mensuels : <strong>{{ $p->ai_credits }} crédits</strong></div>
                        <div style="display:flex;align-items:center;gap:.4rem"><x-icon name="truck" size="16" /> Zones de livraison : <strong>Illimitées</strong></div>
                        <div style="display:flex;align-items:center;gap:.4rem"><x-icon name="whatsapp" size="16" /> Bouton WhatsApp : <strong>Inclus</strong></div>
                        <div style="display:flex;align-items:center;gap:.4rem"><x-icon name="phone" size="16" /> Mobile Money Togo : <strong>Inclus</strong></div>
                    </div>
                </div>

                <div style="margin-top:2rem;border-top:1px solid var(--border);padding-top:1rem;font-size:.85rem;color:var(--text-soft)">
                    Identifiant technique : <span style="font-family:monospace">{{ $p->slug }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
