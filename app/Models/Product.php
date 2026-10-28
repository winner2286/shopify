<?php

namespace App\Models;

use App\Models\Scopes\BelongsToShopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ScopedBy([BelongsToShopScope::class])]
#[Fillable([
    'shop_id', 'title', 'slug', 'description', 'price', 'compare_at_price',
    'stock', 'track_stock', 'low_stock_threshold', 'status', 'sku',
    'attributes', 'created_via', 'ai_confidence',
])]
class Product extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_HIDDEN = 'hidden';
    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'stock' => 'integer',
            'track_stock' => 'boolean',
            'low_stock_threshold' => 'integer',
            'attributes' => 'array',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany('sort_order', 'min');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock > 0 && $this->stock <= $this->low_stock_threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->track_stock && $this->stock <= 0;
    }

    /**
     * Ajustement de stock avec enregistrement obligatoire de traçabilité (C-04)
     */
    public function adjustStock(int $change, string $reason, ?string $comment = null, ?int $userId = null): void
    {
        if (! $this->track_stock) {
            return;
        }

        $newStock = $this->stock + $change;
        $this->update(['stock' => $newStock]);

        $this->stockMovements()->create([
            'user_id' => $userId,
            'quantity_change' => $change,
            'new_stock' => $newStock,
            'reason' => $reason,
            'comment' => $comment,
        ]);
    }
}
