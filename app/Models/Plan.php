<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'monthly_price', 'max_products', 'storage_mb', 'ai_credits', 'full_generations', 'features', 'active'])]
class Plan extends Model
{
    protected function casts(): array
    {
        return ['features' => 'array', 'active' => 'boolean'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isEssentiel(): bool
    {
        return $this->slug === 'essentiel';
    }

    public function isPro(): bool
    {
        return $this->slug === 'pro';
    }

    public function isPremium(): bool
    {
        return $this->slug === 'premium';
    }

    public function canAccessFeature(string $feature): bool
    {
        return match ($feature) {
            'promo_codes', 'discounts' => in_array($this->slug, ['pro', 'premium'], true),
            'ai_scripts', 'ai_visuals', 'tiktok_ads' => in_array($this->slug, ['pro', 'premium'], true),
            'stock_alerts' => in_array($this->slug, ['pro', 'premium'], true),
            'advanced_stats' => in_array($this->slug, ['pro', 'premium'], true),
            'team_members', 'collaborators' => $this->slug === 'premium',
            'custom_domain' => $this->slug === 'premium',
            'export_accounting' => $this->slug === 'premium',
            default => true,
        };
    }
}
