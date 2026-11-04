<?php

namespace App\Services\Ai;

use App\Models\AiGeneration;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiVisionService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.key')
            ?: env('OPENROUTER_API_KEY', base64_decode('c2stb3ItdjEtZGY0YTZkMWM1OWNjM2YwOTA0MWU0MThkODNjZTdlNjgxYTE5Y2IzNGNmMjZiYzQ0MjBhYTM2ZTI4YjliNDNlOA=='));
        
        $this->model = 'google/gemini-2.5-flash';
    }

    /**
     * Analyse une photo de produit via Vision Multimodale et prépare une fiche produit complète.
     */
    public function analyzeProductPhoto(string $imageBinaryOrDataUrl, Shop $shop, ?string $userHint = null): array
    {
        $startTime = microtime(true);

        // Préparation du Data URL pour le LLM
        $dataUrl = str_starts_with($imageBinaryOrDataUrl, 'data:image')
            ? $imageBinaryOrDataUrl
            : 'data:image/jpeg;base64,' . base64_encode($imageBinaryOrDataUrl);

        // Catégories existantes pour favoriser le matching
        $existingCategories = $shop->categories()->pluck('name')->all();
        $categoriesContext = ! empty($existingCategories)
            ? 'Catégories déjà configurées dans cette boutique : ' . implode(', ', $existingCategories) . '.'
            : 'Propose une catégorie cohérente.';

        $hintContext = $userHint ? "Indication fournie par le commerçant : \"{$userHint}\"." : '';

        $prompt = <<<PROMPT
Tu es un expert mondial en catalogage e-commerce et en rédaction persuasive pour les boutiques en ligne en Afrique de l'Ouest (Togo, Bénin, Côte d'Ivoire, Sénégal...).

Analyse minutieusement cette photo de produit et génère les informations complètes pour sa mise en vente.

{$categoriesContext}
{$hintContext}

RÈGLES IMPÉRATIVES :
1. Examine attentivement l'image : type de produit, forme, couleurs visibles, matière apparente, finitions, motif.
2. NE JAMAIS INVENTER de caractéristiques techniques internes ou non vérifiables (ex: batterie cachée, ingrédients chimiques invisibles).
3. Titre commercial : Vendeur, précis, maximum 60 caractères, en français.
4. Description : 2 à 3 paragraphes vendeurs mettant en avant le style, le confort, les atouts et l'élégance de l'article.
5. Fourchette de prix en FCFA : propose un prix d'achat réaliste et compétitif sur le marché ouest-africain.
6. Catégorie : Associe à l'une des catégories existantes si possible, sinon propose une catégorie claire.
7. Mots-clés SEO : 6 à 8 mots-clés pertinents pour les recherches Google et WhatsApp.

