<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'monthly_price', 'max_products', 'storage_mb', 'ai_credits', 'full_generations', 'features', 'active'])]
class Plan extends Model
{
    protected function casts(): array
    {
        return ['features' => 'array', 'active' => 'boolean'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
