@extends('layouts.app')

@section('title', 'Audit, Contrôle & Alertes Système - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1180px;padding:2rem 1rem">
    
    <!-- En-tête Cockpit Admin -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1.5rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <span style="font-size:1.8rem">🛡️</span>
                <h1 style="font-size:1.8rem;margin:0">Audit, Contrôle & Alertes Système</h1>
                @if ($stats['unread_alerts'] > 0)
                    <span class="badge" style="background:#ef4444;color:#ffffff;font-weight:700">
                        {{ $stats['unread_alerts'] }} alerte(s) non lue(s)
                    </span>
                @else
                    <span class="badge badge-ok">Système Sain</span>
                @endif
            </div>
            <p style="margin:0;color:var(--text-soft)">Supervision en direct de l'infrastructure Zanika : santé des APIs (IA, Paiement, Mail, Push), détection d'anomalies et flux des commerçants.</p>
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <button type="button" class="btn btn-sm btn-ghost" data-push-action="test" style="display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--border)">
                <x-icon name="bell" size="14" /> Tester Push Mobile
            </button>
            <form method="POST" action="{{ route('admin.audit.health_check') }}" style="margin:0">
                @csrf
                <button type="submit" class="btn btn-sm btn-ghost" style="display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--border)">
                    <x-icon name="refresh-cw" size="14" /> Rafraîchir Contrôles
                </button>
            </form>
            @if ($stats['unread_alerts'] > 0)
                <form method="POST" action="{{ route('admin.audit.read_all') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary" style="display:inline-flex;align-items:center;gap:.4rem">
                        <x-icon name="check" size="14" /> Tout marquer comme lu
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Onglets de Navigation Super-Admin -->
    <div class="admin-nav-pills" style="margin-bottom:2rem;display:flex;gap:.5rem;flex-wrap:wrap;border-bottom:1px solid var(--border);padding-bottom:.75rem">
        <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
            <x-icon name="zap" size="14" /> Tableau de Bord
        </a>
        <a href="{{ route('admin.audit') }}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
            <x-icon name="shield" size="14" /> Audit & Alertes
        </a>
        <a href="{{ route('admin.shops') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
            <x-icon name="shop" size="14" /> Boutiques
        </a>
        <a href="{{ route('admin.subscriptions') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
            <x-icon name="credit-card" size="14" /> Abonnements & CA
        </a>
        <a href="{{ route('admin.ai_usage') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
            <x-icon name="sparkles" size="14" /> Quotas IA
        </a>
        <a href="{{ route('admin.plans') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
            <x-icon name="diamond" size="14" /> Formules & Tarifs
        </a>
        <a href="{{ route('admin.maintenance') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.35rem">
            <x-icon name="wrench" size="14" /> Maintenance
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    <!-- SECTION 1 : DIAGNOSTIC EN TEMPS RÉEL DE L'INFRASTRUCTURE -->
    <div style="margin-bottom:2.5rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <h2 style="font-size:1.15rem;margin:0;display:flex;align-items:center;gap:.5rem">
                <span>⚡</span> Diagnostic Technique en Direct
            </h2>
            <span style="font-size:.8rem;color:var(--text-soft)">Contrôlé le {{ now()->format('d/m/Y à H:i:s') }}</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:1rem">
            <!-- Groq IA & Relais -->
            <div class="card" style="padding:1.25rem;border-left:4px solid {{ $health['openrouter']['status'] === 'ok' ? '#10b981' : '#f59e0b' }}">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-weight:700;font-size:.92rem">Groq IA (LPU Haute Vitesse)</span>
                    <span class="badge {{ $health['openrouter']['status'] === 'ok' ? 'badge-ok' : 'badge-warning' }}">
                        {{ $health['openrouter']['status'] === 'ok' ? 'Opérationnel' : 'À surveiller' }}
                    </span>
                </div>
                <div style="font-size:1.15rem;font-weight:800;margin:.4rem 0">{{ $health['openrouter']['message'] }}</div>
                <div style="font-size:.8rem;color:var(--text-soft)">Moteur IA souverain ultra-rapide pour le copilote e-commerce, les fiches produits et le studio publicitaire.</div>
            </div>

            <!-- Passerelle GeniusPay -->
            <div class="card" style="padding:1.25rem;border-left:4px solid {{ $health['feexpay']['status'] === 'ok' ? '#10b981' : '#ef4444' }}">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-weight:700;font-size:.92rem">Paiement Mobile GeniusPay</span>
                    <span class="badge {{ $health['feexpay']['status'] === 'ok' ? 'badge-ok' : 'badge-error' }}">
                        {{ $health['feexpay']['status'] === 'ok' ? 'Opérationnel' : 'Erreur' }}
                    </span>
                </div>
                <div style="font-size:1.15rem;font-weight:800;margin:.4rem 0">{{ $health['feexpay']['message'] }}</div>
                <div style="font-size:.8rem;color:var(--text-soft)">T-Money, Moov Money, Orange Money & Cartes bancaires (Devise XOF).</div>
            </div>

            <!-- Délivrabilité Emails -->
            <div class="card" style="padding:1.25rem;border-left:4px solid {{ $health['mail']['status'] === 'ok' ? '#10b981' : '#ef4444' }}">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-weight:700;font-size:.92rem">Relais Email (Google Apps Script)</span>
                    <span class="badge {{ $health['mail']['status'] === 'ok' ? 'badge-ok' : 'badge-error' }}">
                        {{ $health['mail']['status'] === 'ok' ? 'Opérationnel' : 'Erreur' }}
                    </span>
                </div>
                <div style="font-size:1.05rem;font-weight:800;margin:.4rem 0">{{ $health['mail']['message'] }}</div>
                <div style="font-size:.8rem;color:var(--text-soft)">Transmission 100% gratuite et instantanée des codes OTP et alertes.</div>
            </div>

            <!-- Notifications Push VAPID -->
            <div class="card" style="padding:1.25rem;border-left:4px solid {{ $health['vapid']['status'] === 'ok' ? '#10b981' : '#ef4444' }}">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-weight:700;font-size:.92rem">Web Push Mobiles (VAPID)</span>
                    <span class="badge {{ $health['vapid']['status'] === 'ok' ? 'badge-ok' : 'badge-error' }}">
                        {{ $health['vapid']['status'] === 'ok' ? 'Actif' : 'Non configuré' }}
                    </span>
                </div>
                <div style="font-size:1.15rem;font-weight:800;margin:.4rem 0">
                    {{ $stats['push_devices_total'] }} appareil(s) abonné(s)
                </div>
                <div style="font-size:.8rem;color:var(--text-soft)">
                    Admins : <strong>{{ $stats['push_devices_admin'] }}</strong> • Commerçants : <strong>{{ $stats['push_devices_merchants'] }}</strong>
                </div>
            </div>

            <!-- Base de données & Stockage -->
            <div class="card" style="padding:1.25rem;border-left:4px solid {{ $health['database']['status'] === 'ok' ? '#10b981' : '#ef4444' }}">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-weight:700;font-size:.92rem">Base de Données & Stockage</span>
                    <span class="badge badge-ok">Latence : {{ $health['database']['latency_ms'] }} ms</span>
                </div>
                <div style="font-size:1.15rem;font-weight:800;margin:.4rem 0">Système sain & inscriptible</div>
                <div style="font-size:.8rem;color:var(--text-soft)">Permissions stockage framework / logs vérifiées.</div>
            </div>
        </div>
    </div>

    <!-- SECTION 2 : ACTIVITÉ & SÉCURITÉ DU JOUR -->
    <div style="margin-bottom:2.5rem">
        <h2 style="font-size:1.15rem;margin:0 0 1rem 0;display:flex;align-items:center;gap:.5rem">
            <span>📊</span> Activité & Sécurité Aujourd'hui
        </h2>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem">
            <div class="card" style="padding:1.2rem">
                <div style="font-size:.82rem;color:var(--text-soft)">Nouveaux Commerçants</div>
                <div style="font-size:1.75rem;font-weight:800;margin:.2rem 0">{{ $stats['merchants_today'] }}</div>
                <div style="font-size:.8rem;color:var(--text-soft)">{{ $stats['merchants_7d'] }} inscrits sur 7 jours</div>
            </div>

            <div class="card" style="padding:1.2rem">
                <div style="font-size:.82rem;color:var(--text-soft)">Boutiques Publiées</div>
                <div style="font-size:1.75rem;font-weight:800;margin:.2rem 0;color:#10b981">{{ $stats['shops_launched_today'] }}</div>
                <div style="font-size:.8rem;color:var(--text-soft)">Mises en ligne aujourd'hui</div>
            </div>

            <div class="card" style="padding:1.2rem">
                <div style="font-size:.82rem;color:var(--text-soft)">Commandes du Jour</div>
                <div style="font-size:1.75rem;font-weight:800;margin:.2rem 0">{{ $stats['orders_today'] }}</div>
                <div style="font-size:.8rem;color:var(--text-soft)">Passées sur les boutiques</div>
            </div>

            <div class="card" style="padding:1.2rem">
                <div style="font-size:.82rem;color:var(--text-soft)">Volume Financier (GMV)</div>
                <div style="font-size:1.75rem;font-weight:800;margin:.2rem 0;color:var(--brand-primary)">
                    {{ number_format($stats['gmv_today'], 0, ',', ' ') }} <small style="font-size:1rem">FCFA</small>
                </div>
                <div style="font-size:.8rem;color:var(--text-soft)">Total des ventes aujourd'hui</div>
            </div>
        </div>
    </div>

    <!-- SECTION 3 : JOURNAL DES AUDITS & ALERTES SYSTÈME -->
    <div class="card" style="padding:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem">
            <div>
                <h2 style="font-size:1.2rem;margin:0 0 .25rem 0">Journal des Alertes & Activités</h2>
                <p style="margin:0;font-size:.85rem;color:var(--text-soft)">Historique chronologique des événements importants : inscriptions, boutiques lancées, failles ou anomalies.</p>
            </div>

            <!-- Filtres rapides -->
            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                <a href="{{ route('admin.audit') }}" class="btn btn-xs {{ empty($filterCategory) && empty($filterSeverity) && !$onlyUnread ? 'btn-primary' : 'btn-ghost' }}">
                    Toutes
                </a>
                <a href="{{ route('admin.audit', ['unread' => 1]) }}" class="btn btn-xs {{ $onlyUnread ? 'btn-primary' : 'btn-ghost' }}">
                    Non lues ({{ $stats['unread_alerts'] }})
                </a>
                <a href="{{ route('admin.audit', ['category' => 'merchant_registered']) }}" class="btn btn-xs {{ $filterCategory === 'merchant_registered' ? 'btn-primary' : 'btn-ghost' }}">
                    Inscriptions
                </a>
                <a href="{{ route('admin.audit', ['category' => 'shop_created']) }}" class="btn btn-xs {{ $filterCategory === 'shop_created' ? 'btn-primary' : 'btn-ghost' }}">
                    Boutiques
                </a>
                <a href="{{ route('admin.audit', ['category' => 'order_fraud']) }}" class="btn btn-xs {{ $filterCategory === 'order_fraud' ? 'btn-primary' : 'btn-ghost' }}">
                    Commandes VIP / Fraude
                </a>
                <a href="{{ route('admin.audit', ['category' => 'system_health']) }}" class="btn btn-xs {{ $filterCategory === 'system_health' ? 'btn-primary' : 'btn-ghost' }}">
                    Santé Système
                </a>
            </div>
        </div>

        @if ($alerts->isEmpty())
            <div style="text-align:center;padding:3rem 1rem;color:var(--text-soft)">
                <div style="font-size:2.5rem;margin-bottom:.5rem">🎉</div>
                <div style="font-size:1.05rem;font-weight:700">Aucune alerte à afficher</div>
                <p style="font-size:.85rem;margin-top:.25rem">Tout fonctionne parfaitement sur la plateforme Zanika.</p>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:.75rem">
                @foreach ($alerts as $alert)
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem;border-radius:12px;background:var(--bg-soft);border:1px solid {{ $alert->is_read ? 'var(--border)' : 'rgba(255, 45, 109, 0.3)' }};flex-wrap:wrap;transition:all .15s ease">
                        <div style="display:flex;align-items:flex-start;gap:.85rem;flex:1;min-width:280px">
                            <!-- Icône par catégorie -->
                            <div style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:{{ $alert->severity === 'critical' ? 'rgba(239, 68, 68, 0.15)' : ($alert->severity === 'warning' ? 'rgba(245, 158, 11, 0.15)' : 'rgba(16, 185, 129, 0.15)') }}">
                                @if ($alert->category === 'merchant_registered')
                                    <span style="font-size:1.2rem">🚀</span>
                                @elseif ($alert->category === 'shop_created')
                                    <span style="font-size:1.2rem">🏪</span>
                                @elseif ($alert->category === 'order_fraud')
                                    <span style="font-size:1.2rem">💰</span>
                                @else
                                    <span style="font-size:1.2rem">⚙️</span>
                                @endif
                            </div>

                            <div style="flex:1">
                                <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                                    <strong style="font-size:.95rem;color:var(--text)">{{ $alert->title }}</strong>
                                    
                                    @if (!$alert->is_read)
                                        <span class="badge" style="background:#ef4444;color:#ffffff;font-size:.7rem;padding:1px 6px">NOUVEAU</span>
                                    @endif

                                    <span class="badge {{ $alert->severity === 'critical' ? 'badge-error' : ($alert->severity === 'warning' ? 'badge-warning' : 'badge-ok') }}" style="font-size:.7rem">
                                        {{ strtoupper($alert->severity) }}
                                    </span>

                                    <span style="font-size:.78rem;color:var(--text-soft)">
                                        {{ $alert->created_at ? $alert->created_at->diffForHumans() . ' (' . $alert->created_at->format('d/m/Y à H:i') . ')' : 'Récemment' }}
                                    </span>
                                </div>

                                <p style="margin:.35rem 0 .5rem 0;font-size:.88rem;color:var(--text-soft);line-height:1.45">
                                    {{ $alert->message }}
                                </p>

                                @if (!empty($alert->data))
                                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;font-size:.75rem">
                                        @foreach ($alert->data as $key => $val)
                                            @if (is_scalar($val))
                                                <span style="background:var(--bg);padding:2px 7px;border-radius:6px;border:1px solid var(--border)">
                                                    <strong>{{ $key }}:</strong> {{ $val }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:.5rem;flex-shrink:0">
                            @if ($alert->action_url)
                                <a href="{{ $alert->action_url }}" target="_blank" class="btn btn-xs btn-ghost" style="display:inline-flex;align-items:center;gap:.3rem">
                                    <span>Consulter</span>
                                    <x-icon name="external-link" size="12" />
                                </a>
                            @endif

                            @if (!$alert->is_read)
                                <form method="POST" action="{{ route('admin.audit.read', $alert) }}" style="margin:0">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-ghost" title="Marquer comme lu">
                                        <x-icon name="check" size="13" />
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:1.5rem">
                {{ $alerts->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
