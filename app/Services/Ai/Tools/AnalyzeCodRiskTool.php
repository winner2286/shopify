<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiOrderIntelligenceService;
use App\Services\Ai\Contracts\AiToolInterface;

class AnalyzeCodRiskTool implements AiToolInterface
{
    public function name(): string
    {
        return 'analyze_cod_risk';
    }

    public function description(): string
    {
        return 'Analyse la fiabilité d\'un client et le niveau de risque d\'annulation/refus d\'une commande Cash on Delivery (COD) à partir de son historique et de ses coordonnées.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['order_identifier'],
            'properties' => [
                'order_identifier' => [
                    'type' => 'string',
                    'description' => 'Numéro de commande (ex: CMD-2026-XXXX) ou ID numérique de la commande.',
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
        $identifier = trim($arguments['order_identifier'] ?? '');

        $order = Order::where('shop_id', $shop->id)
            ->where(function ($q) use ($identifier) {
                $q->where('order_number', $identifier);
                if (is_numeric($identifier)) {
                    $q->orWhere('id', (int) $identifier);
                }
            })
            ->first();

        if (! $order) {
            return [
                'error' => "Commande '{$identifier}' introuvable pour cette boutique.",
            ];
        }

        $service = app(AiOrderIntelligenceService::class);
        return $service->analyzeOrderRisk($order);
    }
}
