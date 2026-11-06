<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use App\Services\Ai\Contracts\AiToolInterface;

class AdjustProductPricePercentageTool implements AiToolInterface
{
    public function __construct(
        protected ?ActionTokenService $tokenService = null
    ) {
        $this->tokenService = $tokenService ?? new ActionTokenService();
    }

    public function name(): string
    {
        return 'adjust_product_price_percentage';
    }

    public function description(): string
    {
        return 'Augmente ou diminue le prix de vente d\'un produit selon un pourcentage spécifié (ex: +10% ou -15%). Calcule le montant arrondi en FCFA et requiert confirmation avant application.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['percentage'],
            'properties' => [
                'product_id' => [
                    'type' => 'integer',
                    'description' => 'ID numérique du produit (optionnel si product_name est fourni).',
                ],
                'product_name' => [
                    'type' => 'string',
                    'description' => 'Nom ou fragment du titre du produit à modifier.',
                ],
                'percentage' => [
                    'type' => 'number',
                    'description' => 'Pourcentage de variation du prix. Valeur positive pour une hausse (ex: 10 pour +10%), négative pour une baisse (ex: -15 pour -15%).',
                ],
                'confirmed_by_user' => [
                    'type' => 'boolean',
                    'description' => 'True uniquement lorsque l\'utilisateur clique sur le bouton de validation de modification.',
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
        $productId = isset($arguments['product_id']) ? (int) $arguments['product_id'] : 0;
        $productName = trim($arguments['product_name'] ?? '');
        $percentage = (float) ($arguments['percentage'] ?? 0);
        $isConfirmed = ! empty($arguments['confirmed_by_user']);

        // Recherche du produit avec isolation hermétique par shop_id
        $product = null;
        if ($productId > 0) {
            $product = Product::withoutGlobalScopes()->where('shop_id', $shop->id)->find($productId);
        }

        if (! $product && ! empty($productName)) {
            $product = Product::withoutGlobalScopes()->where('shop_id', $shop->id)
                ->where('title', 'like', "%{$productName}%")
                ->first();
        }

        if (! $product) {
            return [
                'error' => "Produit introuvable pour cette boutique. Veuillez préciser le nom exact ou l'ID de l'article.",
            ];
        }

        if ($percentage == 0) {
            return [
                'error' => "Le pourcentage de variation doit être différent de 0 (ex: 10 pour +10% ou -15 pour -15%).",
            ];
        }

        $currentPrice = (int) $product->price;
        $multiplier = 1 + ($percentage / 100);
        // Arrondi aux 50 ou 100 FCFA les plus proches pour des prix commerciaux naturels
        $calculatedRaw = $currentPrice * $multiplier;
        $newPrice = (int) (round($calculatedRaw / 50) * 50);

        if ($newPrice <= 0) {
            $newPrice = 100; // Seuil plancher
        }

        $diff = $newPrice - $currentPrice;
        $sign = $diff >= 0 ? '+' : '';

        $actionParams = [
            'product_id' => $product->id,
            'new_price' => $newPrice,
            'percentage' => $percentage,
        ];

        // Si non confirmé : renvoyer action_required pour bouton interactif avec jeton HMAC
        if (! $isConfirmed) {
            $direction = $percentage > 0 ? "hausse" : "baisse";
            $initiator = $user ?? $shop->owner ?? User::first();
            $token = $this->tokenService->generateToken($shop, $initiator, $this->name(), $actionParams);

            return [
                'status' => 'action_required',
                'action_type' => $this->name(),
                'action_token' => $token,
                'product_id' => $product->id,
                'product_title' => $product->title,
                'changes' => [
                    'current_price' => $currentPrice,
                    'new_price' => $newPrice,
                    'percentage' => $percentage,
                    'difference_fcfa' => $diff,
                ],
                'message' => "Confirmation requise : Appliquer une {$direction} de {$percentage}% sur \"{$product->title}\" ? Le prix passera de " . number_format($currentPrice, 0, ',', ' ') . " {$shop->currency} à " . number_format($newPrice, 0, ',', ' ') . " {$shop->currency} ({$sign}" . number_format($diff, 0, ',', ' ') . " {$shop->currency}).",
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

        // Exécution réelle côté backend
        $product->price = $newPrice;
        $product->save();

        return [
            'status' => 'success',
            'product_id' => $product->id,
            'title' => $product->title,
            'old_price' => $currentPrice,
            'new_price' => $newPrice,
            'percentage_applied' => $percentage,
            'summary' => "Le prix de \"{$product->title}\" a été mis à jour avec succès : " . number_format($newPrice, 0, ',', ' ') . " {$shop->currency} ({$sign}{$percentage}%).",
        ];
    }
}
