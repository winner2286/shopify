<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'category',
    'title',
    'slug',
    'content',
    'keywords',
    'target_routes',
    'is_active',
    'sort_order',
    'metadata',
])]
class AiKnowledgeChunk extends Model
{
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
