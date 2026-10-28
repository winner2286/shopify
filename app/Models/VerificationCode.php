<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'purpose', 'code_hash', 'attempts', 'expires_at', 'used_at'])]
class VerificationCode extends Model
{
    public const MAX_ATTEMPTS = 5;
    public const TTL_MINUTES = 10;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < self::MAX_ATTEMPTS;
    }
}
