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
        // Si l'utilisateur connecté est un commerçant ayant une boutique, on filtre obligatoirement sur sa boutique.
        if (Auth::check() && Auth::user()->isMerchant() && Auth::user()->shop) {
            $builder->where($model->qualifyColumn('shop_id'), Auth::user()->shop->id);
        }
    }
}
