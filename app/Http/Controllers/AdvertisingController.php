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
     * H-02, H-03, H-04 : Affichage des textes, scripts et visuels publicitaires
     */
    public function show(Request $request, Advertisement $ad)
    {
        $shop = $request->user()->shop;

        // Isolation RG-02
        if ($ad->shop_id !== $shop->id) {
            abort(403);
        }

        $ad->load('product');

        return view('advertising.show', compact('shop', 'ad'));
    }
}
