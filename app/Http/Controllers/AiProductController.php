<?php

namespace App\Http\Controllers;

use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Ai\AiVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AiProductController extends Controller
{
    /**
     * Analyse une photo de produit déposée par le commerçant et pré-remplit la fiche produit.
     */
    public function analyzePhoto(Request $request, AiVisionService $visionService): JsonResponse
    {
        $request->validate([
            'photo' => 'nullable|file|image|max:10240', // 10 Mo max
            'photo_base64' => 'nullable|string',
            'user_hint' => 'nullable|string|max:200',
        ]);

        $shop = $request->user()->shop;
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        $imageBinary = null;
        $tempPhotoUrl = null;
        $tempPhotoPath = null;

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $imageBinary = file_get_contents($file->getRealPath());

            // Sauvegarder la photo pour pouvoir l'attacher au produit lors de la soumission finale
            $filename = 'ai_draft_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $tempPhotoPath = "shops/{$shop->id}/products/{$filename}";
            Storage::disk('public')->put($tempPhotoPath, $imageBinary);
            $tempPhotoUrl = Storage::disk('public')->url($tempPhotoPath);
        } elseif ($request->filled('photo_base64')) {
            $base64 = $request->input('photo_base64');
            $imageBinary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));

            $filename = 'ai_draft_' . uniqid() . '.jpg';
            $tempPhotoPath = "shops/{$shop->id}/products/{$filename}";
            Storage::disk('public')->put($tempPhotoPath, $imageBinary);
            $tempPhotoUrl = Storage::disk('public')->url($tempPhotoPath);
        }

        if (! $imageBinary) {
            return response()->json(['error' => 'Veuillez fournir une photo valide.'], 422);
        }

        $userHint = $request->input('user_hint');
        $analysis = $visionService->analyzeProductPhoto($imageBinary, $shop, $userHint);

        // Si une catégorie correspondante existe dans la boutique, renvoyer son ID
        $matchedCategoryId = null;
        if (! empty($analysis['category_name'])) {
            $cat = Category::where('shop_id', $shop->id)
                ->where('name', 'like', '%' . trim($analysis['category_name']) . '%')
                ->first();
            $matchedCategoryId = $cat?->id;
        }

        return response()->json([
            'status' => 'success',
            'analysis' => $analysis,
            'matched_category_id' => $matchedCategoryId,
            'temp_photo_path' => $tempPhotoPath,
            'temp_photo_url' => $tempPhotoUrl,
        ]);
    }

    /**
     * Optimise une fiche produit existante (titre, SEO, description, prix barré).
     */
    public function optimizeProduct(Request $request, Product $product, AiVisionService $visionService): JsonResponse
    {
        $shop = $request->user()->shop;
        if (! $shop || $product->shop_id !== $shop->id) {
            return response()->json(['error' => 'Non autorisé.'], 403);
        }

        $result = $visionService->optimizeExistingProduct($product, $shop);

        // Si l'utilisateur a cliqué sur "Appliquer directement"
        if ($request->boolean('apply') && $result['status'] === 'success') {
            if (! empty($result['optimized_title'])) {
                $product->title = $result['optimized_title'];
            }
            if (! empty($result['optimized_description'])) {
                $product->description = $result['optimized_description'];
            }
            if (! empty($result['suggested_compare_price']) && $result['suggested_compare_price'] > $product->price) {
                $product->compare_at_price = (int) $result['suggested_compare_price'];
            }
            $product->save();

            $result['applied'] = true;
        }

        return response()->json($result);
    }

    /**
     * Espace Studio Visuel IA pour les affiches, bannières et mises en situation.
     */
    public function studio(Request $request): View
    {
        $shop = $request->user()->shop;
        if (! $shop) {
            abort(403);
        }

        $products = $shop->products()->where('status', Product::STATUS_ACTIVE)->get(['id', 'title', 'price']);
        
        // Historique des visuels générés par l'IA pour cette boutique
        $recentVisuals = AiGeneration::where('shop_id', $shop->id)
            ->where('type', 'ai_visual_generation')
            ->latest('id')
            ->take(12)
            ->get();

        return view('ai.studio', [
            'shop' => $shop,
            'products' => $products,
            'recentVisuals' => $recentVisuals,
        ]);
    }

    /**
     * Génère un visuel publicitaire IA avec le moteur Flux.
     */
    public function generateVisual(Request $request, AiVisionService $visionService): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:300',
            'style' => 'required|string|in:studio_minimalist,luxury_showroom,lifestyle_urban,flash_sale,tropical_african',
            'format' => 'required|string|in:1:1,9:16,16:9',
            'product_id' => 'nullable|integer',
        ]);

        $shop = $request->user()->shop;
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        $result = $visionService->generateMarketingVisual($shop, $request->all());

        return response()->json($result);
    }
}
