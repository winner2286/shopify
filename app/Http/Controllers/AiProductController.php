<?php

namespace App\Http\Controllers;

use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Ai\AiVisionService;
use App\Services\Ai\PhotoroomService;
use App\Services\Ai\ProductStudioService;
use App\Services\PublicMediaStorage;
use App\Support\SearchHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AiProductController extends Controller
{
    private function withVisualCredits(Shop $shop, \Closure $operation): ?array
    {
        $reserved = $shop->hasEnoughAiCredits(1);
        if ($reserved) {
            $shop->consumeAiCredits(1);
        }
        try {
            $result = $operation();
        } catch (\Throwable $exception) {
            if ($reserved) {
                $shop->decrement('ai_credits_used');
                $shop->refresh();
            }
            throw $exception;
        }
        $succeeded = ($result['status'] ?? '') === 'success';
        $photoroom = ($result['engine'] ?? '') === 'photoroom-ai';
        if ($reserved && (! $succeeded || $photoroom)) {
            $shop->decrement('ai_credits_used');
            $shop->refresh();
        }
        if ($succeeded && ! $photoroom && ! $reserved) {
            throw ValidationException::withMessages(['credits' => 'Crédits IA insuffisants pour le moteur local.']);
        }

        return $result;
    }

    protected function isDecodableImageBinary(?string $binary): bool
    {
        if (! is_string($binary) || trim($binary) === '') {
            return false;
        }

        if (strlen($binary) > 15 * 1024 * 1024) {
            return false;
        }
        $dimensions = @getimagesizefromstring($binary);
        if (! $dimensions || $dimensions[0] > 6000 || $dimensions[1] > 6000
            || $dimensions[0] * $dimensions[1] > 16000000) {
            return false;
        }

        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($binary);
            if (! $img) {
                return false;
            }
            imagedestroy($img);

            return true;
        }

        return str_starts_with($binary, "\x89PNG")
            || str_starts_with($binary, "\xFF\xD8\xFF")
            || str_starts_with($binary, 'RIFF');
    }

    /**
     * Analyse une photo de produit déposée par le commerçant et pré-remplit la fiche produit.
     */
    public function analyzePhoto(Request $request, AiVisionService $visionService): JsonResponse
    {
        $request->validate([
            'photo' => 'nullable|file|max:15360', // 15 Mo max (MIME mobiles tolérés puis vérifiés en binaire)
            'photo_base64' => 'nullable|string',
            'user_hint' => 'nullable|string|max:200',
        ]);

        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? Shop::first() : null);
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        if (! $shop->hasEnoughAiCredits(1)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Crédits IA mensuels épuisés.',
                'message' => 'Crédits IA mensuels épuisés.',
            ], 402);
        }

        $imageBinary = null;
        $tempPhotoUrl = null;
        $tempPhotoPath = null;

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $imageBinary = file_get_contents($file->getRealPath());

            // Sauvegarder la photo pour pouvoir l'attacher au produit lors de la soumission finale
            $guessedExt = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
            $ext = in_array($guessedExt, ['jpg', 'jpeg', 'png', 'webp'], true) ? $guessedExt : 'jpg';
            $filename = 'ai_draft_'.uniqid().'.'.$ext;
            $tempPhotoPath = "shops/{$shop->id}/products/{$filename}";
            $tempPhotoUrl = Storage::disk('public')->url($tempPhotoPath);
        } elseif ($request->filled('photo_base64')) {
            $base64 = $request->input('photo_base64');
            $imageBinary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));

            $filename = 'ai_draft_'.uniqid().'.jpg';
            $tempPhotoPath = "shops/{$shop->id}/products/{$filename}";
            $tempPhotoUrl = Storage::disk('public')->url($tempPhotoPath);
        }

        if (! $this->isDecodableImageBinary($imageBinary)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Format d\'image non supporté ou fichier invalide. Utilisez une photo JPG, PNG ou WebP.',
            ], 422);
        }

        app(PublicMediaStorage::class)->put($tempPhotoPath, $imageBinary);
        $userHint = $request->input('user_hint');
        $analysis = $shop->withAiCredits(1, fn () => $visionService->analyzeProductPhoto($imageBinary, $shop, $userHint));

        // Si une catégorie correspondante existe dans la boutique, renvoyer son ID
        $matchedCategoryId = null;
        if (! empty($analysis['category_name'])) {
            $catQuery = Category::where('shop_id', $shop->id);
            SearchHelper::whereLike($catQuery, 'name', trim($analysis['category_name']));
            $cat = $catQuery->first();
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
     * Vérification qualité IA & Studio Photo Professionnel :
     * Analyse le produit, sépare fidèlement le sujet sans altération (Règle 4.A),
     * adapte le décor et l'éclairage selon la catégorie et génère les packshots comparatifs 1:1.
     */
    public function verifyQuality(Request $request, ProductStudioService $studioService): JsonResponse
    {
        $request->validate([
            'photo' => 'nullable|file|max:15360',
            'photo_base64' => 'nullable|string',
            'photo_path' => 'nullable|string',
            'category_hint' => 'nullable|string|max:200',
            'style' => 'nullable|string|in:retouch,studio_white,studio_ambient,studio_dark,lifestyle,scene_template,custom_background',
            'scene_prompt' => 'nullable|string|max:500',
            'scene_template' => 'nullable|string|in:podium,parquet,spa,marble,dressing',
            'background' => 'nullable|required_if:style,custom_background|image|mimes:jpeg,jpg,png,webp|max:5120',
            'subject_mask' => 'nullable|image|mimes:png|max:5120',
            'brightness' => 'nullable|integer|between:-15,15',
            'contrast' => 'nullable|integer|between:-10,10',
            'sharpen' => 'nullable|boolean',
            'presentation' => 'nullable|in:auto,hanging,flat,standing',
            'crop_top' => 'nullable|integer|between:0,35',
            'crop_right' => 'nullable|integer|between:0,35',
            'crop_bottom' => 'nullable|integer|between:0,35',
            'crop_left' => 'nullable|integer|between:0,35',
            'position_x' => 'nullable|integer|between:10,90',
            'position_y' => 'nullable|integer|between:15,90',
            'scale' => 'nullable|integer|between:20,85',
            'shadow' => 'nullable|integer|between:0,50',
        ]);

        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? Shop::first() : null);
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        if (! $shop->hasEnoughAiCredits(12)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Crédits IA insuffisants (12 crédits requis pour le studio packshot).',
            ], 402);
        }

        $imageBinary = null;
        if ($request->hasFile('photo')) {
            $imageBinary = file_get_contents($request->file('photo')->getRealPath());
        } elseif ($request->filled('photo_base64')) {
            $base64 = $request->input('photo_base64');
            $imageBinary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        } elseif ($request->filled('photo_path')) {
            $photoPath = (string) $request->input('photo_path');
            if (! $shop->ownsPublicPath($photoPath)) {
                return response()->json(['error' => 'Chemin photo non autorisé.'], 403);
            }
            if (Storage::disk('public')->exists($photoPath)) {
                $imageBinary = Storage::disk('public')->get($photoPath);
            }
        }

        if (! $this->isDecodableImageBinary($imageBinary)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Format d\'image non supporté ou fichier invalide. Utilisez une photo JPG, PNG ou WebP.',
            ], 422);
        }

        try {
            $categoryHint = $request->input('category_hint');
            $options = $request->only([
                'scene_template', 'brightness', 'contrast', 'sharpen', 'position_x', 'position_y', 'scale', 'shadow',
                'presentation', 'crop_top', 'crop_right', 'crop_bottom', 'crop_left',
            ]);
            foreach (['background', 'subject_mask'] as $field) {
                if ($request->hasFile($field)) {
                    $binary = file_get_contents($request->file($field)->getRealPath());
                    if (! $this->isDecodableImageBinary($binary)) {
                        throw ValidationException::withMessages([$field => 'Image invalide ou trop grande. Choisissez un fichier JPG, PNG ou WebP de moins de 16 mégapixels.']);
                    }
                    $options[$field] = $binary;
                }
            }
            $studioResult = $shop->withAiCredits(12, fn () => $studioService->previewProductPhoto(
                $shop, $imageBinary, $request->input('style') ?: 'retouch', $categoryHint, (string) $request->input('scene_prompt', ''), $options
            ));

            // Journalisation et débit des crédits isolés pour ne jamais bloquer la restitution des packshots
            try {
                $studioEngine = $studioResult['engine'] ?? 'zanika-product-studio';
                $studioCostFcfa = $studioEngine === 'photoroom-ai'
                    ? ($shop->canUsePhotoroomGenerativeScene() ? 62 : 13)
                    : 6;
                $openAiCostUsd = null;
                if ($studioEngine === 'openai-image') {
                    $usage = $studioResult['usage'] ?? [];
                    $openAiCostUsd = (($usage['input_tokens'] ?? 0) * 8 + ($usage['output_tokens'] ?? 0) * 30) / 1_000_000;
                    $studioCostFcfa = (int) ceil($openAiCostUsd * (float) config('services.openai_images.usd_to_fcfa_estimate', 650));
                }

                AiGeneration::create([
                    'shop_id' => $shop->id,
                    'type' => 'ai_quality_check',
                    'step' => 'studio_packshot',
                    'model_used' => $studioResult['model_used'] ?? $studioEngine,
                    'prompt_tokens' => $studioResult['usage']['input_tokens'] ?? 0,
                    'completion_tokens' => $studioResult['usage']['output_tokens'] ?? 0,
                    'cost_estimated_fcfa' => $studioCostFcfa,
                    'duration_ms' => $studioResult['duration_ms'] ?? 1200,
                    'status' => 'success',
                    'output_data' => ['usage' => $studioResult['usage'] ?? [], 'engine' => $studioEngine, 'cost_estimated_usd' => $openAiCostUsd],
                    'input_summary' => "Studio Packshot [{$studioResult['category']}] : ".($studioResult['product_title'] ?? 'Article'),
                ]);
            } catch (\Throwable $logError) {
                Log::warning('Studio Photo IA credit/audit log skipped: '.$logError->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'engine' => $studioResult['engine'] ?? 'zanika-product-studio',
                'cutout_method' => $studioResult['cutout_method'] ?? null,
                'category' => $studioResult['category'] ?? 'general',
                'product_title' => $studioResult['product_title'] ?? null,
                'cutout_path' => $studioResult['cutout_path'] ?? null,
                'cutout_url' => $studioResult['cutout_url'] ?? null,
                'crop_frame' => $studioResult['crop_frame'] ?? null,
                'source_path' => $studioResult['source_path'] ?? null,
                'source_url' => $studioResult['source_url'] ?? null,
                'variations' => $studioResult['variations'] ?? [],
                'quality_audit' => $studioResult['quality_audit'] ?? null,
                'duration_ms' => $studioResult['duration_ms'] ?? null,
                'message' => 'Aperçu prêt. Comparez-le à votre photo avant de valider.',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Studio Photo IA verifyQuality failure: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'error' => 'Impossible de traiter cette photo avec le Studio IA pour le moment. Réessayez avec une image JPG ou PNG.',
            ], 500);
        }
    }

    /**
     * Génère une variante de décor studio supplémentaire (+6 crédits).
     */
    public function generateVariant(Request $request, ProductStudioService $studioService): JsonResponse
    {
        $request->validate([
            'photo' => 'nullable|file|max:15360',
            'photo_path' => 'nullable|string',
            'photo_base64' => 'nullable|string',
            'cutout_path' => 'nullable|string',
            'style' => 'nullable|string',
            'category' => 'nullable|string',
        ]);

        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? Shop::first() : null);
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        if (! $shop->hasEnoughAiCredits(6)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Crédits IA insuffisants (6 crédits requis pour générer une variante studio).',
            ], 402);
        }

        $cutoutPngBinary = null;

        // 1. Récupérer le PNG détouré si déjà disponible
        if ($request->filled('cutout_path')) {
            $cutoutPath = (string) $request->input('cutout_path');
            if (! $shop->ownsPublicPath($cutoutPath)) {
                return response()->json(['error' => 'Chemin détourage non autorisé.'], 403);
            }
            if (Storage::disk('public')->exists($cutoutPath)) {
                $cutoutPngBinary = Storage::disk('public')->get($cutoutPath);
            }
        }

        // 2. Sinon extraire le détourage depuis la photo
        if (! $cutoutPngBinary) {
            $binary = null;
            if ($request->filled('photo_path')) {
                $photoPath = (string) $request->input('photo_path');
                if (! $shop->ownsPublicPath($photoPath)) {
                    return response()->json(['error' => 'Chemin photo non autorisé.'], 403);
                }
                if (Storage::disk('public')->exists($photoPath)) {
                    $binary = Storage::disk('public')->get($photoPath);
                }
            } elseif ($request->filled('photo_base64')) {
                $base64 = $request->input('photo_base64');
                $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
            } elseif ($request->hasFile('photo')) {
                $binary = file_get_contents($request->file('photo')->getRealPath());
            }

            if ($binary) {
                $extracted = $studioService->extractCutout($binary, $shop);
                $cutoutPngBinary = $extracted['binary'] ?? null;
                if (($extracted['method'] ?? '') === 'photoroom_v1_segment') {
                    $shop->consumePhotoroomCredits(1);
                }
            }
        }

        if ($cutoutPngBinary) {
            $style = $request->input('style', 'studio_dark');
            $category = $request->input('category', 'general');

            $variation = $shop->withAiCredits(6, fn () => $studioService->renderVariant($shop, $cutoutPngBinary, $style, $category));

            if ($variation) {
                AiGeneration::create([
                    'shop_id' => $shop->id,
                    'type' => 'ai_quality_check',
                    'step' => 'studio_variant',
                    'model_used' => 'zanika-product-studio',
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'cost_estimated_fcfa' => 6,
                    'duration_ms' => 300,
                    'status' => 'success',
                    'input_summary' => "Variante studio [{$style}] : {$category}",
                ]);

                return response()->json([
                    'status' => 'success',
                    'variation' => $variation,
                ]);
            }
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

        if (! $shop->hasEnoughAiCredits(1)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Crédits IA mensuels épuisés.',
            ], 402);
        }

        $result = $shop->withAiCredits(1, fn () => $visionService->optimizeExistingProduct($product, $shop));

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
    public function studio(Request $request, PhotoroomService $photoroom): View|RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? Shop::first() : null);
        if (! $shop) {
            return redirect()->route('dashboard')->with('status', "Veuillez d'abord configurer une boutique.");
        }

        $products = $shop->products()
            ->where('status', Product::STATUS_ACTIVE)
            ->with(['primaryImage', 'images'])
            ->get(['id', 'title', 'price']);

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
            'photoroomConfigured' => $photoroom->isConfigured(),
            'photoroomCreditsRemaining' => $shop->photoroomCreditsRemaining(),
            'photoroomCreditsMonthly' => $shop->photoroomCreditsMonthly(),
            'aiCreditsRemaining' => $shop->aiCreditsRemaining(),
            'aiCreditsMonthly' => $shop->aiCreditsMonthly(),
        ]);
    }

    /**
     * Génère un visuel publicitaire IA (Photoroom Studio si photo/produit sélectionné, sinon moteur Flux).
     */
    public function generateVisual(Request $request, AiVisionService $visionService): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:300',
            'style' => 'required|string|in:studio_minimalist,luxury_showroom,lifestyle_urban,flash_sale,tropical_african',
            'format' => 'required|string|in:1:1,9:16,16:9',
            'product_id' => 'nullable|integer',
            'badge_text' => 'nullable|string|max:40',
            'image' => 'nullable|file|image|max:10240',
        ]);

        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? Shop::first() : null);
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        if (! $shop->hasEnoughAiCredits(1) && ! $shop->hasEnoughPhotoroomCredits(1)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Crédits IA et quota Photoroom mensuels épuisés.',
            ], 402);
        }

        $sourceImageBinary = null;
        if ($request->hasFile('image')) {
            $sourceImageBinary = file_get_contents($request->file('image')->getRealPath()) ?: null;
        }

        if ($sourceImageBinary === null && ! $shop->hasEnoughAiCredits(1)) {
            return response()->json(['status' => 'error', 'error' => 'Crédits IA insuffisants.'], 402);
        }
        $result = $this->withVisualCredits($shop, fn () => $visionService->generateMarketingVisual(
            $shop,
            $request->except('image'),
            $sourceImageBinary
        ));

        if (($result['status'] ?? '') === 'success') {
            $shop->refresh();
            $result['photoroom_credits_remaining'] = $shop->photoroomCreditsRemaining();
            $result['ai_credits_remaining'] = $shop->aiCreditsRemaining();
        }

        return response()->json($result);
    }

    /**
     * Détourage / Sublimation 1-Clic (Photoroom AI + fallback local) pour Affiches Promo & Vidéos Pub.
     */
    public function cutoutForAd(Request $request, ProductStudioService $studioService): JsonResponse
    {
        $request->validate([
            'product_id' => 'nullable|integer',
            'image' => 'nullable|file|max:15360',
            'image_base64' => 'nullable|string',
            'mode' => 'nullable|string|in:transparent,studio_white',
            'save_to_product' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? Shop::first() : null);
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        if (! $shop->hasEnoughPhotoroomCredits(1) && ! $shop->hasEnoughAiCredits(1)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Quotas Photoroom et crédits IA mensuels épuisés.',
            ], 402);
        }

        $imageBinary = null;
        $product = null;

        if ($request->filled('product_id')) {
            $product = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->with(['primaryImage', 'images'])
                ->find((int) $request->input('product_id'));

            if (! $product) {
                return response()->json(['error' => 'Produit introuvable ou non autorisé.'], 403);
            }
        }

        if ($request->hasFile('image')) {
            $imageBinary = file_get_contents($request->file('image')->getRealPath()) ?: null;
        } elseif ($request->filled('image_base64')) {
            $base64 = (string) $request->input('image_base64');
            $decoded = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64), true);
            $imageBinary = is_string($decoded) && $decoded !== '' ? $decoded : null;
        } elseif ($product) {
            $primary = $product->primaryImage ?? $product->images->first();
            if ($primary) {
                if (! empty($primary->path) && Storage::disk('public')->exists($primary->path)) {
                    $imageBinary = Storage::disk('public')->get($primary->path);
                } elseif (! empty($primary->image_data) && is_string($primary->image_data)) {
                    $clean = preg_replace('#^data:image/\w+;base64,#i', '', $primary->image_data);
                    $decoded = base64_decode((string) $clean, true);
                    $imageBinary = is_string($decoded) && $decoded !== '' ? $decoded : null;
                }
            }
        }

        if (! $this->isDecodableImageBinary($imageBinary)) {
            return response()->json([
                'success' => false,
                'error' => 'Veuillez sélectionner un produit possédant une photo ou importer une image valide (JPG, PNG, WebP).',
            ], 422);
        }

        $mode = (string) $request->input('mode', 'transparent');
        $cutout = $this->withVisualCredits($shop, fn () => $studioService->cutoutTransparent($shop, $imageBinary, $mode));

        if (! $cutout || ($cutout['status'] ?? '') !== 'success') {
            return response()->json([
                'success' => false,
                'error' => 'Impossible de détourer cette image pour le moment.',
            ], 500);
        }

        $shop->refresh();
        AiGeneration::create([
            'shop_id' => $shop->id,
            'type' => 'ai_photoroom_cutout',
            'step' => $cutout['cutout_method'] ?? 'photoroom_cutout',
            'model_used' => $cutout['engine'] ?? 'zanika-product-studio',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_estimated_fcfa' => ($cutout['engine'] ?? '') === 'photoroom-ai' ? 13 : 1,
            'duration_ms' => 450,
            'status' => 'success',
            'input_summary' => $product
                ? "Détourage 1-clic [{$mode}] pour le produit #{$product->id} ({$product->title})"
                : "Détourage 1-clic [{$mode}] depuis image importée",
            'output_data' => [
                'path' => $cutout['path'],
                'mode' => $mode,
                'engine' => $cutout['engine'],
            ],
        ]);

        if ($request->boolean('save_to_product') && $product) {
            $rawB64 = preg_replace('#^data:image/\w+;base64,#i', '', (string) $cutout['data_uri']);
            $product->images()->increment('sort_order');
            $product->images()->create([
                'path' => $cutout['path'],
                'sort_order' => 0,
                'size_bytes' => Storage::disk('public')->exists($cutout['path'])
                    ? Storage::disk('public')->size($cutout['path'])
                    : 0,
                'image_data' => $rawB64,
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'url' => $cutout['url'],
            'path' => $cutout['path'],
            'data_uri' => $cutout['data_uri'],
            'engine' => $cutout['engine'],
            'cutout_method' => $cutout['cutout_method'],
            'photoroom_credits_remaining' => $shop->photoroomCreditsRemaining(),
            'ai_credits_remaining' => $shop->aiCreditsRemaining(),
            'message' => $cutout['engine'] === 'photoroom-ai'
                ? 'Photo détourée et sublimée avec Photoroom IA !'
                : 'Photo détourée avec Zanika Studio !',
        ]);
    }
}
