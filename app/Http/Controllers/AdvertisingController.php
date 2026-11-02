<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Product;
use App\Models\Shop;
use App\Services\AiAdvertisingService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdvertisingController extends Controller
{
    public function __construct(
        protected AiAdvertisingService $aiAdvertisingService
    ) {}

    /**
     * H-05 : Bibliothèque des publicités générées
     */
    public function index(Request $request)
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('dashboard');
        }

        $ads = $shop->advertisements()->with('product')->paginate(12);

        return view('advertising.index', compact('shop', 'ads'));
    }

    /**
     * Option 4 : Générateur d'Affiches Promo & Statuts WhatsApp 1-Clic
     */
    public function flyers(Request $request)
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('dashboard');
        }

        $products = $shop->products()
            ->where('status', Product::STATUS_ACTIVE)
            ->with(['primaryImage', 'images', 'categories'])
            ->get();

        $selectedProductId = (int) $request->query('produit_id');

        return view('advertising.flyers', compact('shop', 'products', 'selectedProductId'));
    }

    /**
     * H-01 : Formulaire de création de publicité vidéo par l'IA
     */
    public function create(Request $request)
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('dashboard');
        }

        $products = $shop->products()
            ->where('status', Product::STATUS_ACTIVE)
            ->with(['primaryImage', 'images', 'categories'])
            ->get();

        $categories = $shop->categories()
            ->where('is_visible', true)
            ->withCount(['products' => fn($q) => $q->where('status', Product::STATUS_ACTIVE)])
            ->get();

        $selectedProductId = (int) $request->query('produit_id');

        return view('advertising.create', compact('shop', 'products', 'categories', 'selectedProductId'));
    }

    /**
     * Génération de la publicité par l'IA
     */
    public function store(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        $validated = $request->validate([
            'angle' => ['nullable', 'string', 'in:promotions,nouveautes,bestsellers,coup_de_coeur,boutique'],
            'tone' => ['nullable', 'string', 'in:dynamique,luxe,fun,urgent,chaleureux,amical,premium'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'voice' => ['nullable', 'string', 'in:femme,homme,douce'],
            'target_duration' => ['nullable', 'integer', 'in:15,20,25'],
            'custom_message' => ['nullable', 'string', 'max:250'],
            'product_id' => ['nullable', 'integer'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'selected_product_ids' => ['nullable', 'array'],
            'selected_product_ids.*' => ['integer'],
            'channel' => ['nullable', 'string', 'in:whatsapp,tiktok,sms,instagram,facebook,general'],
            'objective' => ['nullable', 'string'],
            'promo_price' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! empty($validated['product_id'])) {
            $productExists = $shop->products()->where('id', $validated['product_id'])->exists();
            if (! $productExists) {
                abort(404, 'Produit introuvable.');
            }
        }

        $channel = $validated['channel'] ?? 'tiktok';

        // Séparation des fonctionnalités publicitaires IA selon la formule
        // (toutes les publicités générées ici sont des vidéos 9:16 : même règle quelle que soit la plateforme de diffusion)
        if (! $shop->canUseFeature('tiktok_ads')) {
            return back()->withInput()->with('error', "La génération de scripts vidéo TikTok & visuels Instagram est réservée aux formules Pro et Premium. Passez à la formule Pro (15 000 FCFA) pour débloquer ces canaux !");
        }

        if ($shop->aiCreditsRemaining() <= 0) {
            return back()->withInput()->with('error', "Quota mensuel de crédits IA atteint ({$shop->aiCreditsMonthly()} crédits sur votre formule {$shop->currentPlan()->name}). Passez au forfait supérieur pour continuer à générer des publicités !");
        }

        try {
            $ad = $this->aiAdvertisingService->generate($shop, $validated);

            return redirect()->route('ads.show', $ad)->with('status', 'Votre publicité vidéo 9:16 a été générée avec succès à partir de vos vraies données !');

        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Régénère les textes et scripts de l'annonce avec l'IA adaptée
     */
    public function regenerate(Request $request, Advertisement $ad)
    {
        $shop = $request->user()->shop;

        if ($ad->shop_id !== $shop->id) {
            abort(403);
        }

        try {
            $ad = $this->aiAdvertisingService->regenerateAd($ad, $shop, $request->all());

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Variation régénérée avec succès !',
                    'scripts' => $ad->generated_scripts,
                    'visual_config' => $ad->visual_config,
                ]);
            }

            return redirect()->route('ads.show', $ad)->with('status', 'Votre publicité vidéo a été régénérée avec une nouvelle variation !');
        } catch (Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Erreur lors de la régénération : ' . $e->getMessage());
        }
    }

    /**
     * Change la plateforme de diffusion (TikTok, Instagram, WhatsApp, Facebook, Général) :
     * la phrase finale de la voix indique où trouver le lien de la boutique.
     */
    public function updatePlatform(Request $request, Advertisement $ad)
    {
        $shop = $request->user()->shop;

        if ($ad->shop_id !== $shop->id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'platform' => ['required', 'string', 'in:tiktok,instagram,whatsapp,facebook,general'],
        ]);

        $videoAdGen = app(\App\Services\VideoAdGeneratorService::class);
        $platform = $videoAdGen->normalizePlatform($validated['platform']);

        $vc = $ad->visual_config ?? [];
        $vc['platform'] = $platform;
        $vc['shop_url'] = $shop->public_url;

        $scripts = $videoAdGen->applyPlatformCta($ad->generated_scripts ?? [], $shop, $platform);

        $ad->update([
            'channel' => $platform,
            'visual_config' => $vc,
            'generated_scripts' => $scripts,
        ]);

        $ctaPack = $videoAdGen->buildPlatformCtaPack($shop);

        // Pré-chauffer le cache TTS pour la nouvelle voix en tâche de fond
        try {
            $mediaCtrl = app(\App\Http\Controllers\MediaController::class);
            if (! empty($ctaPack[$platform]['voices'])) {
                foreach ($ctaPack[$platform]['voices'] as $voiceLine) {
                    $mediaCtrl->warmTtsCache($voiceLine);
                }
            }
        } catch (\Throwable $e) {
            // Silencieux si hors ligne
        }

        return response()->json([
            'success' => true,
            'platform' => $platform,
            'scripts' => $ad->generated_scripts,
            'cta_pack' => $ctaPack,
            'message' => 'Destination et appel à l’action mis à jour !',
        ]);
    }

    /**
     * Met à jour en direct les produits sélectionnés dans le studio vidéo
     */
    public function updateProducts(Request $request, Advertisement $ad)
    {
        $shop = $request->user()->shop;

        if ($ad->shop_id !== $shop->id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'selected_product_ids' => ['nullable', 'array'],
            'selected_product_ids.*' => ['integer'],
            'angle' => ['nullable', 'string', 'in:promotions,nouveautes,bestsellers,coup_de_coeur,boutique'],
            'tone' => ['nullable', 'string', 'in:dynamique,luxe,fun,urgent,chaleureux'],
            'product_id' => ['nullable', 'integer'],
        ]);

        try {
            $ad = $this->aiAdvertisingService->regenerateAd($ad, $shop, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Produits et vidéo mis à jour avec succès !',
                'visual_config' => $ad->visual_config,
                'scripts' => $ad->generated_scripts,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * H-02, H-03, H-04 : Studio Vidéo Publicitaire IA 9:16
     */
    public function show(Request $request, Advertisement $ad)
    {
        $shop = $request->user()->shop;

        // Isolation RG-02
        if ($ad->shop_id !== $shop->id) {
            abort(403);
        }

        $ad->load(['product.images', 'product.primaryImage']);

        $vc = $ad->visual_config ?? [];
        $videoAdGen = app(\App\Services\VideoAdGeneratorService::class);

        // Si la publicité n'a pas encore le format multi-produits (anciennes annonces en base)
        if (empty($vc['products'])) {
            $angle = $vc['angle'] ?? ($ad->objective === 'promotion' ? 'promotions' : 'nouveautes');
            $selected = $videoAdGen->selectProductsForAd($shop, $angle, $ad->product_id);
            $vc['products'] = $videoAdGen->formatProductsPayload($selected, $shop, ($angle === 'boutique'));
            $vc['shop_logo_url'] = $videoAdGen->resolveShopLogoUrl($shop);
            $vc['shop_color'] = $shop->brand_color ?: '#6d3df2';
            $vc['shop_name'] = $shop->name;
            $vc['angle'] = $angle;
            $vc['tone'] = $vc['tone'] ?? $ad->tone ?? 'dynamique';
            $vc['is_store_presentation'] = ($angle === 'boutique');
            $ad->update(['visual_config' => $vc]);
        }

        $productImageUrl = $this->resolveProductImageForAd($ad, $shop);

        // Plateforme de diffusion + phrases finales (boutique en ligne / WhatsApp / où est le lien)
        $platform = $videoAdGen->normalizePlatform($vc['platform'] ?? $ad->channel);
        $ctaPack = $videoAdGen->buildPlatformCtaPack($shop);

        // Pré-chauffage du cache audio TTS en arrière-plan pour zéro latence
        try {
            $mediaCtrl = app(\App\Http\Controllers\MediaController::class);
            foreach ($ctaPack[$platform]['voices'] as $ctaVoice) {
                $mediaCtrl->warmTtsCache($ctaVoice);
            }
        } catch (\Throwable $e) {
            // Silencieux si hors connexion
        }

        try {
            $scripts = $ad->generated_scripts ?? [];
            $mediaCtrl = app(\App\Http\Controllers\MediaController::class);
            $scriptsModified = false;

            foreach ($scripts as &$scr) {
                if (! empty($scr['scenes']) && is_array($scr['scenes'])) {
                    foreach ($scr['scenes'] as &$sc) {
                        if (! empty($sc['voiceover'])) {
                            $normalized = $mediaCtrl->normalizeSpeechText($sc['voiceover']);
                            if ($normalized !== $sc['voiceover']) {
                                $sc['voiceover'] = $normalized;
                                $scriptsModified = true;
                            }
                            $mediaCtrl->warmTtsCache($sc['voiceover']);
                        }
                    }
                    unset($sc);
                    $scr['steps'] = $videoAdGen->convertScenesToSteps($scr['scenes']);
                }
            }
            unset($scr);

            if ($scriptsModified) {
                $ad->update(['generated_scripts' => $scripts]);
            }
        } catch (\Throwable $e) {
            // Silencieux si hors connexion
        }

        // Produits et catégories de la boutique pour la modal de modification
        $shopProducts = $shop->products()
            ->where('status', Product::STATUS_ACTIVE)
            ->with(['primaryImage', 'images', 'categories'])
            ->get();

        $shopCategories = $shop->categories()
            ->where('is_visible', true)
            ->get();

        // Résolution de l'avatar UGC pour le studio vidéo
        $ugcPath = public_path('images/brand/ugc_presenter.jpg');
        $ugcAvatarUrl = file_exists($ugcPath) && filesize($ugcPath) > 0
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($ugcPath))
            : asset('images/brand/ugc_presenter.jpg');

        return view('advertising.show', compact('shop', 'ad', 'productImageUrl', 'ugcAvatarUrl', 'shopProducts', 'shopCategories', 'platform', 'ctaPack'));
    }

    /**
     * Résout l'URL ou le Data URI Base64 ultra-robuste de l'image du produit pour le Studio Vidéo
     */
    private function resolveProductImageForAd(Advertisement $ad, Shop $shop): string
    {
        // 1. Produit explicite de la publicité
        $product = $ad->product;

        // 2. Si la publicité a été générée pour toute la boutique, chercher le produit phare avec photo
        if (! $product) {
            $product = $shop->products()
                ->whereHas('images')
                ->with(['images', 'primaryImage'])
                ->first()
                ?? $shop->products()->with(['images', 'primaryImage'])->first();
        }

        // 3. Extraction de la photo du produit (priorité aux données Base64 zéro-latence)
        if ($product) {
            $primary = $product->primaryImage ?? $product->images->first();

            // A. Données Base64 déjà stockées en base de données (100% zéro latence, zéro CORS)
            if ($primary && ! empty($primary->image_data)) {
                $data = $primary->image_data;
                if (str_starts_with($data, 'data:image')) {
                    return $data;
                }
                return 'data:image/webp;base64,' . $data;
            }

            // B. Fichier physique sur le disque public -> conversion en Base64 pour le canvas
            if ($primary && ! empty($primary->path)) {
                $diskPath = Storage::disk('public')->path($primary->path);
                if (file_exists($diskPath) && filesize($diskPath) > 0) {
                    $mime = @mime_content_type($diskPath) ?: 'image/webp';
                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($diskPath));
                }

                if (! empty($primary->thumbnail_path)) {
                    $thumbPath = Storage::disk('public')->path($primary->thumbnail_path);
                    if (file_exists($thumbPath) && filesize($thumbPath) > 0) {
                        $mime = @mime_content_type($thumbPath) ?: 'image/webp';
                        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($thumbPath));
                    }
                }

                return $primary->url;
            }
        }

        // 4. Logo de la boutique
        if ($shop->logo_path) {
            $diskPath = Storage::disk('public')->path($shop->logo_path);
            if (file_exists($diskPath) && filesize($diskPath) > 0) {
                $mime = @mime_content_type($diskPath) ?: 'image/png';
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($diskPath));
            }
            return '/storage/' . ltrim($shop->logo_path, '/');
        }

        // 5. Photo de catalogue de secours selon la catégorie
        $catHint = mb_strtolower($shop->business_category . ' ' . $shop->name);
        $catalogPath = null;
        if (str_contains($catHint, 'resto') || str_contains($catHint, 'food') || str_contains($catHint, 'burger')) {
            $catalogPath = public_path('images/catalog/burger.webp');
        } elseif (str_contains($catHint, 'poisson') || str_contains($catHint, 'fish')) {
            $catalogPath = public_path('images/catalog/poisson.webp');
        } elseif (str_contains($catHint, 'frite') || str_contains($catHint, 'snack')) {
            $catalogPath = public_path('images/catalog/frites.webp');
        }

        if ($catalogPath && file_exists($catalogPath)) {
            return 'data:image/webp;base64,' . base64_encode(file_get_contents($catalogPath));
        }

        return '/api/placeholder-image?id=' . ($product?->id ?? '') . '&title=' . urlencode($product?->title ?? $shop->name) . '&color=' . urlencode($shop->brand_color ?: '#6d3df2');
    }

    /**
     * Enregistre l'exportation d'une vidéo publicitaire (statistiques & historique IA)
     */
    public function logVideoExport(Request $request, Advertisement $ad)
    {
        $shop = $request->user()->shop;

        if ($ad->shop_id !== $shop->id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $ad->increment('shares_count');

        // Log de consommation IA (CDC Section 6.1 et J-04)
        \App\Models\AiGeneration::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'type' => 'ad_video_export',
            'step' => 'video_rendering',
            'model_used' => 'motion_canvas_9_16',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_estimated_fcfa' => 0,
            'duration_ms' => 14000,
            'status' => 'success',
            'input_summary' => "Export vidéo 9:16 pour ad #{$ad->id} (" . ($ad->product?->title ?: $shop->name) . ")",
            'output_data' => [
                'ad_id' => $ad->id,
                'channel' => $ad->channel,
                'theme' => $request->input('theme', 'boutique'),
                'video_style' => $request->input('video_style', 'capcut'),
                'script_index' => $request->input('script_index', 0),
                'format' => '9:16_vertical_hd',
            ],
        ]);

        return response()->json([
            'success' => true,
            'shares_count' => $ad->shares_count,
            'message' => 'Export vidéo enregistré avec succès !',
        ]);
    }

    /**
     * Enregistre un fichier audio personnalisé (voix commerçant ou musique MP3)
     */
    public function uploadCustomAudio(Request $request, Advertisement $ad)
    {
        $shop = $request->user()->shop;

        if ($ad->shop_id !== $shop->id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $request->validate([
            'audio' => ['required', 'file', 'max:25600'], // max 25MB
            'type' => ['required', 'string', 'in:voice,music'],
        ]);

        $file = $request->file('audio');
        $type = $request->input('type');

        $ext = $file->getClientOriginalExtension() ?: ($type === 'music' ? 'mp3' : 'webm');
        $fileName = 'ad_' . $ad->id . '_' . $type . '_' . time() . '.' . $ext;
        $path = $file->storeAs('ads/audio', $fileName, 'public');

        $url = Storage::disk('public')->url($path);

        $vc = $ad->visual_config ?? [];
        if ($type === 'voice') {
            $vc['custom_voice_url'] = $url;
            $vc['voice_mode'] = 'custom';
        } else {
            $vc['custom_music_url'] = $url;
            $vc['music_mode'] = 'custom';
        }

        $ad->update(['visual_config' => $vc]);

        return response()->json([
            'success' => true,
            'url' => $url,
            'type' => $type,
            'message' => 'Fichier audio enregistré avec succès !',
        ]);
    }

    /**
     * Réinitialise l'audio personnalisé pour revenir aux presets / voix IA
     */
    public function resetCustomAudio(Request $request, Advertisement $ad)
    {
        $shop = $request->user()->shop;

        if ($ad->shop_id !== $shop->id) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $type = $request->input('type', 'voice');
        $vc = $ad->visual_config ?? [];

        if ($type === 'voice') {
            unset($vc['custom_voice_url']);
            $vc['voice_mode'] = 'ai';
        } else {
            unset($vc['custom_music_url']);
            $vc['music_mode'] = 'preset';
        }

        $ad->update(['visual_config' => $vc]);

        return response()->json([
            'success' => true,
            'type' => $type,
            'message' => 'Configuration audio réinitialisée.',
        ]);
    }
}
