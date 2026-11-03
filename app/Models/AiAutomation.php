<?php

namespace App\Models;

use App\Models\Scopes\BelongsToShopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToShopScope::class])]
#[Fillable([
    'shop_id',
    'event_type',
    'name',
    'is_active',
    'config',
    'last_triggered_at',
    'trigger_count',
])]
class AiAutomation extends Model
{
    public const EVENT_LOW_STOCK = 'low_stock';
    public const EVENT_PENDING_COD = 'pending_cod_order';
    public const EVENT_DAILY_SUMMARY = 'daily_summary';
    public const EVENT_ABANDONED_CART = 'abandoned_cart';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
            'last_triggered_at' => 'datetime',
            'trigger_count' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
