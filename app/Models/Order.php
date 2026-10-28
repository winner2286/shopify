<?php

namespace App\Models;

use App\Models\Scopes\BelongsToShopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToShopScope::class])]
#[Fillable([
    'shop_id', 'customer_id', 'order_number',
    'customer_name', 'customer_phone', 'customer_email',
    'shipping_zone_id', 'delivery_zone_name', 'delivery_address', 'delivery_notes',
    'subtotal', 'shipping_cost', 'total',
    'payment_method', 'payment_status', 'paid_at',
    'status', 'cancellation_reason', 'cancelled_at',
])]
class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash_on_delivery';
    public const PAYMENT_TMONEY = 'tmoney';
    public const PAYMENT_FLOOZ = 'flooz';
    public const PAYMENT_CARD = 'card';

    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'shipping_cost' => 'integer',
            'total' => 'integer',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_CONFIRMED => 'Confirmée',
            self::STATUS_SHIPPED => 'Expédiée',
            self::STATUS_DELIVERED => 'Livrée',
            self::STATUS_CANCELLED => 'Annulée',
            default => ucfirst($this->status),
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::PAYMENT_CASH => 'Espèces à la livraison',
            self::PAYMENT_TMONEY => 'T-Money Togo',
            self::PAYMENT_FLOOZ => 'Flooz Moov',
            self::PAYMENT_CARD => 'Carte bancaire',
            default => ucfirst($this->payment_method),
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_STATUS_PENDING => 'En attente',
            self::PAYMENT_STATUS_PAID => 'Payé',
            self::PAYMENT_STATUS_FAILED => 'Échoué',
            self::PAYMENT_STATUS_REFUNDED => 'Remboursé',
            default => ucfirst($this->payment_status),
        };
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true);
    }

    /**
     * Enregistre un changement de statut dans l'historique d'audit (CDC E-02)
     */
    public function recordStatusChange(string $toStatus, string $authorType = 'merchant', ?int $authorId = null, ?string $comment = null): void
    {
        $fromStatus = $this->status;
        $this->update(['status' => $toStatus]);

        $this->statusHistories()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'author_type' => $authorType,
            'author_id' => $authorId,
            'comment' => $comment,
        ]);
    }

    /**
     * Génère un numéro de commande unique (ex: CMD-20261026-0042)
     */
    public static function generateOrderNumber(Shop $shop): string
    {
        $prefix = 'CMD-' . date('Ymd') . '-';
        $countToday = self::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->whereDate('created_at', today())
            ->count() + 1;

        $candidate = $prefix . str_pad((string) $countToday, 4, '0', STR_PAD_LEFT);

        while (self::withoutGlobalScopes()->where('order_number', $candidate)->exists()) {
            $candidate = $prefix . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }
}
