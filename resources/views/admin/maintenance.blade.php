@extends('layouts.app')

@section('title', 'Mode Maintenance : Super-Admin - ' . config('platform.name'))

@section('content')
<div class="container" style="max-width:960px;margin:2rem auto;padding:0 1rem">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
        <div>
            <div style="display:flex;align-items:center;gap:.5rem">
                <a href="{{ route('admin.dashboard') }}" style="color:var(--text-soft);text-decoration:none">Super-Admin</a>
                <span style="color:var(--text-soft)">/</span>
                <span style="font-weight:600">Mode Maintenance</span>
            </div>
            <h1 style="font-size:1.75rem;font-weight:800;font-family:var(--font-display);margin-top:.25rem">
                Contrôle du Mode Maintenance
            </h1>
        </div>

        <div class="admin-nav-pills">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="dashboard" size="15" /> Tableau de bord
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
            <a href="{{ route('admin.plans') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="diamond" size="15" /> Formules
            </a>
            <a href="{{ route('admin.maintenance') }}" class="btn btn-sm" style="display:inline-flex;align-items:center;gap:.3rem;background:rgba(239, 68, 68, 0.15);color:#ef4444;font-weight:700">
                <x-icon name="wrench" size="15" /> Maintenance
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    <!-- Statut Actuel -->
    @if ($isDown)
        <div class="card" style="padding:2rem;border-left:5px solid #ef4444;background:rgba(239, 68, 68, 0.05);margin-bottom:2rem;border-radius:12px">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1.5rem">
                <div style="flex:1;min-width:280px">
                    <div style="display:inline-flex;align-items:center;gap:.5rem;background:#ef4444;color:#ffffff;padding:.3rem .85rem;border-radius:9999px;font-size:.85rem;font-weight:700;margin-bottom:1rem">
                        <span style="display:inline-block;width:8px;height:8px;background:#ffffff;border-radius:50%;animation:pulse 1.5s infinite"></span>
                        MODE MAINTENANCE ACTIF (Site Coupé au Public)
                    </div>
                    <h2 style="font-size:1.35rem;font-weight:700;margin-bottom:.5rem">
                        La plateforme est actuellement inaccessible aux visiteurs et commerçants.
                    </h2>
                    <p style="color:var(--text-soft);font-size:.95rem;line-height:1.6;margin-bottom:1rem">
                        Toute tentative d'accès à la page d'accueil, aux boutiques publiques et au tableau de bord marchand affiche la page d'attente technique 503.
                    </p>

                    @if (!empty($info['activated_at']))
                        <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:1rem;font-size:.9rem;margin-bottom:1.25rem">
                            <div style="display:flex;gap:1.5rem;flex-wrap:wrap">
                                <div>
                                    <span style="color:var(--text-soft);display:block;font-size:.8rem">Activé le :</span>
                                    <strong>{{ $info['activated_at'] }}</strong>
                                </div>
                                @if (!empty($info['activated_by']))
                                    <div>
                                        <span style="color:var(--text-soft);display:block;font-size:.8rem">Par :</span>
                                        <strong>{{ $info['activated_by'] }}</strong>
                                    </div>
                                @endif
                                @if (!empty($info['estimated_time']))
                                    <div>
                                        <span style="color:var(--text-soft);display:block;font-size:.8rem">Durée estimée :</span>
                                        <strong style="color:#fbbf24">{{ $info['estimated_time'] }}</strong>
                                    </div>
                                @endif
                            </div>
                            @if (!empty($info['message']))
                                <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid rgba(255,255,255,0.06)">
                                    <span style="color:var(--text-soft);font-size:.8rem">Message affiché au public :</span>
                                    <div style="color:#e2e8f0;margin-top:.25rem;font-style:italic">« {{ $info['message'] }} »</div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Lien secret de contournement -->
                    <div style="background:rgba(99, 102, 241, 0.08);border:1px solid rgba(99, 102, 241, 0.25);border-radius:8px;padding:1rem;margin-bottom:1rem">
                        <div style="font-weight:700;color:#818cf8;font-size:.9rem;margin-bottom:.35rem;display:flex;align-items:center;gap:.4rem">
                            <x-icon name="shield" size="16" />
                            Lien secret de contournement (Bypass) pour le fondateur & l'équipe :
                        </div>
                        <div style="font-size:.85rem;color:var(--text-soft);margin-bottom:.65rem">
                            En visitant ce lien secret, votre navigateur recevra automatiquement le cookie d'autorisation pour naviguer sur le site public et tester les boutiques même pendant la maintenance.
                        </div>
                        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                            <code style="background:rgba(0,0,0,0.3);padding:.4rem .8rem;border-radius:6px;font-size:.85rem;color:#a5b4fc">
                                {{ url('/' . $secret) }}
                            </code>
                            <a href="{{ url('/' . $secret) }}" target="_blank" class="btn btn-secondary btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                                <x-icon name="external-link" size="14" /> Activer le contournement sur mon navigateur
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Bouton Désactivation -->
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem;background:rgba(0,0,0,0.2);border-radius:12px;border:1px solid rgba(255,255,255,0.06);min-width:240px">
                    <form method="POST" action="{{ route('admin.maintenance.toggle') }}" style="width:100%;margin:0">
                        @csrf
                        <input type="hidden" name="action" value="deactivate">
                        <button type="submit" class="btn btn-ok" style="width:100%;padding:1rem;font-size:1.05rem;font-weight:800;display:flex;align-items:center;justify-content:center;gap:.5rem;box-shadow:0 4px 14px rgba(34, 197, 94, 0.35)">
                            <x-icon name="check" size="20" /> Remettre le Site En Ligne
                        </button>
                    </form>
                    <div style="font-size:.8rem;color:var(--text-soft);text-align:center;margin-top:.75rem">
                        Rétablit immédiatement l'accès à tous les commerçants et clients.
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card" style="padding:2rem;border-left:5px solid #22c55e;background:rgba(34, 197, 94, 0.04);margin-bottom:2rem;border-radius:12px">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1.5rem">
                <div>
                    <div style="display:inline-flex;align-items:center;gap:.5rem;background:#22c55e;color:#ffffff;padding:.3rem .85rem;border-radius:9999px;font-size:.85rem;font-weight:700;margin-bottom:1rem">
                        <span style="display:inline-block;width:8px;height:8px;background:#ffffff;border-radius:50%"></span>
                        SITE EN LIGNE (Production Ouverte)
                    </div>
                    <h2 style="font-size:1.35rem;font-weight:700;margin-bottom:.5rem">
                        La plateforme fonctionne normalement pour tous les utilisateurs.
                    </h2>
                    <p style="color:var(--text-soft);font-size:.95rem;line-height:1.6;max-width:640px">
                        Toutes les vitrines de boutiques, le tunnel de paiement T-Money/Flooz et les espaces d'administration marchands sont 100% opérationnels.
                    </p>
                </div>
            </div>
        </div>

        <!-- Formulaire d'activation de la maintenance -->
        <div class="card" style="padding:2rem;margin-bottom:2rem;border-radius:12px">
            <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem">
                <x-icon name="wrench" size="20" style="color:#f59e0b" />
                Activer le Mode Maintenance
            </h3>
            <p style="color:var(--text-soft);font-size:.9rem;line-height:1.6;margin-bottom:1.5rem">
                Utilisez cette fonction lorsque vous effectuez une mise à niveau importante, une migration de base de données ou une maintenance technique. Les visiteurs verront une page 503 soignée aux couleurs de Zanika.
            </p>

            <form method="POST" action="{{ route('admin.maintenance.toggle') }}" onsubmit="return confirm('Êtes-vous sûr de vouloir couper l\'accès public et basculer tout le site en mode maintenance ?');">
                @csrf
                <input type="hidden" name="action" value="activate">

                <div class="form-group" style="margin-bottom:1.25rem">
                    <label style="font-weight:600;font-size:.9rem;margin-bottom:.4rem;display:block">
                        Message d'information affiché aux visiteurs & commerçants :
                    </label>
                    <textarea name="message" rows="3" class="input" style="width:100%;font-size:.95rem;line-height:1.5" placeholder="Ex: Nous effectuons actuellement une amélioration technique planifiée de la plateforme pour optimiser la rapidité et la fiabilité de vos boutiques.">Nous effectuons actuellement une amélioration technique planifiée de la plateforme pour optimiser la rapidité et la fiabilité de vos boutiques.</textarea>
                    <div style="font-size:.8rem;color:var(--text-soft);margin-top:.25rem">
                        Ce message sera affiché directement sur la page 503 d'attente.
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.5rem">
                    <label style="font-weight:600;font-size:.9rem;margin-bottom:.4rem;display:block">
                        Durée estimée (optionnel) :
                    </label>
                    <input type="text" name="estimated_time" class="input" style="max-width:350px" placeholder="Ex: 30 minutes, 1 heure, jusqu'à 02h00">
                    <div style="font-size:.8rem;color:var(--text-soft);margin-top:.25rem">
                        Indique une estimation rassurante à vos clients.
                    </div>
                </div>

                <div style="padding:1rem;background:rgba(245, 158, 11, 0.08);border:1px solid rgba(245, 158, 11, 0.25);border-radius:8px;font-size:.85rem;color:#fbbf24;margin-bottom:1.5rem">
                    <strong>🛡️ Garantie Super-Admin :</strong> Vos accès à <code>/admin</code> resteront entièrement ouverts et vous pourrez désactiver la maintenance à tout moment en 1 clic.
                </div>

                <button type="submit" class="btn" style="background:linear-gradient(135deg, #ef4444, #dc2626);color:#ffffff;font-weight:800;padding:.85rem 1.75rem;font-size:1rem;border:none;border-radius:8px;box-shadow:0 4px 14px rgba(239, 68, 68, 0.35);cursor:pointer">
                    🔴 Basculer le Site en Mode Maintenance
                </button>
            </form>
        </div>
    @endif

    <!-- Guide d'utilisation pour le fondateur -->
    <div class="card" style="padding:1.5rem;border-radius:12px;background:rgba(255,255,255,0.02)">
        <h4 style="font-size:1rem;font-weight:700;margin-bottom:.75rem">
            ℹ️ Comment fonctionne la maintenance Zanika ?
        </h4>
        <ul style="color:var(--text-soft);font-size:.9rem;line-height:1.7;padding-left:1.25rem;margin:0">
            <li><strong>Protection complète</strong> : Dès l'activation, toutes les requêtes publiques sont arrêtées et redirigées vers la page d'erreur HTTP 503 pré-rendue.</li>
            <li><strong>Accès Super-Admin préservé</strong> : L'espace d'administration (<code>/admin/*</code>) et la connexion (<code>/connexion</code>) sont automatiquement exclus du blocage pour que vous gardiez toujours le contrôle.</li>
            <li><strong>Lien secret de contournement</strong> : Vous disposez d'un lien secret (<code>/zanika-admin-bypass</code>) qui dépose un cookie chiffré dans votre navigateur, vous permettant de parcourir les vitrines publiques comme si la maintenance n'était pas active.</li>
            <li><strong>Commande Terminal CLI</strong> : Vous pouvez également activer la maintenance via la console avec <code>php artisan admin:maintenance on</code> ou <code>php artisan admin:maintenance off</code>.</li>
        </ul>
    </div>
</div>
@endsection
