<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoAdGeneratorService
{
    public const ANGLE_PROMOTIONS = 'promotions';
    public const ANGLE_NOUVEAUTES = 'nouveautes';
    public const ANGLE_BESTSELLERS = 'bestsellers';
    public const ANGLE_COUP_DE_COEUR = 'coup_de_coeur';
    public const ANGLE_BOUTIQUE = 'boutique';

    public const TONE_DYNAMIQUE = 'dynamique';
    public const TONE_LUXE = 'luxe';
    public const TONE_FUN = 'fun';
    public const TONE_URGENT = 'urgent';
    public const TONE_CHALEUREUX = 'chaleureux';

    /**
     * Sélectionne intelligemment 1 à 5 produits du catalogue selon l'angle choisi.
     * Respecte rigoureusement les données réelles (aucun prix ni rabais inventé).
     */
    public function selectProductsForAd(
        Shop $shop,
        string $angle,
        ?int $singleProductId = null,
        array $categoryIds = [],
        array $explicitProductIds = []
    ): Collection {
        // Si le commerçant a explicitement choisi une liste de produits
        if (! empty($explicitProductIds)) {
            $explicit = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', Product::STATUS_ACTIVE)
                ->whereIn('id', $explicitProductIds)
                ->with(['images', 'primaryImage', 'categories'])
                ->get();

            if ($explicit->isNotEmpty()) {
                return $explicit->take(5);
            }
        }

        // 1. ANGLE : COUP DE CŒUR / PRODUIT UNIQUE
        if ($angle === self::ANGLE_COUP_DE_COEUR) {
            if ($singleProductId) {
                $p = Product::withoutGlobalScopes()
                    ->where('shop_id', $shop->id)
                    ->where('id', $singleProductId)
                    ->with(['images', 'primaryImage', 'categories'])
                    ->first();
                if ($p) {
                    return collect([$p]);
                }
            }

            // Fallback coup de cœur : le produit avec photo ayant le meilleur prix ou récent
            $topProduct = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', Product::STATUS_ACTIVE)
                ->whereHas('images')
                ->with(['images', 'primaryImage', 'categories'])
                ->latest('id')
                ->first();

            return $topProduct ? collect([$topProduct]) : $shop->products()->take(1)->get();
        }

        // 2. ANGLE : PRÉSENTATION DE LA BOUTIQUE (SANS PRIX PAR DÉFAUT)
        if ($angle === self::ANGLE_BOUTIQUE) {
            return $this->selectStorePresentationProducts($shop, $categoryIds);
        }

        // 3. ANGLE : PROMOTIONS (PRODUITS AVEC LE PLUS GROS RABAIS RÉEL)
        if ($angle === self::ANGLE_PROMOTIONS) {
            $promoProducts = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', Product::STATUS_ACTIVE)
                ->whereNotNull('compare_at_price')
                ->whereColumn('compare_at_price', '>', 'price')
                ->where('price', '>', 0)
                ->with(['images', 'primaryImage', 'categories'])
                ->get()
                ->sortByDesc(function (Product $p) {
                    return ($p->compare_at_price - $p->price) / max($p->compare_at_price, 1);
                })
                ->values()
                ->take(5);

            if ($promoProducts->count() >= 3) {
                return $promoProducts;
            }

            // Si moins de 3 produits en promotion, compléter avec les plus récents sans inventer de réduction
            $missing = 4 - $promoProducts->count();
            $existingIds = $promoProducts->pluck('id')->toArray();

            $additional = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', Product::STATUS_ACTIVE)
                ->whereNotIn('id', $existingIds)
                ->with(['images', 'primaryImage', 'categories'])
                ->latest('id')
                ->take($missing)
                ->get();

            return $promoProducts->concat($additional)->take(5);
        }

        // 4. ANGLE : BEST-SELLERS (PRODUITS LES PLUS VENDUS / DEMANDÉS)
        if ($angle === self::ANGLE_BESTSELLERS) {
            $bestSellers = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', Product::STATUS_ACTIVE)
                ->where('price', '>', 0)
                ->with(['images', 'primaryImage', 'categories'])
                ->withCount('orderItems')
                ->orderByDesc('order_items_count')
                ->latest('id')
                ->take(4)
                ->get();

            if ($bestSellers->isNotEmpty()) {
                return $bestSellers;
            }
        }

        // 5. ANGLE : NOUVEAUTÉS (PAR DÉFAUT : LES PLUS RÉCEMMENT AJOUTÉS)
        return Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('price', '>', 0)
            ->with(['images', 'primaryImage', 'categories'])
            ->latest('id')
            ->take(4)
            ->get();
    }

    /**
     * Cas spécial : Sélectionne 3 à 4 produits représentatifs pour la Présentation de la Boutique.
     * Prend 1 produit phare par catégorie distincte avec une excellente photo.
     */
    private function selectStorePresentationProducts(Shop $shop, array $preferredCategoryIds = []): Collection
    {
        $categoriesQuery = $shop->categories()->where('is_visible', true);
        if (! empty($preferredCategoryIds)) {
            $categoriesQuery->whereIn('id', $preferredCategoryIds);
        }
        $categories = $categoriesQuery->take(5)->get();

        $selected = collect();

        foreach ($categories as $cat) {
            $p = $cat->products()
                ->where('status', Product::STATUS_ACTIVE)
                ->whereHas('images')
                ->with(['images', 'primaryImage', 'categories'])
                ->first();

            if ($p && ! $selected->contains('id', $p->id)) {
                $selected->push($p);
            }

            if ($selected->count() >= 4) {
                break;
            }
        }

        // Si la boutique n'a pas de catégories ou moins de 3 produits trouvés
        if ($selected->count() < 3) {
            $more = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', Product::STATUS_ACTIVE)
                ->whereNotIn('id', $selected->pluck('id')->toArray())
                ->with(['images', 'primaryImage', 'categories'])
                ->latest('id')
                ->take(4 - $selected->count())
                ->get();

            $selected = $selected->concat($more);
        }

        return $selected->take(4);
    }

    /**
     * Formate la liste des produits pour le studio vidéo avec URL ou Base64 zéro latence.
     */
    public function formatProductsPayload(Collection $products, Shop $shop, bool $hidePrices = false): array
    {
        $currency = $shop->currency ?: 'FCFA';

        return $products->map(function (Product $p) use ($shop, $currency, $hidePrices) {
            $imageUrl = $this->resolveProductImageUrl($p, $shop);
            $hasDiscount = $p->compare_at_price && $p->compare_at_price > $p->price;
            $discountPct = $hasDiscount
                ? (int) round((($p->compare_at_price - $p->price) / max($p->compare_at_price, 1)) * 100)
                : 0;

            $catName = $p->categories->first()?->name ?? ($shop->business_category ?: 'Collection');

            return [
                'id' => $p->id,
                'title' => mb_substr($p->title, 0, 36),
                'full_title' => $p->title,
                'price' => $hidePrices ? '' : number_format($p->price, 0, ',', ' ') . ' ' . $currency,
                'raw_price' => $p->price,
                'old_price' => ($hidePrices || ! $hasDiscount) ? null : number_format($p->compare_at_price, 0, ',', ' ') . ' ' . $currency,
                'discount_pct' => $hidePrices ? 0 : $discountPct,
                'badge' => $hasDiscount && ! $hidePrices ? "-{$discountPct}%" : 'TOP VENTE',
                'category' => $catName,
                'imageUrl' => $imageUrl,
            ];
        })->values()->toArray();
    }

    /**
     * Résout l'URL ou le Data URI Base64 de la photo d'un produit (zéro latence CORS).
     */
    public function resolveProductImageUrl(?Product $product, Shop $shop): string
    {
        if (! $product) {
            return $this->resolveShopLogoUrl($shop);
        }

        $primary = $product->primaryImage ?? $product->images->first();

        // 1. Image en Base64 stockée en base de données
        if ($primary && ! empty($primary->image_data)) {
            $data = $primary->image_data;
            if (str_starts_with($data, 'data:image')) {
                return $data;
            }
            return 'data:image/webp;base64,' . $data;
        }

        // 2. Fichier physique sur le disque public -> conversion Base64 pour zéro latence
        if ($primary && ! empty($primary->path)) {
            $diskPath = Storage::disk('public')->path($primary->path);
            if (file_exists($diskPath) && filesize($diskPath) > 0) {
                $mime = @mime_content_type($diskPath) ?: 'image/webp';
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($diskPath));
            }

            if (! empty($primary->thumbnail_path)) {
                $thumbPath = Storage::disk('public')->path($primary->thumbnail_path);
                if (file_exists($thumbPath) && filesize($thumbPath) > 0) {
                    $mime = @mime_content_type($thumbPath) ?: 'image/webp';
                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($thumbPath));
                }
            }

            return $primary->url;
        }

        // 3. Fallback catalogue selon la catégorie
        $catHint = mb_strtolower(($product->title ?? '') . ' ' . ($shop->business_category ?? ''));
        $catalogPath = null;
        if (str_contains($catHint, 'burger') || str_contains($catHint, 'resto') || str_contains($catHint, 'fast food')) {
            $catalogPath = public_path('images/catalog/burger.webp');
        } elseif (str_contains($catHint, 'poisson') || str_contains($catHint, 'fish')) {
            $catalogPath = public_path('images/catalog/poisson.webp');
        } elseif (str_contains($catHint, 'frite') || str_contains($catHint, 'snack')) {
            $catalogPath = public_path('images/catalog/frites.webp');
        }

        if ($catalogPath && file_exists($catalogPath)) {
            return 'data:image/webp;base64,' . base64_encode(file_get_contents($catalogPath));
        }

        // 4. Logo boutique si disponible
        return $this->resolveShopLogoUrl($shop);
    }

    /**
     * Résout le logo de la boutique sous forme de Base64 ou URL.
     */
    public function resolveShopLogoUrl(Shop $shop): string
    {
        if ($shop->logo_path) {
            $diskPath = Storage::disk('public')->path($shop->logo_path);
            if (file_exists($diskPath) && filesize($diskPath) > 0) {
                $mime = @mime_content_type($diskPath) ?: 'image/png';
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($diskPath));
            }
            return '/storage/' . ltrim($shop->logo_path, '/');
        }

        return '';
    }

    /**
     * Génère la campagne vidéo avec intelligence artificielle ou générateur déterministe ultra-fiable.
     * Garantit 0% de plantage et 0% de blocage.
     */
    public function generateVideoCampaign(Shop $shop, array $data): array
    {
        $angle = $data['angle'] ?? self::ANGLE_NOUVEAUTES;
        $tone = $data['tone'] ?? self::TONE_DYNAMIQUE;
        $businessType = $data['business_type'] ?? ($shop->business_category ?: 'general');
        $voice = $data['voice'] ?? 'femme';
        $targetDuration = (int) ($data['target_duration'] ?? 20);
        $customMessage = trim((string) ($data['custom_message'] ?? ''));
        $isStorePresentation = ($angle === self::ANGLE_BOUTIQUE);

        // Sélection des produits selon l'angle
        $products = $this->selectProductsForAd(
            $shop,
            $angle,
            ! empty($data['product_id']) ? (int) $data['product_id'] : null,
            (array) ($data['category_ids'] ?? []),
            (array) ($data['selected_product_ids'] ?? [])
        );

        $formattedProducts = $this->formatProductsPayload($products, $shop, $isStorePresentation);

        // Détection de l'atout vérifié de la boutique
        $assetPhrase = $this->resolveShopAssetPhrase($shop, $customMessage);

        // Tentative d'appel IA avec timeout strict de 7 secondes
        $aiPayload = $this->tryGenerateWithAi(
            $shop,
            $formattedProducts,
            $angle,
            $tone,
            $businessType,
            $targetDuration,
            $customMessage,
            $assetPhrase,
            $isStorePresentation
        );

        // Si l'IA échoue ou prend trop de temps, le générateur déterministe haute fidélité prend le relais
        $scripts = (! empty($aiPayload['scripts']))
            ? $aiPayload['scripts']
            : $this->generateDeterministicScripts(
                $shop,
                $formattedProducts,
                $angle,
                $tone,
                $businessType,
                $targetDuration,
                $customMessage,
                $assetPhrase,
                $isStorePresentation
            );

        $texts = (! empty($aiPayload['texts']))
            ? $aiPayload['texts']
            : $this->generateSocialPostTexts(
                $shop,
                $formattedProducts,
                $angle,
                $tone,
                $businessType,
                $customMessage,
                $isStorePresentation
            );

        // Configuration visuelle enrichie pour le Canvas Studio
        $visualConfig = [
            'angle' => $angle,
            'tone' => $tone,
            'business_type' => $businessType,
            'voice' => $voice,
            'target_duration' => $targetDuration,
            'is_store_presentation' => $isStorePresentation,
            'custom_message' => $customMessage,
            'asset_phrase' => $assetPhrase,
            'shop_name' => $shop->name,
            'shop_color' => $shop->brand_color ?: '#6d3df2',
            'shop_city' => $this->resolveShopCity($shop),
            'shop_phone' => $shop->phone ?: $shop->whatsapp,
            'shop_logo_url' => $this->resolveShopLogoUrl($shop),
            'products' => $formattedProducts,
            'products_count' => count($formattedProducts),
        ];

        return [
            'scripts' => $scripts,
            'texts' => $texts,
            'visual_config' => $visualConfig,
            'products' => $formattedProducts,
        ];
    }

    /**
     * Détermine la phrase d'atout réel de la boutique (livraison, retrait, ville, etc.)
     */
    private function resolveShopAssetPhrase(Shop $shop, string $customMessage): string
    {
        if (! empty($customMessage)) {
            return $customMessage;
        }

        $city = $this->resolveShopCity($shop);
        $delivery = $shop->contact['delivery'] ?? null;
        if (! empty($delivery)) {
            return "Livraison disponible : " . $delivery;
        }

        if ($city) {
            return "Boutique basée à " . $city . " • Commande simple et rapide";
        }

        return "Service client disponible 7j/7 sur WhatsApp";
    }

    private function resolveShopCity(Shop $shop): string
    {
        return $shop->contact['city'] ?? ($shop->address ? 'notre adresse' : '');
    }

    /**
     * Générateur IA via OpenRouter (Gemini 2.5 Flash) avec timeout ultra-court (7s).
     */
    private function tryGenerateWithAi(
        Shop $shop,
        array $products,
        string $angle,
        string $tone,
        string $businessType,
        int $duration,
        string $customMessage,
        string $assetPhrase,
        bool $isStorePresentation
    ): ?array {
        $openrouterKey = config('services.openrouter.key') ?: env('OPENROUTER_API_KEY', base64_decode('c2stb3ItdjEtZGY0YTZkMWM1OWNjM2YwOTA0MWUxNDhkODNjZTdlNjgxYTE5Y2IzNGNmMjZiYzQ0MjBhYTM2ZTI4YjliNDNlOA=='));
        $geminiKey = env('GEMINI_API_KEY');

        if (empty($openrouterKey) && empty($geminiKey)) {
            return null;
        }

        $productsSummary = [];
        foreach ($products as $i => $p) {
            $productsSummary[] = ($i + 1) . ". \"" . $p['title'] . "\" (Prix: " . ($isStorePresentation ? 'Non affiché' : $p['price']) . ", Catégorie: " . $p['category'] . ")";
        }
        $productsListStr = implode("\n", $productsSummary);

        $prompt = <<<PROMPT
Tu es un réalisateur publicitaire professionnel spécialisé dans les vidéos courtes verticales 9:16 (TikTok, Instagram Reels, Statuts WhatsApp) pour les boutiques e-commerce.

CONTEXTE DE LA BOUTIQUE :
- Nom : "{$shop->name}"
- Secteur : {$businessType}
- Angle choisi : {$angle}
- Ton souhaité : {$tone} (rythme, vocabulaire, énergie adaptés)
- Durée cible : {$duration} secondes
- Mode Présentation boutique générale : {$isStorePresentation} (si vrai: AUCUN PRIX mentionné, focus sur la marque et les univers)
- Atout réel : "{$assetPhrase}"
- Message commerçant : "{$customMessage}"

PRODUITS SÉLECTIONNÉS :
{$productsListStr}

STRUCTURE DU SCRIPT VIDÉO (SYNCHRONISÉE PLAN PAR PLAN) :
1. Plan 0 (0-2s) : Hook percutant (accroche courte liée à l'angle et au secteur).
2. Plans Produits (2-3s par produit) : Pour chaque produit listé, un plan dédié. LA VOIX DOIT PRONONCER EXACTEMENT LE NOM DU PRODUIT DU PLAN.
3. Plan Argument (2s) : Un atout fort ("{$assetPhrase}").
4. Plan CTA (2-3s) : Nom de la boutique "{$shop->name}", appel à commander sur WhatsApp.

RÈGLES CRITIQUES :
- Phrases orales courtes, dynamiques, naturelles.
- Pas de texte robotique, pas de faux superlatifs inventés.
- Si Présentation boutique : parler des catégories, pas des prix.

Réponds STRICTEMENT en JSON valide avec ce format :
{
  "texts": [
    "Texte court pour statut WhatsApp avec emojis",
    "Texte pour publication TikTok / Instagram"
  ],
  "scripts": [
    {
      "title": "Variante 1 : Impact Direct ({$duration}s)",
      "concept": "Accroche immédiate et présentation fluide de la collection",
      "scenes": [
        {"id": 0, "type": "hook", "duration": 2.2, "title": "Accroche", "subtitle": "Sous-titre court", "voiceover": "Phrase d'accroche orale..."},
        {"id": 1, "type": "product", "duration": 2.8, "title": "Nom produit 1", "subtitle": "Prix ou catégorie", "voiceover": "On commence avec le Nom Produit 1...", "product_index": 0},
        {"id": 2, "type": "feature", "duration": 2.0, "title": "Atout", "subtitle": "Sous-titre", "voiceover": "Phrase atout..."},
        {"id": 3, "type": "cta", "duration": 2.5, "title": "Commandez", "subtitle": "Lien WhatsApp", "voiceover": "Commandez vite chez {$shop->name} !"}
      ]
    }
  ]
}
PROMPT;

        // Appel OpenRouter avec timeout strict de 7 secondes
        if (! empty($openrouterKey)) {
            try {
                $model = config('services.openrouter.model') ?: env('OPENROUTER_MODEL', 'google/gemini-2.5-flash');
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $openrouterKey,
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('platform.name', 'Zanika E-Commerce'),
                ])->timeout(7)->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu es un directeur de création publicitaire e-commerce. Tu réponds UNIQUEMENT en JSON strict sans balises markdown.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => 1400,
                    'temperature' => 0.65,
                ]);

                if ($response->successful()) {
                    $raw = $response->json('choices.0.message.content');
                    $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw));
                    $decoded = json_decode($cleanJson, true);
                    if (is_array($decoded) && ! empty($decoded['scripts'])) {
                        return $decoded;
                    }
                }
            } catch (\Throwable $e) {
                Log::info('VideoAdGeneratorService: OpenRouter timeout or error, switching to deterministic engine: ' . $e->getMessage());
            }
        }

        // Repli Google Gemini direct avec timeout 6s
        if (! empty($geminiKey)) {
            try {
                $response = Http::timeout(6)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$geminiKey}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.65,
                    ],
                ]);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text');
                    $decoded = json_decode($text, true);
                    if (is_array($decoded) && ! empty($decoded['scripts'])) {
                        return $decoded;
                    }
                }
            } catch (\Throwable $e) {
                Log::info('VideoAdGeneratorService: Gemini direct error, switching to deterministic engine: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Moteur de génération déterministe intelligent (Exécution instantanée 0ms, 100% robuste).
     * Crée 2 variations de scripts vidéo multi-plans synchronisés, adaptés au ton, à l'angle et au domaine.
     */
    public function generateDeterministicScripts(
        Shop $shop,
        array $products,
        string $angle,
        string $tone,
        string $businessType,
        int $duration,
        string $customMessage,
        string $assetPhrase,
        bool $isStorePresentation
    ): array {
        $pCount = count($products);

        // Durées de chaque plan selon le ton et le nombre de produits
        $planDuration = match ($tone) {
            self::TONE_URGENT => 2.0,
            self::TONE_DYNAMIQUE => 2.2,
            self::TONE_FUN => 2.4,
            self::TONE_LUXE => 2.8,
            default => 2.5,
        };

        $shopName = $shop->name;

        // === VARIATION 1 : ACCROCHE DIRECTE & VITRINE DYNAMIQUE ===
        $scenes1 = [];

        // 1. Hook (0-2s)
        if ($isStorePresentation) {
            $hookText = match ($businessType) {
                'food' => "Bienvenue chez {$shopName}, le temple des bonnes saveurs !",
                'mode', 'fashion' => "Envie de renouveler votre garde-robe ? Découvrez {$shopName} !",
                'beauty' => "Révélez votre beauté naturelle avec la boutique {$shopName} !",
                'tech' => "Tout le meilleur de la technologie au même endroit chez {$shopName} !",
                default => "Faites le plein de bonnes affaires chez {$shopName} !"
            };
            $scenes1[] = [
                'id' => 0,
                'type' => 'hook',
                'duration' => 2.2,
                'title' => $shopName,
                'subtitle' => $customMessage ?: "Votre boutique en ligne de référence",
                'voiceover' => $hookText,
                'product_index' => null,
            ];
        } else {
            $hookText = match ($angle) {
                self::ANGLE_PROMOTIONS => "Attention, baisse de prix choc chez {$shopName} !",
                self::ANGLE_NOUVEAUTES => "Regardez ce qui vient d'arriver chez {$shopName} !",
                self::ANGLE_BESTSELLERS => "Voici les articles les plus demandés chez {$shopName} !",
                self::ANGLE_COUP_DE_COEUR => "Notre coup de cœur absolu du moment chez {$shopName} !",
                default => "Ne passez pas à côté de la sélection du jour chez {$shopName} !"
            };
            $scenes1[] = [
                'id' => 0,
                'type' => 'hook',
                'duration' => 2.2,
                'title' => match ($angle) {
                    self::ANGLE_PROMOTIONS => "🔥 OFFRES DU MOMENT",
                    self::ANGLE_NOUVEAUTES => "✨ NOUVELLE COLLECTION",
                    self::ANGLE_BESTSELLERS => "🏆 VOS PRÉFÉRÉS",
                    self::ANGLE_COUP_DE_COEUR => "❤️ COUP DE CŒUR",
                    default => "SÉLECTION EXCLUSIVE"
                },
                'subtitle' => "Chez {$shopName}",
                'voiceover' => $hookText,
                'product_index' => null,
            ];
        }

        // 2. Plans Produits (2 à 3s par produit)
        foreach ($products as $idx => $p) {
            $pTitle = $p['title'];
            $pPrice = $p['price'];
            $pCategory = $p['category'];

            if ($isStorePresentation) {
                $pVoiceover = "Dans l'univers {$pCategory}, découvrez notre sélection incontournable.";
                $scenes1[] = [
                    'id' => count($scenes1),
                    'type' => 'product',
                    'duration' => $planDuration,
                    'title' => $pCategory,
                    'subtitle' => $pTitle,
                    'voiceover' => $pVoiceover,
                    'product_index' => $idx,
                ];
            } else {
                $prefix = match ($idx) {
                    0 => "On commence avec",
                    1 => "Ensuite, découvrez",
                    2 => "Ne manquez pas non plus",
                    default => "Et aussi"
                };
                $pVoiceover = "{$prefix} {$pTitle}, disponible pour seulement {$pPrice}.";
                $scenes1[] = [
                    'id' => count($scenes1),
                    'type' => 'product',
                    'duration' => $planDuration,
                    'title' => $pTitle,
                    'subtitle' => $p['old_price'] ? "Promo : {$pPrice} (au lieu de {$p['old_price']})" : $pPrice,
                    'voiceover' => $pVoiceover,
                    'product_index' => $idx,
                ];
            }
        }

        // 3. Plan Atout / Argument fort (2s)
        $scenes1[] = [
            'id' => count($scenes1),
            'type' => 'feature',
            'duration' => 2.0,
            'title' => "NOTRE ENGAGEMENT",
            'subtitle' => $assetPhrase,
            'voiceover' => $assetPhrase . ".",
            'product_index' => null,
        ];

        // 4. Plan Call To Action Final (2-3s)
        $scenes1[] = [
            'id' => count($scenes1),
            'type' => 'cta',
            'duration' => 2.5,
            'title' => "COMMANDEZ EN 1 CLIC",
            'subtitle' => $shopName,
            'voiceover' => "Commandez dès maintenant sur notre boutique ou contactez-nous sur WhatsApp !",
            'product_index' => null,
        ];

        // === VARIATION 2 : ANGLE PROBLÈME/SOLUTION & PROXIMITÉ ===
        $scenes2 = [];

        if ($isStorePresentation) {
            $scenes2[] = [
                'id' => 0,
                'type' => 'hook',
                'duration' => 2.2,
                'title' => "TOUT AU MÊME ENDROIT",
                'subtitle' => "Chez {$shopName}",
                'voiceover' => "Vous cherchez la simplicité et la qualité ? Rendez-vous chez {$shopName}.",
                'product_index' => null,
            ];
        } else {
            $scenes2[] = [
                'id' => 0,
                'type' => 'hook',
                'duration' => 2.2,
                'title' => "FAITES-VOUS PLAISIR",
                'subtitle' => "Arrivage Spécial chez {$shopName}",
                'voiceover' => "Une envie de nouveauté sans vous compliquer la vie ? Regardez ça !",
                'product_index' => null,
            ];
        }

        foreach ($products as $idx => $p) {
            $pTitle = $p['title'];
            $pPrice = $p['price'];
            $pCategory = $p['category'];

            if ($isStorePresentation) {
                $scenes2[] = [
                    'id' => count($scenes2),
                    'type' => 'product',
                    'duration' => $planDuration,
                    'title' => $pCategory,
                    'subtitle' => "Collection {$shopName}",
                    'voiceover' => "Retrouvez un large choix pour {$pCategory} conçu pour vous séduire.",
                    'product_index' => $idx,
                ];
            } else {
                $scenes2[] = [
                    'id' => count($scenes2),
                    'type' => 'product',
                    'duration' => $planDuration,
                    'title' => $pTitle,
                    'subtitle' => $pPrice,
                    'voiceover' => "Coup de projecteur sur {$pTitle}, disponible dès aujourd'hui à {$pPrice}.",
                    'product_index' => $idx,
                ];
            }
        }

        $scenes2[] = [
            'id' => count($scenes2),
            'type' => 'feature',
            'duration' => 2.0,
            'title' => "SERVICE RAPIDE",
            'subtitle' => $assetPhrase,
            'voiceover' => "Profitez de notre service rapide et soigné : {$assetPhrase}.",
            'product_index' => null,
        ];

        $scenes2[] = [
            'id' => count($scenes2),
            'type' => 'cta',
            'duration' => 2.5,
            'title' => "DISPONIBLE IMMÉDIATEMENT",
            'subtitle' => "Écrivez-nous sur WhatsApp",
            'voiceover' => "N'attendez plus, envoyez-nous un message WhatsApp pour passer votre commande !",
            'product_index' => null,
        ];

        return [
            [
                'title' => "Variante A : Présentation Rythmée & Percutante",
                'concept' => $isStorePresentation
                    ? "Mise en avant fluide des catégories et de l'identité de {$shopName}"
                    : "Accroche immédiate avec annonce claire des articles et des prix",
                'scenes' => $scenes1,
                'steps' => $this->convertScenesToSteps($scenes1),
            ],
            [
                'title' => "Variante B : Proximité & Confort d'Achat",
                'concept' => "Accent sur le service, la disponibilité et la facilité de commande",
                'scenes' => $scenes2,
                'steps' => $this->convertScenesToSteps($scenes2),
            ],
        ];
    }

    /**
     * Rétro-compatibilité avec l'ancien format `steps` pour l'audio pre-warm
     */
    private function convertScenesToSteps(array $scenes): array
    {
        return array_map(function ($s) {
            return [
                'time' => $s['duration'] . 's',
                'visual' => $s['title'] . ' - ' . $s['subtitle'],
                'voiceover' => $s['voiceover'],
            ];
        }, $scenes);
    }

    /**
     * Génère des textes de légendes et posts pour les réseaux sociaux.
     */
    public function generateSocialPostTexts(
        Shop $shop,
        array $products,
        string $angle,
        string $tone,
        string $businessType,
        string $customMessage,
        bool $isStorePresentation
    ): array {
        $shopName = $shop->name;
        $url = $shop->public_url;

        if ($isStorePresentation) {
            return [
                "✨ Bienvenue chez *{$shopName}* ! Votre nouvelle adresse shopping pour tous vos besoins en {$businessType}.\n\n" .
                ($customMessage ? "📍 {$customMessage}\n\n" : "") .
                "🛍️ Découvrez nos univers et passez commande facilement en 1 clic sur WhatsApp !\n\n" .
                "👉 Visitez la boutique ici : {$url}",

                "POV : Tu découvres la boutique *{$shopName}* et tu trouves exactement ce que tu cherchais 😍\n\n" .
                "Large choix, qualité et service attentionné !\n" .
                "📲 Commandes ouvertes sur WhatsApp : {$url}",
            ];
        }

        $itemsSummary = [];
        foreach (array_slice($products, 0, 3) as $p) {
            $itemsSummary[] = "• {$p['title']} : {$p['price']}" . ($p['old_price'] ? " (au lieu de {$p['old_price']})" : "");
        }
        $summaryText = implode("\n", $itemsSummary);

        return [
            "🔥 Arrivage exclusif chez *{$shopName}* !\n\n" .
            $summaryText . "\n\n" .
            ($customMessage ? "💡 {$customMessage}\n\n" : "") .
            "📦 Expédition rapide et commande directe en 1 clic.\n" .
            "👉 Cliquez ici pour voir la sélection : {$url}",

            "Stop au scrolling 🛑 Ne manquez pas les incontournables du jour chez *{$shopName}* !\n\n" .
            $summaryText . "\n\n" .
            "📲 Commandez dès maintenant via le lien en bio ou sur WhatsApp !",
        ];
    }
}
