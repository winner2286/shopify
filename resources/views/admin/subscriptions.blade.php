@extends('layouts.app')

@section('title', 'Gestion des Abonnements & Revenus : Super-Admin - '.config('platform.name'))

@section('content')
<div class="container" style="max-width:1160px;padding:2rem 1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
        <div>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
                <x-icon name="credit-card" size="24" style="color:var(--ok)" />
                <h1 style="font-size:1.8rem;margin:0">Abonnements & Revenus SaaS</h1>
            </div>
            <p style="margin:0;color:var(--text-soft)">Supervision des formules souscrites, des essais gratuits 14 jours et des encaissements de la plateforme.</p>
        </div>

        <div class="admin-nav-pills">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="zap" size="15" /> Dashboard
            </a>
            <a href="{{ route('admin.shops') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:.3rem">
                <x-icon name="shop" size="15" /> Boutiques
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

    <!-- Indicateurs Clés Abonnements -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;margin-bottom:2rem">
        <div class="card" style="padding:1.25rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Abonnés Actifs (Payants)</div>
            <div style="font-size:1.85rem;font-weight:700;font-family:var(--font-display);color:var(--ok);margin:.2rem 0">{{ $totalActive }}</div>
            <div style="font-size:.8rem;color:var(--text-soft)">Commerçants convertis</div>
        </div>

        <div class="card" style="padding:1.25rem">
            <div style="font-size:.85rem;color:var(--text-soft)">En Essai Gratuit (14 jours)</div>
            <div style="font-size:1.85rem;font-weight:700;font-family:var(--font-display);color:#f59e0b;margin:.2rem 0">{{ $totalTrial }}</div>
            <div style="font-size:.8rem;color:var(--text-soft)">Prospects à convertir</div>
        </div>

        <div class="card" style="padding:1.25rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Essais Expirés / Sans Plan</div>
            <div style="font-size:1.85rem;font-weight:700;font-family:var(--font-display);color:var(--danger);margin:.2rem 0">{{ $totalExpired }}</div>
            <div style="font-size:.8rem;color:var(--text-soft)">Boutiques à relancer</div>
        </div>

        <div class="card" style="padding:1.25rem">
            <div style="font-size:.85rem;color:var(--text-soft)">Revenu Mensuel Récurrent (MRR)</div>
            <div style="font-size:1.65rem;font-weight:700;font-family:var(--font-display)" class="gradient-text">{{ number_format($mrrEstimated, 0, ',', ' ') }} FCFA</div>
            <div style="font-size:.8rem;color:var(--text-soft)">Chiffre d'affaires mensuel prévisionnel</div>
        </div>
    </div>

    <!-- Filtres & Recherche -->
    <div class="card" style="margin-bottom:1.5rem;padding:1rem">
        <form method="GET" action="{{ route('admin.subscriptions') }}" style="display:flex;gap:.75rem;flex-wrap:wrap">
            <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Rechercher par nom de boutique ou commerçant..." class="input" style="flex:1;min-width:240px">
            
            <select name="status" class="select" style="min-width:180px">
                <option value="">Tous les statuts</option>
                <option value="trial" @selected(($statusFilter ?? '') === 'trial')>En essai gratuit</option>
                <option value="active" @selected(($statusFilter ?? '') === 'active')>Actif (Payant)</option>
                <option value="expired" @selected(($statusFilter ?? '') === 'expired')>Expiré</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
            @if (!empty($search) || !empty($statusFilter))
                <a href="{{ route('admin.subscriptions') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
            @endif
        </form>
    </div>

    <!-- Tableau des Boutiques et Abonnements -->
    <div class="table-wrap" style="margin-bottom:1.5rem">
        <table class="table" style="width:100%;min-width:850px;border-collapse:collapse;font-size:.92rem">
            <thead>
                <tr style="background:var(--bg-soft);border-bottom:1px solid var(--border);text-align:left">
                    <th style="padding:1rem">Boutique & Marchand</th>
                    <th style="padding:1rem">Formule Actuelle</th>
                    <th style="padding:1rem">Statut Abonnement</th>
                    <th style="padding:1rem">Échéance / Fin d'essai</th>
                    <th style="padding:1rem;text-align:right">Action Fondateur</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shops as $shop)
                    @php
                        $sub = $shop->subscription;
                        $plan = $sub?->plan;
                        $trialEnds = $sub?->trial_ends_at;
                        $daysLeft = $trialEnds ? now()->diffInDays($trialEnds, false) : null;
                    @endphp
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:1rem">
                            <div style="font-weight:700;font-size:.98rem">
                                {{ $shop->name ?: 'Sans nom' }}
                                <a href="{{ url('/b/' . $shop->subdomain) }}" target="_blank" style="font-size:.8rem;color:var(--text-soft);margin-left:.4rem;text-decoration:underline">/b/{{ $shop->subdomain }}</a>
                            </div>
                            <div style="font-size:.82rem;color:var(--text-soft);margin-top:.2rem">
                                {{ $shop->owner?->name }} • {{ $shop->owner?->email }}
                                @if ($shop->owner?->phone)
                                    • <a href="https://wa.me/{{ preg_replace('/\D/', '', $shop->owner->phone) }}" target="_blank" style="color:#25D366;font-weight:600">WhatsApp</a>
                                @endif
                            </div>
                        </td>

                        <td style="padding:1rem">
                            @if ($plan)
                                <span class="badge" style="background:rgba(109,61,242,0.15);color:var(--brand-1);font-weight:700;border:1px solid rgba(109,61,242,0.3)">
                                    {{ $plan->name }}
                                </span>
                            @else
                                <span class="badge badge-draft">Essai Sans Plan</span>
                            @endif
                        </td>

                        <td style="padding:1rem">
                            @if ($sub && $sub->status === 'active')
                                <span class="badge badge-ok" style="font-size:.78rem">Actif (Payant)</span>
                            @elseif ($sub && $sub->status === 'trial')
                                <span class="badge badge-warn" style="font-size:.78rem;background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid rgba(245,158,11,0.3)">
                                    Essai 14j
                                </span>
                            @elseif ($sub && $sub->status === 'expired')
                                <span class="badge" style="background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);font-size:.78rem">
                                    Expiré
                                </span>
                            @else
                                <span class="badge badge-draft" style="font-size:.78rem">Inactif</span>
                            @endif
                        </td>

                        <td style="padding:1rem">
                            @if ($trialEnds)
                                <div style="font-size:.85rem;font-weight:600">
                                    {{ $trialEnds->format('d/m/Y') }}
                                </div>
                                @if ($sub->status === 'trial')
                                    @if ($daysLeft > 0)
                                        <div style="font-size:.75rem;color:{{ $daysLeft <= 3 ? '#ef4444' : '#f59e0b' }};font-weight:600">
                                            {{ (int) $daysLeft }} jour(s) restant(s)
                                        </div>
                                    @else
                                        <div style="font-size:.75rem;color:var(--danger);font-weight:600">
                                            Terminé il y a {{ abs((int)$daysLeft) }} j
                                        </div>
                                    @endif
                                @endif
                                @if ($sub->trial_reminder_sent_at)
                                    <div style="font-size:.72rem;color:var(--text-soft);margin-top:.2rem;display:inline-flex;align-items:center;gap:.25rem">
                                        <span>✉️ Rappel envoyé le {{ $sub->trial_reminder_sent_at->format('d/m') }}</span>
                                    </div>
                                @endif
                            @else
                                <span style="color:var(--text-soft);font-size:.85rem">—</span>
                            @endif
                        </td>

                        <td style="padding:1rem;text-align:right;white-space:nowrap">
                            @if ($sub && $sub->status === 'trial')
                                <form method="POST" action="{{ route('admin.subscriptions.send_reminder', $shop) }}" style="display:inline-block;margin-right:.4rem" onsubmit="return confirm('Envoyer immédiatement l\'email de rappel J-3 à {{ addslashes($shop->owner?->email ?? '') }} ?')">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Envoyer email rappel d'essai J-3 au marchand" style="display:inline-flex;align-items:center;gap:.3rem;color:#f59e0b;border:1px solid rgba(245,158,11,0.3)">
                                        <x-icon name="send" size="13" />
                                        <span>Rappel J-3</span>
                                    </button>
                                </form>
                            @endif

                            <!-- Formulaire de modification rapide d'abonnement -->
                            <details style="position:relative;display:inline-block;text-align:left">
                                <summary class="btn btn-ghost btn-sm" style="cursor:pointer;list-style:none;display:inline-flex;align-items:center;gap:.3rem">
                                    <x-icon name="sliders" size="14" />
                                    <span>Gérer Plan</span>
                                </summary>
                                <div style="position:absolute;right:0;top:100%;margin-top:.4rem;width:280px;background:var(--surface, #1a1829);border:1px solid var(--border);border-radius:12px;padding:1.2rem;box-shadow:0 15px 35px rgba(0,0,0,0.4);z-index:99">
                                    <h4 style="margin:0 0 .8rem;font-size:.95rem">Modifier l'abonnement</h4>
                                    <form method="POST" action="{{ route('admin.subscriptions.update', $shop) }}">
                                        @csrf
                                        <div style="margin-bottom:.7rem">
                                            <label style="font-size:.8rem;color:var(--text-soft);display:block;margin-bottom:.2rem">Formule :</label>
                                            <select name="plan_id" class="select" style="width:100%;font-size:.85rem;padding:.4rem">
                                                @foreach ($plans as $p)
                                                    <option value="{{ $p->id }}" @selected($sub && $sub->plan_id == $p->id)>{{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div style="margin-bottom:.7rem">
                                            <label style="font-size:.8rem;color:var(--text-soft);display:block;margin-bottom:.2rem">Statut :</label>
                                            <select name="status" class="select" style="width:100%;font-size:.85rem;padding:.4rem">
                                                <option value="trial" @selected($sub && $sub->status === 'trial')>Essai gratuit</option>
                                                <option value="active" @selected($sub && $sub->status === 'active')>Actif (Payé)</option>
                                                <option value="expired" @selected($sub && $sub->status === 'expired')>Expiré</option>
                                                <option value="cancelled" @selected($sub && $sub->status === 'cancelled')>Résilié</option>
                                            </select>
                                        </div>

                                        <div style="margin-bottom:1rem">
                                            <label style="font-size:.8rem;color:var(--text-soft);display:block;margin-bottom:.2rem">Ajouter des jours d'essai :</label>
                                            <input type="number" name="extend_days" min="0" max="365" placeholder="Ex: 14" class="input" style="width:100%;font-size:.85rem;padding:.4rem">
                                        </div>

                                        <button type="submit" class="btn btn-primary btn-sm btn-block">Enregistrer</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;padding:2.5rem;color:var(--text-soft)">
                            Aucune boutique trouvée.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:2rem;display:flex;justify-content:center">
        {{ $shops->links() }}
    </div>
</div>
@endsection
