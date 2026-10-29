<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Product;
use App\Services\AiAdvertisingService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $product = $ad->product;
        $productImageUrl = null;
        if ($product) {
            $primary = $product->primaryImage;
            if ($primary && ! empty($primary->image_data)) {
                $productImageUrl = $primary->image_data;
            } elseif ($primary && ! empty($primary->url)) {
                $productImageUrl = $primary->url;
            } elseif ($product->images->isNotEmpty()) {
                $first = $product->images->first();
                $productImageUrl = $first->thumbnail_url ?: $first->url;
            } else {
                $productImageUrl = '/api/placeholder-image?id=' . $product->id . '&color=' . urlencode($shop->brand_color ?: '#6d3df2');
            }
        } elseif ($shop->logo_path) {
            $productImageUrl = '/storage/' . ltrim($shop->logo_path, '/');
        } else {
            $productImageUrl = '/api/placeholder-image?title=' . urlencode($shop->name) . '&color=' . urlencode($shop->brand_color ?: '#6d3df2');
        }

        return view('advertising.show', compact('shop', 'ad', 'productImageUrl'));
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
