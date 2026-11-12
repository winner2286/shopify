<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\VideoAdGeneratorService;

class GenerateVideoAdScriptTool implements AiToolInterface
{
    public function __construct(
        protected ?VideoAdGeneratorService $videoService = null
    ) {
        $this->videoService = $videoService ?? new VideoAdGeneratorService();
    }

    public function name(): string
    {
        return 'generate_video_ad_script';
    }

    public function description(): string
    {
        return 'Génère un script vidéo publicitaire haute conversion et des accroches percutantes pour TikTok, Instagram Reels, Facebook Ads ou Statut WhatsApp, basé sur les produits réels de la boutique.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'platform' => [
                    'type' => 'string',
                    'enum' => ['tiktok', 'instagram', 'whatsapp', 'facebook', 'general'],
                    'description' => 'Plateforme de diffusion ciblée (défaut : "tiktok"). Adapte le rythme, les phrases d\'appel à l\'action et l\'emplacement du lien.',
                ],
                'angle' => [
                    'type' => 'string',
                    'enum' => ['nouveautes', 'bestsellers', 'promotions', 'coup_de_coeur', 'boutique'],
                    'description' => 'Angle marketing de la vidéo : "nouveautes" (derniers arrivages), "bestsellers" (produits les plus vendus), "promotions" (plus gros rabais réels), "coup_de_coeur" (focus sur 1 produit précis), "boutique" (présentation générale de l\'enseigne).',
                ],
                'product_id' => [
                    'type' => 'integer',
                    'description' => 'ID du produit spécifique si angle "coup_de_coeur" (optionnel).',
                ],
                'tone' => [
                    'type' => 'string',
                    'enum' => ['dynamique', 'luxe', 'fun', 'urgent', 'chaleureux'],
                    'description' => 'Tonalité de la voix et du script (défaut : "dynamique").',
                ],
                'custom_message' => [
                    'type' => 'string',
                    'description' => 'Instruction ou avantage particulier à mettre en avant (ex: "Livraison gratuite ce weekend", "Stock limité à 10 pièces").',
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
        $platform = (string) ($arguments['platform'] ?? 'tiktok');
        $angle = (string) ($arguments['angle'] ?? 'nouveautes');
        $tone = (string) ($arguments['tone'] ?? 'dynamique');
        $productId = isset($arguments['product_id']) ? (int) $arguments['product_id'] : null;
        $customMessage = trim((string) ($arguments['custom_message'] ?? ''));

        if (in_array($platform, ['tiktok', 'instagram'], true)
            && ! $shop->canUseFeature('tiktok_ads')
            && ! ($user?->isAdmin() || $shop->owner?->isAdmin())) {
            return [
                'status' => 'feature_restricted',
                'error' => "La génération de scripts vidéo TikTok et Instagram est réservée aux formules Pro et Premium de Zanika. Votre boutique est actuellement sur la formule « {$shop->currentPlan()->name} ».",
            ];
        }

        // Vérification de sécurité multi-tenant sur le product_id
        if ($productId) {
            $productExists = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('id', $productId)
                ->exists();

            if (! $productExists) {
                return [
                    'status' => 'error',
                    'message' => "Le produit ID #{$productId} n'appartient pas à cette boutique.",
                ];
            }
        }

        $campaign = $this->videoService->generateVideoCampaign($shop, [
            'platform' => $platform,
            'angle' => $angle,
            'tone' => $tone,
            'product_id' => $productId,
            'custom_message' => $customMessage,
        ]);

        $scripts = $campaign['scripts'] ?? [];
        $texts = $campaign['texts'] ?? [];
        $products = $campaign['products'] ?? [];

        return [
            'status' => 'success',
            'platform' => $platform,
            'angle' => $angle,
            'tone' => $tone,
            'products_featured' => array_map(fn($p) => [
                'id' => $p['id'],
                'title' => $p['title'],
                'price' => $p['price'],
                'badge' => $p['badge'] ?? null,
            ], $products),
            'voiceover_script' => $scripts['full_voiceover'] ?? ($scripts['scenes'][0]['text'] ?? 'Script généré pour votre vidéo.'),
            'scenes' => $scripts['scenes'] ?? [],
            'caption_post' => $texts['post'] ?? ($texts['caption'] ?? ''),
            'hashtags' => $texts['hashtags'] ?? [],
            'summary' => "Script vidéo publicitaire généré pour {$platform} (angle : {$angle}, tonalité : {$tone}) avec " . count($products) . " produit(s) mis en avant.",
        ];
    }
}
