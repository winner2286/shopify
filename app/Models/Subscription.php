<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable(['shop_id', 'plan_id', 'monthly_price', 'status', 'starts_at', 'trial_ends_at', 'trial_reminder_sent_at', 'renews_at', 'ends_at'])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'trial_reminder_sent_at' => 'datetime',
            'renews_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function monthlyPriceFor(Plan $plan): int
    {
        if ((int) $this->plan_id === (int) $plan->id && $this->monthly_price !== null) {
            return $this->monthly_price;
        }

        return (int) $plan->monthly_price;
    }

    /** @param Collection<int, Subscription|null> $subscriptions */
    public static function governingSubscription(Collection $subscriptions): ?self
    {
        return $subscriptions->filter(fn (?self $subscription): bool => $subscription?->plan?->active && $subscription->grantsAccess())
            ->sortByDesc(fn (self $subscription): array => [! $subscription->isTrial(), $subscription->updated_at?->getTimestamp(), $subscription->id])
            ->first();
    }

    public function synchronizeAccount(): void
    {
        $shop = $this->shop;
        if (! $shop?->user_id) {
            return;
        }

        $terms = $this->only(['plan_id', 'monthly_price', 'status', 'starts_at', 'trial_ends_at', 'renews_at', 'ends_at']);
        foreach (Shop::where('user_id', $shop->user_id)->whereKeyNot($shop->id)->with('subscription')->get() as $otherShop) {
            if ($otherShop->subscription) {
                $otherShop->subscription->update($terms);
            } else {
                $otherShop->subscriptions()->create($terms);
            }
        }
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    public function grantsAccess(): bool
    {
        if (! in_array($this->status, ['active', 'trial', 'grace'], true)) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && ! $this->ends_at->isFuture()) {
            return false;
        }

        if ($this->isTrial()) {
            return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
        }

        // Existing manually managed active subscriptions may have no end date.
        // Grace periods, however, must always have an explicit deadline.
        return $this->status !== 'grace' || $this->ends_at !== null;
    }

    public function isExpiringSoon(int $days = 3): bool
    {
        if (! $this->isTrial() || ! $this->trial_ends_at) {
            return false;
        }

        return $this->trial_ends_at->isFuture() && (int) ceil(now()->floatDiffInDays($this->trial_ends_at, false)) <= $days;
    }

    public function daysRemaining(): int
    {
        if (! $this->trial_ends_at || ! $this->trial_ends_at->isFuture()) {
            return 0;
        }

        return max(1, (int) ceil(now()->floatDiffInDays($this->trial_ends_at, false)));
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
