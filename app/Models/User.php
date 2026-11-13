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
#[Hidden(['password', 'remember_token'])]
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
     * Récupère la boutique active dans la session ou la boutique par défaut.
     */
    public function activeShop(): ?Shop
    {
        $shopId = session('active_shop_id');
        if ($shopId) {
            $selected = $this->shops()->find($shopId);
            if ($selected) {
                return $selected;
            }
        }

        return $this->shop;
    }

    /**
     * Forfait actif de l'utilisateur (via ses abonnements ou Essentiel par défaut).
     */
    public function currentPlan(): Plan
    {
        $activeSub = Subscription::whereIn('shop_id', $this->shops()->pluck('id'))
            ->whereIn('status', ['active', 'trial', 'grace'])
            ->with('plan')
            ->latest('id')
            ->first();

        if ($activeSub && $activeSub->plan) {
            return $activeSub->plan;
        }

        return Plan::where('slug', 'essentiel')->first() 
            ?? new Plan(['slug' => 'essentiel', 'name' => 'Essentiel', 'max_shops' => 1]);
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
            if ($this->role !== self::ROLE_ADMIN) {
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
