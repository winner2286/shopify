<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use App\Services\Ai\Contracts\AiToolInterface;

class UpdateProductStockOrPriceTool implements AiToolInterface
{
    public function __construct(
        protected ?ActionTokenService $tokenService = null
    ) {
        $this->tokenService = $tokenService ?? new ActionTokenService();
    }

    public function name(): string
    {
        return 'update_product_stock_or_price';
    }

    public function description(): string
    {
        return 'Modifie le prix ou la quantité en stock d\'un produit. ATTENTION: action sensible nécessitant une confirmation explicite de l\'utilisateur.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['product_id'],
            'properties' => [
                'product_id' => [
                    'type' => 'integer',
                    'description' => 'ID numérique du produit.',
                ],
                'new_price' => [
                    'type' => 'integer',
                    'description' => 'Nouveau prix de vente en FCFA (optionnel).',
                ],
                'new_stock' => [
                    'type' => 'integer',
                    'description' => 'Nouvelle quantité physique en stock (optionnel).',
                ],
                'confirmed_by_user' => [
                    'type' => 'boolean',
                    'description' => 'True uniquement si l\'utilisateur a explicitement confirmé cette action via le bouton de validation.',
                ],
                'action_token' => [
                    'type' => 'string',
                    'description' => 'Jeton cryptographique HMAC signé autorisant la mutation.',
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
        $productId = (int) ($arguments['product_id'] ?? 0);
        $product = Product::withoutGlobalScopes()->where('shop_id', $shop->id)->find($productId);

        if (! $product) {
            return [
                'error' => "Produit ID #{$productId} introuvable pour cette boutique.",
            ];
        }

        $newPrice = isset($arguments['new_price']) && is_numeric($arguments['new_price'])
            ? (int) $arguments['new_price']
            : null;

        $newStock = isset($arguments['new_stock']) && is_numeric($arguments['new_stock'])
            ? (int) $arguments['new_stock']
            : null;

        $isConfirmed = ! empty($arguments['confirmed_by_user']);

        $actionParams = [
            'product_id' => $product->id,
            'new_price' => $newPrice,
            'new_stock' => $newStock,
        ];

        // Si l'utilisateur n'a pas encore validé le bouton de confirmation
        if (! $isConfirmed) {
            $initiator = $user ?? $shop->owner ?? User::first();
            $token = $this->tokenService->generateToken($shop, $initiator, $this->name(), $actionParams);

            return [
                'status' => 'action_required',
                'action_type' => 'update_product_stock_or_price',
                'action_token' => $token,
                'product_id' => $product->id,
                'product_title' => $product->title,
                'changes' => [
                    'current_price' => $product->price,
                    'new_price' => $newPrice ?? $product->price,
                    'current_stock' => $product->stock,
                    'new_stock' => $newStock ?? $product->stock,
                ],
                'message' => "Confirmation requise : Souhaitez-vous appliquer ces modifications sur \"{$product->title}\" ?",
            ];
        }

        // Vérification du jeton HMAC lors de l'exécution confirmée
        $token = $arguments['action_token'] ?? '';
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

        // Exécution sécurisée après confirmation
        $updatedFields = [];
        if ($newPrice !== null && $newPrice >= 0) {
            $product->price = $newPrice;
            $updatedFields[] = "Prix mis à jour : " . number_format($newPrice, 0, ',', ' ') . " {$shop->currency}";
        }

        if ($newStock !== null && $newStock >= 0) {
            $previousStock = $product->stock;
            $product->stock = $newStock;
            $product->track_stock = true;
            $updatedFields[] = "Stock mis à jour : {$newStock} unités";

            // Audit dans stock_movements
            StockMovement::withoutGlobalScopes()->create([
                'product_id' => $product->id,
                'user_id' => $executor?->id,
                'quantity_change' => $newStock - $previousStock,
                'new_stock' => $newStock,
                'reason' => 'manual_adjustment',
                'comment' => "Ajustement de stock opéré via le Copilote IA Zanika",
            ]);
        }

        $product->save();

        return [
            'status' => 'success',
            'product_id' => $product->id,
            'product_title' => $product->title,
            'summary' => implode(' | ', $updatedFields),
            'current_price' => $product->price,
            'current_stock' => $product->stock,
        ];
    }
}
