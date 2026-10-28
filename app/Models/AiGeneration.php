<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shop_id', 'type', 'step', 'model_used', 'prompt_tokens',
    'completion_tokens', 'cost_estimated_fcfa', 'duration_ms',
    'status', 'input_summary', 'output_data',
])]
class AiGeneration extends Model
{
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cost_estimated_fcfa' => 'integer',
            'duration_ms' => 'integer',
            'output_data' => 'array',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
