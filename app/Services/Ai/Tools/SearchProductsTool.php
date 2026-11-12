<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

class SearchProductsTool implements AiToolInterface
{
    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'Recherche des produits dans le catalogue par nom/mots-clés, statut, ou catégorie, avec détails de prix et de stock.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Mots-clés ou nom du produit recherché.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['all', 'active', 'hidden', 'archived'],
                    'description' => 'Filtrer par statut (par défaut: active ou all).',
                ],
                'in_stock_only' => [
                    'type' => 'boolean',
                    'description' => 'Si true, ne renvoie que les articles ayant du stock disponible.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Nombre maximal de produits à renvoyer (max 20, défaut 10).',
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
        $q = Product::where('shop_id', $shop->id)->with(['categories', 'primaryImage']);

        if (! empty($arguments['query'])) {
            $term = trim($arguments['query']);
            $q->where(function ($sub) use ($term) {
                \App\Support\SearchHelper::whereLike($sub, ['title', 'description', 'sku'], $term);
            });
        }

        if (! empty($arguments['status']) && $arguments['status'] !== 'all') {
            $q->where('status', $arguments['status']);
        }

        if (! empty($arguments['in_stock_only'])) {
            $q->where(function ($sub) {
                $sub->where('track_stock', false)
                    ->orWhere('stock', '>', 0);
            });
        }

        $limit = min((int) ($arguments['limit'] ?? 10), 20);
        $products = $q->take($limit)->get();

        return [
            'count' => $products->count(),
            'products' => $products->map(function ($p) use ($shop) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'price' => $p->price,
                    'currency' => $shop->currency,
                    'stock' => $p->track_stock ? $p->stock : 'illimité',
                    'status' => $p->status,
                    'categories' => $p->categories->pluck('name')->all(),
                    'has_image' => (bool) $p->primaryImage,
                ];
            })->all(),
        ];
    }
}
