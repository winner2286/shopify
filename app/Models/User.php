<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'country', 'phone', 'locale', 'terms_accepted_at'])]
#[Hidden(['password', 'remember_token', 'credit_quota_state'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_MERCHANT = 'merchant';

    public const ROLE_SUPPORT = 'support';

    public const ROLE_ADMIN = 'admin';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locked_until' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'credit_quota_state' => 'array',
        ];
    }

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class)->latestOfMany();
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    /**
     * Accesseur dynamique : retourne la boutique active en session ou la boutique par défaut.
     * Garantit que $user->shop respecte le basculement multi-boutiques dans toute l'application.
     */
    public function getShopAttribute(): ?Shop
    {
        $shopId = session('active_shop_id');
        if ($shopId) {
            if ($this->relationLoaded('active_shop') && $this->getRelation('active_shop')?->id === (int) $shopId) {
                return $this->getRelation('active_shop');
            }

            $selected = $this->shops()->find($shopId);
            if ($selected) {
                $this->setRelation('active_shop', $selected);

                return $selected;
            }
        }

        if ($this->relationLoaded('shop')) {
            return $this->getRelation('shop');
        }

        $latest = $this->shop()->first();
        if ($latest) {
            $this->setRelation('shop', $latest);
        }

        return $latest;
    }

    /**
     * Récupère la boutique active dans la session ou la boutique par défaut.
     */
    public function activeShop(): ?Shop
    {
        return $this->getShopAttribute();
    }

    /**
     * Forfait actif de l'utilisateur (via ses abonnements ou Essentiel par défaut).
     */
    public function accountSubscription(): ?Subscription
    {
        return Subscription::governingSubscription($this->shops()->with('subscription.plan')->get()->pluck('subscription'));
    }

    public function currentPlan(): Plan
    {
        return $this->accountSubscription()?->plan ?? Plan::defaultEssentiel();
    }

    /**
     * Nombre maximum de boutiques allouées au compte.
     */
    public function maxShops(): int
    {
        return $this->currentPlan()->maxShops();
    }

    /**
     * Nombre actuel de boutiques créées par ce compte.
     */
    public function shopsCount(): int
    {
        return $this->shops()->count();
    }

    /**
     * Vérifie si le compte a le droit de créer une nouvelle boutique.
     */
    public function canCreateShop(): bool
    {
        return $this->shopsCount() < $this->maxShops();
    }

    public function verificationCodes(): HasMany
    {
        return $this->hasMany(VerificationCode::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function isMerchant(): bool
    {
        return $this->role === self::ROLE_MERCHANT;
    }

    public function isAdmin(): bool
    {
        $superAdminEmails = config('platform.super_admin_emails', ['kougnimag@gmail.com', 'kougnima@icloud.com']);
        $normalizedEmail = strtolower(trim((string) $this->email));

        if (in_array($normalizedEmail, $superAdminEmails, true) && ($this->isVerified() || app()->environment('testing', 'local'))) {
            if ($this->exists && $this->role !== self::ROLE_ADMIN) {
                $this->forceFill(['role' => self::ROLE_ADMIN])->saveQuietly();
            }

            return true;
        }

        return $this->role === self::ROLE_ADMIN;
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
