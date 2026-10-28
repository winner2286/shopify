<?php

namespace App\Models;

use App\Models\Scopes\BelongsToShopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToShopScope::class])]
#[Fillable([
    'shop_id', 'product_id', 'channel', 'objective', 'tone',
    'generated_texts', 'generated_scripts', 'visual_config',
    'views_count', 'shares_count',
])]
class Advertisement extends Model
{
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_TIKTOK = 'tiktok';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_INSTAGRAM = 'instagram';

    public const OBJECTIVE_NOUVEAUTE = 'nouveaute';
    public const OBJECTIVE_PROMOTION = 'promotion';
    public const OBJECTIVE_DESTOCKAGE = 'destockage';
    public const OBJECTIVE_RAPPEL = 'rappel';

    public const TONE_AMICAL = 'amical';
    public const TONE_PREMIUM = 'premium';
    public const TONE_DYNAMIQUE = 'dynamique';

    protected function casts(): array
    {
        return [
            'generated_texts' => 'array',
            'generated_scripts' => 'array',
            'visual_config' => 'array',
            'views_count' => 'integer',
            'shares_count' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getChannelLabelAttribute(): string
    {
        return match ($this->channel) {
            self::CHANNEL_WHATSAPP => 'Statut / WhatsApp',
            self::CHANNEL_TIKTOK => 'TikTok & Reels',
            self::CHANNEL_SMS => 'Campagne SMS',
            self::CHANNEL_INSTAGRAM => 'Instagram',
            default => ucfirst($this->channel),
        };
    }

    public function getObjectiveLabelAttribute(): string
    {
        return match ($this->objective) {
            self::OBJECTIVE_NOUVEAUTE => 'Nouveauté',
            self::OBJECTIVE_PROMOTION => 'Promotion',
            self::OBJECTIVE_DESTOCKAGE => 'Déstockage',
            self::OBJECTIVE_RAPPEL => 'Rappel client',
            default => ucfirst($this->objective),
        };
    }

    public function getToneLabelAttribute(): string
    {
        return match ($this->tone) {
            self::TONE_AMICAL => 'Amical & Chaleureux',
            self::TONE_PREMIUM => 'Élégant & Premium',
            self::TONE_DYNAMIQUE => 'Dynamique & Urgent',
            default => ucfirst($this->tone),
        };
    }
}
