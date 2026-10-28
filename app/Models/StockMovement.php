<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'user_id', 'quantity_change', 'new_stock', 'reason', 'comment'])]
class StockMovement extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'new_stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
