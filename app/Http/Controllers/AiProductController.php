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

        // 1. Analyse Vision multimodale & Génération de mises en scène contextuelles adaptées au produit
        $context = $visionService->generateContextualStagings($shop, $imageBinary);
        $variations = $context['stagings'] ?? [];

        // 2. Version Studio Blanc Épuré (E-commerce Marketplace standard 1:1)
        if (function_exists('imagecreatefromstring')) {
            $gd = @imagecreatefromstring($imageBinary);
            if ($gd) {
                $enhanced = $imageService->enhancePhotoQuality($gd);
                $studio = $imageService->formatStudioSquare($enhanced, 1000);
                $studioFilename = "ai_studio_white_{$uniq}.jpg";
                $studioPath = "shops/{$shop->id}/products_ai/{$studioFilename}";
                $tmp = tempnam(sys_get_temp_dir(), 'ai_std_');
                imagejpeg($studio, $tmp, 93);
                Storage::disk('public')->put($studioPath, file_get_contents($tmp));
                @unlink($tmp);
                imagedestroy($studio);

                $variations[] = [
                    'id' => 'studio_clean',
                    'type' => 'generated',
                    'title' => 'Studio Blanc Épuré',
                    'url' => Storage::disk('public')->url($studioPath),
                    'path' => $studioPath,
                    'selected' => empty($variations),
                    'is_source' => false,
                    'badge' => 'Studio Pro',
                ];
            }
        }

        // 3. Photo source originale
        $variations[] = [
            'id' => 'source',
            'type' => 'source',
            'title' => 'Photo source',
            'url' => $sourceUrl,
            'path' => $sourcePath,
            'selected' => false,
            'is_source' => true,
            'badge' => 'Originale',
        ];

        // Journalisation de la vérification qualité (+12 crédits)
        $shop->increment('ai_credits_used', 12);
        AiGeneration::create([
            'shop_id' => $shop->id,
            'type' => 'ai_quality_check',
            'step' => 'contextual_staging',
            'model_used' => 'zanika-lifestyle-vision',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_estimated_fcfa' => 12,
            'duration_ms' => 1200,
            'status' => 'success',
            'input_summary' => "Mise en scène contextuelle [{$context['category']}] : " . ($context['product_title'] ?? 'Article'),
        ]);

        return response()->json([
            'status' => 'success',
            'category' => $context['category'] ?? 'general',
            'product_title' => $context['product_title'] ?? null,
            'message' => 'Mise en scène et décor de shooting créés avec succès',
            'variations' => $variations,
        ]);
    }

    /**
     * Génère une variante de décor supplémentaire (+6 crédits).
     */
    public function generateVariant(Request $request, AiVisionService $visionService): JsonResponse
    {
        $request->validate([
            'photo_path' => 'nullable|string',
            'photo_base64' => 'nullable|string',
        ]);

        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? \App\Models\Shop::first() : null);
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        $uniq = uniqid();
        $newFilename = "ai_variant_lifestyle_{$uniq}";

        $binary = null;
        if ($request->filled('photo_path') && Storage::disk('public')->exists($request->input('photo_path'))) {
            $binary = Storage::disk('public')->get($request->input('photo_path'));
        } elseif ($request->filled('photo_base64')) {
            $base64 = $request->input('photo_base64');
            $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        }

        $prompt = "Commercial fashion e-commerce lookbook photography: clothing items displayed on a designer clothes rack in an elegant boutique showroom with soft ambient lighting, architectural backdrop, warm oak wooden floor, crisp sharp details, 8k";
        $title = "Variante Showroom Spéciale";

        if ($binary) {
            $context = $visionService->generateContextualStagings($shop, $binary);
            if (! empty($context['scenes']['scene_2']['prompt'])) {
                $prompt = $context['scenes']['scene_2']['prompt'] . ", alternative dynamic camera angle, warm natural daylight, 8k";
                $title = ($context['scenes']['scene_2']['title'] ?? 'Variante') . " (Angle 2)";
            }
        }

        $staged = $visionService->renderFluxScene($shop, $prompt, $newFilename);

        $shop->increment('ai_credits_used', 6);

        if ($staged) {
            return response()->json([
                'status' => 'success',
                'variation' => [
                    'id' => 'gen_' . $uniq,
                    'type' => 'generated',
                    'title' => $title,
                    'url' => $staged['url'],
                    'path' => $staged['path'],
                    'selected' => true,
                    'is_source' => false,
                    'badge' => 'IA Studio',
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
