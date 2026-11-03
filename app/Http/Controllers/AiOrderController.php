<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Ai\AiCustomerService;
use App\Services\Ai\AiOrderIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiOrderController extends Controller
{
    /**
     * Analyse en temps réel le risque COD d'une commande spécifique.
     */
    public function analyzeRisk(Request $request, Order $order, AiOrderIntelligenceService $orderAiService): JsonResponse
    {
        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? \App\Models\Shop::first() : null);
        if (! $shop || (! $user->isAdmin() && $order->shop_id !== $shop->id)) {
            return response()->json(['error' => 'Non autorisé.'], 403);
        }

        $analysis = $orderAiService->analyzeOrderRisk($order);

        return response()->json([
            'status' => 'success',
            'analysis' => $analysis,
        ]);
    }

    /**
     * Revue globale intelligente des commandes Cash on Delivery (COD) et tournées de livraison.
     */
    public function codReview(Request $request, AiOrderIntelligenceService $orderAiService): View|JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $shop = $user->shop ?? ($user->isAdmin() ? \App\Models\Shop::first() : null);
        if (! $shop) {
            return redirect()->route('dashboard')->with('status', "Veuillez d'abord configurer une boutique.");
        }

        $dashboard = $orderAiService->getPendingCodDashboard($shop);

        if ($request->wantsJson()) {
            return response()->json($dashboard);
        }

        return view('ai.cod_review', [
            'shop' => $shop,
            'dashboard' => $dashboard,
        ]);
    }

    /**
     * Assistant de relation client et génération de réponse WhatsApp 24/7.
     */
    public function answerCustomer(Request $request, AiCustomerService $customerService): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:1000',
            'phone' => 'nullable|string|max:30',
        ]);

        $shop = $request->user()->shop;
        if (! $shop) {
            return response()->json(['error' => 'Boutique introuvable.'], 403);
        }

        $question = $request->input('question');
        $phone = $request->input('phone');

        $result = $customerService->answerCustomerInquiry($shop, $question, $phone);

        return response()->json($result);
    }
}
