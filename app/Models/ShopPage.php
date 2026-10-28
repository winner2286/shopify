<?php

namespace App\Models;

use App\Models\Scopes\BelongsToShopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToShopScope::class])]
#[Fillable(['shop_id', 'type', 'title', 'content', 'locale', 'is_published'])]
class ShopPage extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public const TYPE_ABOUT = 'about';
    public const TYPE_CONTACT = 'contact';
    public const TYPE_SHIPPING = 'shipping';
    public const TYPE_TERMS = 'terms';

    public static function typeLabels(): array
    {
        return [
            self::TYPE_ABOUT => 'À propos de nous',
            self::TYPE_CONTACT => 'Contact & Localisation',
            self::TYPE_SHIPPING => 'Livraison et Paiement',
            self::TYPE_TERMS => 'Conditions Générales de Vente',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->type] ?? ucfirst($this->type);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
