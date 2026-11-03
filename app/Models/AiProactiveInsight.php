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
    'insight_type',
    'severity',
    'title',
    'explanation',
    'suggested_action',
    'is_dismissed',
    'is_resolved',
    'resolved_at',
])]
class AiProactiveInsight extends Model
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_OPPORTUNITY = 'opportunity';
    public const SEVERITY_INFO = 'info';

    protected function casts(): array
    {
        return [
            'suggested_action' => 'array',
            'is_dismissed' => 'boolean',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
