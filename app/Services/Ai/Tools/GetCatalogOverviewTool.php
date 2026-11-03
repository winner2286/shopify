<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

class GetCatalogOverviewTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_catalog_overview';
    }

    public function description(): string
    {
        return 'Récupère une vue d\'ensemble en temps réel du catalogue de la boutique : total des produits, articles actifs, masqués, ruptures de stock, alertes stock bas et liste des catégories.';
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
        // Extraction scopée sur la boutique
        $products = Product::where('shop_id', $shop->id)->get();
        $categories = $shop->categories()->get(['id', 'name', 'slug']);

        $totalProducts = $products->count();
        $activeProducts = $products->where('status', Product::STATUS_ACTIVE)->count();
        $hiddenProducts = $products->where('status', Product::STATUS_HIDDEN)->count();

        // Analyse des stocks
        $outOfStock = $products->filter(function ($p) {
            return $p->track_stock && $p->stock <= 0;
        })->values();

        $lowStock = $products->filter(function ($p) {
            return $p->track_stock && $p->stock > 0 && $p->stock <= $p->low_stock_threshold;
        })->values();

        return [
            'shop_name' => $shop->name,
            'currency' => $shop->currency,
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'hidden_products' => $hiddenProducts,
            'categories' => $categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'stock_health' => [
                'out_of_stock_count' => $outOfStock->count(),
                'out_of_stock_items' => $outOfStock->map(fn($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'price' => $p->price,
                ])->take(6)->all(),
                'low_stock_count' => $lowStock->count(),
                'low_stock_items' => $lowStock->map(fn($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'stock' => $p->stock,
                    'threshold' => $p->low_stock_threshold,
                    'price' => $p->price,
                ])->take(6)->all(),
            ],
            'catalog_capacity' => [
                'max_allowed' => $shop->maxProducts(),
                'remaining' => $shop->remainingProductsCount(),
            ],
        ];
    }
}
