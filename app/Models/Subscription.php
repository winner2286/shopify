<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shop_id', 'plan_id', 'status', 'starts_at', 'trial_ends_at', 'trial_reminder_sent_at', 'renews_at', 'ends_at'])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'trial_reminder_sent_at' => 'datetime',
            'renews_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    public function isExpiringSoon(int $days = 3): bool
    {
        if (! $this->isTrial() || ! $this->trial_ends_at) {
            return false;
        }

        return $this->trial_ends_at->isFuture() && now()->diffInDays($this->trial_ends_at, false) <= $days;
    }

    public function daysRemaining(): int
    {
        if (! $this->trial_ends_at || ! $this->trial_ends_at->isFuture()) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInHours($this->trial_ends_at, false) / 24));
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
