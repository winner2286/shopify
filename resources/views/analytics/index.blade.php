@extends('layouts.app')

@section('title', 'Statistiques & Performance : '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1120px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <h1 style="font-size:1.8rem;margin:0">Statistiques & Performance (Module I)</h1>
            <p style="margin:0;color:var(--text-soft)">Suivez l'évolution de votre chiffre d'affaires, vos meilleures ventes et l'état des stocks.</p>
        </div>

        <div class="header-actions">
            <a href="{{ route('analytics.export_csv') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="download" size="16" /> Exporter (CSV)
            </a>
            <a href="{{ route('orders.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="package" size="16" /> Commandes
            </a>
        </div>
    </div>

    <!-- 4 Cartes de métriques clés (RG-10 : montants en FCFA) -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1.2rem;margin-bottom:2rem">
        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.3rem">Chiffre d'Affaires Réalisé</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display)" class="gradient-text">
                {{ number_format($totalRevenue, 0, ',', ' ') }} FCFA
            </div>
            <div style="font-size:.8rem;color:var(--ok);margin-top:.3rem;display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="check" size="14" /> Commandes confirmées & livrées
            </div>
        </div>

        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.3rem">Commandes Enregistrées</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display)">
                {{ $totalOrdersCount }}
            </div>
            <div style="font-size:.8rem;color:var(--text-soft);margin-top:.3rem">{{ $deliveredOrdersCount }} livrée(s) avec succès</div>
        </div>

        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.3rem">Panier Moyen Client</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display)">
                {{ number_format($averageCart, 0, ',', ' ') }} FCFA
            </div>
            <div style="font-size:.8rem;color:var(--text-soft);margin-top:.3rem">Par commande validée</div>
        </div>

        <div class="card" style="padding:1.4rem">
            <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.3rem">Acheteurs Distincts</div>
            <div style="font-size:1.8rem;font-weight:700;font-family:var(--font-display)">
                {{ $totalCustomersCount }}
            </div>
            <div style="font-size:.8rem;color:var(--text-soft);margin-top:.3rem">
                <a href="{{ route('customers.index') }}" style="color:var(--brand-2);display:inline-flex;align-items:center;gap:.2rem">
                    Voir le fichier clients <x-icon name="arrow-right" size="12" />
                </a>
            </div>
        </div>
    </div>

    <!-- Graphique SVG des 7 derniers jours (Léger & Performant sur 3G) -->
    <div class="card" style="margin-bottom:2rem;padding:1.8rem">
        <h3 style="margin-top:0;font-size:1.2rem;margin-bottom:1.5rem">Évolution des Ventes sur 7 Jours (FCFA)</h3>

        <div style="display:flex;align-items:flex-end;gap:1.2rem;height:180px;border-bottom:1px solid var(--border);padding-bottom:.5rem">
            @foreach ($dailySales as $ds)
                @php $heightPct = max(8, round(($ds['amount'] / $maxDaily) * 100)); @endphp
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end">
                    <span style="font-size:.75rem;font-weight:700;font-family:var(--font-display);margin-bottom:.3rem;color:var(--text)">
                        {{ $ds['amount'] > 0 ? number_format($ds['amount'], 0, ',', ' ') : '0' }}
                    </span>
                    <div style="width:100%;max-width:44px;height:{{ $heightPct }}%;background:linear-gradient(180deg, var(--brand-1), var(--brand-2));border-radius:6px 6px 0 0;transition:height .3s"></div>
                    <span style="font-size:.75rem;color:var(--text-soft);margin-top:.5rem">{{ $ds['day'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:2rem">
        <!-- Top 5 des Ventes -->
        <div class="card">
            <h3 style="margin-top:0;font-size:1.2rem;margin-bottom:1.2rem">Articles les Plus Vendus</h3>

            @if ($topProducts->isEmpty())
                <p style="color:var(--text-soft);font-size:.9rem">Aucune vente enregistrée pour le moment.</p>
            @else
                <div style="display:flex;flex-direction:column;gap:.8rem">
                    @foreach ($topProducts as $idx => $tp)
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:.8rem;background:var(--bg-soft);border-radius:var(--radius-sm)">
                            <div style="display:flex;align-items:center;gap:.75rem">
                                <span style="font-weight:700;font-size:1rem;color:var(--brand-2);min-width:20px">#{{ $idx + 1 }}</span>
                                <div>
                                    <div style="font-weight:600;font-size:.92rem">{{ $tp->product_title }}</div>
                                    <div style="font-size:.8rem;color:var(--text-soft)">{{ $tp->total_qty }} exemplaire(s) écoulé(s)</div>
                                </div>
                            </div>
                            <div style="font-weight:700;font-family:var(--font-display)">
                                {{ number_format($tp->total_amount, 0, ',', ' ') }} FCFA
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Alertes de Stocks (CDC I-02) -->
        <div class="card">
            <h3 style="margin-top:0;font-size:1.2rem;margin-bottom:1.2rem">Alertes & Niveaux de Stock (I-02)</h3>

            @if ($outOfStock->isEmpty() && $lowStock->isEmpty())
                <div style="background:color-mix(in srgb, var(--ok) 12%, transparent);border:1px solid var(--ok);padding:1rem;border-radius:var(--radius-sm);color:var(--ok);font-size:.92rem;display:flex;align-items:center;gap:.4rem">
                    <x-icon name="check-circle" size="18" /> Tous vos niveaux de stock sont optimaux. Aucune rupture détectée !
                </div>
            @else
                @if ($outOfStock->isNotEmpty())
                    <div style="margin-bottom:1.2rem">
                        <strong style="font-size:.9rem;color:var(--danger);display:flex;align-items:center;gap:.3rem;margin-bottom:.5rem">
                            <x-icon name="alert-triangle" size="16" /> Ruptures de stock immédiates ({{ $outOfStock->count() }}) :
                        </strong>
                        <ul style="margin:0;padding-left:1.2rem;font-size:.9rem;color:var(--text-soft)">
                            @foreach ($outOfStock as $p)
                                <li>
                                    <a href="{{ route('products.edit', $p) }}" style="color:var(--text)">
                                        {{ $p->title }}
                                    </a> (0 disponible)
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($lowStock->isNotEmpty())
                    <div>
                        <strong style="font-size:.9rem;color:var(--warn);display:flex;align-items:center;gap:.3rem;margin-bottom:.5rem">
                            <x-icon name="flame" size="16" /> Stock faible sous le seuil d'alerte ({{ $lowStock->count() }}) :
                        </strong>
                        <ul style="margin:0;padding-left:1.2rem;font-size:.9rem;color:var(--text-soft)">
                            @foreach ($lowStock as $p)
                                <li>
                                    <a href="{{ route('products.edit', $p) }}" style="color:var(--text)">
                                        {{ $p->title }}
                                    </a> — reste <strong style="color:var(--warn)">{{ $p->stock }}</strong> unité(s)
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
