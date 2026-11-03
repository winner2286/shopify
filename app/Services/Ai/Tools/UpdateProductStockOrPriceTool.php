<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

class UpdateProductStockOrPriceTool implements AiToolInterface
{
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
        $product = Product::where('shop_id', $shop->id)->find($productId);

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

        // Si l'utilisateur n'a pas encore validé le bouton de confirmation
        if (! $isConfirmed) {
            return [
                'status' => 'action_required',
                'action_type' => 'update_product_stock_or_price',
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

        // Exécution sécurisée après confirmation
        $updatedFields = [];
        if ($newPrice !== null && $newPrice >= 0) {
            $product->price = $newPrice;
            $updatedFields[] = "Prix mis à jour : " . number_format($newPrice, 0, ',', ' ') . " {$shop->currency}";
        }

        if ($newStock !== null && $newStock >= 0) {
            $product->stock = $newStock;
            $product->track_stock = true;
            $updatedFields[] = "Stock mis à jour : {$newStock} unités";
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
