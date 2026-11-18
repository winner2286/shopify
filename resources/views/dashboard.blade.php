@extends('layouts.app')

@section('title', 'Mon espace : '.config('platform.name'))

@section('content')
@php
    $done = collect($checklist)->where('done', true)->count();
    $pct = (int) round($done / count($checklist) * 100);
    $sub = $shop?->accountSubscription() ?? $shop?->subscription;
    $shopPlan = $shop?->currentPlan();
    $hasSubscriptionAccess = $sub?->grantsAccess() && $sub->plan?->active;
    $isTrialExpiring = $hasSubscriptionAccess && $sub->isExpiringSoon(3);
    $daysLeft = $sub?->daysRemaining() ?? 0;
@endphp
<div class="container dash">
    @if ($user->isAdmin())
        <div class="card" style="margin-bottom:1.5rem;padding:1rem 1.25rem;background:linear-gradient(135deg, rgba(239, 68, 68, 0.12), rgba(220, 38, 38, 0.05));border:1px solid rgba(239, 68, 68, 0.35);border-radius:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
            <div style="display:flex;align-items:center;gap:.75rem">
                <x-icon name="shield" size="24" style="color:#f87171" />
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
            @if ($userShops->count() > 1)
                <div style="display:flex;align-items:center;gap:.5rem;margin-top:.4rem;flex-wrap:wrap">
                    <span style="font-size:.82rem;color:var(--text-soft)">Boutique active :</span>
                    <form method="POST" action="" id="switch-shop-form" style="margin:0">
                        @csrf
                        <select onchange="if(this.value){ document.getElementById('switch-shop-form').action = this.value; document.getElementById('switch-shop-form').submit(); }" class="input" style="padding:.25rem .6rem;height:32px;font-size:.82rem;font-weight:700;border-radius:8px">
                            @foreach ($userShops as $s)
                                <option value="{{ route('shops.switch', $s) }}" @selected($shop && $shop->id === $s->id)>
                                    {{ $s->name ?: 'Sans nom' }} ({{ $s->subdomain ?? 'brouillon' }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            @endif
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
            @if ($canCreateShop)
                <a href="{{ route('shops.create') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
                    <x-icon name="plus" :size="15" />
                    <span>Nouvelle boutique</span>
                </a>
            @else
                <a href="{{ route('shops.create') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem;border-color:rgba(197, 34, 61, 0.35);color:var(--brand-3, #c5223d)">
                    <x-icon name="shop" :size="15" />
                    <span>Limite de boutiques atteinte</span>
                </a>
            @endif
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

    <!-- Bannière d'activation des Notifications Mobiles Instantanées (Commandes & Alertes) -->
    <div class="card push-banner" style="margin-bottom:1.5rem;padding:1.15rem 1.4rem;background:linear-gradient(135deg, rgba(158, 27, 50, 0.08), rgba(115, 16, 34, 0.04));border:1px solid rgba(158, 27, 50, 0.25);border-radius:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
        <div style="display:flex;align-items:center;gap:.85rem">
            <div style="width:42px;height:42px;border-radius:12px;background:color-mix(in srgb, var(--brand-1, #9e1b32) 15%, transparent);color:var(--brand-3, #c5223d);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(158, 27, 50, 0.15);flex-shrink:0">
                <x-icon name="bell" size="20" />
            </div>
            <div>
                <div style="font-weight:700;font-size:1rem;color:var(--text);display:flex;align-items:center;gap:.5rem">
                    <span>Notifications Mobiles Instantanées</span>
                    <span class="badge badge-ok" data-push-state style="font-size:.7rem">Vérification...</span>
                </div>
                <div style="font-size:.85rem;color:var(--text-soft)">Recevez une alerte sonore avec vibration dès qu'un client passe commande sur votre boutique.</div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
            <button type="button" class="btn btn-sm btn-primary" data-push-action="enable" style="display:none;align-items:center;gap:.4rem">
                <x-icon name="bell" size="14" /> Activer sur ce téléphone
            </button>
            <button type="button" class="btn btn-sm btn-ghost" data-push-action="test" style="display:none;align-items:center;gap:.4rem;border:1px solid var(--border)">
                <x-icon name="bell" size="13" /> <span>Tester</span>
            </button>
            <button type="button" class="btn btn-sm btn-ghost" data-push-action="disable" style="display:none;align-items:center;gap:.4rem;color:var(--text-soft)">
                <span>Désactiver</span>
            </button>
        </div>
    </div>
    @if ($isTrialExpiring)
        <div class="card" style="margin-bottom:1.5rem;padding:1.15rem 1.4rem;background:linear-gradient(135deg, rgba(245, 158, 11, 0.14), rgba(217, 119, 6, 0.06));border:1px solid rgba(245, 158, 11, 0.4);border-radius:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
            <div style="display:flex;align-items:center;gap:.9rem">
                <x-icon name="alert-triangle" size="28" style="color:#d97706" />
                <div>
                    <div style="font-weight:700;color:#d97706;font-size:1.05rem">
                        Votre essai gratuit expire dans {{ $daysLeft <= 1 ? ($daysLeft == 0 ? "quelques heures" : "1 jour") : "$daysLeft jours" }} !
                    </div>
                    <div style="font-size:.87rem;color:var(--text-soft);margin-top:.15rem">
                        Choisissez une formule pour continuer à utiliser les crédits du studio et de l’assistant après votre essai.
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
                <a href="{{ route('subscription.checkout', $shopPlan->slug) }}" class="btn btn-primary btn-sm" style="font-weight:700;white-space:nowrap;padding:.55rem 1.25rem;background:linear-gradient(135deg,#f59e0b,#d97706);border:none;box-shadow:0 4px 14px rgba(245, 158, 11, 0.35);color:#fff">
                    Souscrire à {{ $shopPlan->name }} ({{ number_format($sub->monthlyPriceFor($shopPlan), 0, ',', ' ') }} F)
                </a>
                <a href="{{ route('home', ['landing' => 1]) }}#tarifs" class="btn btn-ghost btn-sm" style="white-space:nowrap;font-size:.82rem">
                    Changer de formule
                </a>
            </div>
        </div>
    @elseif ($sub && $sub->status === 'expired')
        <div class="card" style="margin-bottom:1.5rem;padding:1.15rem 1.4rem;background:linear-gradient(135deg, rgba(239, 68, 68, 0.14), rgba(220, 38, 38, 0.06));border:1px solid rgba(239, 68, 68, 0.4);border-radius:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
            <div style="display:flex;align-items:center;gap:.9rem">
                <x-icon name="alert-circle" size="28" style="color:#ef4444" />
                <div>
                    <div style="font-weight:700;color:#ef4444;font-size:1.05rem">
                        Votre abonnement ou votre essai est arrivé à échéance.
                    </div>
                    <div style="font-size:.87rem;color:var(--text-soft);margin-top:.15rem">
                        Choisissez une formule pour retrouver les fonctionnalités et les crédits associés.
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
                <a href="{{ route('home', ['landing' => 1]) }}#tarifs" class="btn btn-primary btn-sm" style="white-space:nowrap;font-size:.82rem">
                    Voir toutes les formules
                </a>
            </div>
        </div>
    @endif

    @if ($shop && ! $user->isAdmin())
        <section class="card panel account-plan" aria-labelledby="account-plan-title">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap">
                <div>
                    <h3 id="account-plan-title" style="margin:0">{{ $hasSubscriptionAccess ? 'Votre formule '.$shopPlan->name : 'Votre accès commerçant' }}</h3>
                    <p class="plan-conditions">
                        @if ($hasSubscriptionAccess && $sub->isTrial())
                            Essai jusqu’au {{ $sub->trial_ends_at->format('d/m/Y') }}
                        @elseif ($hasSubscriptionAccess && $sub->status === 'grace')
                            Période de grâce jusqu’au {{ $sub->ends_at->format('d/m/Y') }}
                        @elseif ($hasSubscriptionAccess)
                            Abonnement actif · {{ number_format($sub->monthlyPriceFor($shopPlan), 0, ',', ' ') }} FCFA par mois
                        @else
                            Accès de base · Aucun abonnement actif
                        @endif
                    </p>
                </div>
                <a href="{{ route('home', ['landing' => 1]) }}#tarifs" class="btn btn-ghost btn-sm">Comparer les formules</a>
            </div>
            <dl class="plan-allowances">
                <div><dt>Boutiques du compte</dt><dd>{{ $userShops->count() }} / {{ $maxShops }}</dd></div>
                <div><dt>Produits de cette boutique</dt><dd>{{ $shop->products()->count() }} / {{ $shop->maxProducts() }}</dd></div>
                <div><dt>Crédits IA restants ce mois</dt><dd>{{ $shop->aiCreditsRemaining() }} / {{ $shop->aiCreditsMonthly() }}</dd></div>
                <div><dt>Décors générés restants ce mois</dt><dd>{{ $shop->studioScenesRemaining() }} / {{ $shop->studioScenesMonthly() }}</dd></div>
                <div><dt>Crédits Photoroom restants ce mois</dt><dd>{{ $shop->photoroomCreditsRemaining() }} / {{ $shop->photoroomCreditsMonthly() }}</dd></div>
            </dl>
            <details>
                <summary>Voir les fonctionnalités de ma formule</summary>
                @include('subscription.plan-benefits', ['plan' => $shopPlan])
                <p class="plan-conditions">{{ $shopPlan->full_generations }} configuration(s) assistée(s) et {{ $shopPlan->storage_mb >= 1024 ? ($shopPlan->storage_mb / 1024).' Go' : $shopPlan->storage_mb.' Mo' }} de stockage par boutique.</p>
                @include('subscription.plan-conditions')
            </details>
        </section>
    @endif

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
            <h3 id="orders-title">Activité & Commandes</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin:1rem 0">
                <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;text-align:center">
                    <strong style="font-size:1.8rem;display:block;color:{{ $pendingOrdersCount > 0 ? 'var(--warn)' : 'var(--text)' }}">
                        {{ $pendingOrdersCount }}
                    </strong>
                    <span style="font-size:.85rem;color:var(--text-soft)">En attente</span>
                </div>
                @if ($canViewAdvancedStats)
                    <a href="{{ route('analytics.index') }}" style="background:var(--bg-soft);padding:1rem;border-radius:12px;text-align:center;text-decoration:none;display:block" title="Voir les statistiques détaillées">
                @else
                    <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;text-align:center">
                @endif
                    <strong style="font-size:1.5rem;display:block" class="gradient-text">
                        {{ number_format($totalRevenue, 0, ',', ' ') }}
                    </strong>
                    <span style="font-size:.85rem;color:var(--text-soft);display:block">{{ $shop->currencyLabel() }} encaissés</span>
                @if ($canViewAdvancedStats)
                        <span style="font-size:.72rem;color:var(--brand-1);font-weight:600;display:inline-flex;align-items:center;gap:2px;margin-top:.25rem">
                            Voir stats <x-icon name="arrow-right" :size="11" />
                        </span>
                    </a>
                @else
                    </div>
                @endif
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:1rem">
                <a href="{{ route('orders.index') }}" class="btn btn-primary btn-sm" style="justify-content:center;text-align:center">Gérer commandes</a>
                @if ($canViewAdvancedStats)
                    <a href="{{ route('analytics.index') }}" class="btn btn-ghost btn-sm" style="justify-content:center;text-align:center;color:var(--brand-1);font-weight:600">
                        <x-icon name="analytics" :size="15" /> Statistiques
                    </a>
                @endif
            </div>
        </section>

        <section class="card panel reveal" aria-labelledby="shop-title">
            <h3 id="shop-title">Catalogue & Produits</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin:1rem 0">
                <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;text-align:center">
                    <strong style="font-size:1.8rem;display:block" class="gradient-text">{{ $productsCount }}</strong>
                    <span style="font-size:.85rem;color:var(--text-soft)">Produits actifs</span>
                </div>
                @if ($canViewStockAlerts)
                    <div style="background:var(--bg-soft);padding:1rem;border-radius:12px;text-align:center">
                        <strong style="font-size:1.8rem;display:block;color:{{ $lowStockCount > 0 ? 'var(--warn)' : 'var(--text)' }}">{{ $lowStockCount }}</strong>
                        <span style="font-size:.85rem;color:var(--text-soft)">Stock bas</span>
                    </div>
                @endif
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
