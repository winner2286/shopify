<?php

namespace App\Models;

use App\Models\Scopes\BelongsToShopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToShopScope::class])]
#[Fillable([
    'shop_id', 'name', 'price', 'free_shipping_threshold',
    'delivery_estimate', 'is_pickup', 'is_active', 'display_order',
])]
class ShippingZone extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'free_shipping_threshold' => 'integer',
            'is_pickup' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Calcule le montant des frais de livraison pour un sous-total donné
     */
    public function calculateFee(int $subtotal): int
    {
        if ($this->is_pickup) {
            return 0;
        }

        if ($this->free_shipping_threshold && $subtotal >= $this->free_shipping_threshold) {
            return 0;
        }

        return (int) $this->price;
    }
}
