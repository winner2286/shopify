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
     * H-01 : Formulaire de création de publicité par l'IA
     */
    public function create(Request $request)
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('dashboard');
        }

        $products = $shop->products()->where('status', Product::STATUS_ACTIVE)->get();
        $selectedProductId = (int) $request->query('produit_id');

        return view('advertising.create', compact('shop', 'products', 'selectedProductId'));
    }

    /**
     * Génération de la publicité par l'IA
     */
    public function store(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        $validated = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'channel' => ['required', 'in:whatsapp,tiktok,sms,instagram'],
            'objective' => ['required', 'in:nouveaute,promotion,destockage,rappel'],
            'tone' => ['required', 'in:amical,premium,dynamique'],
            'promo_price' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! empty($validated['product_id'])) {
            $productExists = $shop->products()->where('id', $validated['product_id'])->exists();
            if (! $productExists) {
                abort(404, 'Produit introuvable.');
            }
        }

        try {
            $ad = $this->aiAdvertisingService->generate($shop, $validated);

            return redirect()->route('ads.show', $ad)->with('status', 'Votre campagne publicitaire et vos scripts ont été générés avec succès par l\'IA !');

        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * H-02, H-03, H-04 : Affichage des textes, scripts et visuels publicitaires & Studio Vidéo IA
     */
    public function show(Request $request, Advertisement $ad)
    {
        $shop = $request->user()->shop;

        // Isolation RG-02
        if ($ad->shop_id !== $shop->id) {
            abort(403);
        }

        $ad->load(['product.images', 'product.primaryImage']);

        $productImageUrl = $this->resolveProductImageForAd($ad, $shop);

        return view('advertising.show', compact('shop', 'ad', 'productImageUrl'));
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
}
