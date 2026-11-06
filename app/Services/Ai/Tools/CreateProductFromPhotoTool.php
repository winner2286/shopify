<?php

namespace App\Services\Ai\Tools;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use App\Services\Ai\AiVisionService;
use App\Services\Ai\Contracts\AiToolInterface;
use Illuminate\Support\Str;

class CreateProductFromPhotoTool implements AiToolInterface
{
    public function __construct(
        protected ?AiVisionService $visionService = null,
        protected ?ActionTokenService $tokenService = null
    ) {
        $this->visionService = $visionService ?? new AiVisionService();
        $this->tokenService = $tokenService ?? new ActionTokenService();
    }

    public function name(): string
    {
        return 'create_product_from_photo';
    }

    public function description(): string
    {
        return 'Analyse une photo de produit via Vision Multimodale (IA), extrait le titre vendeur, la description persuasive, la catégorie et le prix recommandé en FCFA, puis prépare la création de la fiche produit avec validation humaine.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['image_data'],
            'properties' => [
                'image_data' => [
                    'type' => 'string',
                    'description' => 'Image encodée en Base64 / Data URL (ex: "data:image/jpeg;base64,...") ou chemin d\'accès du fichier dans le stockage.',
                ],
                'merchant_hint' => [
                    'type' => 'string',
                    'description' => 'Indication optionnelle donnée par le commerçant (ex: "C\'est une robe en pagne tissé taille 40", "Chaussure en cuir véritable").',
                ],
                'price_override' => [
                    'type' => 'integer',
                    'description' => 'Prix fixé manuellement en FCFA par le commerçant (écrase le prix suggéré par l\'IA si précisé).',
                ],
                'stock' => [
                    'type' => 'integer',
                    'description' => 'Quantité initiale en stock (défaut : 10).',
                ],
                'confirmed_by_user' => [
                    'type' => 'boolean',
                    'description' => 'True uniquement si l\'utilisateur a cliqué sur le bouton de confirmation.',
                ],
                'action_token' => [
                    'type' => 'string',
                    'description' => 'Jeton cryptographique HMAC signé autorisant la création.',
                ],
            ],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function execute(Shop $shop, array $arguments, ?User $user = null): array
    {
        // 1. Vérification du quota de catalogue selon le forfait
        if (! $shop->canAddProduct()) {
            return [
                'status' => 'quota_exceeded',
                'error' => "Limite de catalogue atteinte ({$shop->products()->count()} / {$shop->maxProducts()} produits). Veuillez mettre à niveau votre forfait d'abonnement Zanika pour ajouter de nouveaux articles.",
            ];
        }

        $isConfirmed = ! empty($arguments['confirmed_by_user']);

        // Étape 1 : Analyse multimodale de l'image et génération de la proposition
        if (! $isConfirmed) {
            $imageData = (string) ($arguments['image_data'] ?? '');
            if (empty($imageData)) {
                return ['status' => 'error', 'message' => "Aucune image fournie pour l'analyse visuelle."];
            }

            $hint = isset($arguments['merchant_hint']) ? trim((string) $arguments['merchant_hint']) : null;

            // Analyse via le service Vision
            $analysis = $this->visionService->analyzeProductPhoto($imageData, $shop, $hint);

            $title = ! empty($analysis['title']) ? trim((string) $analysis['title']) : 'Nouveau Produit';
            $description = ! empty($analysis['description']) ? trim((string) $analysis['description']) : 'Description du produit.';
            $suggestedPrice = (int) ($analysis['price_fcfa'] ?? ($analysis['price_range']['recommended_fcfa'] ?? 5000));
            $finalPrice = isset($arguments['price_override']) && is_numeric($arguments['price_override'])
                ? max(100, (int) $arguments['price_override'])
                : max(100, $suggestedPrice);

            $comparePrice = ! empty($analysis['compare_at_price_fcfa']) ? (int) $analysis['compare_at_price_fcfa'] : null;
            $categoryName = ! empty($analysis['category_name']) ? trim((string) $analysis['category_name']) : ($shop->business_category ?: 'Général');
            $stock = isset($arguments['stock']) && is_numeric($arguments['stock']) ? max(0, (int) $arguments['stock']) : 10;
            $sku = 'ART-' . strtoupper(Str::random(6));

            $productPayload = [
                'title' => $title,
                'description' => $description,
                'price' => $finalPrice,
                'compare_at_price' => $comparePrice,
                'stock' => $stock,
                'category_name' => $categoryName,
                'sku' => $sku,
                'status' => 'active',
            ];

            $initiator = $user ?? $shop->owner ?? User::first();
            $token = $this->tokenService->generateToken($shop, $initiator, $this->name(), $productPayload);

            $formattedPrice = number_format($finalPrice, 0, ',', ' ') . " {$shop->currency}";

            return [
                'status' => 'action_required',
                'action_type' => $this->name(),
                'action_token' => $token,
                'product_payload' => $productPayload,
                'analysis' => $analysis,
                'preview' => [
                    'title' => $title,
                    'price' => $formattedPrice,
                    'stock' => $stock,
                    'category' => $categoryName,
                    'sku' => $sku,
                ],
                'message' => "📸 Photo analysée avec succès ! Souhaitez-vous créer la fiche du produit « {$title} » au prix de {$formattedPrice} (Stock : {$stock}, Catégorie : {$categoryName}) ?",
            ];
        }

        // Étape 2 : Validation du jeton HMAC et insertion sécurisée en base
        $token = $arguments['action_token'] ?? '';
        $executor = $user ?? $shop->owner ?? User::first();

        $actionParams = [
            'title' => trim((string) ($arguments['title'] ?? '')),
            'description' => trim((string) ($arguments['description'] ?? '')),
            'price' => (int) ($arguments['price'] ?? 0),
            'compare_at_price' => isset($arguments['compare_at_price']) ? (int) $arguments['compare_at_price'] : null,
            'stock' => (int) ($arguments['stock'] ?? 0),
            'category_name' => trim((string) ($arguments['category_name'] ?? '')),
            'sku' => trim((string) ($arguments['sku'] ?? '')),
            'status' => 'active',
        ];

        if (! empty($token)) {
            $validation = $this->tokenService->validateToken($token, $shop, $executor, $this->name(), $actionParams);
            if (! $validation['valid']) {
                return [
                    'status' => 'security_rejection',
                    'error' => "Refus de sécurité : " . ($validation['error'] ?? 'Jeton invalide.'),
                ];
            }
        }

        // Création ou association de la catégorie
        $category = null;
        if (! empty($actionParams['category_name'])) {
            $category = Category::withoutGlobalScopes()->firstOrCreate(
                [
                    'shop_id' => $shop->id,
                    'name' => $actionParams['category_name'],
                ],
                [
                    'slug' => Str::slug($actionParams['category_name']) . '-' . Str::random(4),
                    'is_visible' => true,
                    'display_order' => 1,
                ]
            );
        }

        // Création du produit
        $baseSlug = Str::slug($actionParams['title']);
        $uniqueSlug = $baseSlug . '-' . Str::lower(Str::random(5));

        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => $actionParams['title'],
            'slug' => $uniqueSlug,
            'description' => $actionParams['description'],
            'price' => $actionParams['price'],
            'compare_at_price' => $actionParams['compare_at_price'],
            'stock' => $actionParams['stock'],
            'sku' => $actionParams['sku'],
            'status' => Product::STATUS_ACTIVE,
            'track_stock' => true,
        ]);

        if ($category) {
            $product->categories()->sync([$category->id]);
        }

        // Audit de mouvement de stock initial
        if ($actionParams['stock'] > 0) {
            StockMovement::withoutGlobalScopes()->create([
                'product_id' => $product->id,
                'user_id' => $executor?->id,
                'quantity_change' => $actionParams['stock'],
                'new_stock' => $actionParams['stock'],
                'reason' => 'initial_creation',
                'comment' => 'Création initiale du produit via Studio Vision IA (copilote)',
            ]);
        }

        $formattedPrice = number_format($product->price, 0, ',', ' ') . " {$shop->currency}";

        return [
            'status' => 'success',
            'product_id' => $product->id,
            'title' => $product->title,
            'price' => $product->price,
            'formatted_price' => $formattedPrice,
            'stock' => $product->stock,
            'sku' => $product->sku,
            'summary' => "Le produit « {$product->title} » a été créé avec succès à partir de sa photo au prix de {$formattedPrice} avec un stock de {$product->stock} unités.",
        ];
    }
}
