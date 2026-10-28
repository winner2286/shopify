<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'from_status', 'to_status', 'author_type', 'author_id', 'comment',
])]
class OrderStatusHistory extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
