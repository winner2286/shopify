@extends('layouts.app')

@section('title', 'Mon espace : '.config('platform.name'))

@section('content')
@php $done = collect($checklist)->where('done', true)->count(); $pct = (int) round($done / count($checklist) * 100); @endphp
<div class="container dash">
    @if ($user->isAdmin())
        <div class="card" style="margin-bottom:1.5rem;padding:1rem 1.25rem;background:linear-gradient(135deg, rgba(239, 68, 68, 0.12), rgba(220, 38, 38, 0.05));border:1px solid rgba(239, 68, 68, 0.35);border-radius:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
            <div style="display:flex;align-items:center;gap:.75rem">
                <span style="font-size:1.6rem">🛡️</span>
                <div>
                    <div style="font-weight:700;color:#f87171;font-size:1rem">Vous êtes connecté en tant que Super-Administrateur (Fondateur)</div>
                    <div style="font-size:.85rem;color:var(--text-soft)">Vous êtes actuellement sur la vue marchande. Pour superviser l'ensemble des boutiques, abonnements et alertes, accédez à votre espace dédié.</div>
                </div>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-sm" style="background:#ef4444;color:#ffffff;font-weight:700;border:none;display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;border-radius:8px;text-decoration:none">
                Ouvrir le Cockpit Super-Admin →
            </a>
        </div>
    @endif
    <div class="dash-head">
        <div>
            <p style="margin:0">Bonjour,</p>
            <h1>{{ $user->name }}</h1>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
            @if ($shop && $shop->subdomain)
                <a href="{{ url('/b/' . $shop->subdomain) }}" target="_blank" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
                    <x-icon name="eye" :size="15" />
                    <span>Voir ma vitrine</span>
                    <x-icon name="external-link" :size="12" />
                </a>
            @endif
            <a href="{{ route('shop.settings') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
                <x-icon name="settings" :size="15" />
                <span>Paramètres Boutique</span>
            </a>
            <a href="{{ route('onboarding.step1') }}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
                <x-icon name="sparkles" :size="15" />
                <span>Assistant IA</span>
            </a>
            <span class="pill">Statut : {{ $shop ? ($shop->isPublished() ? 'En ligne' : ($shop->isPaused() ? 'En pause' : 'Brouillon')) : 'Non configurée' }}</span>
        </div>
    </div>

    @if (session('status')) <div class="alert alert-ok" role="status">{{ session('status') }}</div> @endif

    <div class="dash-grid">
        <section class="card panel reveal" aria-labelledby="start-title">
            <h3 id="start-title">Démarrage rapide</h3>
            <p style="margin:0">{{ $done }} étape(s) sur {{ count($checklist) }} terminée(s)</p>
            <div class="progress" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><i style="width: {{ $pct }}%"></i></div>
            <ul class="todo">
                @foreach ($checklist as $item)
                    <li class="{{ $item['done'] ? 'done' : '' }}">
                        <span class="box" aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center">
                            @if ($item['done']) <x-icon name="check" :size="13" /> @endif
                        </span>
                        @if ($loop->index === 0)
                            <a href="{{ route('onboarding.step1') }}" style="color:inherit">{{ $item['label'] }}</a>
                        @elseif ($loop->index === 1)
                            <a href="{{ route('products.create') }}" style="color:inherit">{{ $item['label'] }}</a>
                        @elseif ($loop->index === 3)
                            <a href="{{ route('shop.settings') }}" style="color:inherit;font-weight:bold;display:inline-flex;align-items:center;gap:.3rem">{{ $item['label'] }} <x-icon name="arrow-right" :size="13" /></a>
                        @else
                            <span>{{ $item['label'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="card panel reveal" aria-labelledby="orders-title">
            <h3 id="orders-title">Activité & Commandes (Module E)</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin:1rem 0">
                <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;text-align:center">
                    <strong style="font-size:1.8rem;display:block;color:{{ $pendingOrdersCount > 0 ? 'var(--warn)' : 'var(--text)' }}">
                        {{ $pendingOrdersCount }}
                    </strong>
                    <span style="font-size:.85rem;color:var(--text-soft)">En attente</span>
                </div>
                <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;text-align:center">
                    <strong style="font-size:1.5rem;display:block" class="gradient-text">
                        {{ number_format($totalRevenue, 0, ',', ' ') }}
                    </strong>
                    <span style="font-size:.85rem;color:var(--text-soft)">FCFA encaissés</span>
                </div>
            </div>
            <div style="display:flex;gap:.5rem;margin-top:1rem">
                <a href="{{ route('orders.index') }}" class="btn btn-primary btn-sm btn-block">Gérer les commandes</a>
                <a href="{{ route('customers.index') }}" class="btn btn-ghost btn-sm btn-block">Fichier clients</a>
            </div>
        </section>

        <section class="card panel reveal" aria-labelledby="shop-title">
            <h3 id="shop-title">Catalogue & Produits</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin:1rem 0">
                <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;text-align:center">
                    <strong style="font-size:1.8rem;display:block" class="gradient-text">{{ $productsCount }}</strong>
                    <span style="font-size:.85rem;color:var(--text-soft)">Produits actifs</span>
                </div>
                <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;text-align:center">
                    <strong style="font-size:1.8rem;display:block;color:{{ $lowStockCount > 0 ? 'var(--warn)' : 'var(--text)' }}">{{ $lowStockCount }}</strong>
                    <span style="font-size:.85rem;color:var(--text-soft)">Stock bas</span>
                </div>
            </div>
            <div style="display:flex;gap:.5rem;margin-top:1rem">
                <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm btn-block">+ Nouveau produit</a>
                <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm btn-block">Voir le catalogue</a>
            </div>
        </section>

        <section class="card panel reveal" aria-labelledby="account-title">
            <h3 id="account-title">Mon compte</h3>
            <p style="margin-bottom:.3rem">{{ $user->email }}</p>
            <p class="hint" style="margin-top:0">Pays : {{ $user->country }}</p>
            <form method="POST" action="{{ route('logout.all') }}" style="margin-top:1rem">@csrf
                <button id="logout-all" type="submit" class="btn btn-ghost btn-sm">Me déconnecter de tous les appareils</button>
            </form>
        </section>
    </div>
</div>
@endsection
