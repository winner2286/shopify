<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPage;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StorefrontController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * Résout la boutique par son sous-domaine et vérifie les statuts
     */
    private function resolveShop(string $subdomain, Request $request): array
    {
        $shop = Shop::where('subdomain', strtolower($subdomain))->first();

        if (! $shop) {
            abort(404, "La boutique « {$subdomain} » n'existe pas ou a été déplacée.");
        }

        if ($shop->status === Shop::SUSPENDED) {
            abort(403, 'Cette boutique est actuellement suspendue.');
        }

        $currentUser = Auth::user();
        $isOwner = $currentUser && $currentUser->id === $shop->user_id;

        // Si la boutique est en pause ou brouillon (B-09, RG-13)
        if (in_array($shop->status, [Shop::DRAFT, Shop::PAUSED], true)) {
            if (! $isOwner) {
                return ['shop' => $shop, 'preview' => false, 'maintenance' => true];
            }
        }

        return ['shop' => $shop, 'preview' => $isOwner && $shop->status !== Shop::PUBLISHED, 'maintenance' => false];
    }

    /**
     * D-01 : Accueil de la vitrine
     */
    public function home(Request $request, string $subdomain)
    {
        $res = $this->resolveShop($subdomain, $request);
        $shop = $res['shop'];

        if ($res['maintenance']) {
            return response()->view('storefront.maintenance', compact('shop'), 503);
        }

        $preview = $res['preview'];

        // Catégories visibles ayant des produits
        $categories = Category::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('is_visible', true)
            ->orderBy('display_order')
            ->get();

        // Produits vedettes (actifs avec prix > 0)
        $products = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('price', '>', 0)
            ->with(['images', 'categories'])
            ->latest()
            ->take(12)
            ->get();

        $cartCount = $this->cartService->getItemsCount($shop);

        return view('storefront.home', compact('shop', 'categories', 'products', 'preview', 'cartCount'));
    }

    /**
     * D-02 : Catalogue et recherche
     */
    public function products(Request $request, string $subdomain)
    {
        $res = $this->resolveShop($subdomain, $request);
        $shop = $res['shop'];

        if ($res['maintenance']) {
            return response()->view('storefront.maintenance', compact('shop'), 503);
        }

        $preview = $res['preview'];

        $query = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('price', '>', 0)
            ->with(['images', 'categories']);

        // Recherche par titre ou description
        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtre par catégorie
        $activeCategory = null;
        if ($catSlug = $request->input('categorie')) {
            $activeCategory = Category::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('slug', $catSlug)
                ->first();

            if ($activeCategory) {
                $query->whereHas('categories', function ($q) use ($activeCategory) {
                    $q->where('categories.id', $activeCategory->id);
                });
            }
        }

        // Tri
        $sort = $request->input('tri', 'recent');
        match ($sort) {
            'prix_croissant' => $query->orderBy('price', 'asc'),
            'prix_decroissant' => $query->orderBy('price', 'desc'),
            default => $query->latest(),
        };

        $products = $query->paginate(16)->withQueryString();

        $categories = Category::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('is_visible', true)
            ->orderBy('display_order')
            ->get();

        $cartCount = $this->cartService->getItemsCount($shop);

        return view('storefront.products', compact(
            'shop', 'products', 'categories', 'activeCategory', 'preview', 'cartCount', 'search', 'sort'
        ));
    }

    /**
     * D-03 : Fiche produit détaillée
     */
    public function product(Request $request, string $subdomain, string $slug)
    {
        $res = $this->resolveShop($subdomain, $request);
        $shop = $res['shop'];

        if ($res['maintenance']) {
            return response()->view('storefront.maintenance', compact('shop'), 503);
        }

        $preview = $res['preview'];

        /** @var Product|null $product */
        $product = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('slug', $slug)
            ->with(['images', 'categories'])
            ->first();

        if (! $product) {
            abort(404, 'Ce produit est introuvable.');
        }

        // Produits complémentaires / suggestions
        $firstCat = $product->categories->first();
        $relatedProducts = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('id', '!=', $product->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('price', '>', 0)
            ->when($firstCat, function ($q) use ($firstCat) {
                $q->whereHas('categories', fn ($sq) => $sq->where('categories.id', $firstCat->id));
            })
            ->with(['images'])
            ->take(4)
            ->get();

        $cartCount = $this->cartService->getItemsCount($shop);

        // Message WhatsApp pré-rempli pour commande rapide
        $waMessage = urlencode("Bonjour {$shop->name}, je souhaite commander l'article suivant :\n- {$product->title}\n- Prix : " . number_format($product->price, 0, ',', ' ') . " FCFA\nLien : " . url()->current());
        $waUrl = $shop->whatsapp_formatted ? "https://wa.me/{$shop->whatsapp_formatted}?text={$waMessage}" : null;

        return view('storefront.product', compact('shop', 'product', 'relatedProducts', 'preview', 'cartCount', 'waUrl'));
    }

    /**
     * D-07 : Pages publiques d'information
     */
    public function page(Request $request, string $subdomain, string $type)
    {
        $res = $this->resolveShop($subdomain, $request);
        $shop = $res['shop'];

        if ($res['maintenance']) {
            return response()->view('storefront.maintenance', compact('shop'), 503);
        }

        $preview = $res['preview'];

        $page = ShopPage::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('type', $type)
            ->where('is_published', true)
            ->first();

        if (! $page) {
            abort(404, 'Page introuvable.');
        }

        $cartCount = $this->cartService->getItemsCount($shop);

        return view('storefront.page', compact('shop', 'page', 'preview', 'cartCount'));
    }

    /**
     * D-04 : Panier client
     */
    public function cart(Request $request, string $subdomain)
    {
        $res = $this->resolveShop($subdomain, $request);
        $shop = $res['shop'];

        if ($res['maintenance']) {
            return response()->view('storefront.maintenance', compact('shop'), 503);
        }

        $preview = $res['preview'];
        $cart = $this->cartService->get($shop);
        $cartCount = $this->cartService->getItemsCount($shop);
        $subtotal = $this->cartService->getSubtotal($shop);

        return view('storefront.cart', compact('shop', 'cart', 'cartCount', 'subtotal', 'preview'));
    }

    /**
     * Ajout d'un article au panier
     */
    public function addToCart(Request $request, string $subdomain)
    {
        $res = $this->resolveShop($subdomain, $request);
        $shop = $res['shop'];

        $productId = (int) $request->input('product_id');
        $quantity = (int) $request->input('quantity', 1);

        $result = $this->cartService->add($shop, $productId, $quantity);

        if ($request->wantsJson()) {
            $result['count'] = $this->cartService->getItemsCount($shop);
            $result['subtotal'] = $this->cartService->getSubtotal($shop);
            $result['subtotal_formatted'] = number_format($result['subtotal'], 0, ',', ' ') . ' FCFA';
            return response()->json($result);
        }

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('status', $result['message']);
    }

    /**
     * Modification de quantité
     */
    public function updateCart(Request $request, string $subdomain): RedirectResponse
    {
        $res = $this->resolveShop($subdomain, $request);
        $shop = $res['shop'];

        $productId = (int) $request->input('product_id');
        $quantity = (int) $request->input('quantity');

        $result = $this->cartService->update($shop, $productId, $quantity);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('status', $result['message']);
    }

    /**
     * Suppression d'un article
     */
    public function removeFromCart(Request $request, string $subdomain, int $productId): RedirectResponse
    {
        $res = $this->resolveShop($subdomain, $request);
        $shop = $res['shop'];

        $this->cartService->remove($shop, $productId);

        return back()->with('status', 'Article retiré du panier.');
    }

    /**
     * Vider le panier
     */
    public function clearCart(Request $request, string $subdomain): RedirectResponse
    {
        $res = $this->resolveShop($subdomain, $request);
        $shop = $res['shop'];

        $this->cartService->clear($shop);

        return back()->with('status', 'Votre panier a été vidé.');
    }
}
