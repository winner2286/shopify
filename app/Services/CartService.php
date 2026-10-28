<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Session;

class CartService
{
    private function sessionKey(Shop $shop): string
    {
        return 'cart_' . $shop->id;
    }

    /**
     * Récupère le contenu du panier pour une boutique donnée
     */
    public function get(Shop $shop): array
    {
        return Session::get($this->sessionKey($shop), []);
    }

    /**
     * Ajoute un produit au panier
     * Retourne ['success' => bool, 'message' => string, 'cart' => array]
     */
    public function add(Shop $shop, int $productId, int $quantity = 1): array
    {
        if ($quantity <= 0) {
            $quantity = 1;
        }

        /** @var Product|null $product */
        $product = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->find($productId);

        if (! $product) {
            return [
                'success' => false,
                'message' => 'Ce produit n\'est pas disponible.',
                'cart' => $this->get($shop),
            ];
        }

        if ($product->price <= 0) {
            return [
                'success' => false,
                'message' => 'Le prix de cet article n\'a pas encore été fixé.',
                'cart' => $this->get($shop),
            ];
        }

        $cart = $this->get($shop);
        $currentQty = isset($cart[$productId]) ? $cart[$productId]['quantity'] : 0;
        $newQty = $currentQty + $quantity;

        // Vérification de la disponibilité du stock (CDC D-04)
        if ($product->track_stock && $newQty > $product->stock) {
            $allowed = max(0, $product->stock - $currentQty);
            return [
                'success' => false,
                'message' => $product->stock <= 0
                    ? 'Désolé, ce produit est en rupture de stock.'
                    : "Stock insuffisant : vous ne pouvez ajouter que {$allowed} article(s) supplémentaire(s).",
                'cart' => $cart,
            ];
        }

        $image = $product->images()->orderBy('sort_order')->first();
        $imageUrl = $image ? ($image->thumbnail_url ?: $image->url) : null;

        $cart[$productId] = [
            'product_id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'price' => (int) $product->price,
            'quantity' => $newQty,
            'image_url' => $imageUrl,
            'stock' => $product->track_stock ? $product->stock : 9999,
        ];

        Session::put($this->sessionKey($shop), $cart);

        return [
            'success' => true,
            'message' => "{$product->title} a été ajouté au panier !",
            'cart' => $cart,
        ];
    }

    /**
     * Met à jour la quantité d'un article
     */
    public function update(Shop $shop, int $productId, int $quantity): array
    {
        $cart = $this->get($shop);

        if (! isset($cart[$productId])) {
            return [
                'success' => false,
                'message' => 'Article non trouvé dans le panier.',
                'cart' => $cart,
            ];
        }

        if ($quantity <= 0) {
            return $this->remove($shop, $productId);
        }

        /** @var Product|null $product */
        $product = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->find($productId);

        if ($product && $product->track_stock && $quantity > $product->stock) {
            $cart[$productId]['quantity'] = $product->stock;
            Session::put($this->sessionKey($shop), $cart);
            return [
                'success' => false,
                'message' => "Stock maximal atteint ({$product->stock} disponible(s)).",
                'cart' => $cart,
            ];
        }

        $cart[$productId]['quantity'] = $quantity;
        Session::put($this->sessionKey($shop), $cart);

        return [
            'success' => true,
            'message' => 'Quantité mise à jour.',
            'cart' => $cart,
        ];
    }

    /**
     * Supprime un article du panier
     */
    public function remove(Shop $shop, int $productId): array
    {
        $cart = $this->get($shop);
        if (isset($cart[$productId])) {
            unset($cart[$productId]);
            Session::put($this->sessionKey($shop), $cart);
        }

        return [
            'success' => true,
            'message' => 'Article retiré du panier.',
            'cart' => $cart,
        ];
    }

    /**
     * Vide complètement le panier
     */
    public function clear(Shop $shop): void
    {
        Session::forget($this->sessionKey($shop));
    }

    /**
     * Nombre total d'articles dans le panier
     */
    public function getItemsCount(Shop $shop): int
    {
        $cart = $this->get($shop);
        $count = 0;
        foreach ($cart as $item) {
            $count += (int) $item['quantity'];
        }
        return $count;
    }

    /**
     * Sous-total du panier en FCFA
     */
    public function getSubtotal(Shop $shop): int
    {
        $cart = $this->get($shop);
        $total = 0;
        foreach ($cart as $item) {
            $total += (int) $item['price'] * (int) $item['quantity'];
        }
        return $total;
    }
}
