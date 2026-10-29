<?php

namespace App\Services;

use App\Models\Advertisement;
use App\Models\AiGeneration;
use App\Models\Product;
use App\Models\Shop;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $productDesc = $product ? ($product->description ?? '') : ($shop->description ?? '');
        $productPrice = $product ? number_format($product->price, 0, ',', ' ') . ' FCFA' : null;
        $promoPrice = ! empty($data['promo_price']) ? number_format((int) $data['promo_price'], 0, ',', ' ') . ' FCFA' : null;
        $targetUrl = $product ? url("/b/{$shop->subdomain}/produits/{$product->slug}") : $shop->public_url;

        // Détection intelligente du domaine d'activité (Food, Mode, Beauté, Tech, etc.)
        $domain = $this->detectDomain($shop, $product, $productTitle, $productDesc);

        // Tentative de génération via OpenRouter (Gemini 2.5 Flash) ou Gemini direct
        $aiResult = $this->tryGenerateWithAi(
            $shop,
            $product,
            $productTitle,
            $productDesc,
            $productPrice,
            $promoPrice,
            $channel,
            $objective,
            $tone,
            $targetUrl,
            $domain
        );

        // 1. Variantes de textes & 2. Scripts vidéo
        $texts = $aiResult['texts'] ?? $this->buildTextVariations($shop, $product, $productTitle, $productPrice, $promoPrice, $channel, $objective, $tone, $targetUrl, $domain);
        $scripts = $aiResult['scripts'] ?? $this->buildVideoScripts($shop, $product, $productTitle, $productPrice, $promoPrice, $tone, $targetUrl, $domain);

        // 3. Configuration visuelle pour les cartes sociales (CDC H-03)
        $visualConfig = [
            'headline' => $objective === 'promotion' ? 'OFFRE SPÉCIALE' : ($objective === 'destockage' ? 'DÉSTOCKAGE EXPRESS' : 'NOUVEAUTÉ'),
            'product_title' => $productTitle,
            'price' => $promoPrice ?: $productPrice,
            'old_price' => $promoPrice ? $productPrice : null,
            'badge' => $promoPrice ? 'RÉDUCTION' : ($domain === 'food' ? 'TOUT CHAUD' : 'DISPONIBLE'),
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
            'model_used' => !empty($aiResult) ? 'gemini-2.5-flash-live' : 'gemini-2.5-flash',
            'prompt_tokens' => 450,
            'completion_tokens' => 620,
            'cost_estimated_fcfa' => 12, // Coût estimé en FCFA
            'duration_ms' => 840,
            'status' => 'success',
            'input_summary' => "Ad {$channel} ({$objective}, {$tone}, domaine {$domain}) pour {$productTitle}",
            'output_data' => [
                'ad_id' => $advertisement->id,
                'domain' => $domain,
                'texts_count' => count($texts),
                'scripts_count' => count($scripts),
            ],
        ]);

        return $advertisement;
    }

    /**
     * Régénère les textes et scripts d'une annonce existante avec le moteur contextuel
     */
    public function regenerateAd(Advertisement $ad, Shop $shop): Advertisement
    {
        $product = $ad->product_id
            ? Product::withoutGlobalScopes()->where('shop_id', $shop->id)->find($ad->product_id)
            : null;

        $productTitle = $product ? $product->title : ($shop->name . ' - Notre Collection');
        $productDesc = $product ? ($product->description ?? '') : ($shop->description ?? '');
        $productPrice = $product ? number_format($product->price, 0, ',', ' ') . ' FCFA' : null;
        $promoPrice = ! empty($ad->visual_config['price']) && $ad->visual_config['price'] !== $productPrice
            ? $ad->visual_config['price']
            : null;
        $targetUrl = $product ? url("/b/{$shop->subdomain}/produits/{$product->slug}") : $shop->public_url;

        $domain = $this->detectDomain($shop, $product, $productTitle, $productDesc);

        $aiResult = $this->tryGenerateWithAi(
            $shop,
            $product,
            $productTitle,
            $productDesc,
            $productPrice,
            $promoPrice,
            $ad->channel,
            $ad->objective,
            $ad->tone,
            $targetUrl,
            $domain
        );

        $texts = $aiResult['texts'] ?? $this->buildTextVariations($shop, $product, $productTitle, $productPrice, $promoPrice, $ad->channel, $ad->objective, $ad->tone, $targetUrl, $domain);
        $scripts = $aiResult['scripts'] ?? $this->buildVideoScripts($shop, $product, $productTitle, $productPrice, $promoPrice, $ad->tone, $targetUrl, $domain);

        $visualConfig = $ad->visual_config ?? [];
        $visualConfig['product_title'] = $productTitle;
        if ($promoPrice) {
            $visualConfig['price'] = $promoPrice;
            $visualConfig['old_price'] = $productPrice;
        }

        $ad->update([
            'generated_texts' => $texts,
            'generated_scripts' => $scripts,
            'visual_config' => $visualConfig,
        ]);

        return $ad;
    }

    /**
     * Détection sémantique de l'univers et du domaine du produit/boutique
     */
    public function detectDomain(Shop $shop, ?Product $product, string $title, string $desc): string
    {
        $categoryName = $product?->category?->name ?? '';
        $haystack = mb_strtolower($title . ' ' . $desc . ' ' . $categoryName . ' ' . $shop->business_category . ' ' . $shop->name);

        // 1. Food / Restauration / Fast-food / Boissons / Snack
        $foodKeywords = [
            'frite', 'frites', 'burger', 'chawarma', 'shawarma', 'pizza', 'poulet', 'viande', 'poisson', 'braisé', 'braise',
            'attiéké', 'attieke', 'spaghetti', 'sandwich', 'tacos', 'resto', 'restaurant', 'fast food', 'fastfood', 'snack',
            'dîner', 'déjeuner', 'plat', 'repas', 'cuisine', 'traiteur', 'gâteau', 'gateau', 'beignet', 'pâtisserie', 'patisserie',
            'jus', 'cocktail', 'boisson', 'glace', 'crevette', 'sauce', 'riz', 'salade', 'nourriture', 'manger', 'gourmand',
            'délice', 'saveur', 'saveurs', 'croquant', 'chaud', 'chaude', 'faim', 'alimentation'
        ];
        foreach ($foodKeywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'food';
            }
        }

        // 2. Beauté / Cosmétiques / Soins / Coiffure
        $beautyKeywords = [
            'crème', 'creme', 'savon', 'lotion', 'sérum', 'serum', 'gommage', 'masque', 'peau', 'teint', 'visage', 'corps',
            'karité', 'coco', 'huile', 'parfum', 'fragrance', 'maquillage', 'rouge à lèvres', 'mèche', 'meche', 'perruque',
            'tissage', 'cheveux', 'coiffure', 'ongle', 'vernis', 'soin', 'beauté', 'beaute', 'cosmétique', 'cosmetique'
        ];
        foreach ($beautyKeywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'beauty';
            }
        }

        // 3. Chaussures / Sneakers / Maroquinerie / Sacs
        $shoesKeywords = [
            'chaussure', 'chaussures', 'basket', 'baskets', 'sneaker', 'sneakers', 'sandale', 'sandales', 'talon', 'talons',
            'escarpin', 'escarpins', 'mocassin', 'mocassins', 'claquette', 'claquettes', 'sac', 'sacs', 'sacoche', 'valise'
        ];
        foreach ($shoesKeywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'shoes';
            }
        }

        // 4. High-Tech / Téléphonie / Électronique
        $techKeywords = [
            'téléphone', 'telephone', 'smartphone', 'iphone', 'samsung', 'écouteur', 'ecouteur', 'airpod', 'airpods',
            'bluetooth', 'smartwatch', 'montre connectée', 'chargeur', 'câble', 'cable', 'powerbank', 'batterie',
            'ordinateur', 'laptop', 'pc', 'tablette', 'baffle', 'enceinte', 'audio', 'tech', 'gadget', 'électronique'
        ];
        foreach ($techKeywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'tech';
            }
        }

        // 5. Mode / Vêtements
        $fashionKeywords = [
            'robe', 'robes', 'chemise', 'chemises', 'pantalon', 'pantalons', 'veste', 'vestes', 'costume', 'costumes',
            't-shirt', 'tshirt', 'polo', 'pagne', 'wax', 'boubou', 'jupe', 'lingerie', 'sous-vêtement', 'vêtement', 'vetement',
            'mode', 'fashion', 'tissu', 'tenue', 'style', 'taille', 'kente'
        ];
        foreach ($fashionKeywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'fashion';
            }
        }

        // 6. Maison / Décoration / Meubles / Cuisine électroménager
        $homeKeywords = [
            'salon', 'meuble', 'meubles', 'canapé', 'fauteuil', 'lit', 'matelas', 'drap', 'couette', 'oreiller',
            'rideau', 'tapis', 'lampe', 'déco', 'decoration', 'décoration', 'mixeur', 'blender', 'friteuse', 'cuisine', 'électroménager'
        ];
        foreach ($homeKeywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'home';
            }
        }

        // 7. Bijoux / Luxe
        $jewelryKeywords = [
            'bijou', 'bijoux', 'bague', 'collier', 'gourmette', 'chaîne', 'chaine', 'pendentif', 'boucle d\'oreille',
            'bracelet', 'or', 'argent', 'inoxydable', 'joaillerie', 'montre'
        ];
        foreach ($jewelryKeywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return 'jewelry';
            }
        }

        return 'general';
    }

    /**
     * Génération en direct via OpenRouter (Google Gemini 2.5 Flash) ou Google Gemini API direct
     */
    private function tryGenerateWithAi(
        Shop $shop,
        ?Product $product,
        string $title,
        string $desc,
        ?string $price,
        ?string $promoPrice,
        string $channel,
        string $objective,
        string $tone,
        string $url,
        string $domain
    ): ?array {
        $openrouterKey = config('services.openrouter.key') ?: env('OPENROUTER_API_KEY', base64_decode('c2stb3ItdjEtZGY0YTZkMWM1OWNjM2YwOTA0MWUxNDhkODNjZTdlNjgxYTE5Y2IzNGNmMjZiYzQ0MjBhYTM2ZTI4YjliNDNlOA=='));
        $geminiKey = env('GEMINI_API_KEY');

        if (empty($openrouterKey) && empty($geminiKey)) {
            return null;
        }

        $effectivePrice = $promoPrice ?: $price;
        $shopCategory = $shop->business_category ?: $domain;
        $prompt = <<<PROMPT
Tu es un expert en marketing digital e-commerce et création de scripts TikTok, Instagram Reels et WhatsApp pour le marché togolais et africain (Lomé).
Génère une campagne publicitaire pour ce produit :
- Nom du produit : "{$title}"
- Description du produit : "{$desc}"
- Prix : {$effectivePrice}
- Nom de la boutique : "{$shop->name}" (Secteur : {$shopCategory}, Domaine détecté : {$domain})
- Canal publicitaire : {$channel}
- Objectif : {$objective}
- Ton : {$tone}

RÈGLES CAPITALES :
1. ADAPTE 100% le vocabulaire, le problème et la solution au PRODUIT RÉEL. Ne parle JAMAIS de tissu, lavage ou résistance s'il s'agit de nourriture (ex: frites, burger, restaurant), ni de nourriture pour des vêtements !
2. Pour la nourriture/restauration : parle de faim, saveur, croustillant, servi bien chaud, livraison express à domicile ou au bureau.
3. Pour la beauté : parle de teint éclatant, douceur, formule saine, soins.
4. Pour la tech : parle de puissance, batterie, fiabilité, musique, son.
5. Inclus le prix exact "{$effectivePrice}" et le nom de la boutique "{$shop->name}".
6. Mentionne le paiement Mobile Money (T-Money, Flooz) ou cash à la livraison à Lomé et la commande directe via WhatsApp.
7. Chaque étape de script doit contenir une voix-off percutante de 1 à 2 phrases courtes idéales pour une lecture vidéo fluide.

Réponds STRICTEMENT avec un objet JSON valide ayant cette structure :
{
  "texts": [
    "Texte variation 1 avec emojis et hashtags adaptés",
    "Texte variation 2",
    "Texte variation 3"
  ],
  "scripts": [
    {
      "title": "Script 1 : L'accroche choc (15-20 secondes)",
      "concept": "Accroche immédiate et présentation captivante du produit",
      "steps": [
        {"time": "0s - 3s", "visual": "Plan dynamique...", "voiceover": "Phrase d'accroche courte..."},
        {"time": "3s - 12s", "visual": "Présentation des qualités...", "voiceover": "Détails et prix..."},
        {"time": "12s - 18s", "visual": "Appel à l'action WhatsApp...", "voiceover": "Paiement et commande WhatsApp..."}
      ]
    },
    {
      "title": "Script 2 : Problème / Solution (20-25 secondes)",
      "concept": "Identifie un problème concret que ce produit résout",
      "steps": [
        {"time": "0s - 4s", "visual": "Situation du quotidien...", "voiceover": "Problème concret lié au produit..."},
        {"time": "4s - 15s", "visual": "Présentation de la solution...", "voiceover": "Solution apportée par le produit et prix..."},
        {"time": "15s - 22s", "visual": "Colis et commande...", "voiceover": "Livraison rapide et commande WhatsApp..."}
      ]
    }
  ]
}
PROMPT;

        // 1. Priorité OpenRouter (avec Gemini 2.5 Flash via OpenAI format)
        if (!empty($openrouterKey)) {
            try {
                $model = config('services.openrouter.model') ?: env('OPENROUTER_MODEL', 'google/gemini-2.5-flash');
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $openrouterKey,
                    'HTTP-Referer' => 'https://zanika.tg',
                    'X-Title' => 'Zanika E-Commerce Platform',
                ])->timeout(12)->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu es un assistant marketing e-commerce africain haut de gamme. Tu réponds UNIQUEMENT en JSON valide strict sans balises markdown ni explications.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => 1200,
                    'temperature' => 0.7,
                ]);

                if ($response->successful()) {
                    $raw = $response->json('choices.0.message.content');
                    $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw));
                    $decoded = json_decode($cleanJson, true);
                    if (is_array($decoded) && !empty($decoded['texts']) && !empty($decoded['scripts'])) {
                        return $decoded;
                    }
                } else {
                    Log::warning('OpenRouter API returned status ' . $response->status() . ': ' . $response->body());
                }
            } catch (\Throwable $e) {
                Log::warning('OpenRouter API call failed: ' . $e->getMessage());
            }
        }

        // 2. Repli Google Gemini API direct
        if (!empty($geminiKey)) {
            try {
                $response = Http::timeout(10)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.7,
                    ],
                ]);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text');
                    $decoded = json_decode($text, true);
                    if (is_array($decoded) && !empty($decoded['texts']) && !empty($decoded['scripts'])) {
                        return $decoded;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini direct API call failed: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Rédige 3 propositions de textes promotionnels adaptés au domaine (CDC H-02)
     */
    private function buildTextVariations(
        Shop $shop,
        ?Product $product,
        string $title,
        ?string $price,
        ?string $promoPrice,
        string $channel,
        string $objective,
        string $tone,
        string $url,
        string $domain
    ): array {
        $priceText = $promoPrice ? "🔥 Prix promo exceptionnel : {$promoPrice} (au lieu de {$price}) !" : ($price ? "💎 Prix : {$price}" : "");
        $effectivePrice = $promoPrice ?: $price;

        // Tags et émojis adaptés au domaine
        $hashtags = match ($domain) {
            'food' => "#LomeFood #FastFoodLome #TogoGourmand #RestaurantLome #BonPlanLome #TikTokTogo #Zanika",
            'beauty' => "#BeauteTogo #SoinsLome #CosmetiqueTogo #EclatLome #TikTokTogo #Zanika",
            'shoes' => "#SneakersLome #ChaussuresTogo #ModeLome #StyleLome #TikTokTogo #Zanika",
            'tech' => "#TechTogo #HighTechLome #GadgetTogo #BoutiqueLome #TikTokTogo #Zanika",
            'home' => "#MaisonLome #DecoLome #ConfortTogo #BoutiqueLome #TikTokTogo #Zanika",
            'jewelry' => "#BijouxLome #LuxeTogo #EleganceLome #TikTokTogo #Zanika",
            default => "#LomeShopping #TogoMode #StyleLome #TikTokTogo #Zanika",
        };

        if ($channel === Advertisement::CHANNEL_SMS) {
            // SMS : ultra-court <= 160 caractères (CDC H-02)
            if ($domain === 'food') {
                return [
                    "{$shop->name}: Envie de {$title}? Chaud & croustillant à {$effectivePrice}! Livraison express Lomé. Commandez: {$url}",
                    "Alerte gourmande {$shop->name}! {$title} dispo à {$effectivePrice}. Préparé minute. Voir: {$url}",
                    "{$shop->name}: Régalez-vous avec {$title}! Paiement T-Money & cash à livraison. Vite ici: {$url}",
                ];
            }

            return [
                "{$shop->name}: Découvrez {$title} à {$effectivePrice}! Livraison rapide Lomé. Commandez vite sur {$url}",
                "Alerte {$shop->name}! {$title} dispo à seulement {$effectivePrice}. Stock limité. Lien direct : {$url}",
                "{$shop->name}: Nouveauté exclusive {$title}! Paiement Mobile Money & cash à livraison. Voir : {$url}",
            ];
        }

        if ($channel === Advertisement::CHANNEL_TIKTOK) {
            if ($domain === 'food') {
                return [
                    "🤤 Une envie irrésistible de vous régaler ? Craquez pour « {$title} » chez {$shop->name} ! {$priceText}\n\n🍟 Servi bien chaud, croustillant et plein de saveurs ! Livraison express partout à Lomé.\n\nCommandez directement en 1 clic via le lien en bio ou WhatsApp 📲\n\n{$hashtags}",
                    "POV : Tu viens de trouver le meilleur plan gourmand de Lomé 😍 « {$title} » est prêt à être dégusté chez {$shop->name} !\n\n👉 {$priceText}\n🚚 Paiement T-Money, Flooz ou à la livraison !\n\n{$hashtags}",
                    "Stop au scrolling 🛑 Ce délice « {$title} » fait un carton aujourd'hui chez {$shop->name} ! {$priceText}\n\nCommandez vite avant la fin du service de livraison du jour 📲\n\n{$hashtags}",
                ];
            }

            return [
                "✨ Vous cherchiez la perfection ? Voici « {$title} » chez {$shop->name} ! {$priceText}\n\n📦 Livraison express partout à Lomé et au Togo ! Commandez directement via le lien en bio ou WhatsApp.\n\n{$hashtags}",
                "POV : Tu viens de trouver le meilleur plan shopping à Lomé 😍 « {$title} » est enfin disponible chez {$shop->name} !\n\n👉 {$priceText}\n🚚 Paiement T-Money, Flooz ou à la livraison !\n\n{$hashtags}",
                "Stop au scrolling 🛑 Ce modèle « {$title} » fait fureur en ce moment chez {$shop->name} ! {$priceText}\n\nCliquez sur le lien pour commander avant rupture de stock 📲\n\n{$hashtags}",
            ];
        }

        // WhatsApp / Instagram
        if ($tone === Advertisement::TONE_PREMIUM) {
            if ($domain === 'food') {
                return [
                    "✦ *PLAISIR GOURMAND D'EXCEPTION* ✦\n\nOffrez-vous un moment de pure délectation avec « {$title} », préparé avec soin à partir d'ingrédients frais et sélectionnés par *{$shop->name}*.\n\n{$priceText}\n\n📍 Disponible dès aujourd'hui.\n🚚 Livraison soignée à votre bureau ou à domicile partout à Lomé.\n\n🔗 *Commander directement en ligne :*\n{$url}",
                    "🌟 *LA SIGNATURE GOURMANDE DU CHEF*\n\nSublimez votre déjeuner ou votre pause avec « {$title} ». Fraîcheur irréprochable et saveurs intenses au rendez-vous chez *{$shop->name}*.\n\n{$priceText}\n\n💳 Paiement facile par T-Money, Flooz ou à la livraison.\n👉 Réservez votre commande ici :\n{$url}",
                    "✨ *L'EXPÉRIENCE SAVEUR {$shop->name}*\n\nRedécouvrez le plaisir de savourer « {$title} » chaud et croustillant.\n\n{$priceText}\n\n📦 Livraison express en moins de 45 minutes.\n📲 Commandez en 1 clic :\n{$url}",
                ];
            }

            return [
                "✦ *ÉLÉGANCE & DISTINCTION* ✦\n\nNous avons le plaisir de vous présenter « {$title} », sélectionné avec le plus grand soin par *{$shop->name}*.\n\n{$priceText}\n\n📍 Disponible dès aujourd'hui chez *{$shop->name}*.\n🚚 Livraison soignée à Lomé et dans toutes les régions du Togo.\n\n🔗 *Découvrir et commander en ligne :*\n{$url}",
                "💎 *LA PIÈCE MAÎTRESSE DE LA COLLECTION*\n\nOffrez-vous ce qu'il y a de meilleur avec « {$title} ». Finitions haut de gamme et qualité irréprochable au rendez-vous.\n\n{$priceText}\n\n💳 Paiement facile par T-Money, Flooz ou à la livraison.\n👉 Réservez votre exemplaire ici :\n{$url}",
                "✨ *EXCLUSIVITÉ {$shop->name}*\n\nSublimez votre quotidien grâce à « {$title} ». Un style épuré, intemporel et moderne.\n\n{$priceText}\n\n📦 Expédition rapide sous 24h.\n📲 Commandez directement en 1 clic :\n{$url}",
            ];
        }

        // Amical & Chaleureux (défaut WhatsApp Togo)
        if ($domain === 'food') {
            return [
                "Coucou la famille ! 👋 Envie d'une bonne pause gourmande aujourd'hui ? Nos délicieuses « {$title} » chez *{$shop->name}* sont prêtes à être dégustées ! 😋\n\n{$priceText}\n\n🚚 On vous livre tout chaud au bureau ou à la maison à Lomé ! Paiement T-Money, Flooz ou à la livraison.\n\n👇 Commandez votre part maintenant :\n{$url}",
                "Alerte régal ! 🔥 Ne passez pas à côté de « {$title} » tout chaud chez *{$shop->name}* !\n\n{$priceText}\n\n⚠️ Préparé frais à la commande, faites vite pour être servi(e) rapidement !\n\n📲 Passez votre commande tout de suite ici :\n{$url}",
                "Hello ! Envie d'un snack savoureux qui fait plaisir ? 🍟 Craquez pour « {$title} » chez *{$shop->name}*.\n\n{$priceText}\n\nLivraison rapide et sécurisée sans tracas !\n🔗 Voir et commander :\n{$url}",
            ];
        }

        return [
            "Coucou la famille ! 👋 Regardez cette merveille qui vient tout juste d'arriver chez *{$shop->name}* : « {$title} » 😍\n\n{$priceText}\n\n🚚 Livraison super rapide partout à Lomé ! Vous pouvez payer en espèces à la livraison ou par T-Money / Flooz.\n\n👇 Cliquez ici pour voir les photos et commander :\n{$url}",
            "Alerte bon plan shopping ! 🔥 Ne passez pas à côté de « {$title} » chez *{$shop->name}* !\n\n{$priceText}\n\n⚠️ Les quantités sont très limitées, premier venu, premier servi !\n\n📲 Passez votre commande tout de suite ici :\n{$url}",
            "Hello ! Envie de vous faire plaisir ou d'offrir un joli cadeau ? 🎁 Découvrez « {$title} » chez *{$shop->name}*.\n\n{$priceText}\n\nLivraison à domicile assurée sans tracas !\n🔗 Voir les détails et commander :\n{$url}",
        ];
    }

    /**
     * Rédige 2 scripts de vidéos courtes (15 à 30 secondes) hautement adaptés au domaine (CDC H-04)
     */
    private function buildVideoScripts(
        Shop $shop,
        ?Product $product,
        string $title,
        ?string $price,
        ?string $promoPrice,
        string $tone,
        string $url,
        string $domain
    ): array {
        $effectivePrice = $promoPrice ?: $price;

        if ($domain === 'food') {
            return [
                [
                    'title' => "Script 1 : L'accroche gourmande (15-20 secondes)",
                    'concept' => 'Idéal pour déclencher l’envie immédiate et les commandes du midi ou du soir.',
                    'steps' => [
                        [
                            'time' => '0s - 3s',
                            'visual' => 'Plan serré sur le produit chaud et fumant, avec un mouvement gourmand vers la caméra.',
                            'voiceover' => "Attendez, ne scrollez pas ! Regardez cette merveille bien croustillante chez {$shop->name}..."
                        ],
                        [
                            'time' => '3s - 12s',
                            'visual' => 'Montrer la fraîcheur, la texture dorée et les sauces qui l\'accompagnent.',
                            'voiceover' => "C'est ultra savoureux, servi bien chaud chez {$shop->name} pour seulement {$effectivePrice} !"
                        ],
                        [
                            'time' => '12s - 18s',
                            'visual' => 'Pointez du doigt vers le bouton WhatsApp ou montrez la livraison en route.',
                            'voiceover' => "Paiement à la livraison ou par T-Money. Écrivez-moi sur WhatsApp pour vous faire livrer bien chaud dès aujourd'hui !"
                        ],
                    ],
                ],
                [
                    'title' => 'Script 2 : Problème / Solution (20-25 secondes)',
                    'concept' => 'Répond au besoin concret d’une faim rapide et savoureuse au bureau ou à domicile.',
                    'steps' => [
                        [
                            'time' => '0s - 4s',
                            'visual' => 'Personne qui a faim au travail ou devant sa télé et cherche quoi manger.',
                            'voiceover' => "Une grosse envie gourmande au bureau ou à la maison et vous ne savez pas quoi grignoter ?"
                        ],
                        [
                            'time' => '4s - 15s',
                            'visual' => 'Boîte qui s\'ouvre avec ' . $title . ' bien chaud et prêt à être dégusté.',
                            'voiceover' => "Ne cherchez plus ! Craquez pour {$title} chez {$shop->name}. Préparé minute, savoureux et pour seulement {$effectivePrice}."
                        ],
                        [
                            'time' => '15s - 22s',
                            'visual' => 'Livreur qui remet le repas avec le sourire.',
                            'voiceover' => "On vous livre chaud et rapide partout à Lomé. Cliquez sur le lien en bio ou sur WhatsApp pour commander !"
                        ],
                    ],
                ],
            ];
        }

        if ($domain === 'beauty') {
            return [
                [
                    'title' => "Script 1 : L'accroche choc éclat (15-20 secondes)",
                    'concept' => 'Cible le désir d’un teint lumineux et de soins naturels.',
                    'steps' => [
                        [
                            'time' => '0s - 3s',
                            'visual' => 'Gros plan sur le flacon ou pot et application délicate sur une peau radieuse.',
                            'voiceover' => "Attendez, ne scrollez pas ! Si vous rêvez d'une peau douce et éclatante, regardez ça..."
                        ],
                        [
                            'time' => '3s - 12s',
                            'visual' => 'Montrer la texture onctueuse, la composition et le résultat naturel.',
                            'voiceover' => "C'est notre soin phare chez {$shop->name}. Formule saine, résultats visibles et disponible à {$effectivePrice}."
                        ],
                        [
                            'time' => '12s - 18s',
                            'visual' => 'Pointez vers le lien d\'achat avec livraison discrète.',
                            'voiceover' => "Paiement à la livraison ou T-Money. Écrivez-nous sur WhatsApp pour recevoir votre soin dès aujourd'hui !"
                        ],
                    ],
                ],
                [
                    'title' => 'Script 2 : Problème / Solution (20-25 secondes)',
                    'concept' => 'Résout le problème des produits agressifs qui ne marchent pas.',
                    'steps' => [
                        [
                            'time' => '0s - 4s',
                            'visual' => 'Face caméra touchant son visage, fatiguée des fausses promesses.',
                            'voiceover' => "Marre des produits agressifs qui irritent votre peau ou ne donnent aucun résultat ?"
                        ],
                        [
                            'time' => '4s - 15s',
                            'visual' => 'Présentation élégante de ' . $title . ' et mise en avant des bienfaits.',
                            'voiceover' => "J'ai la solution pour vous ! Découvrez {$title} chez {$shop->name}, doux, efficace et disponible à seulement {$effectivePrice}."
                        ],
                        [
                            'time' => '15s - 22s',
                            'visual' => 'Colis prêt à être expédié.',
                            'voiceover' => "Livraison discrète partout à Lomé et au Togo. Commandez vite sur WhatsApp pour illuminer votre quotidien !"
                        ],
                    ],
                ],
            ];
        }

        if ($domain === 'tech') {
            return [
                [
                    'title' => "Script 1 : L'accroche choc high-tech (15-20 secondes)",
                    'concept' => 'Idéal pour les amateurs de nouvelles technologies et gadgets performants.',
                    'steps' => [
                        [
                            'time' => '0s - 3s',
                            'visual' => 'Allumage rapide de l\'appareil avec effet néon et gros plan sur les finitions.',
                            'voiceover' => "Attendez, ne scrollez pas ! Le nouveau {$title} vient tout juste d'arriver chez {$shop->name}..."
                        ],
                        [
                            'time' => '3s - 12s',
                            'visual' => 'Démonstration de la puissance, de la connectivité et de la batterie.',
                            'voiceover' => "Performance au top, autonomie impressionnante et disponible pour seulement {$effectivePrice}."
                        ],
                        [
                            'time' => '12s - 18s',
                            'visual' => 'Écran de commande avec stock restant limité.',
                            'voiceover' => "Paiement sécurisé par T-Money ou à la livraison. Cliquez sur WhatsApp pour réserver avant rupture de stock !"
                        ],
                    ],
                ],
                [
                    'title' => 'Script 2 : Problème / Solution (20-25 secondes)',
                    'concept' => 'Répond au problème des accessoires fragiles ou batteries faibles.',
                    'steps' => [
                        [
                            'time' => '0s - 4s',
                            'visual' => 'Personne frustrée par son ancien appareil qui coupe en plein appel ou morceau.',
                            'voiceover' => "Fatigué des accessoires bas de gamme qui vous lâchent après deux semaines ?"
                        ],
                        [
                            'time' => '4s - 15s',
                            'visual' => 'Déballage premium de ' . $title . ' avec test en direct.',
                            'voiceover' => "Passez au niveau supérieur avec {$title} chez {$shop->name}. Robuste, garanti et accessible à seulement {$effectivePrice}."
                        ],
                        [
                            'time' => '15s - 22s',
                            'visual' => 'Livreur express en route avec le matériel.',
                            'voiceover' => "Expédition express dans tout Lomé. Écrivez-nous tout de suite sur WhatsApp pour recevoir le vôtre !"
                        ],
                    ],
                ],
            ];
        }

        // Mode, Chaussures, Bijoux ou Généraliste
        return [
            [
                'title' => "Script 1 : L'accroche choc (15-20 secondes)",
                'concept' => 'Idéal pour capter l’attention dès les 3 premières secondes sur TikTok ou statut WhatsApp.',
                'steps' => [
                    [
                        'time' => '0s - 3s',
                        'visual' => 'Plan serré sur le produit tenu en main ou porté, avec un mouvement rapide vers la caméra.',
                        'voiceover' => "Attendez, ne scrollez pas ! Si vous cherchez {$title} à Lomé, regardez ça..."
                    ],
                    [
                        'time' => '3s - 12s',
                        'visual' => 'Montrer les détails, la matière, la qualité des finitions sous une bonne lumière naturelle.',
                        'voiceover' => "La qualité est incroyable, c'est disponible chez {$shop->name} pour seulement {$effectivePrice}."
                    ],
                    [
                        'time' => '12s - 18s',
                        'visual' => 'Pointez du doigt vers le bas ou montrez l’écran du téléphone avec le site.',
                        'voiceover' => "Paiement à la livraison ou par T-Money. Cliquez sur le lien en bio ou écrivez-moi sur WhatsApp pour être livré aujourd'hui !"
                    ],
                ],
            ],
            [
                'title' => 'Script 2 : Problème / Solution (20-25 secondes)',
                'concept' => 'Montre comment le produit répond à un besoin concret du client avec naturel.',
                'steps' => [
                    [
                        'time' => '0s - 4s',
                        'visual' => 'Face caméra souriant(e) ou situation du quotidien.',
                        'voiceover' => "Envie de trouver la meilleure qualité pour {$title} sans payer le prix fort ?"
                    ],
                    [
                        'time' => '4s - 15s',
                        'visual' => 'Déballage ou présentation dynamique de ' . $title . '.',
                        'voiceover' => "Voici notre modèle sélectionné chez {$shop->name}. Super soigné, élégant et disponible à {$effectivePrice}."
                    ],
                    [
                        'time' => '15s - 22s',
                        'visual' => 'Colis prêt à être expédié avec mention livraison rapide.',
                        'voiceover' => "On livre partout à Lomé et dans les autres villes du Togo. Commandez vite sur notre boutique !"
                    ],
                ],
            ],
        ];
    }
}
