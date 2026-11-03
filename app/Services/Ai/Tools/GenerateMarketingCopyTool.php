<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\AiMarketingAutomationService;
use App\Services\Ai\Contracts\AiToolInterface;

class GenerateMarketingCopyTool implements AiToolInterface
{
    protected AiMarketingAutomationService $marketingService;

    public function __construct(?AiMarketingAutomationService $marketingService = null)
    {
        $this->marketingService = $marketingService ?? new AiMarketingAutomationService();
    }

    public function name(): string
    {
        return 'generate_marketing_copy';
    }

    public function description(): string
    {
        return 'Génère des copies publicitaires et messages marketing vendeurs pour WhatsApp, Facebook/Instagram Ads, SMS ou Offre Bundle/Promo sur un produit du catalogue ou pour l\'ensemble de la boutique.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'channel' => [
                    'type' => 'string',
                    'enum' => ['whatsapp', 'facebook_ads', 'sms', 'bundle_offer'],
                    'description' => "Canal marketing : 'whatsapp' (statut ou message broadcast), 'facebook_ads' (pub sponsorisée Meta), 'sms' (SMS court <160 char) ou 'bundle_offer' (pack duo/promotionnel).",
                ],
                'product_title_or_id' => [
                    'type' => 'string',
                    'description' => "Titre partiel ou ID du produit à promouvoir. Si vide, promeut la boutique de manière globale.",
                ],
                'angle' => [
                    'type' => 'string',
                    'enum' => ['urgency', 'social_proof', 'discount', 'benefit'],
                    'description' => "Angle psychologique : 'urgency' (stocks limités), 'social_proof' (témoignages/succès), 'discount' (remise de prix), 'benefit' (bénéfice solution direct). Défaut 'urgency'.",
                ],
                'custom_discount' => [
                    'type' => 'string',
                    'description' => "Remise spéciale ou avantage offert (ex: '-20%', 'Livraison offerte', 'Un offert pour 2 achetés').",
                ],
            ],
            'required' => ['channel'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(Shop $shop, array $arguments, ?User $user = null): array
    {
        $productId = null;
        if (! empty($arguments['product_title_or_id'])) {
            $identifier = trim((string) $arguments['product_title_or_id']);
            if (is_numeric($identifier)) {
                $p = Product::where('shop_id', $shop->id)->find((int) $identifier);
                $productId = $p?->id;
            } else {
                $p = Product::where('shop_id', $shop->id)
                    ->where('title', 'like', "%{$identifier}%")
                    ->first();
                $productId = $p?->id;
            }
        }

        $params = [
            'product_id' => $productId,
            'channel' => $arguments['channel'] ?? 'whatsapp',
            'angle' => $arguments['angle'] ?? 'urgency',
            'custom_discount' => $arguments['custom_discount'] ?? null,
            'target_audience' => "Clients de la boutique {$shop->name}",
        ];

        return $this->marketingService->generateCampaignCopy($shop, $params);
    }
}
