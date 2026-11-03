@extends('layouts.app')

@section('title', 'Conseiller Stratégique IA : '.config('platform.name'))

@section('content')
<div class="container advisor-page-container" style="max-width:1160px;padding:2rem 1rem">
    
    <!-- En-tête -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1.5rem;margin-bottom:2rem">
        <div>
            <div style="display:inline-flex;align-items:center;gap:.5rem;padding:.3rem .8rem;border-radius:999px;background:linear-gradient(135deg, rgba(99,102,241,0.15), rgba(168,85,247,0.15));border:1px solid rgba(168,85,247,0.3);color:var(--brand-2);font-size:.8rem;font-weight:600;margin-bottom:.6rem">
                <x-icon name="sparkles" size="14" /> MODULE 8 & 11 • CONSEILLER STRATÉGIQUE D'AFFAIRES
            </div>
            <h1 style="font-size:1.85rem;margin:0;font-family:var(--font-display)">Conseiller d'Affaires & Audit Stratégique IA</h1>
            <p style="margin:.4rem 0 0 0;color:var(--text-soft);max-width:640px;font-size:.95rem">
                Surveillance de la rentabilité, détection du capital immobilisé en stock dormant, alertes réassort et plan d'action exécutif pour propulser votre boutique.
            </p>
        </div>

        <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
            <a href="{{ route('analytics.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="bar-chart-2" size="16" /> Stats Standard
            </a>
            <a href="{{ route('ai.assistant') }}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:.4rem">
                <x-icon name="message-square" size="16" /> Poser une question à l'IA
            </a>
        </div>
    </div>

    <!-- CARTE SCORE DE SANTÉ GLOBALE & BRIEFING EXÉCUTIF -->
    <div class="card" style="padding:1.8rem;margin-bottom:2.2rem;background:linear-gradient(145deg, var(--card-bg, #ffffff), color-mix(in srgb, var(--brand-1) 5%, transparent));border:1px solid color-mix(in srgb, var(--brand-2) 25%, var(--border))">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.5rem">
            
            <div style="display:flex;align-items:center;gap:1.5rem">
                <!-- Cercle score -->
                <div style="position:relative;width:92px;height:92px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:radial-gradient(circle, var(--bg-soft) 60%, transparent 62%), conic-gradient(var(--brand-2) {{ $diagnosis['health_score'] * 3.6 }}deg, color-mix(in srgb, var(--text-soft) 20%, transparent) 0deg);box-shadow:0 8px 24px -6px rgba(99,102,241,0.25)">
                    <span style="font-size:1.6rem;font-weight:800;font-family:var(--font-display);line-height:1;color:var(--text)">{{ $diagnosis['health_score'] }}</span>
                    <span style="font-size:.65rem;font-weight:700;color:var(--text-soft);letter-spacing:1px">/100</span>
                </div>

                <div>
                    <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--brand-2)">Score de Santé Commerciale</div>
                    <h3 style="margin:.2rem 0 .4rem 0;font-size:1.3rem">
                        {{ $diagnosis['health_score'] >= 75 ? 'Excellente Performance Globale 🚀' : ($diagnosis['health_score'] >= 55 ? 'Santé Commerciale Modérée 📈' : 'Attention Requise sur les Stocks ⚠️') }}
                    </h3>
                    <p style="margin:0;font-size:.9rem;color:var(--text-soft);max-width:550px">
                        {{ $diagnosis['strategic_verdict'] }}
                    </p>
                </div>
            </div>

            <div>
                <button type="button" id="btn-generate-briefing" onclick="generateAiExecutiveBriefing()" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.25rem">
                    <x-icon name="sparkles" size="18" />
                    <span>Générer un Briefing Exécutif IA</span>
                </button>
                <div style="font-size:.75rem;color:var(--text-soft);text-align:right;margin-top:.4rem">
                    Consomme 1 crédit IA • Reste : <strong>{{ $shop->aiCreditsRemaining() }}</strong>
                </div>
            </div>

        </div>

        <!-- Zone d'affichage du Briefing IA rédigé -->
        <div id="briefing-container" style="display:none;margin-top:1.5rem;padding-top:1.5rem;border-top:1px dashed var(--border)">
            <div style="display:flex;align-items:center;gap:.5rem;color:var(--brand-2);font-weight:700;font-size:.9rem;margin-bottom:.8rem">
                <x-icon name="file-text" size="18" /> BRIEFING STRATÉGIQUE GÉNÉRÉ PAR LE DIRECTEUR COMMERCIAL IA
            </div>
            <div id="briefing-content" style="background:var(--bg-soft);padding:1.4rem;border-radius:var(--radius-sm);font-size:.92rem;line-height:1.65;white-space:pre-wrap"></div>
        </div>
    </div>

    <!-- SECTION COMPARAISON DE PÉRIODES -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.2rem">
        <div>
            <h2 style="font-size:1.35rem;margin:0">Évolution & Croissance des Ventes</h2>
            <p style="margin:.2rem 0 0 0;font-size:.85rem;color:var(--text-soft)">Analyse comparative des performances face à la période précédente</p>
        </div>

        <!-- Sélecteur de période -->
        <div style="display:inline-flex;background:var(--bg-soft);padding:.25rem;border-radius:var(--radius-sm);border:1px solid var(--border)">
            <a href="?period=7d" class="btn btn-sm {{ $period === '7d' ? 'btn-primary' : 'btn-ghost' }}" style="padding:.35rem .75rem;font-size:.82rem">7 Jours</a>
            <a href="?period=30d" class="btn btn-sm {{ $period === '30d' ? 'btn-primary' : 'btn-ghost' }}" style="padding:.35rem .75rem;font-size:.82rem">30 Jours</a>
            <a href="?period=month" class="btn btn-sm {{ $period === 'month' ? 'btn-primary' : 'btn-ghost' }}" style="padding:.35rem .75rem;font-size:.82rem">Mois en cours</a>
        </div>
    </div>

    <!-- 4 Cartes Comparatives -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1.2rem;margin-bottom:2rem">
        
        <!-- Chiffre d'Affaires -->
        <div class="card" style="padding:1.3rem">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <span style="font-size:.82rem;color:var(--text-soft)">Chiffre d'Affaires Réalisé</span>
                @php $revPct = $comparison['growth']['revenue_pct']; @endphp
                <span style="font-size:.8rem;font-weight:700;padding:.2rem .5rem;border-radius:6px;display:inline-flex;align-items:center;gap:.2rem;background:{{ $revPct >= 0 ? 'color-mix(in srgb, var(--ok) 15%, transparent)' : 'color-mix(in srgb, var(--danger) 15%, transparent)' }};color:{{ $revPct >= 0 ? 'var(--ok)' : 'var(--danger)' }}">
                    <x-icon name="{{ $revPct >= 0 ? 'trending-up' : 'trending-down' }}" size="14" />
                    {{ $revPct >= 0 ? '+' : '' }}{{ $revPct }}%
                </span>
            </div>
            <div style="font-size:1.7rem;font-weight:700;font-family:var(--font-display);margin-top:.4rem">
                {{ number_format($comparison['current']['revenue_fcfa'], 0, ',', ' ') }} FCFA
            </div>
            <div style="font-size:.78rem;color:var(--text-soft);margin-top:.4rem">
                Précédent : {{ number_format($comparison['previous']['revenue_fcfa'], 0, ',', ' ') }} FCFA
                ({{ ($comparison['growth']['revenue_diff_fcfa'] >= 0 ? '+' : '') . number_format($comparison['growth']['revenue_diff_fcfa'], 0, ',', ' ') }} FCFA)
            </div>
        </div>

        <!-- Volume de Commandes -->
        <div class="card" style="padding:1.3rem">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <span style="font-size:.82rem;color:var(--text-soft)">Commandes Enregistrées</span>
                @php $ordPct = $comparison['growth']['orders_pct']; @endphp
                <span style="font-size:.8rem;font-weight:700;padding:.2rem .5rem;border-radius:6px;display:inline-flex;align-items:center;gap:.2rem;background:{{ $ordPct >= 0 ? 'color-mix(in srgb, var(--ok) 15%, transparent)' : 'color-mix(in srgb, var(--danger) 15%, transparent)' }};color:{{ $ordPct >= 0 ? 'var(--ok)' : 'var(--danger)' }}">
                    <x-icon name="{{ $ordPct >= 0 ? 'trending-up' : 'trending-down' }}" size="14" />
                    {{ $ordPct >= 0 ? '+' : '' }}{{ $ordPct }}%
                </span>
            </div>
            <div style="font-size:1.7rem;font-weight:700;font-family:var(--font-display);margin-top:.4rem">
                {{ $comparison['current']['orders_count'] }}
            </div>
            <div style="font-size:.78rem;color:var(--text-soft);margin-top:.4rem">
                Précédent : {{ $comparison['previous']['orders_count'] }} commandes
                ({{ ($comparison['growth']['orders_diff'] >= 0 ? '+' : '') . $comparison['growth']['orders_diff'] }})
            </div>
        </div>

        <!-- Panier Moyen -->
        <div class="card" style="padding:1.3rem">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <span style="font-size:.82rem;color:var(--text-soft)">Panier Moyen Client</span>
                @php $aovPct = $comparison['growth']['aov_pct']; @endphp
                <span style="font-size:.8rem;font-weight:700;padding:.2rem .5rem;border-radius:6px;display:inline-flex;align-items:center;gap:.2rem;background:{{ $aovPct >= 0 ? 'color-mix(in srgb, var(--ok) 15%, transparent)' : 'color-mix(in srgb, var(--warn) 15%, transparent)' }};color:{{ $aovPct >= 0 ? 'var(--ok)' : 'var(--warn)' }}">
                    <x-icon name="{{ $aovPct >= 0 ? 'trending-up' : 'trending-down' }}" size="14" />
                    {{ $aovPct >= 0 ? '+' : '' }}{{ $aovPct }}%
                </span>
            </div>
            <div style="font-size:1.7rem;font-weight:700;font-family:var(--font-display);margin-top:.4rem">
                {{ number_format($comparison['current']['average_basket_fcfa'], 0, ',', ' ') }} FCFA
            </div>
            <div style="font-size:.78rem;color:var(--text-soft);margin-top:.4rem">
                Précédent : {{ number_format($comparison['previous']['average_basket_fcfa'], 0, ',', ' ') }} FCFA
            </div>
        </div>

        <!-- Taux de Réussite Livraison -->
        <div class="card" style="padding:1.3rem">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <span style="font-size:.82rem;color:var(--text-soft)">Taux de Livraison Réussie</span>
                @php $delivRate = $comparison['current']['delivery_rate_percent']; @endphp
                <span style="font-size:.8rem;font-weight:700;padding:.2rem .5rem;border-radius:6px;background:{{ $delivRate >= 70 ? 'color-mix(in srgb, var(--ok) 15%, transparent)' : 'color-mix(in srgb, var(--warn) 15%, transparent)' }};color:{{ $delivRate >= 70 ? 'var(--ok)' : 'var(--warn)' }}">
                    {{ $delivRate }}%
                </span>
            </div>
            <div style="font-size:1.7rem;font-weight:700;font-family:var(--font-display);margin-top:.4rem">
                {{ $comparison['current']['delivered_count'] }} livrées
            </div>
            <div style="font-size:.78rem;color:var(--text-soft);margin-top:.4rem">
                Sur {{ $comparison['current']['orders_count'] }} commande(s) totale(s)
            </div>
        </div>

    </div>

    <!-- DYNAMIQUE PAR PRODUIT : GAINERS & DECLINERS -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.5rem;margin-bottom:2.2rem">
        
        <!-- Top Gainers -->
        <div class="card" style="padding:1.4rem">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;color:var(--ok);font-weight:700;font-size:1rem">
                <x-icon name="trending-up" size="18" /> TOP ARTICLES EN PROGRESSION (GAINERS)
            </div>

            @if (empty($comparison['gainers']))
                <p style="color:var(--text-soft);font-size:.88rem;margin:0">Aucun produit en forte hausse sur cette période.</p>
            @else
                <div style="display:flex;flex-direction:column;gap:.75rem">
                    @foreach ($comparison['gainers'] as $g)
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:.7rem .85rem;background:var(--bg-soft);border-radius:var(--radius-sm)">
                            <div>
                                <div style="font-weight:600;font-size:.9rem">{{ $g['title'] }}</div>
                                <div style="font-size:.78rem;color:var(--text-soft)">
                                    {{ $g['current_qty'] }} vendus ({{ $g['diff_qty'] >= 0 ? '+' : '' }}{{ $g['diff_qty'] }} vs précédent)
                                </div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-weight:700;color:var(--ok);font-size:.9rem">
                                    +{{ number_format($g['diff_rev_fcfa'], 0, ',', ' ') }} FCFA
                                </div>
                                <div style="font-size:.75rem;color:var(--text-soft)">{{ number_format($g['current_rev'], 0, ',', ' ') }} FCFA totaux</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Decliners -->
        <div class="card" style="padding:1.4rem">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;color:var(--danger);font-weight:700;font-size:1rem">
                <x-icon name="trending-down" size="18" /> ARTICLES EN RECUL (À RELANCER)
            </div>

            @if (empty($comparison['decliners']))
                <p style="color:var(--text-soft);font-size:.88rem;margin:0">Aucun produit en baisse significative sur cette période.</p>
            @else
                <div style="display:flex;flex-direction:column;gap:.75rem">
                    @foreach ($comparison['decliners'] as $d)
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:.7rem .85rem;background:var(--bg-soft);border-radius:var(--radius-sm)">
                            <div>
                                <div style="font-weight:600;font-size:.9rem">{{ $d['title'] }}</div>
                                <div style="font-size:.78rem;color:var(--text-soft)">
                                    {{ $d['current_qty'] }} vendus ({{ $d['diff_qty'] }} vs précédent)
                                </div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-weight:700;color:var(--danger);font-size:.9rem">
                                    {{ number_format($d['diff_rev_fcfa'], 0, ',', ' ') }} FCFA
                                </div>
                                <div style="font-size:.75rem;color:var(--text-soft)">Baisse constatée</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    <!-- 3 PILIERS STRATÉGIQUES : STOCK DORMANT, RÉASSORT, ET RISQUE COD -->
    <h2 style="font-size:1.35rem;margin:0 0 1.2rem 0">3 Diagnostics Stratégiques Actionnables</h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.5rem">
        
        <!-- PILIER 1 : STOCK DORMANT & CAPITAL IMMOBILISÉ -->
        <div class="card" style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between">
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem">
                    <span style="display:inline-flex;align-items:center;gap:.4rem;font-weight:700;font-size:.85rem;color:var(--warn)">
                        <x-icon name="dollar-sign" size="16" /> CAPITAL IMMOBILISÉ
                    </span>
                    <span class="badge" style="background:color-mix(in srgb, var(--warn) 15%, transparent);color:var(--warn);font-size:.75rem">
                        {{ $diagnosis['dead_stock']['total_items_count'] }} produit(s) dormants
                    </span>
                </div>

                <div style="font-size:1.6rem;font-weight:800;font-family:var(--font-display);color:var(--warn);margin-bottom:.4rem">
                    {{ number_format($diagnosis['dead_stock']['total_capital_immobilise_fcfa'], 0, ',', ' ') }} FCFA
                </div>
                <p style="font-size:.85rem;color:var(--text-soft);margin:0 0 1rem 0">
                    Valeur marchande piégée dans des articles sans aucune vente enregistrée sur les 30 derniers jours.
                </p>

                @if (!empty($diagnosis['dead_stock']['items']))
                    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem">
                        @foreach ($diagnosis['dead_stock']['items'] as $item)
                            <div style="font-size:.82rem;padding:.5rem;background:var(--bg-soft);border-radius:var(--radius-sm);display:flex;justify-content:space-between">
                                <div>
                                    <strong>{{ $item['title'] }}</strong> ({{ $item['stock'] }} en stock)
                                </div>
                                <div style="font-weight:600">{{ number_format($item['capital_immobilise_fcfa'], 0, ',', ' ') }} F</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div style="margin-top:1rem;padding-top:.8rem;border-top:1px solid var(--border)">
                <a href="{{ route('ai.studio') }}" class="btn btn-ghost btn-sm" style="width:100%;text-align:center;display:block">
                    Créer un visuel promo avec l'IA →
                </a>
            </div>
        </div>

        <!-- PILIER 2 : URGENCES DE RÉASSORT -->
        <div class="card" style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between">
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem">
                    <span style="display:inline-flex;align-items:center;gap:.4rem;font-weight:700;font-size:.85rem;color:var(--danger)">
                        <x-icon name="alert-triangle" size="16" /> RUPTURES IMMINENTES
                    </span>
                    <span class="badge" style="background:color-mix(in srgb, var(--danger) 15%, transparent);color:var(--danger);font-size:.75rem">
                        {{ $diagnosis['restock_urgency']['critical_items_count'] }} en danger
                    </span>
                </div>

                <div style="font-size:1.6rem;font-weight:800;font-family:var(--font-display);color:var(--danger);margin-bottom:.4rem">
                    {{ $diagnosis['restock_urgency']['critical_items_count'] }} Best-Sellers
                </div>
                <p style="font-size:.85rem;color:var(--text-soft);margin:0 0 1rem 0">
                    Articles à forte rotation dont le stock risque de s'épuiser sous 48h à 96h, causant des pertes sèches de ventes.
                </p>

                @if (empty($diagnosis['restock_urgency']['items']))
                    <div style="padding:.8rem;background:color-mix(in srgb, var(--ok) 12%, transparent);border-radius:var(--radius-sm);color:var(--ok);font-size:.85rem">
                        ✓ Vos best-sellers disposent de stocks confortables.
                    </div>
                @else
                    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem">
                        @foreach ($diagnosis['restock_urgency']['items'] as $item)
                            <div style="font-size:.82rem;padding:.5rem;background:var(--bg-soft);border-radius:var(--radius-sm);display:flex;justify-content:space-between;align-items:center">
                                <div>
                                    <strong>{{ $item['title'] }}</strong>
                                    <div style="font-size:.75rem;color:var(--danger)">Reste {{ $item['current_stock'] }} unité(s) • {{ $item['days_remaining_est'] }}j restants</div>
                                </div>
                                <div style="text-align:right">
                                    <span class="badge" style="background:color-mix(in srgb, var(--danger) 15%, transparent);color:var(--danger);font-size:.7rem">
                                        Perte ~{{ number_format($item['potential_loss_7d_fcfa'], 0, ',', ' ') }} F
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div style="margin-top:1rem;padding-top:.8rem;border-top:1px solid var(--border)">
                <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm" style="width:100%;text-align:center;display:block">
                    Ajuster les stocks produits →
                </a>
            </div>
        </div>

        <!-- PILIER 3 : RISQUE ET CONTRÔLE DU COD -->
        <div class="card" style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between">
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem">
                    <span style="display:inline-flex;align-items:center;gap:.4rem;font-weight:700;font-size:.85rem;color:var(--brand-2)">
                        <x-icon name="truck" size="16" /> GESTION DU RISQUE COD
                    </span>
                    <span class="badge" style="background:color-mix(in srgb, var(--brand-2) 15%, transparent);color:var(--brand-2);font-size:.75rem">
                        {{ $diagnosis['cod_diagnosis']['cod_share_percent'] }}% du volume
                    </span>
                </div>

                <div style="font-size:1.6rem;font-weight:800;font-family:var(--font-display);margin-bottom:.4rem">
                    {{ $diagnosis['cod_diagnosis']['cod_success_rate'] }}% de livraison
                </div>
                <p style="font-size:.85rem;color:var(--text-soft);margin:0 0 1rem 0">
                    Taux d'annulation constaté : <strong>{{ $diagnosis['cod_diagnosis']['cod_cancel_rate'] }}%</strong>.
                    Frais de port engagés sur colis refusés : <strong>{{ number_format($diagnosis['cod_diagnosis']['estimated_lost_shipping_fcfa'], 0, ',', ' ') }} FCFA</strong>.
                </p>

                <div style="padding:.8rem;background:var(--bg-soft);border-radius:var(--radius-sm);font-size:.82rem;line-height:1.5">
                    💡 <strong>Conseil d'expert :</strong>
                    Exigez une confirmation WhatsApp systématique avant expédition ou proposez une remise de 5% pour inciter au paiement Mobile Money immédiat.
                </div>
            </div>

            <div style="margin-top:1rem;padding-top:.8rem;border-top:1px solid var(--border)">
                <a href="{{ route('ai.orders.cod_review') }}" class="btn btn-ghost btn-sm" style="width:100%;text-align:center;display:block">
                    Accéder à la Revue COD Intelligente →
                </a>
            </div>
        </div>

    </div>

