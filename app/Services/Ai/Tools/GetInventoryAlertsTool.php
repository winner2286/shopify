<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

class GetInventoryAlertsTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_inventory_alerts';
    }

    public function description(): string
    {
        return 'Identifie tous les produits en rupture totale de stock ou ayant atteint leur seuil d\'alerte critique pour réapprovisionnement prioritaire.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(Shop $shop, array $arguments, ?User $user = null): array
    {
        $products = Product::where('shop_id', $shop->id)
            ->where('track_stock', true)
            ->get();

        $outOfStock = $products->filter(fn($p) => $p->stock <= 0)->values();
        $criticalStock = $products->filter(fn($p) => $p->stock > 0 && $p->stock <= $p->low_stock_threshold)->values();

        return [
            'total_tracked_products' => $products->count(),
            'currency' => $shop->currency,
            'out_of_stock' => [
                'count' => $outOfStock->count(),
                'products' => $outOfStock->map(fn($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'price' => $p->price,
                    'urgency' => 'CRITIQUE (Rupture)',
                ])->all(),
            ],
            'low_stock_warning' => [
                'count' => $criticalStock->count(),
                'products' => $criticalStock->map(fn($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'current_stock' => $p->stock,
                    'threshold' => $p->low_stock_threshold,
                    'price' => $p->price,
                    'urgency' => 'ATTENTION (Seuil atteint)',
                ])->all(),
            ],
        ];
    }
}
