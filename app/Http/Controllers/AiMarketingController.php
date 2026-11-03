<?php

namespace App\Http\Controllers;

use App\Models\AiAutomation;
use App\Models\Product;
use App\Services\Ai\AiMarketingAutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiMarketingController extends Controller
{
    protected AiMarketingAutomationService $marketingService;

    public function __construct(AiMarketingAutomationService $marketingService)
    {
        $this->marketingService = $marketingService;
    }

    /**
     * Studio de Rédaction Marketing & Publicitaire IA (Module 9).
     */
    public function index(Request $request): View
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            abort(403, "Boutique requise.");
        }

        $products = Product::where('shop_id', $shop->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->orderBy('title')
            ->get();

        $selectedProductId = (int) $request->query('product_id', 0);

        return view('ai.marketing', compact('shop', 'products', 'selectedProductId'));
    }

    /**
     * Génère des copies marketing avec l'IA (Gemini 2.5 Flash).
     */
    public function generate(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json(['status' => 'error', 'message' => "Boutique introuvable."], 403);
        }

        if ($shop->aiCreditsRemaining() <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Vous avez épuisé vos crédits IA mensuels. Passez à un forfait supérieur pour continuer à générer des campagnes publicitaires.",
            ], 402);
        }

        $validated = $request->validate([
            'channel' => 'required|string|in:whatsapp,facebook_ads,sms,bundle_offer',
            'product_id' => 'nullable|integer',
            'angle' => 'required|string|in:urgency,social_proof,discount,benefit',
            'custom_discount' => 'nullable|string|max:100',
            'target_audience' => 'nullable|string|max:150',
        ]);

        $result = $this->marketingService->generateCampaignCopy($shop, $validated);

        if (($result['status'] ?? '') === 'success') {
            $shop->increment('ai_credits_used');
            $result['credits_remaining'] = $shop->aiCreditsRemaining();

            return response()->json($result);
        }

        return response()->json($result, 500);
    }

    /**
     * Centre de Contrôle des Automatisations IA (Module 10).
     */
    public function automations(Request $request): View
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            abort(403, "Boutique requise.");
        }

        $this->marketingService->syncDefaultAutomations($shop);

        $automations = AiAutomation::where('shop_id', $shop->id)
            ->orderBy('id')
            ->get();

        return view('ai.automations', compact('shop', 'automations'));
    }

    /**
     * Active ou désactive une automatisation en 1 clic.
     */
    public function toggleAutomation(Request $request, AiAutomation $automation): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop || $automation->shop_id !== $shop->id) {
            return response()->json(['status' => 'error', 'message' => "Action non autorisée."], 403);
        }

        $automation->is_active = ! $automation->is_active;
        $automation->save();

        return response()->json([
            'status' => 'success',
            'is_active' => $automation->is_active,
            'message' => "L'automatisation '{$automation->name}' est désormais " . ($automation->is_active ? 'activée' : 'en pause') . ".",
        ]);
    }

    /**
     * Exécute manuellement les règles actives pour tester et inspecter les alertes.
     */
    public function runAutomationsNow(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json(['status' => 'error', 'message' => "Boutique requise."], 403);
        }

        $report = $this->marketingService->runPendingAutomations($shop);

        return response()->json([
            'status' => 'success',
            'report' => $report,
        ]);
    }
}
