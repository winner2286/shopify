<?php

namespace App\Services\Ai\Tools;

use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiOrderIntelligenceService;
use App\Services\Ai\Contracts\AiToolInterface;

class GetPendingCodReviewTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_pending_cod_review';
    }

    public function description(): string
    {
        return 'Fournit la revue complète des commandes en paiement à la livraison (COD) : total du cash à collecter par les livreurs, commandes en attente de confirmation et alertes de risque.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(Shop $shop, array $arguments, ?User $user = null): array
    {
        $service = app(AiOrderIntelligenceService::class);
        return $service->getPendingCodDashboard($shop);
    }
}
