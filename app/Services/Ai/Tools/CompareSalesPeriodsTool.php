<?php

namespace App\Services\Ai\Tools;

use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiAnalyticsAdvisorService;
use App\Services\Ai\Contracts\AiToolInterface;

class CompareSalesPeriodsTool implements AiToolInterface
{
    protected AiAnalyticsAdvisorService $advisorService;

    public function __construct(?AiAnalyticsAdvisorService $advisorService = null)
    {
        $this->advisorService = $advisorService ?? new AiAnalyticsAdvisorService();
    }

    public function name(): string
    {
        return 'compare_sales_periods';
    }

    public function description(): string
    {
        return 'Compare les performances commerciales de la boutique entre deux périodes (7 jours, 30 jours ou mois en cours vs mois précédent) : croissance du CA (%), volume de commandes, panier moyen, et identification des produits stars en hausse ou en déclin.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['7d', '30d', 'month'],
                    'description' => "Période de comparaison : '7d' (7 derniers jours vs 7 précédents), '30d' (30 derniers jours vs 30 précédents), ou 'month' (mois en cours vs mois précédent). Par défaut '7d'.",
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
        $period = in_array($arguments['period'] ?? '', ['7d', '30d', 'month']) ? $arguments['period'] : '7d';

        return $this->advisorService->comparePeriods($shop, $period);
    }
}
