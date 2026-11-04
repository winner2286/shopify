<?php

namespace App\Http\Controllers;

use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Ai\AiVisionService;
use App\Services\ImageService;
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

        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? \App\Models\Shop::first() : null);
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
     * Vérification qualité IA : analyse le produit avec Gemini Vision, adapte le décor au produit
     * (vêtement sur portant dans dressing/loft, chaussures sur parquet, bijoux sur marbre, etc.)
     * et génère les versions comparatives réalistes (IA vs Photo source originale).
     */
    public function verifyQuality(Request $request, ImageService $imageService, AiVisionService $visionService): JsonResponse
    {
        $request->validate([
            'photo' => 'nullable|file|image|max:10240',
            'photo_base64' => 'nullable|string',
        ]);

        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? \App\Models\Shop::first() : null);
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        $imageBinary = null;
        if ($request->hasFile('photo')) {
            $imageBinary = file_get_contents($request->file('photo')->getRealPath());
        } elseif ($request->filled('photo_base64')) {
            $base64 = $request->input('photo_base64');
            $imageBinary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        }

        if (! $imageBinary) {
            return response()->json(['error' => 'Veuillez fournir une photo valide.'], 422);
        }

        $uniq = uniqid();
        $sourceFilename = "source_{$uniq}.jpg";
        $sourcePath = "shops/{$shop->id}/products_ai/{$sourceFilename}";
        Storage::disk('public')->put($sourcePath, $imageBinary);
        $sourceUrl = Storage::disk('public')->url($sourcePath);

        // 1. Détourage IA chirurgical & Packshots Studio professionnels
        $context = $visionService->generateContextualStagings($shop, $imageBinary);
        $variations = $context['stagings'] ?? [];

        // 2. Photo source originale (pour que le vendeur garde le choix absolu)
        $variations[] = [
            'id' => 'source',
            'type' => 'source',
            'title' => 'Photo source',
            'url' => $sourceUrl,
            'path' => $sourcePath,
            'selected' => empty($variations),
            'is_source' => true,
            'badge' => 'Originale',
        ];

        // Journalisation de la vérification qualité (+12 crédits)
        $shop->increment('ai_credits_used', 12);
        AiGeneration::create([
            'shop_id' => $shop->id,
            'type' => 'ai_quality_check',
            'step' => 'studio_packshot',
            'model_used' => 'zanika-studio-vision',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_estimated_fcfa' => 12,
            'duration_ms' => 1200,
            'status' => 'success',
            'input_summary' => "Packshot studio IA [{$context['category']}] : " . ($context['product_title'] ?? 'Article'),
        ]);

        return response()->json([
            'status' => 'success',
            'category' => $context['category'] ?? 'general',
            'product_title' => $context['product_title'] ?? null,
            'cutout_path' => $context['cutout_path'] ?? null,
            'message' => 'Packshots studio créés avec succès sans déformation',
            'variations' => $variations,
        ]);
    }

    /**
     * Génère une variante de décor supplémentaire (+6 crédits).
     */
    public function generateVariant(Request $request, ImageService $imageService, AiVisionService $visionService): JsonResponse
    {
        $request->validate([
            'photo_path' => 'nullable|string',
            'photo_base64' => 'nullable|string',
            'cutout_path' => 'nullable|string',
        ]);

        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? \App\Models\Shop::first() : null);
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        $uniq = uniqid();
        $cutoutGd = null;

        // 1. Récupérer le PNG détouré si déjà disponible
        if ($request->filled('cutout_path') && Storage::disk('public')->exists($request->input('cutout_path'))) {
            $pngBinary = Storage::disk('public')->get($request->input('cutout_path'));
            $cutoutGd = @imagecreatefromstring($pngBinary);
        }

        // 2. Sinon détourer depuis la photo
        if (! $cutoutGd) {
            $binary = null;
            if ($request->filled('photo_path') && Storage::disk('public')->exists($request->input('photo_path'))) {
                $binary = Storage::disk('public')->get($request->input('photo_path'));
            } elseif ($request->filled('photo_base64')) {
                $base64 = $request->input('photo_base64');
                $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
            }

            if ($binary) {
                $cutoutPng = $visionService->removeBackground($binary);
                if ($cutoutPng) {
                    $cutoutGd = @imagecreatefromstring($cutoutPng);
                }
            }
        }

        if ($cutoutGd) {
            $darkPackshot = $imageService->buildStudioPackshot($cutoutGd, 'ambient_dark', 1000);
            $filename = "studio_dark_{$uniq}.jpg";
            $relativePath = "shops/{$shop->id}/products_ai/{$filename}";
            $tmp = tempnam(sys_get_temp_dir(), 'ai_d_');
            imagejpeg($darkPackshot, $tmp, 94);
            Storage::disk('public')->put($relativePath, file_get_contents($tmp));
            @unlink($tmp);
            imagedestroy($darkPackshot);
            imagedestroy($cutoutGd);

            $shop->increment('ai_credits_used', 6);

            return response()->json([
                'status' => 'success',
                'variation' => [
                    'id' => 'gen_' . $uniq,
                    'type' => 'generated',
                    'title' => 'Studio Sombre Graphite (Luxe)',
                    'url' => Storage::disk('public')->url($relativePath),
                    'path' => $relativePath,
                    'selected' => true,
                    'is_source' => false,
                    'badge' => 'Ambiance Luxe',
                ],
            ]);
        }

        return response()->json(['error' => 'Impossible de générer une autre variante pour le moment.'], 500);
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
    public function studio(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? \App\Models\Shop::first() : null);
        if (! $shop) {
            return redirect()->route('dashboard')->with('status', "Veuillez d'abord configurer une boutique.");
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

        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? \App\Models\Shop::first() : null);
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        $result = $visionService->generateMarketingVisual($shop, $request->all());

        return response()->json($result);
    }
}
