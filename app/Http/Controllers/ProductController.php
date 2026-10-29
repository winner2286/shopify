<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Liste des produits avec filtres, recherche et pagination (C-05)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (! $user) {
                return redirect()->route('login');
            }

            $shop = $user->shop;
            if (! $shop) {
                $shop = Shop::firstOrCreate(['user_id' => $user->id], [
                    'name' => 'Ma Boutique',
                    'status' => Shop::DRAFT,
                ]);
            }

            $query = $shop->products()->with(['categories', 'images'])->latest('id');

            // Filtre par recherche textuelle (titre ou sku)
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            // Filtre par statut (active, hidden, archived)
            if ($status = $request->input('status')) {
                $query->where('status', $status);
            } else {
                // Par défaut on exclut les archivés de la vue standard
                $query->where('status', '!=', Product::STATUS_ARCHIVED);
            }

            // Filtre par catégorie
            if ($categoryId = $request->input('category_id')) {
                $query->whereHas('categories', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                });
            }

            // Filtre stock bas / épuisé
            if ($request->input('filter') === 'low_stock') {
                $query->where('track_stock', true)
                      ->where('stock', '>', 0)
                      ->whereColumn('stock', '<=', 'low_stock_threshold');
            } elseif ($request->input('filter') === 'out_of_stock') {
                $query->where('track_stock', true)
                      ->where('stock', '<=', 0);
            }

            $products = $query->paginate(20)->withQueryString();
            $categories = $shop->categories()->get();

            return view('products.index', compact('products', 'categories'));
        } catch (\Throwable $e) {
            report($e);
            Log::error('Erreur catalogue produits: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $categories = collect();
            $products = (new LengthAwarePaginator([], 0, 20))->withPath(route('products.index'));
            return view('products.index', compact('products', 'categories'))
                ->with('error', 'Chargement du catalogue : ' . $e->getMessage());
        }
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop ?? Shop::firstOrCreate(['user_id' => $user->id], ['name' => 'Ma Boutique', 'status' => Shop::DRAFT]);
        $categories = $shop->categories()->get();

        return view('products.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop ?? Shop::firstOrCreate(['user_id' => $user->id], ['name' => 'Ma Boutique', 'status' => Shop::DRAFT]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:1'], // Entier > 0 en FCFA (RG-10)
            'compare_at_price' => ['nullable', 'integer', 'min:1'],
            'stock' => ['nullable', 'integer'],
            'track_stock' => ['nullable', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,hidden'],
            'sku' => ['nullable', 'string', 'max:50'],
            'category_ids' => ['nullable', 'array', 'max:3'], // 1 à 3 catégories max (C-01)
            'category_ids.*' => ['exists:categories,id'],
            'photos' => ['nullable', 'array', 'max:8'], // 1 à 8 photos (C-01)
            'photos.*' => ['image', 'mimes:jpeg,png,webp,jpg', 'max:5120'], // Max 5 Mo par photo
        ], [
            'title.required' => 'Le titre du produit est obligatoire.',
            'price.required' => 'Le prix est obligatoire.',
            'price.min' => 'Le prix doit être supérieur à 0 FCFA.',
            'category_ids.max' => 'Vous pouvez choisir au maximum 3 catégories.',
            'photos.max' => 'Vous ne pouvez pas ajouter plus de 8 photos.',
        ]);

        $trackStock = $request->boolean('track_stock', true);
        $initialStock = $trackStock ? ($validated['stock'] ?? 0) : 0;

        $product = $shop->products()->create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']) . '-' . Str::random(5),
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'compare_at_price' => $validated['compare_at_price'] ?? null,
            'stock' => $initialStock,
            'track_stock' => $trackStock,
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? 3,
            'status' => $validated['status'],
            'sku' => $validated['sku'] ?? null,
        ]);

        // Association des catégories
        if (! empty($validated['category_ids'])) {
            $product->categories()->sync($validated['category_ids']);
        }

        // Mouvement de stock initial
        if ($trackStock && $initialStock != 0) {
            $product->stockMovements()->create([
                'user_id' => $request->user()->id,
                'quantity_change' => $initialStock,
                'new_stock' => $initialStock,
                'reason' => 'initial',
                'comment' => 'Stock initial à la création',
            ]);
        }

        // Traitement et stockage des photos (C-06)
        if ($request->hasFile('photos')) {
            $imageService = app(\App\Services\ImageService::class);
            $sortOrder = 0;
            foreach ($request->file('photos') as $file) {
                $processed = $imageService->processAndStore($file, "shops/{$shop->id}/products");
                $product->images()->create([
                    'path' => $processed['path'],
                    'thumbnail_path' => $processed['thumbnail_path'],
                    'sort_order' => $sortOrder++,
                    'size_bytes' => $processed['size_bytes'],
                    'image_data' => $processed['image_data'] ?? null,
                ]);
            }
        }

        return redirect()->route('products.index')->with('status', 'Produit créé avec succès !');
    }

    public function edit(Request $request, Product $product)
    {
        $user = $request->user();
        $shop = $user->shop ?? Shop::firstOrCreate(['user_id' => $user->id], ['name' => 'Ma Boutique', 'status' => Shop::DRAFT]);
        $categories = $shop->categories()->get();
        $product->load(['categories', 'images', 'stockMovements.author']);

        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop ?? Shop::firstOrCreate(['user_id' => $user->id], ['name' => 'Ma Boutique', 'status' => Shop::DRAFT]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:1'],
            'compare_at_price' => ['nullable', 'integer', 'min:1'],
            'track_stock' => ['nullable', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,hidden,archived'],
            'sku' => ['nullable', 'string', 'max:50'],
            'category_ids' => ['nullable', 'array', 'max:3'],
            'category_ids.*' => ['exists:categories,id'],
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp,jpg', 'max:5120'],
            'stock_adjustment' => ['nullable', 'integer'],
            'adjustment_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $trackStock = $request->boolean('track_stock', true);

        $product->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'compare_at_price' => $validated['compare_at_price'] ?? null,
            'track_stock' => $trackStock,
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? 3,
            'status' => $validated['status'],
            'sku' => $validated['sku'] ?? null,
        ]);

        // Ajustement de stock si spécifié (C-04)
        if ($trackStock && ! empty($validated['stock_adjustment']) && $validated['stock_adjustment'] != 0) {
            $product->adjustStock(
                (int) $validated['stock_adjustment'],
                'manual_adjustment',
                $validated['adjustment_reason'] ?? 'Ajustement manuel dans la fiche produit',
                $request->user()->id
            );
        }

        // Catégories
        $product->categories()->sync($validated['category_ids'] ?? []);

        // Nouvelles photos
        if ($request->hasFile('photos')) {
            // Nettoyage des anciennes images fantômes sans données binaires si l'utilisateur en téléverse de nouvelles
            $hasValidImages = $product->images()->whereNotNull('image_data')->where('image_data', '!=', '')->exists();
            if (! $hasValidImages) {
                $product->images()->delete();
            }

            $currentCount = $product->images()->count();
            $sortOrder = $currentCount;

            $imageService = app(\App\Services\ImageService::class);
            foreach ($request->file('photos') as $file) {
                if ($currentCount >= 8) break; // Limite de 8 photos

                $processed = $imageService->processAndStore($file, "shops/{$shop->id}/products");
                $product->images()->create([
                    'path' => $processed['path'],
                    'thumbnail_path' => $processed['thumbnail_path'],
                    'sort_order' => $sortOrder++,
                    'size_bytes' => $processed['size_bytes'],
                    'image_data' => $processed['image_data'] ?? null,
                ]);
                $currentCount++;
            }
        }

        return redirect()->route('products.index')->with('status', 'Produit mis à jour.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Règle RG-14 : si présent dans des commandes futures, on archive.
        // Sinon, suppression ou archivage.
        $product->update(['status' => Product::STATUS_ARCHIVED]);

        return redirect()->route('products.index')->with('status', 'Produit archivé.');
    }

    /**
     * Suppression d'une image spécifique
     */
    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        if ($image->product_id === $product->id) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }

        return back()->with('status', 'Image supprimée.');
    }
}
