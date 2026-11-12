@extends('layouts.app')

@section('title', 'Codes Promo : '.config('platform.name'))

@section('content')
<div class="container dash">
    <div class="dash-head">
        <div>
            <p style="margin:0"><a href="{{ route('products.index') }}" style="display:inline-flex;align-items:center;gap:.3rem"><x-icon name="arrow-left" size="14" /> Retour aux Produits</a></p>
            <h1>Codes Promo &amp; Réductions</h1>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-err" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-err" role="alert">
            <ul style="margin:0;padding-left:1.2rem">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless ($canManageDiscounts)
        <div class="alert alert-err" role="alert" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <span>Les codes promo sont disponibles à partir de la formule <strong>Pro</strong>.</span>
            <a href="{{ route('subscription.plans') }}" class="btn btn-primary btn-sm">Passer au forfait Pro</a>
        </div>
    @endunless

    <div class="categories-layout">
        <section class="card panel">
            <h3>Créer un code promo</h3>
            <form method="POST" action="{{ route('discounts.store') }}">
                @csrf
                <div class="field">
                    <label for="code">Code (majuscules sans espace)</label>
                    <input id="code" name="code" type="text" class="input" value="{{ old('code') }}" placeholder="Ex: NOEL15, BIENVENUE500" style="text-transform:uppercase" required @disabled(! $canManageDiscounts)>
                </div>

                <div class="field">
                    <label for="type">Type de réduction</label>
                    <select id="type" name="type" class="input" required @disabled(! $canManageDiscounts)>
                        <option value="percentage" {{ old('type', 'percentage') === 'percentage' ? 'selected' : '' }}>Pourcentage (%)</option>
                        <option value="fixed" {{ old('type') === 'fixed' ? 'selected' : '' }}>Montant fixe (FCFA)</option>
                    </select>
                </div>

                <div class="field">
                    <label for="value">Valeur (% ou FCFA)</label>
                    <input id="value" name="value" type="number" class="input" value="{{ old('value') }}" min="1" placeholder="Ex: 15 ou 2000" required @disabled(! $canManageDiscounts)>
                </div>

                <div class="field">
                    <label for="min_order_amount">Minimum d'achat (FCFA, optionnel)</label>
                    <input id="min_order_amount" name="min_order_amount" type="number" class="input" value="{{ old('min_order_amount') }}" min="0" placeholder="Ex: 10000" @disabled(! $canManageDiscounts)>
                </div>

                <div class="field">
                    <label for="max_uses">Limite d'utilisations (optionnel)</label>
                    <input id="max_uses" name="max_uses" type="number" class="input" value="{{ old('max_uses') }}" min="1" placeholder="Ex: 50" @disabled(! $canManageDiscounts)>
                </div>

                <div class="field">
                    <label for="starts_at">Début de validité (optionnel)</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" class="input" value="{{ old('starts_at') }}" @disabled(! $canManageDiscounts)>
                </div>

                <div class="field">
                    <label for="expires_at">Date d'expiration (optionnel)</label>
                    <input id="expires_at" name="expires_at" type="datetime-local" class="input" value="{{ old('expires_at') }}" @disabled(! $canManageDiscounts)>
                </div>

                <button type="submit" class="btn btn-primary btn-block" @disabled(! $canManageDiscounts)>Créer le code promo</button>
            </form>
        </section>

        <section class="card panel">
            <h3>Mes Codes Promo ({{ $discounts->count() }})</h3>
            @if ($discounts->isEmpty())
                <div class="empty">
                    <div style="display:flex;justify-content:center;margin-bottom:1rem;color:var(--text-soft)" aria-hidden="true">
                        <x-icon name="tag" size="48" />
                    </div>
                    Aucun code promo créé pour l'instant.<br>
                    Créez un code de réduction pour booster vos conversions au panier.
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Réduction</th>
                                <th>Utilisations</th>
                                <th>Validité</th>
                                <th>Statut</th>
                                <th style="text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($discounts as $discount)
                                <tr>
                                    <td><strong>{{ $discount->code }}</strong></td>
                                    <td>
                                        {{ $discount->type === 'percentage' ? $discount->value . '%' : number_format($discount->value, 0, ',', ' ') . ' FCFA' }}
                                        @if ($discount->min_order_amount)
                                            <div style="font-size:.75rem;color:var(--text-soft)">Min. {{ number_format($discount->min_order_amount, 0, ',', ' ') }} FCFA</div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $discount->used_count }}{{ $discount->max_uses ? ' / ' . $discount->max_uses : ' (illimité)' }}
                                    </td>
                                    <td style="font-size:.82rem;color:var(--text-soft)">
                                        @if ($discount->expires_at)
                                            Jusqu'au {{ $discount->expires_at->format('d/m/Y H:i') }}
                                        @else
                                            Sans expiration
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge-status {{ $discount->is_active ? 'badge-active' : 'badge-hidden' }}">
                                            {{ $discount->is_active ? 'Actif' : 'Inactif' }}
                                        </span>
                                    </td>
                                    <td style="text-align:right;white-space:nowrap">
                                        @if ($canManageDiscounts)
                                            <form method="POST" action="{{ route('discounts.toggle', $discount) }}" style="display:inline">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-sm">
                                                    {{ $discount->is_active ? 'Désactiver' : 'Activer' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('discounts.destroy', $discount) }}" style="display:inline" data-confirm="Supprimer définitivement le code promo « {{ $discount->code }} » ?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">Supprimer</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection

