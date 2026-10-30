<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'name', 'subdomain', 'status', 'onboarding_step', 'onboarding_data',
    'locale', 'currency', 'description', 'business_category', 'theme', 'contact',
    'logo_path', 'brand_color', 'visual_style', 'target_audience',
])]
class Shop extends Model
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const PAUSED = 'paused';
    public const SUSPENDED = 'suspended';

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'contact' => 'array',
            'onboarding_data' => 'array',
            'onboarding_step' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Formule d'abonnement active de la boutique
     */
    public function currentPlan(): Plan
    {
        $sub = $this->subscription;
        if ($sub && in_array($sub->status, ['active', 'trial', 'grace'], true) && $sub->plan) {
            return $sub->plan;
        }

        return Plan::where('slug', 'essentiel')->first() ?? Plan::first() ?? new Plan([
            'slug' => 'essentiel',
            'name' => 'Essentiel',
            'monthly_price' => 10000,
            'max_products' => 50,
            'storage_mb' => 2048,
            'ai_credits' => 80,
            'full_generations' => 1,
            'active' => true,
        ]);
    }

    public function maxProducts(): int
    {
        return (int) $this->currentPlan()->max_products;
    }

    public function canAddProduct(): bool
    {
        return $this->products()->count() < $this->maxProducts();
    }

    public function remainingProductsCount(): int
    {
        return max(0, $this->maxProducts() - $this->products()->count());
    }

    public function canUseFeature(string $feature): bool
    {
        return $this->currentPlan()->canAccessFeature($feature);
    }

    public function aiCreditsMonthly(): int
    {
        return (int) $this->currentPlan()->ai_credits;
    }

    public function aiCreditsUsedThisMonth(): int
    {
        return (int) $this->aiGenerations()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
    }

    public function aiCreditsRemaining(): int
    {
        return max(0, $this->aiCreditsMonthly() - $this->aiCreditsUsedThisMonth());
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('display_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(ShopPage::class);
    }

    public function aiGenerations(): HasMany
    {
        return $this->hasMany(AiGeneration::class);
    }

    public function activeProducts(): HasMany
    {
        return $this->hasMany(Product::class)->where('status', Product::STATUS_ACTIVE);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class)->orderBy('name');
    }

    public function shippingZones(): HasMany
    {
        return $this->hasMany(ShippingZone::class)->orderBy('display_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function advertisements(): HasMany
    {
        return $this->hasMany(Advertisement::class)->latest();
    }

    /**
     * Initialise les zones de livraison types pour le Togo si aucune n'est configurée
     */
    public function ensureDefaultShippingZones(): void
    {
        if ($this->shippingZones()->exists()) {
            return;
        }

        $defaults = [
            [
                'name' => 'Lomé Intra-muros',
                'price' => 1000,
                'free_shipping_threshold' => 25000,
                'delivery_estimate' => 'Livré en 24h',
                'is_pickup' => false,
                'display_order' => 1,
            ],
            [
                'name' => 'Grand Lomé (Adidogomé, Agoè, Baguida)',
                'price' => 1500,
                'free_shipping_threshold' => 30000,
                'delivery_estimate' => 'Livré en 24h à 48h',
                'is_pickup' => false,
                'display_order' => 2,
            ],
            [
                'name' => 'Intérieur du Togo (Kara, Sokodé, Atakpamé, Dapaong)',
                'price' => 3000,
                'free_shipping_threshold' => 50000,
                'delivery_estimate' => 'Livré en 48h à 72h',
                'is_pickup' => false,
                'display_order' => 3,
            ],
            [
                'name' => 'Retrait gratuit en boutique',
                'price' => 0,
                'free_shipping_threshold' => null,
                'delivery_estimate' => 'Disponible immédiatement',
                'is_pickup' => true,
                'display_order' => 4,
            ],
        ];

        foreach ($defaults as $zone) {
            ShippingZone::withoutGlobalScopes()->create(array_merge($zone, ['shop_id' => $this->id, 'is_active' => true]));
        }
    }


    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    public function isPaused(): bool
    {
        return $this->status === self::PAUSED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/b/' . ($this->subdomain ?: 'boutique'));
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->contact['phone'] ?? $this->owner?->phone ?? null;
    }

    public function getWhatsappAttribute(): ?string
    {
        return $this->contact['whatsapp'] ?? $this->contact['phone'] ?? $this->owner?->phone ?? null;
    }

    public function getEmailContactAttribute(): ?string
    {
        return $this->contact['email'] ?? $this->owner?->email ?? null;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->contact['address'] ?? null;
    }

    public function getCityAttribute(): ?string
    {
        return $this->contact['city'] ?? 'Lomé';
    }

    public function getMapsUrlAttribute(): ?string
    {
        if (! empty($this->contact['maps_url'])) {
            return $this->contact['maps_url'];
        }
        if (! empty($this->address) && preg_match('/https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google\.com|maps\.app\.goo\.gl)[^\s\)]+/', $this->address, $matches)) {
            return $matches[0];
        }
        if (! empty($this->address)) {
            $query = trim($this->address . ($this->city ? ', ' . $this->city : ', Lomé'));
            return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($query);
        }
        return null;
    }

    public function getWhatsappFormattedAttribute(): ?string
    {
        $raw = $this->whatsapp;
        if (! $raw) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        // Par défaut pour le Togo si 8 chiffres (ex: 90123456), ajouter l'indicatif 228
        if (strlen($digits) === 8) {
            $digits = '228' . $digits;
        }
        return $digits;
    }

    /**
     * Checklist de publication (B-07, RG-09)
     * Retourne ['can_publish' => bool, 'checks' => array, 'errors' => array]
     */
    public function publicationChecklist(): array
    {
        $errors = [];
        $checks = [];

        // 1. Informations de base
        $hasName = ! empty(trim($this->name ?? ''));
        $checks['name'] = [
            'label' => 'Nom de la boutique renseigné',
            'passed' => $hasName,
        ];
        if (! $hasName) {
            $errors[] = 'Veuillez renseigner le nom de votre boutique.';
        }

        // 2. Contact
        $hasContact = ! empty($this->phone) || ! empty($this->whatsapp) || ! empty($this->email_contact);
        $checks['contact'] = [
            'label' => 'Moyen de contact (Téléphone / WhatsApp ou Email)',
            'passed' => $hasContact,
        ];
        if (! $hasContact) {
            $errors[] = 'Veuillez renseigner un numéro de téléphone ou WhatsApp pour vos clients.';
        }

        // 3. Sous-domaine valide
        $sub = strtolower(trim($this->subdomain ?? ''));
        $reserved = config('platform.reserved_subdomains', []);
        $validSub = (bool) preg_match('/^[a-z0-9][a-z0-9\-]{1,28}[a-z0-9]$/', $sub) && ! in_array($sub, $reserved, true);
        $checks['subdomain'] = [
            'label' => 'Lien de boutique valide (sous-domaine : 3 à 30 caractères alphanumériques)',
            'passed' => $validSub,
        ];
        if (! $validSub) {
            $errors[] = 'Le sous-domaine de la boutique est invalide ou réservé.';
        }

        // 4. Au moins 1 produit actif avec prix > 0 FCFA (CDC B-07)
        $validProductsCount = $this->products()
            ->where('status', Product::STATUS_ACTIVE)
            ->where('price', '>', 0)
            ->count();
        $hasValidProduct = $validProductsCount >= 1;
        $checks['products'] = [
            'label' => 'Au moins un produit actif avec un prix défini (> 0 FCFA)',
            'passed' => $hasValidProduct,
            'count' => $validProductsCount,
        ];
        if (! $hasValidProduct) {
            $errors[] = 'Vous devez configurer au moins un produit actif avec un prix supérieur à 0 FCFA avant de publier.';
        }

        return [
            'can_publish' => empty($errors),
            'checks' => $checks,
            'errors' => $errors,
        ];
    }

    /**
     * Génère un sous-domaine unique, valide et non réservé pour la boutique
     */
    public static function generateUniqueSubdomain(string $nameOrSlug, ?int $ignoreShopId = null): string
    {
        $base = Str::slug($nameOrSlug);
        $base = strtolower(trim($base));
        $base = preg_replace('/[^a-z0-9\-]/', '', $base);
        $base = trim($base, '-');
        if (strlen($base) < 3) {
            $base = 'boutique';
        }

        // Limiter la base pour permettre l'ajout de suffixes numériques (max 30 car. total)
        $base = substr($base, 0, 24);
        $base = rtrim($base, '-');
        if (strlen($base) < 3) {
            $base = 'boutique';
        }

        $reserved = config('platform.reserved_subdomains', []);

        $candidate = $base;
        $counter = 1;

        while (
            in_array($candidate, $reserved, true) ||
            static::withoutGlobalScopes()
                ->where('subdomain', $candidate)
                ->when($ignoreShopId, fn ($q) => $q->where('id', '!=', $ignoreShopId))
                ->exists()
        ) {
            $counter++;
            $candidate = substr($base, 0, 24) . '-' . $counter;
        }

        return $candidate;
    }
}

