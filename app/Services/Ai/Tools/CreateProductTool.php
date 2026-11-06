<?php

namespace App\Services\Ai\Tools;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use App\Services\Ai\Contracts\AiToolInterface;
use Illuminate\Support\Str;

class CreateProductTool implements AiToolInterface
{
    public function __construct(
        protected ?ActionTokenService $tokenService = null
    ) {
        $this->tokenService = $tokenService ?? new ActionTokenService();
    }

    public function name(): string
    {
        return 'create_product';
    }

    public function description(): string
    {
        return 'Prépare et crée un nouveau produit dans le catalogue de la boutique avec son titre, prix, stock initial, description et catégorie. ATTENTION : Action sensible d\'écriture nécessitant une confirmation formelle du marchand.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'price'],
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Titre commercial attractif du produit (max 120 caractères).',
                ],
                'price' => [
                    'type' => 'integer',
                    'description' => 'Prix de vente normal en FCFA (nombre entier strictement supérieur à 0).',
                ],
                'compare_at_price' => [
                    'type' => 'integer',
                    'description' => 'Prix barré d\'origine pour afficher une promotion (optionnel, supérieur au prix de vente).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Description commerciale complète et persuasive de l\'article.',
                ],
                'stock' => [
                    'type' => 'integer',
                    'description' => 'Quantité physique initiale disponible en stock (défaut : 10).',
                ],
                'track_stock' => [
                    'type' => 'boolean',
                    'description' => 'Active le suivi du stock pour cet article (défaut : true).',
                ],
                'low_stock_threshold' => [
                    'type' => 'integer',
                    'description' => 'Seuil d\'alerte de stock faible (défaut : 3).',
                ],
                'category_name' => [
                    'type' => 'string',
                    'description' => 'Nom de la catégorie de rattachement (existante ou à créer).',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'hidden'],
                    'description' => 'Statut de visibilité du produit sur la vitrine (défaut : active).',
                ],
                'sku' => [
                    'type' => 'string',
                    'description' => 'Référence interne unique SKU (optionnelle, générée automatiquement si omise).',
                ],
                'confirmed_by_user' => [
                    'type' => 'boolean',
                    'description' => 'True uniquement si l\'utilisateur a cliqué sur le bouton de validation sécurisé.',
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
        // 1. Contrôle des quotas de catalogue du forfait marchand
        if (! $shop->canAddProduct()) {
            return [
                'status' => 'quota_exceeded',
                'error' => "Quota de catalogue atteint : vous avez atteint le maximum de {$shop->maxProducts()} produits de votre formule {$shop->currentPlan()->name}. Veuillez passer à une formule supérieure pour ajouter cet article.",
            ];
        }

        $title = trim((string) ($arguments['title'] ?? ''));
        if (empty($title)) {
            return ['error' => 'Le titre du produit est obligatoire.'];
        }

        $price = (int) ($arguments['price'] ?? 0);
        if ($price <= 0) {
            return ['error' => 'Le prix du produit doit être un montant supérieur à 0 FCFA.'];
        }

        $compareAtPrice = isset($arguments['compare_at_price']) && is_numeric($arguments['compare_at_price'])
            ? (int) $arguments['compare_at_price']
            : null;

        $description = trim((string) ($arguments['description'] ?? ''));
        $trackStock = isset($arguments['track_stock']) ? (bool) $arguments['track_stock'] : true;
        $stock = isset($arguments['stock']) && is_numeric($arguments['stock']) ? max(0, (int) $arguments['stock']) : 10;
        $lowStockThreshold = isset($arguments['low_stock_threshold']) && is_numeric($arguments['low_stock_threshold']) ? max(0, (int) $arguments['low_stock_threshold']) : 3;
        $rawStatus = (string) ($arguments['status'] ?? 'active');
        $status = in_array($rawStatus, ['active', 'hidden'], true) ? $rawStatus : 'active';
        $categoryName = trim((string) ($arguments['category_name'] ?? ''));
        $sku = ! empty($arguments['sku']) ? trim((string) $arguments['sku']) : strtoupper(Str::slug(mb_substr($title, 0, 4)) . '-' . Str::random(4));

        $isConfirmed = ! empty($arguments['confirmed_by_user']);

        // 2. Étape Human-In-The-Loop : Préparation de la fiche et génération du jeton HMAC
        if (! $isConfirmed) {
            $actionParams = [
                'title' => $title,
                'price' => $price,
                'compare_at_price' => $compareAtPrice,
                'description' => $description,
                'stock' => $stock,
                'track_stock' => $trackStock,
                'low_stock_threshold' => $lowStockThreshold,
                'category_name' => $categoryName,
                'status' => $status,
                'sku' => $sku,
            ];

            $initiator = $user ?? $shop->owner ?? User::first();
            $token = $this->tokenService->generateToken($shop, $initiator, $this->name(), $actionParams);

            $formattedPrice = number_format($price, 0, ',', ' ') . " {$shop->currency}";
            $promoInfo = $compareAtPrice && $compareAtPrice > $price
                ? " (Prix d'origine barré : " . number_format($compareAtPrice, 0, ',', ' ') . " {$shop->currency})"
                : '';

            return [
                'status' => 'action_required',
                'action_type' => $this->name(),
                'action_token' => $token,
                'product_payload' => $actionParams,
                'preview' => [
                    'title' => $title,
                    'price' => $price,
                    'formatted_price' => $formattedPrice,
                    'compare_at_price' => $compareAtPrice,
                    'stock' => $stock,
                    'category' => $categoryName ?: 'Général',
                    'description_excerpt' => Str::limit($description, 160),
                ],
                'message' => "Confirmation requise : Souhaitez-vous publier ce nouveau produit \"{$title}\" à {$formattedPrice}{$promoInfo} avec un stock de {$stock} unités ?",
            ];
        }

        // 3. Validation de sécurité du jeton HMAC lors de l'exécution
        $token = $arguments['action_token'] ?? '';
        $actionParams = [
            'title' => $title,
            'price' => $price,
            'compare_at_price' => $compareAtPrice,
            'description' => $description,
            'stock' => $stock,
            'track_stock' => $trackStock,
            'low_stock_threshold' => $lowStockThreshold,
            'category_name' => $categoryName,
            'status' => $status,
            'sku' => $sku,
        ];

        $executor = $user ?? $shop->owner ?? User::first();
        if (! empty($token)) {
            $validation = $this->tokenService->validateToken($token, $shop, $executor, $this->name(), $actionParams);
            if (! $validation['valid']) {
                return [
                    'status' => 'security_rejection',
                    'error' => "Refus de sécurité : " . ($validation['error'] ?? 'Jeton invalide.'),
                ];
            }
        }

        // 4. Création effective du produit
        $uniqueSlug = Str::slug($title) . '-' . Str::random(5);

        $product = Product::withoutGlobalScopes()->create([
            'shop_id' => $shop->id,
            'title' => $title,
            'slug' => $uniqueSlug,
            'description' => $description ?: null,
            'price' => $price,
            'compare_at_price' => $compareAtPrice,
            'stock' => $trackStock ? $stock : 0,
            'track_stock' => $trackStock,
            'low_stock_threshold' => $lowStockThreshold,
            'status' => $status,
            'sku' => $sku,
            'created_via' => 'ai_copilot',
        ]);

        // Rattachement de la catégorie
        if (! empty($categoryName)) {
            $category = Category::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('name', $categoryName)
                ->first();

            if (! $category) {
                $category = Category::withoutGlobalScopes()->create([
                    'shop_id' => $shop->id,
                    'name' => $categoryName,
                    'slug' => Str::slug($categoryName) . '-' . Str::random(4),
                    'display_order' => 1,
                    'is_visible' => true,
                ]);
            }

            $product->categories()->sync([$category->id]);
        }

        // Audit du mouvement de stock initial
        if ($trackStock && $stock > 0) {
            StockMovement::withoutGlobalScopes()->create([
                'product_id' => $product->id,
                'user_id' => $executor?->id,
                'quantity_change' => $stock,
                'new_stock' => $stock,
                'reason' => 'initial_creation',
                'comment' => "Création de fiche produit par le Copilote IA Zanika",
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
            'status_label' => $product->status,
            'summary' => "Le produit \"{$product->title}\" a été créé avec succès au tarif de {$formattedPrice} (Stock initial : {$product->stock} unités).",
            'product_url' => url("/produits/{$product->id}/edit"),
        ];
    }
}
