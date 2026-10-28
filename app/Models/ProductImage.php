<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'path', 'thumbnail_path', 'sort_order', 'size_bytes', 'image_data'])]
class ProductImage extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        if (empty($this->path)) {
            return '';
        }

        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        return '/storage/' . ltrim($this->path, '/');
    }

    public function getThumbnailUrlAttribute(): string
    {
        $thumb = $this->thumbnail_path ?: $this->path;
        if (empty($thumb)) {
            return '';
        }

        if (str_starts_with($thumb, 'http://') || str_starts_with($thumb, 'https://')) {
            return $thumb;
        }

        return '/storage/' . ltrim($thumb, '/');
    }
}