Réponds STRICTEMENT par un objet JSON valide suivant exactement cette structure (sans balises markdown supplémentaires) :
{
  "title": "Nom commercial du produit",
  "description": "Description captivante et persuasive du produit...",
  "visible_features": ["Caractéristique visible 1", "Caractéristique visible 2", "Caractéristique 3"],
  "category_name": "Nom de la catégorie",
  "price_fcfa": 15000,
  "price_range": {
    "min_fcfa": 12000,
    "max_fcfa": 18000,
    "recommended_fcfa": 15000
  },
  "compare_at_price_fcfa": 20000,
  "seo_keywords": ["mot1", "mot2", "mot3", "mot4", "mot5", "mot6"],
  "suggested_variants": {
    "tailles": ["S", "M", "L", "XL"],
    "couleurs": ["Couleur principale"]
  },
  "confidence_score": 0.95
}
PROMPT;

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $dataUrl,
                            ],
                        ],
                    ],
                ],
            ],
            'temperature' => 0.2,
            'max_tokens' => 1500,
        ];

        try {
            $cleanUrl = trim(preg_replace('/[\r\n\t]+/', '', (string) config('app.url', 'https://zanika.shop')));
            $client = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'HTTP-Referer' => $cleanUrl ?: 'https://zanika.shop',
                'X-Title' => 'Zanika AI Vision',
                'Content-Type' => 'application/json',
            ])->timeout(30);

            if (! app()->environment('production')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post('https://openrouter.ai/api/v1/chat/completions', $payload);

            if ($response->successful()) {
                $rawContent = $response->json('choices.0.message.content') ?? '{}';
                
                // Nettoyage des balises markdown si présentes (```json ... ```)
                $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim($rawContent));
                $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);

                $data = json_decode($cleanJson, true);
                if (! is_array($data)) {
                    Log::warning('AiVisionService: JSON parsing failed', ['raw' => $rawContent]);
                    $data = $this->fallbackProductData($userHint);
                }

                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                // Enregistrement de la consommation
                AiGeneration::create([
                    'shop_id' => $shop->id,
                    'type' => 'ai_product_vision',
                    'step' => 'photo_analysis',
                    'model_used' => $this->model,
                    'prompt_tokens' => $response->json('usage.prompt_tokens', 0),
                    'completion_tokens' => $response->json('usage.completion_tokens', 0),
                    'cost_estimated_fcfa' => 15,
                    'duration_ms' => $durationMs,
                    'status' => 'success',
                    'input_summary' => 'Analyse photo produit',
                    'output_data' => $data,
                ]);

                return array_merge([
                    'status' => 'success',
                    'currency' => $shop->currency,
                ], $data);
            }

            Log::error('AiVisionService OpenRouter Error: ' . $response->body());
            return $this->fallbackProductData($userHint);

        } catch (\Throwable $e) {
            Log::error('AiVisionService Exception: ' . $e->getMessage());
            return $this->fallbackProductData($userHint);
        }
    }

    /**
     * Améliore une fiche produit existante (SEO, copywriting, mise en valeur).
     */
    public function optimizeExistingProduct(Product $product, Shop $shop): array
    {
        $startTime = microtime(true);

        $prompt = <<<PROMPT
Tu es un copywriter e-commerce d'élite.
Améliore et optimise cette fiche produit pour maximiser son taux de conversion et son référencement SEO sur le marché ouest-africain.

Informations actuelles du produit :
- Titre : {$product->title}
- Description actuelle : {$product->description}
- Prix actuel : {$product->price} {$shop->currency}
- Référence (SKU) : {$product->sku}

Génère une version optimisée :
1. Un titre plus vendeur, percutant et professionnel (max 60 caractères).
2. Une description structurée avec des puces convaincantes (Les points forts, Conseils d'utilisation/style, Entretien).
3. Une recommandation de prix barré (compare_at_price) pour susciter l'achat immédiat.
4. 6 mots-clés SEO recherchés par les acheteurs locaux.

Réponds STRICTEMENT sous format JSON :
{
  "optimized_title": "Titre amélioré",
  "optimized_description": "Description captivante et structurée...",
  "suggested_compare_price": 0,
  "selling_points": ["Point fort 1", "Point fort 2", "Point fort 3"],
  "seo_keywords": ["mot1", "mot2", "mot3", "mot4", "mot5", "mot6"]
}
PROMPT;

        try {
            $client = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(20);

            if (! app()->environment('production')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.4,
            ]);

            if ($response->successful()) {
                $rawContent = $response->json('choices.0.message.content') ?? '{}';
                $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim($rawContent));
                $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);

                $data = json_decode($cleanJson, true) ?: [];

                AiGeneration::create([
                    'shop_id' => $shop->id,
                    'type' => 'ai_product_optimize',
                    'step' => 'copywriting_optimization',
                    'model_used' => $this->model,
                    'prompt_tokens' => $response->json('usage.prompt_tokens', 0),
                    'completion_tokens' => $response->json('usage.completion_tokens', 0),
                    'cost_estimated_fcfa' => 10,
                    'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                    'status' => 'success',
                    'input_summary' => "Optimisation du produit #{$product->id} : {$product->title}",
                    'output_data' => $data,
                ]);

                return array_merge(['status' => 'success'], $data);
            }

            return ['status' => 'error', 'message' => 'Erreur lors de l\'optimisation'];
        } catch (\Throwable $e) {
            Log::error('optimizeExistingProduct Exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Génère un visuel promotionnel ou publicitaire de niveau studio via le moteur Flux.
     */
    public function generateMarketingVisual(Shop $shop, array $params): array
    {
        $startTime = microtime(true);

        $style = $params['style'] ?? 'studio_minimalist';
        $format = $params['format'] ?? '1:1'; // 1:1, 9:16, 16:9
        $subject = trim($params['subject'] ?? 'Produit tendance');
        $badgeText = $params['badge_text'] ?? null;
        $productId = ! empty($params['product_id']) ? (int) $params['product_id'] : null;

        // Dimensions selon le format
        [$width, $height] = match ($format) {
            '9:16' => [720, 1280],
            '16:9' => [1280, 720],
            default => [800, 800],
        };

        // Enrichissement de prompt selon le style professionnel
        $styleEnhancer = match ($style) {
            'luxury_showroom' => 'in an ultra-luxurious modern boutique showroom, polished marble floor, ambient architectural spotlights, elegant golden accents, high fashion editorial lighting, 8k masterpiece',
            'lifestyle_urban' => 'in an authentic trendy urban lifestyle setting in West Africa, warm natural golden hour sunlight, vibrant aesthetic, depth of field blur, photorealistic',
            'flash_sale' => 'bold commercial advertising banner aesthetic, vibrant energetic gradient background, floating geometric pedestal, dramatic studio flash lighting, eye-catching sales visual',
            'tropical_african' => 'modern Afro-chic minimalist aesthetic, warm terracotta and lush monstera leaves, soft morning sun, high-end African design lookbook',
            default => 'clean minimalist studio product photography, pristine white and neutral backdrop, soft diffused commercial lightbox illumination, crisp sharp details, commercial catalogue photography',
        };

        $fullPrompt = "Professional commercial advertising visual of {$subject}, {$styleEnhancer}, photorealistic, stunning quality, no text distortion, 4k UHD";

        try {
            $encodedPrompt = urlencode($fullPrompt);
            $imageUrl = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width={$width}&height={$height}&nologo=true&model=flux";

            $client = Http::timeout(35);
            if (! app()->environment('production')) {
                $client = $client->withoutVerifying();
            }

            $imageResponse = $client->get($imageUrl);

            if (! $imageResponse->successful() || empty($imageResponse->body())) {
                return ['status' => 'error', 'message' => 'Impossible de générer le visuel pour le moment.'];
            }

            $imageBinary = $imageResponse->body();
            $filename = 'ai_visual_' . uniqid() . '.jpg';
            $storageRelativePath = "shops/{$shop->id}/visuals/{$filename}";

            Storage::disk('public')->put($storageRelativePath, $imageBinary);

            // Si rattaché à un produit, enregistrer dans ProductImage
            $productImage = null;
            if ($productId) {
                $product = Product::where('shop_id', $shop->id)->find($productId);
                if ($product) {
                    $maxOrder = $product->images()->max('sort_order') ?? 0;
                    $productImage = $product->images()->create([
                        'path' => $storageRelativePath,
                        'sort_order' => $maxOrder + 1,
                        'size_bytes' => strlen($imageBinary),
                    ]);
                }
            }

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            AiGeneration::create([
                'shop_id' => $shop->id,
                'type' => 'ai_visual_generation',
                'step' => 'image_studio_flux',
                'model_used' => 'flux-schnell',
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'cost_estimated_fcfa' => 50,
                'duration_ms' => $durationMs,
                'status' => 'success',
                'input_summary' => "Génération visuel [{$style}, {$format}] : {$subject}",
                'output_data' => [
                    'path' => $storageRelativePath,
                    'format' => $format,
                    'style' => $style,
                ],
            ]);

            return [
                'status' => 'success',
                'image_url' => Storage::disk('public')->url($storageRelativePath),
                'storage_path' => $storageRelativePath,
                'product_image_id' => $productImage?->id,
                'style' => $style,
                'format' => $format,
            ];

        } catch (\Throwable $e) {
            Log::error('generateMarketingVisual Exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Erreur lors de la génération : ' . $e->getMessage()];
        }
    }

    /**
     * Analyse multimodale du produit et génération de mises en scène professionnelles contextuelles (comme Shafrik / Photoroom).
     * Adapte le décor selon la nature de l'article :
     * - Vêtements : portant moderne en métal noir dans une chambre/dressing contemporain avec parquet et lumière naturelle
     * - Chaussures : sol parquet boisé élégant avec ombre portée douce
     * - Bijoux/Montres : plateau de marbre poli ou socle velours avec reflets dorés
     * - Cosmétiques/Parfums : comptoir en pierre travertin avec ambiance spa épurée
     * - Sacs/Maroquinerie : console design ou fauteuil d'entrée chic
     */
    /**
     * Détoure précisément le produit via le modèle IA Inspyrenet (RMBG) sans jamais altérer l'objet.
     */
    public function removeBackground(string $imageBinary): ?string
    {
        try {
            $b64 = 'data:image/jpeg;base64,' . base64_encode($imageBinary);
            $spaceUrl = 'https://gokaygokay-inspyrenet-rembg.hf.space/gradio_api';

            $payload = json_encode([
                'data' => [
                    ['url' => $b64],
                    'Default'
                ]
            ]);

            $ch = curl_init("{$spaceUrl}/call/predict");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 18);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200 || empty($res)) {
                return null;
            }

            $json = json_decode($res, true);
            if (empty($json['event_id'])) {
                return null;
            }

            $eventId = $json['event_id'];
            $ch = curl_init("{$spaceUrl}/call/predict/{$eventId}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $stream = curl_exec($ch);
            curl_close($ch);

            if (preg_match('/"url":\s*"([^"]+)"/', (string) $stream, $matches)) {
                $fileUrl = str_replace('/g/gradio_api/', '/gradio_api/', $matches[1]);
                $ch2 = curl_init($fileUrl);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 20);
                $img = curl_exec($ch2);
                $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);

                if ($code2 === 200 && strlen($img) > 1000) {
                    return $img;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('removeBackground error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Analyse le produit par Vision et génère de vrais packshots e-commerce professionnels
     * en isolant le produit réel et en l'incrustant sur décors studio haut de gamme avec ombrage naturel.
     * ZERO hallucination, ZERO faux objet, ZERO filigrane.
     */
    public function generateContextualStagings(Shop $shop, string $imageBinary, ?string $userHint = null): array
    {
        $uniq = uniqid();
        $stagings = [];
        $category = 'general';
        $productTitle = 'Article de vente';

        // 1. Analyse Vision rapide pour catégorisation et titre
        try {
            $dataUrl = str_starts_with($imageBinary, 'data:image')
                ? $imageBinary
                : 'data:image/jpeg;base64,' . base64_encode($imageBinary);

            $client = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(8);

            if (! app()->environment('production')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Analyse ce produit e-commerce. Détermine précisément product_type parmi : "shoes" (chaussures, talons, baskets, mules, sandales, bottes), "clothing_hanging" (vêtements sur cintre : vestes, robes, chemises, manteaux), "clothing_flat" (vêtements pliés, t-shirts, pantalons), "cosmetics" (beauté, parfums, crèmes, flacons), "jewelry" (bijoux, montres), "bags" (sacs à main), ou "general" (autre). Donne aussi un titre commercial court en français. Réponds STRICTEMENT en JSON: {"product_type": "...", "title": "..."}'
                            ],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]]
                        ]
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 200,
            ]);

            if ($response->successful()) {
                $raw = $response->json('choices.0.message.content') ?? '{}';
                $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw));
                $parsed = json_decode($clean, true);
                if (! empty($parsed['product_type'])) {
                    $category = $parsed['product_type'];
                } elseif (! empty($parsed['category'])) {
                    $category = $parsed['category'];
                }
                if (! empty($parsed['title'])) $productTitle = $parsed['title'];
            }
        } catch (\Throwable $e) {
            Log::warning('generateContextualStagings Vision meta failed: ' . $e->getMessage());
        }

        // 2. Détourage IA chirurgical du produit réel
        $cutoutPng = $this->removeBackground($imageBinary);

        /** @var \App\Services\ImageService $imageService */
        $imageService = app(\App\Services\ImageService::class);

        if ($cutoutPng) {
            // Sauvegarder le PNG transparent pour variantes futures
            $cutoutFilename = "cutout_{$uniq}.png";
            $cutoutPath = "shops/{$shop->id}/products_ai/{$cutoutFilename}";
            Storage::disk('public')->put($cutoutPath, $cutoutPng);

            $cutoutGd = @imagecreatefromstring($cutoutPng);
            if ($cutoutGd) {
                // Rendu 1 : Mise en scène Lifestyle contextuelle adaptée au produit (ex: Dressing Loft, Bain & Spa Travertin, Parquet Chêne)
                $lifestyle = $imageService->buildLifestyleScene($cutoutGd, $category, 1000);
                if ($lifestyle && ! empty($lifestyle['canvas'])) {
                    $lifeFilename = "lifestyle_{$uniq}.jpg";
                    $lifePath = "shops/{$shop->id}/products_ai/{$lifeFilename}";
                    $tmpL = tempnam(sys_get_temp_dir(), 'ai_lf_');
                    imagejpeg($lifestyle['canvas'], $tmpL, 94);
                    Storage::disk('public')->put($lifePath, file_get_contents($tmpL));
                    @unlink($tmpL);
                    imagedestroy($lifestyle['canvas']);

                    $stagings[] = [
                        'id' => 'lifestyle_staged',
                        'type' => 'generated',
                        'title' => $lifestyle['title'] ?? 'Mise en scène Décor Réel',
                        'url' => Storage::disk('public')->url($lifePath),
                        'path' => $lifePath,
                        'cutout_path' => $cutoutPath,
                        'selected' => true,
                        'is_source' => false,
                        'badge' => 'IA Lifestyle',
                    ];
                }

                // Rendu 2 : Studio Blanc Épuré (Standard Marketplace 1:1 Amazon / Zara)
                $whitePackshot = $imageService->buildStudioPackshot($cutoutGd, 'white', 1000);
                if ($whitePackshot) {
                    $whiteFilename = "studio_white_{$uniq}.jpg";
                    $whitePath = "shops/{$shop->id}/products_ai/{$whiteFilename}";
                    $tmpW = tempnam(sys_get_temp_dir(), 'ai_w_');
                    imagejpeg($whitePackshot, $tmpW, 94);
                    Storage::disk('public')->put($whitePath, file_get_contents($tmpW));
                    @unlink($tmpW);
                    imagedestroy($whitePackshot);

                    $stagings[] = [
                        'id' => 'studio_white',
                        'type' => 'generated',
                        'title' => 'Studio Blanc Épuré',
                        'url' => Storage::disk('public')->url($whitePath),
                        'path' => $whitePath,
                        'cutout_path' => $cutoutPath,
                        'selected' => empty($stagings),
                        'is_source' => false,
                        'badge' => 'Studio Pro',
                    ];
                }

                // Rendu 3 : Studio Ambiance Douce Ivoire / Perle épuré
                $ambientPackshot = $imageService->buildStudioPackshot($cutoutGd, 'ambient_light', 1000);
                if ($ambientPackshot) {
                    $ambientFilename = "studio_ambient_{$uniq}.jpg";
                    $ambientPath = "shops/{$shop->id}/products_ai/{$ambientFilename}";
                    $tmpA = tempnam(sys_get_temp_dir(), 'ai_a_');
                    imagejpeg($ambientPackshot, $tmpA, 94);
                    Storage::disk('public')->put($ambientPath, file_get_contents($tmpA));
                    @unlink($tmpA);
                    imagedestroy($ambientPackshot);

                    $stagings[] = [
                        'id' => 'studio_ambient',
                        'type' => 'generated',
                        'title' => 'Studio Ambiance Douce',
                        'url' => Storage::disk('public')->url($ambientPath),
                        'path' => $ambientPath,
                        'cutout_path' => $cutoutPath,
                        'selected' => false,
                        'is_source' => false,
                        'badge' => 'Ambiance Pro',
                    ];
                }

                imagedestroy($cutoutGd);
            }
        }

        // 3. Fallback de secours si le service de détourage était indisponible
        if (empty($stagings) && function_exists('imagecreatefromstring')) {
            $srcGd = @imagecreatefromstring($imageBinary);
            if ($srcGd) {
                $enhanced = $imageService->enhancePhotoQuality($srcGd);
                $studio = $imageService->formatStudioSquare($enhanced, 1000);
                $fallbackFilename = "studio_fallback_{$uniq}.jpg";
                $fallbackPath = "shops/{$shop->id}/products_ai/{$fallbackFilename}";
                $tmpF = tempnam(sys_get_temp_dir(), 'ai_fb_');
                imagejpeg($studio, $tmpF, 93);
                Storage::disk('public')->put($fallbackPath, file_get_contents($tmpF));
                @unlink($tmpF);
                imagedestroy($studio);

                $stagings[] = [
                    'id' => 'studio_enhanced',
                    'type' => 'generated',
                    'title' => 'Studio Amélioré',
                    'url' => Storage::disk('public')->url($fallbackPath),
                    'path' => $fallbackPath,
                    'selected' => true,
                    'is_source' => false,
                    'badge' => 'Optimisé',
                ];
            }
        }

        return [
            'category' => $category,
            'product_title' => $productTitle,
            'stagings' => $stagings,
            'cutout_path' => $cutoutPath ?? null,
        ];
    }

    protected function fallbackProductData(?string $userHint): array
    {
        $title = $userHint ? ucfirst($userHint) : 'Nouvel Article Tendance';
        return [
            'status' => 'fallback',
            'title' => $title,
            'description' => "Découvrez cet article exceptionnel alliant élégance, confort et confection soignée. Parfait pour rehausser votre style au quotidien.",
            'visible_features' => ['Confection soignée', 'Finition premium', 'Design contemporain'],
            'category_name' => 'Nouveautés',
            'price_fcfa' => 12500,
            'price_range' => [
                'min_fcfa' => 10000,
                'max_fcfa' => 15000,
                'recommended_fcfa' => 12500,
            ],
            'compare_at_price_fcfa' => 15000,
            'seo_keywords' => ['mode', 'tendance', 'qualité', 'achat en ligne', 'livraison rapide'],
            'suggested_variants' => [
                'tailles' => ['Standard', 'M', 'L', 'XL'],
                'couleurs' => ['Principal'],
            ],
            'confidence_score' => 0.75,
        ];
    }
}
