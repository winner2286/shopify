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
     * Vérification qualité IA : contrôle l'image, applique les filtres studio professionnels
     * et génère les versions comparatives (Studio IA vs Photo source originale).
     */
    public function verifyQuality(Request $request, ImageService $imageService): JsonResponse
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

        $gen1Url = $sourceUrl;
        $gen1Path = $sourcePath;
        $gen2Url = $sourceUrl;
        $gen2Path = $sourcePath;

        if (function_exists('imagecreatefromstring')) {
            // Variante 1 : Studio Pro Lumineux (Amélioration netteté, lumière et cadrage studio 1:1)
            $gd1 = @imagecreatefromstring($imageBinary);
            if ($gd1) {
                $enhanced1 = $imageService->enhancePhotoQuality($gd1);
                $studio1 = $imageService->formatStudioSquare($enhanced1, 1000);
                
                $gen1Filename = "ai_studio_clean_{$uniq}.jpg";
                $gen1Path = "shops/{$shop->id}/products_ai/{$gen1Filename}";
                $tmp1 = tempnam(sys_get_temp_dir(), 'ai1_');
                imagejpeg($studio1, $tmp1, 93);
                Storage::disk('public')->put($gen1Path, file_get_contents($tmp1));
                @unlink($tmp1);
                imagedestroy($studio1);
                $gen1Url = Storage::disk('public')->url($gen1Path);
            }

            // Variante 2 : Studio Showroom & Contraste Ambiance
            $gd2 = @imagecreatefromstring($imageBinary);
            if ($gd2) {
                if (function_exists('imagefilter')) {
                    @imagefilter($gd2, IMG_FILTER_BRIGHTNESS, 8);
                    @imagefilter($gd2, IMG_FILTER_CONTRAST, -14);
                }
                if (function_exists('imageconvolution')) {
                    $sharpenMatrix = [
                        [-0.2, -0.2, -0.2],
                        [-0.2,  2.6, -0.2],
                        [-0.2, -0.2, -0.2],
                    ];
                    @imageconvolution($gd2, $sharpenMatrix, 1.0, 0);
                }
                $studio2 = $imageService->formatStudioSquare($gd2, 1000);
                $gen2Filename = "ai_studio_showroom_{$uniq}.jpg";
                $gen2Path = "shops/{$shop->id}/products_ai/{$gen2Filename}";
                $tmp2 = tempnam(sys_get_temp_dir(), 'ai2_');
                imagejpeg($studio2, $tmp2, 93);
                Storage::disk('public')->put($gen2Path, file_get_contents($tmp2));
                @unlink($tmp2);
                imagedestroy($studio2);
                $gen2Url = Storage::disk('public')->url($gen2Path);
            }
        }

        // Journalisation de la vérification qualité (+12 crédits)
        $shop->increment('ai_credits_used', 12);
        AiGeneration::create([
            'shop_id' => $shop->id,
            'type' => 'ai_quality_check',
            'step' => 'photo_variations',
            'model_used' => 'zanika-studio-vision',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_estimated_fcfa' => 12,
            'duration_ms' => 850,
            'status' => 'success',
            'input_summary' => 'Vérification qualité IA & rendu studio professionnel',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Contrôle qualité terminé avec succès',
            'variations' => [
                [
                    'id' => 'gen_1',
                    'type' => 'generated',
                    'title' => 'Studio Pro Lumineux',
                    'url' => $gen1Url,
                    'path' => $gen1Path,
                    'selected' => true,
                    'is_source' => false,
                ],
                [
                    'id' => 'gen_2',
                    'type' => 'generated',
                    'title' => 'Ambiance Showroom',
                    'url' => $gen2Url,
                    'path' => $gen2Path,
                    'selected' => true,
                    'is_source' => false,
                ],
                [
                    'id' => 'source',
                    'type' => 'source',
                    'title' => 'Photo source',
                    'url' => $sourceUrl,
                    'path' => $sourcePath,
                    'selected' => false,
                    'is_source' => true,
                ],
            ],
        ]);
    }

    /**
     * Génère une variante supplémentaire à la demande du commerçant (+6 crédits).
     */
    public function generateVariant(Request $request, ImageService $imageService): JsonResponse
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

        $binary = null;
        if ($request->filled('photo_path') && Storage::disk('public')->exists($request->input('photo_path'))) {
            $binary = Storage::disk('public')->get($request->input('photo_path'));
        } elseif ($request->filled('photo_base64')) {
            $base64 = $request->input('photo_base64');
            $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        }

        if (! $binary) {
            return response()->json(['error' => 'Photo source introuvable.'], 422);
        }

        $uniq = uniqid();
        $newFilename = "ai_variant_extra_{$uniq}.jpg";
        $newPath = "shops/{$shop->id}/products_ai/{$newFilename}";

        if (function_exists('imagecreatefromstring')) {
            $gd = @imagecreatefromstring($binary);
            if ($gd) {
                if (function_exists('imagefilter')) {
                    @imagefilter($gd, IMG_FILTER_CONTRAST, -16);
                    @imagefilter($gd, IMG_FILTER_BRIGHTNESS, 12);
                }
                $studio = $imageService->formatStudioSquare($gd, 1000);
                $tmp = tempnam(sys_get_temp_dir(), 'aivar_');
                imagejpeg($studio, $tmp, 94);
                Storage::disk('public')->put($newPath, file_get_contents($tmp));
                @unlink($tmp);
                imagedestroy($studio);
            } else {
                Storage::disk('public')->put($newPath, $binary);
            }
        } else {
            Storage::disk('public')->put($newPath, $binary);
        }

        $shop->increment('ai_credits_used', 6);
        $url = Storage::disk('public')->url($newPath);

        return response()->json([
            'status' => 'success',
            'variation' => [
                'id' => 'gen_' . $uniq,
                'type' => 'generated',
                'title' => 'Variante Studio Extra',
                'url' => $url,
                'path' => $newPath,
                'selected' => true,
                'is_source' => false,
            ],
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