</div>

<script>
async function generateAiExecutiveBriefing() {
    const btn = document.getElementById('btn-generate-briefing');
    const container = document.getElementById('briefing-container');
    const content = document.getElementById('briefing-content');

    btn.disabled = true;
    btn.innerHTML = `
        <span style="display:inline-block;animation:spin 1s linear infinite">⏳</span>
        <span>Analyse stratégique en cours...</span>
    `;

    try {
        const response = await fetch("{{ route('analytics.advisor.briefing') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.status === 'success') {
            container.style.display = 'block';
            content.textContent = data.briefing;
            btn.innerHTML = `<x-icon name="check" size="18" /> <span>Briefing généré !</span>`;
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = `<x-icon name="sparkles" size="18" /> <span>Rafraîchir le Briefing IA</span>`;
            }, 3000);
        } else {
            alert(data.message || "Erreur lors de la génération du briefing exécutif.");
            btn.disabled = false;
            btn.innerHTML = `<x-icon name="sparkles" size="18" /> <span>Générer un Briefing Exécutif IA</span>`;
        }
    } catch (err) {
        alert("Erreur de communication avec le serveur.");
        btn.disabled = false;
        btn.innerHTML = `<x-icon name="sparkles" size="18" /> <span>Générer un Briefing Exécutif IA</span>`;
    }
}
</script>
<style>
@media (max-width: 768px) {
    .advisor-page-container {
        padding-bottom: 85px !important;
    }
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
@endsection
