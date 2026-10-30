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

    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin-bottom:1.5rem">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-err" role="alert" style="margin-bottom:1.5rem">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.5rem">
        @foreach ($plans as $p)
            <div class="card" style="padding:1.8rem;display:flex;flex-direction:column;justify-content:space-between;border:1px solid {{ $p->active ? 'var(--border)' : 'rgba(239,68,68,0.4)' }};background:{{ $p->active ? 'var(--surface)' : 'rgba(239,68,68,0.02)' }}">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.8rem">
                        <div>
                            <h2 style="font-size:1.5rem;margin:0 0 .3rem;display:flex;align-items:center;gap:.5rem">
                                {{ $p->name }}
                                @if ($p->slug === 'pro')
                                    <span style="font-size:.7rem;padding:.2rem .5rem;background:var(--ok);color:#fff;border-radius:20px;font-weight:700">POPULAIRE</span>
                                @endif
                            </h2>
                            <span style="font-family:monospace;font-size:.8rem;color:var(--text-soft)">slug: {{ $p->slug }}</span>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.35rem">
                            <span class="badge {{ $p->active ? 'badge-ok' : 'badge-danger' }}" style="font-size:.78rem">
                                {{ $p->active ? '🟢 Active' : '🔴 Désactivée' }}
                            </span>
                            <span class="badge" style="font-size:.72rem;background:var(--bg-soft);color:var(--text-soft)">
                                {{ $p->subscriptions_count }} boutique(s)
                            </span>
                        </div>
                    </div>

                    <div style="margin:1.2rem 0;padding:.8rem;background:var(--bg-soft);border-radius:10px">
                        <div style="display:flex;align-items:baseline;gap:.4rem">
                            <span style="font-size:2rem;font-weight:800;font-family:var(--font-display);color:var(--text)">
                                {{ number_format($p->monthly_price, 0, ',', ' ') }}
                            </span>
                            <span style="color:var(--text-soft);font-size:.88rem">FCFA / mois</span>
                        </div>
                        <div style="font-size:.8rem;color:var(--text-faint);margin-top:.2rem">
                            Annuel (-20%) : {{ number_format(round($p->monthly_price * 0.8), 0, ',', ' ') }} FCFA / mois
                        </div>
                    </div>

                    <div style="border-top:1px solid var(--border);padding-top:1rem;display:flex;flex-direction:column;gap:.55rem;font-size:.88rem">
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <span style="display:inline-flex;align-items:center;gap:.4rem;color:var(--text-soft)"><x-icon name="package" size="15" /> Max Produits :</span>
                            <strong>{{ number_format($p->max_products, 0, ',', ' ') }}</strong>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <span style="display:inline-flex;align-items:center;gap:.4rem;color:var(--text-soft)"><x-icon name="image" size="15" /> Stockage Photos :</span>
                            <strong>{{ $p->storage_mb >= 1024 ? ($p->storage_mb / 1024).' Go' : $p->storage_mb.' Mo' }}</strong>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <span style="display:inline-flex;align-items:center;gap:.4rem;color:var(--text-soft)"><x-icon name="sparkles" size="15" /> Crédits IA / mois :</span>
                            <strong style="color:var(--brand-primary)">{{ $p->ai_credits }} crédits</strong>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <span style="display:inline-flex;align-items:center;gap:.4rem;color:var(--text-soft)"><x-icon name="refresh" size="15" /> Générations complètes :</span>
                            <strong>{{ $p->full_generations }}</strong>
                        </div>
                    </div>

                    @if (!empty($p->features) && count($p->features) > 0)
                        <div style="margin-top:1rem;padding-top:.8rem;border-top:1px dashed var(--border)">
                            <div style="font-size:.78rem;font-weight:700;color:var(--text-soft);margin-bottom:.4rem;text-transform:uppercase">Avantages inclus :</div>
                            <ul style="list-style:none;padding:0;margin:0;font-size:.82rem;display:flex;flex-direction:column;gap:.35rem">
                                @foreach ($p->features as $feat)
                                    <li style="display:flex;align-items:center;gap:.4rem">
                                        <x-icon name="check" size="13" style="color:var(--ok);flex-shrink:0" />
                                        <span>{{ $feat }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div style="margin-top:1.5rem;border-top:1px solid var(--border);padding-top:1rem;display:flex;gap:.6rem;flex-wrap:wrap">
                    <!-- Bouton Activer / Désactiver -->
                    <form method="POST" action="{{ route('admin.plans.toggle', $p) }}" style="flex:1">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-block" style="{{ $p->active ? 'background:rgba(239,68,68,0.12);color:var(--danger);border:1px solid rgba(239,68,68,0.3)' : 'background:rgba(16,185,129,0.12);color:var(--ok);border:1px solid rgba(16,185,129,0.3)' }}">
                            {{ $p->active ? 'Désactiver' : 'Activer' }}
                        </button>
                    </form>

                    <!-- Bouton Modifier -->
                    <button type="button" class="btn btn-ghost btn-sm" style="border:1px solid var(--border);display:inline-flex;align-items:center;gap:.3rem" onclick="document.getElementById('edit-plan-modal-{{ $p->id }}').classList.add('open')">
                        <x-icon name="edit" size="14" />
                        <span>Modifier</span>
                    </button>
                </div>
            </div>

            <!-- Modal d'édition de la formule -->
            <div id="edit-plan-modal-{{ $p->id }}" class="admin-modal" aria-hidden="true">
                <div class="admin-modal-backdrop" onclick="document.getElementById('edit-plan-modal-{{ $p->id }}').classList.remove('open')"></div>
                <div class="admin-modal-dialog">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
                        <h3 style="margin:0;font-size:1.25rem;display:flex;align-items:center;gap:.5rem">
                            <x-icon name="edit" size="18" style="color:var(--brand-primary)" />
                            Modifier la formule : {{ $p->name }}
                        </h3>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('edit-plan-modal-{{ $p->id }}').classList.remove('open')">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('admin.plans.update', $p) }}">
                        @csrf
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
                            <div>
                                <label class="label">Nom de la formule *</label>
                                <input type="text" name="name" class="input" value="{{ old('name', $p->name) }}" required>
                            </div>
                            <div>
                                <label class="label">Prix mensuel (FCFA) *</label>
                                <input type="number" name="monthly_price" class="input" value="{{ old('monthly_price', $p->monthly_price) }}" required min="0" step="500">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
                            <div>
                                <label class="label">Limite de produits en catalogue *</label>
                                <input type="number" name="max_products" class="input" value="{{ old('max_products', $p->max_products) }}" required min="1">
                            </div>
                            <div>
                                <label class="label">Stockage photos (en Mo) *</label>
                                <input type="number" name="storage_mb" class="input" value="{{ old('storage_mb', $p->storage_mb) }}" required min="100">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
                            <div>
                                <label class="label">Crédits d'IA par mois *</label>
                                <input type="number" name="ai_credits" class="input" value="{{ old('ai_credits', $p->ai_credits) }}" required min="0">
                            </div>
                            <div>
                                <label class="label">Générations complètes boutique *</label>
                                <input type="number" name="full_generations" class="input" value="{{ old('full_generations', $p->full_generations) }}" required min="1">
                            </div>
                        </div>

                        <div style="margin-bottom:1rem">
                            <label class="label">Liste des fonctionnalités & avantages (1 ligne par point)</label>
                            <textarea name="features" class="input" rows="4" placeholder="Ex: Codes promo, Support WhatsApp...">{{ is_array($p->features) ? implode("\n", $p->features) : '' }}</textarea>
                        </div>

                        <div style="margin-bottom:1.5rem;display:flex;align-items:center;gap:.6rem;background:var(--bg-soft);padding:.75rem;border-radius:8px">
                            <input type="checkbox" id="active-{{ $p->id }}" name="active" value="1" @checked($p->active) style="width:18px;height:18px;cursor:pointer">
                            <label for="active-{{ $p->id }}" style="margin:0;cursor:pointer;font-weight:600;font-size:.9rem">
                                Formule active et visible sur le site public
                            </label>
                        </div>

                        <div style="display:flex;justify-content:flex-end;gap:.75rem">
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('edit-plan-modal-{{ $p->id }}').classList.remove('open')">
                                Annuler
                            </button>
                            <button type="submit" class="btn btn-primary">
                                Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>

<style>
.admin-modal { display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; padding: 1rem; }
.admin-modal.open { display: flex !important; }
.admin-modal-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(6px); }
.admin-modal-dialog { position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: 16px; max-width: 580px; width: 100%; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); z-index: 1; max-height: 90vh; overflow-y: auto; }
</style>
@endsection
