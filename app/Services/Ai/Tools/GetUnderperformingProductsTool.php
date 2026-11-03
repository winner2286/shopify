<?php

namespace App\Services\Ai\Tools;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use Carbon\Carbon;

class GetUnderperformingProductsTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_underperforming_products';
    }

    public function description(): string
    {
        return 'Identifie les produits en stock ayant peu ou pas de ventes récentes (produits dormants/slow-movers) pour préparer une opération de déstockage ou de promotion.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days' => [
                    'type' => 'integer',
                    'description' => 'Nombre de jours d\'analyse sans vente (défaut : 30 jours).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Nombre maximal de produits à retourner (défaut : 5).',
                ],
            ],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(Shop $shop, array $arguments, ?User $user = null): array
    {
        $days = (int) ($arguments['days'] ?? 30);
        $limit = (int) ($arguments['limit'] ?? 5);
        $since = Carbon::now()->subDays($days);

        // Récupérer les IDs des produits vendus dans la période
        $soldIds = OrderItem::withoutGlobalScopes()
            ->whereHas('order', function ($q) use ($shop, $since) {
                $q->where('shop_id', $shop->id)
                  ->where('created_at', '>=', $since)
                  ->where('status', '!=', 'cancelled');
            })
            ->pluck('product_id')
            ->unique();

        // Produits actifs avec du stock physique n'ayant pas été vendus
        $dormantProducts = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('stock', '>', 0)
            ->whereNotIn('id', $soldIds)
            ->orderByDesc('stock')
            ->take($limit)
            ->get();

        $items = $dormantProducts->map(function (Product $p) use ($shop) {
            $suggestedPromoPrice = (int) (round(($p->price * 0.85) / 50) * 50); // -15%
            return [
                'id' => $p->id,
                'title' => $p->title,
                'current_price' => $p->price,
                'stock' => $p->stock,
                'stock_value_fcfa' => $p->stock * $p->price,
                'suggested_promo_price' => $suggestedPromoPrice,
                'discount_percentage' => 15,
            ];
        })->toArray();

        $totalFrozenCapital = array_sum(array_column($items, 'stock_value_fcfa'));

        return [
            'status' => 'success',
            'analysis_period_days' => $days,
            'underperforming_count' => count($items),
            'total_frozen_capital_fcfa' => $totalFrozenCapital,
            'products' => $items,
            'strategic_advice' => "Ces produits immobilisent " . number_format($totalFrozenCapital, 0, ',', ' ') . " {$shop->currency} de trésorerie. Une remise de 15% ou une offre pack permettrait de débloquer ces fonds rapidement.",
        ];
    }
}
