<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'monthly_price', 'max_products', 'max_shops', 'storage_mb', 'ai_credits', 'photoroom_credits', 'studio_scene_credits', 'full_generations', 'features', 'active'])]
class Plan extends Model
{
    public const TRIAL_DAYS = 14;

    public const STANDARD_PLANS = [
        'essentiel' => ['name' => 'Essentiel', 'monthly_price' => 7500, 'max_products' => 50, 'max_shops' => 1, 'storage_mb' => 2048, 'ai_credits' => 80, 'photoroom_credits' => 25, 'studio_scene_credits' => 3, 'full_generations' => 1],
        'pro' => ['name' => 'Pro', 'monthly_price' => 15000, 'max_products' => 300, 'max_shops' => 3, 'storage_mb' => 8192, 'ai_credits' => 350, 'photoroom_credits' => 60, 'studio_scene_credits' => 20, 'full_generations' => 2],
        'premium' => ['name' => 'Premium', 'monthly_price' => 30000, 'max_products' => 2500, 'max_shops' => 5, 'storage_mb' => 25600, 'ai_credits' => 1200, 'photoroom_credits' => 180, 'studio_scene_credits' => 60, 'full_generations' => 5],
    ];

    protected function casts(): array
    {
        return ['features' => 'array', 'active' => 'boolean', 'max_shops' => 'integer', 'photoroom_credits' => 'integer', 'studio_scene_credits' => 'integer'];
    }

    public static function defaultEssentiel(): self
    {
        return static::where('slug', 'essentiel')->first()
            ?? new self(['slug' => 'essentiel', 'active' => true] + self::STANDARD_PLANS['essentiel']);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function maxShops(): int
    {
        if ($this->max_shops !== null && $this->max_shops > 0) {
            return (int) $this->max_shops;
        }

        return self::STANDARD_PLANS[$this->slug]['max_shops'] ?? 1;
    }

    public function isUnlimitedShops(): bool
    {
        return $this->maxShops() >= 999;
    }

    /**
     * Quota mensuel dédié Photoroom Studio (détourage & packshots v2/edit).
     */
    public function photoroomCreditsMonthly(): int
    {
        if ($this->photoroom_credits !== null) {
            return max(0, (int) $this->photoroom_credits);
        }

        return self::STANDARD_PLANS[$this->slug]['photoroom_credits'] ?? self::STANDARD_PLANS['essentiel']['photoroom_credits'];
    }

    public function studioScenesMonthly(): int
    {
        return max(0, $this->studio_scene_credits ?? self::STANDARD_PLANS[$this->slug]['studio_scene_credits'] ?? 0);
    }

    public function supportsGenerativeScenes(): bool
    {
        return in_array(strtolower((string) $this->slug), ['pro', 'premium'], true);
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
            'export_accounting' => $this->slug === 'premium',
            default => false,
        };
    }

    /** @return list<string> */
    public function includedFeatures(): array
    {
        $features = [
            'Boutique en ligne, catalogue et gestion des commandes',
            'Partage de la boutique et commandes via WhatsApp',
            'Recadrage, retouche et décors du studio',
            'Textes et accroches avec l’IA',
            'Tableau de bord des ventes',
        ];
        foreach ([
            'promo_codes' => 'Codes promo et remises programmées',
            'ai_visuals' => 'Campagnes, affiches et montage vidéo publicitaires',
            'stock_alerts' => 'Alertes de rupture de stock dans Zanika',
            'advanced_stats' => 'Statistiques avancées et produits les plus vendus',
            'export_accounting' => 'Exports CSV des commandes et des statistiques, compatibles Excel',
        ] as $capability => $label) {
            if ($this->canAccessFeature($capability)) {
                $features[] = $label;
            }
        }

        return $features;
    }
}
