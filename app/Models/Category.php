<?php

namespace App\Models;

use App\Models\Scopes\BelongsToShopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ScopedBy([BelongsToShopScope::class])]
#[Fillable(['shop_id', 'name', 'slug', 'display_order', 'is_visible'])]
class Category extends Model
{
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
