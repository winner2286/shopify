<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiAnalyticsAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAdvisorController extends Controller
{
    protected AiAnalyticsAdvisorService $advisorService;

    public function __construct(AiAnalyticsAdvisorService $advisorService)
    {
        $this->advisorService = $advisorService;
    }

    /**
     * Espace Conseiller IA & Diagnostics Stratégiques de Ventes.
     */
    public function index(Request $request): View|JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            abort(403, "Boutique requise.");
        }

        $period = $request->query('period', '7d');
        if (! in_array($period, ['7d', '30d', 'month'])) {
            $period = '7d';
        }

        $comparison = $this->advisorService->comparePeriods($shop, $period);
        $diagnosis = $this->advisorService->generateBusinessDiagnosis($shop, false);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'comparison' => $comparison,
                'diagnosis' => $diagnosis,
            ]);
        }

        return view('analytics.advisor', compact('shop', 'comparison', 'diagnosis', 'period'));
    }

    /**
     * Génère un briefing exécutif rédigé par l'IA (Gemini 2.5 Flash).
     */
    public function generateBriefing(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json(['status' => 'error', 'message' => "Boutique introuvable."], 403);
        }

        if ($shop->aiCreditsRemaining() <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Vous avez épuisé vos crédits IA mensuels. Passez à un forfait supérieur pour continuer à générer des analyses exécutives.",
            ], 402);
        }

        $diagnosis = $this->advisorService->generateBusinessDiagnosis($shop, true);

        // Décompte de 1 crédit IA
        $shop->increment('ai_credits_used');

        return response()->json([
            'status' => 'success',
            'health_score' => $diagnosis['health_score'],
            'briefing' => $diagnosis['ai_executive_briefing'] ?? "Briefing indisponible.",
            'credits_remaining' => $shop->aiCreditsRemaining(),
        ]);
    }
}
