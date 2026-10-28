<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shop_id', 'plan_id', 'status', 'starts_at', 'trial_ends_at', 'renews_at', 'ends_at'])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'renews_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
