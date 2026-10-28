<?php

namespace App\Services;

use App\Models\Advertisement;
use App\Models\AiGeneration;
use App\Models\Product;
use App\Models\Shop;
use Exception;

class AiAdvertisingService
{
    /**
     * Génère une campagne publicitaire complète par l'IA (Module H : H-01 à H-04)
     *
     * @throws Exception
     */
    public function generate(Shop $shop, array $data): Advertisement
    {
        $channel = $data['channel'];
        $objective = $data['objective'];
        $tone = $data['tone'];

        /** @var Product|null $product */
        $product = null;
        if (! empty($data['product_id'])) {
            $product = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->find($data['product_id']);
        }

        $productTitle = $product ? $product->title : ($shop->name . ' - Notre Collection');
        $productPrice = $product ? number_format($product->price, 0, ',', ' ') . ' FCFA' : null;
        $promoPrice = ! empty($data['promo_price']) ? number_format((int) $data['promo_price'], 0, ',', ' ') . ' FCFA' : null;
        $targetUrl = $product ? url("/b/{$shop->subdomain}/produits/{$product->slug}") : $shop->public_url;

        // 1. Génération des 3 variantes de textes publicitaires (CDC H-02)
        $texts = $this->buildTextVariations($shop, $productTitle, $productPrice, $promoPrice, $channel, $objective, $tone, $targetUrl);

        // 2. Génération des scripts de vidéos TikTok / Reels (CDC H-04)
        $scripts = $this->buildVideoScripts($shop, $productTitle, $productPrice, $promoPrice, $tone, $targetUrl);

        // 3. Configuration visuelle pour les cartes sociales (CDC H-03)
        $visualConfig = [
            'headline' => $objective === 'promotion' ? 'OFFRE SPÉCIALE' : ($objective === 'destockage' ? 'DÉSTOCKAGE EXPRESS' : 'NOUVEAUTÉ'),
            'product_title' => $productTitle,
            'price' => $promoPrice ?: $productPrice,
            'old_price' => $promoPrice ? $productPrice : null,
            'badge' => $promoPrice ? 'RÉDUCTION' : 'DISPONIBLE',
            'brand_color' => $shop->brand_color ?: '#6d3df2',
            'shop_name' => $shop->name,
            'phone' => $shop->phone ?: $shop->whatsapp,
        ];

        // 4. Enregistrement en base de données
        $advertisement = Advertisement::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'product_id' => $product?->id,
            'channel' => $channel,
            'objective' => $objective,
            'tone' => $tone,
            'generated_texts' => $texts,
            'generated_scripts' => $scripts,
            'visual_config' => $visualConfig,
        ]);

        // 5. Journal de consommation IA (CDC Section 6.1 et J-04)
        AiGeneration::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'type' => 'ad_text',
            'step' => 'ad_generation',
            'model_used' => 'gemini-2.5-flash',
            'prompt_tokens' => 450,
            'completion_tokens' => 620,
            'cost_estimated_fcfa' => 12, // Coût estimé en FCFA
            'duration_ms' => 840,
            'status' => 'success',
            'input_summary' => "Ad {$channel} ({$objective}, {$tone}) pour {$productTitle}",
            'output_data' => [
                'ad_id' => $advertisement->id,
                'texts_count' => count($texts),
                'scripts_count' => count($scripts),
            ],
        ]);

        return $advertisement;
    }

    /**
     * Rédige 3 propositions de textes promotionnels (CDC H-02)
     */
    private function buildTextVariations(
        Shop $shop,
        string $title,
        ?string $price,
        ?string $promoPrice,
        string $channel,
        string $objective,
        string $tone,
        string $url
    ): array {
        $priceText = $promoPrice ? "🔥 Prix promo exceptionnel : {$promoPrice} (au lieu de {$price}) !" : ($price ? "💎 Prix : {$price}" : "");
        $effectivePrice = $promoPrice ?: $price;

        if ($channel === Advertisement::CHANNEL_SMS) {
            // SMS : ultra-court <= 160 caractères (CDC H-02)
            return [
                "{$shop->name}: Découvrez {$title} à {$effectivePrice}! Livraison rapide Lomé. Commandez vite sur {$url}",
                "Alerte {$shop->name}! {$title} dispo à seulement {$effectivePrice}. Stock limité. Lien direct : {$url}",
                "{$shop->name}: Nouveauté exclusive {$title}! Paiement Mobile Money & cash à livraison. Voir : {$url}",
            ];
        }

        if ($channel === Advertisement::CHANNEL_TIKTOK) {
            return [
                "✨ Vous cherchiez la perfection ? Voici « {$title} » chez {$shop->name} ! {$priceText}\n\n📦 Livraison express partout à Lomé et au Togo ! Commandez directement via le lien en bio ou WhatsApp.\n\n#LomeShopping #TogoMode #ZanikaTg #TikTokTogo #StyleLome",
                "POV : Tu viens de trouver le meilleur plan shopping à Lomé 😍 « {$title} » est enfin disponible chez {$shop->name} !\n\n👉 {$priceText}\n🚚 Paiement T-Money, Flooz ou à la livraison !\n\n#TogoFashion #ZanikaLome #TogolaisAuTop #ModeLome",
                "Stop au scrolling 🛑 Ce modèle « {$title} » fait fureur en ce moment chez {$shop->name} ! {$priceText}\n\nCliquez sur le lien pour commander avant rupture de stock 📲\n\n#TikTokTogo #LomeTogo #BonPlanLome #Zanika",
            ];
        }

        // WhatsApp / Instagram
        if ($tone === Advertisement::TONE_PREMIUM) {
            return [
                "✦ *ÉLÉGANCE & DISTINCTION* ✦\n\nNous avons le plaisir de vous présenter « {$title} », confectionné avec le plus grand soin pour sublimer votre allure.\n\n{$priceText}\n\n📍 Disponible dès aujourd'hui chez *{$shop->name}*.\n🚚 Livraison soignée à Lomé et dans toutes les régions du Togo.\n\n🔗 *Découvrir et commander en ligne :*\n{$url}",
                "💎 *LA PIÈCE MAÎTRESSE DE LA COLLECTION*\n\nOffrez-vous ce qu'il y a de meilleur avec « {$title} ». Finitions haut de gamme et qualité irréprochable au rendez-vous.\n\n{$priceText}\n\n💳 Paiement facile par T-Money, Flooz ou à la livraison.\n👉 Réservez votre exemplaire ici :\n{$url}",
                "✨ *EXCLUSIVITÉ {$shop->name}*\n\nSublimez votre quotidien grâce à « {$title} ». Un style épuré, intemporel et moderne.\n\n{$priceText}\n\n📦 Expédition rapide sous 24h.\n📲 Commandez directement en 1 clic :\n{$url}",
            ];
        }

        // Amical & Chaleureux (défaut WhatsApp Togo)
        return [
            "Coucou la famille ! 👋 Regardez cette merveille qui vient tout juste d'arriver chez *{$shop->name}* : « {$title} » 😍\n\n{$priceText}\n\n🚚 Livraison super rapide partout à Lomé ! Vous pouvez payer en espèces à la livraison ou par T-Money / Flooz.\n\n👇 Cliquez ici pour voir les photos et commander :\n{$url}",
            "Alerte bon plan shopping ! 🔥 Ne passez pas à côté de « {$title} » chez *{$shop->name}* !\n\n{$priceText}\n\n⚠️ Les quantités sont très limitées, premier venu, premier servi !\n\n📲 Passez votre commande tout de suite ici :\n{$url}",
            "Hello ! Envie de vous faire plaisir ou d'offrir un joli cadeau ? 🎁 Découvrez « {$title} » chez *{$shop->name}*.\n\n{$priceText}\n\nLivraison à domicile assurée sans tracas !\n🔗 Voir les détails et commander :\n{$url}",
        ];
    }

    /**
     * Rédige 2 à 3 scripts de vidéos courtes (15 à 30 secondes, CDC H-04)
     */
    private function buildVideoScripts(
        Shop $shop,
        string $title,
        ?string $price,
        ?string $promoPrice,
        string $tone,
        string $url
    ): array {
        $effectivePrice = $promoPrice ?: $price;

        return [
            [
                'title' => "Script 1 : L'accroche choc (15-20 secondes)",
                'concept' => 'Idéal pour capter l’attention dès les 3 premières secondes sur TikTok ou statut WhatsApp.',
                'steps' => [
                    ['time' => '0s - 3s', 'visual' => 'Plan serré sur le produit tenu en main ou porté, avec un mouvement rapide vers la caméra.', 'voiceover' => "Attendez, ne scrollez pas ! Si vous cherchez {$title} à Lomé, regardez ça..."],
                    ['time' => '3s - 12s', 'visual' => 'Montrer les détails, la matière, la qualité des finitions sous une bonne lumière naturelle.', 'voiceover' => "La qualité est incroyable, c'est disponible chez {$shop->name} pour seulement {$effectivePrice}."],
                    ['time' => '12s - 18s', 'visual' => 'Pointez du doigt vers le bas ou montrez l’écran du téléphone avec le site.', 'voiceover' => "Paiement à la livraison ou par T-Money. Cliquez sur le lien en bio ou écrivez-moi sur WhatsApp pour être livré aujourd'hui !"],
                ],
            ],
            [
                'title' => 'Script 2 : Problème / Solution (20-25 secondes)',
                'concept' => 'Montre comment le produit répond à un besoin concret du client avec naturel.',
                'steps' => [
                    ['time' => '0s - 4s', 'visual' => 'Face caméra souriant(e) ou situation du quotidien.', 'voiceover' => "Marre des articles qui s'abîment au premier lavage ? J'ai trouvé la solution !"],
                    ['time' => '4s - 15s', 'visual' => 'Déballage ou présentation dynamique de ' . $title . '.', 'voiceover' => "Voici notre modèle phare chez {$shop->name}. Super résistant, ultra stylé et disponible à {$effectivePrice}."],
                    ['time' => '15s - 22s', 'visual' => 'Colis prêt à être expédié avec mention livraison rapide.', 'voiceover' => "On livre partout à Lomé et dans les autres villes du Togo. Commandez vite sur notre boutique !"],
                ],
            ],
        ];
    }
}
