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
        return $this->hasOne(Shop::class);
    }

    public function verificationCodes(): HasMany
    {
        return $this->hasMany(VerificationCode::class);
    }

    public function isMerchant(): bool
    {
        return $this->role === self::ROLE_MERCHANT;
    }

    public function isAdmin(): bool
    {
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
