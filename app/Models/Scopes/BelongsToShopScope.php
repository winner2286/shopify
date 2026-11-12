<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Règle de gestion RG-02 : Isolation des données par boutique.
 * Appliqué automatiquement sur tous les modèles liés à une boutique (Product, Category, Order, etc.)
 */
class BelongsToShopScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Isolation multi-tenant fail-closed : tout utilisateur non-admin est strictement borné à sa boutique.
        if (Auth::check() && ! Auth::user()->isAdmin()) {
            $shopId = Auth::user()->shop?->id ?? -1;
            $builder->where($model->qualifyColumn('shop_id'), $shopId);
        }
    }
}
