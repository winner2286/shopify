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
    'category',
    'memory_key',
    'memory_value',
    'confidence',
    'source',
    'last_recalled_at',
])]
class AiShopMemory extends Model
{
    public const CAT_PREFERENCE = 'merchant_preference';
    public const CAT_PROFILE = 'business_profile';
    public const CAT_GOAL = 'commercial_goal';
    public const CAT_HABIT = 'operational_habit';
    public const CAT_RECOMMENDATION = 'past_recommendation';

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'last_recalled_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
