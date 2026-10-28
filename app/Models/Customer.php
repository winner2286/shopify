<?php

namespace App\Models;

use App\Models\Scopes\BelongsToShopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToShopScope::class])]
#[Fillable([
    'shop_id', 'name', 'phone', 'email', 'city', 'address',
    'total_orders_count', 'total_spent_fcfa', 'last_order_at',
])]
class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'total_orders_count' => 'integer',
            'total_spent_fcfa' => 'integer',
            'last_order_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }
}
