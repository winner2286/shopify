<?php

namespace App\Services\Ai\Tools;

use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiAnalyticsAdvisorService;
use App\Services\Ai\Contracts\AiToolInterface;

class GetBusinessRecommendationsTool implements AiToolInterface
{
    protected AiAnalyticsAdvisorService $advisorService;

    public function __construct(?AiAnalyticsAdvisorService $advisorService = null)
    {
        $this->advisorService = $advisorService ?? new AiAnalyticsAdvisorService();
    }

    public function name(): string
    {
        return 'get_business_recommendations';
    }

    public function description(): string
    {
        return 'Audit d\'affaires et recommandations stratégiques IA de la boutique : diagnostique le capital immobilisé en stock dormant (produits sans vente depuis 30j), les alertes critiques de rupture sur best-sellers, le taux d\'annulation du Cash on Delivery (COD) et formule des plans d\'action pour accroître la rentabilité.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'with_ai_briefing' => [
                    'type' => 'boolean',
                    'description' => "Inclure ou non un briefing exécutif rédigé par l'IA (défaut true).",
                ],
            ],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(Shop $shop, array $arguments, ?User $user = null): array
    {
        $withBriefing = (bool) ($arguments['with_ai_briefing'] ?? true);

        return $this->advisorService->generateBusinessDiagnosis($shop, $withBriefing);
    }
}
