<?php

namespace App\Services\Ai\Tools;

use App\Models\DiscountCode;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use App\Services\Ai\Contracts\AiToolInterface;
use Illuminate\Support\Str;

class ManageDiscountCodesTool implements AiToolInterface
{
    public function __construct(
        protected ?ActionTokenService $tokenService = null
    ) {
        $this->tokenService = $tokenService ?? new ActionTokenService();
    }

    public function name(): string
    {
        return 'manage_discount_codes';
    }

    public function description(): string
    {
        return 'Consulte, crée et gère les codes de réduction promotionnels pour la boutique (ex: -15% avec le code ZANIKA15 ou remise de 2 000 FCFA). Vérifie l\'éligibilité au forfait Pro/Premium et requiert validation pour toute création.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['action'],
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['list', 'create', 'toggle'],
                    'description' => 'Opération demandée : "list" pour voir les codes existants, "create" pour créer un nouveau code promo, "toggle" pour activer ou désactiver un code.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Code promotionnel textuel à saisir par les acheteurs (ex: "SOLDE15", "BIENVENUE"). Sera converti en majuscules.',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['percentage', 'fixed'],
                    'description' => 'Type de réduction : "percentage" pour un rabais en % ou "fixed" pour un montant fixe déduit en FCFA (défaut : percentage).',
                ],
                'value' => [
                    'type' => 'integer',
                    'description' => 'Valeur de la réduction (ex: 15 pour 15% de rabais, ou 2000 pour 2 000 FCFA déduits).',
                ],
                'min_order_amount' => [
                    'type' => 'integer',
                    'description' => 'Montant minimal de commande requis pour appliquer le code en FCFA (optionnel, ex: 15000).',
                ],
                'max_uses' => [
                    'type' => 'integer',
                    'description' => 'Nombre maximal de commandes pouvant utiliser ce code (optionnel, ex: 50).',
                ],
                'days_valid' => [
                    'type' => 'integer',
                    'description' => 'Nombre de jours de validité à compter d\'aujourd\'hui (optionnel, ex: 7 pour une semaine).',
                ],
                'confirmed_by_user' => [
                    'type' => 'boolean',
                    'description' => 'True uniquement si l\'utilisateur a cliqué sur le bouton de validation.',
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
        // 1. Vérification d'éligibilité selon le forfait d'abonnement (CDC Plan Pro/Premium)
        if (! $shop->canUseFeature('promo_codes') && ! ($user?->isAdmin() || $shop->owner?->isAdmin())) {
            return [
                'status' => 'feature_restricted',
                'error' => "La création de codes promotionnels et réductions avancées est réservée aux formules Pro et Premium de Zanika. Votre boutique est actuellement sur la formule « {$shop->currentPlan()->name} ». Veuillez mettre à niveau votre abonnement pour activer les codes promo.",
            ];
        }

        $action = trim((string) ($arguments['action'] ?? 'list'));

        // 2. Action de consultation (Lecture)
        if ($action === 'list') {
            $codes = DiscountCode::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->latest()
                ->get();

            return [
                'status' => 'success',
                'action' => 'list',
                'codes_count' => $codes->count(),
                'codes' => $codes->map(fn($c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'type' => $c->type,
                    'value' => $c->value,
                    'formatted_value' => $c->type === DiscountCode::TYPE_PERCENTAGE ? "-{$c->value}%" : "-" . number_format($c->value, 0, ',', ' ') . " {$shop->currency}",
                    'min_order_amount' => $c->min_order_amount,
                    'used_count' => $c->used_count,
                    'max_uses' => $c->max_uses,
                    'is_active' => $c->is_active,
                    'expires_at' => $c->expires_at ? $c->expires_at->format('d/m/Y') : 'Illimité',
                ])->all(),
            ];
        }

        // 3. Action de création d'un code promo
        $rawCode = trim((string) ($arguments['code'] ?? ''));
        if (empty($rawCode)) {
            $rawCode = 'PROMO' . Str::upper(Str::random(4));
        }
        $code = Str::upper(preg_replace('/[^A-Za-z0-9_-]/', '', $rawCode));

        $type = in_array($arguments['type'] ?? 'percentage', ['percentage', 'fixed'], true)
            ? $arguments['type']
            : 'percentage';

        $value = (int) ($arguments['value'] ?? 10);
        if ($value <= 0) {
            return ['error' => 'La valeur de la réduction doit être supérieure à 0.'];
        }
        if ($type === 'percentage' && $value > 90) {
            return ['error' => 'Le pourcentage de réduction ne peut pas excéder 90%.'];
        }

        $minAmount = isset($arguments['min_order_amount']) && is_numeric($arguments['min_order_amount'])
            ? max(0, (int) $arguments['min_order_amount'])
            : null;

        $maxUses = isset($arguments['max_uses']) && is_numeric($arguments['max_uses'])
            ? max(1, (int) $arguments['max_uses'])
            : null;

        $daysValid = isset($arguments['days_valid']) && is_numeric($arguments['days_valid'])
            ? max(1, (int) $arguments['days_valid'])
            : null;

        $expiresAt = ! empty($arguments['expires_at'])
            ? (string) $arguments['expires_at']
            : ($daysValid ? now()->addDays($daysValid)->toDateTimeString() : null);

        $isConfirmed = ! empty($arguments['confirmed_by_user']);

        $actionParams = [
            'action' => 'create',
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'min_order_amount' => $minAmount,
            'max_uses' => $maxUses,
            'days_valid' => $daysValid,
            'expires_at' => $expiresAt,
        ];

        // Étape Human-In-The-Loop : demande d'approbation et jeton HMAC
        if (! $isConfirmed) {
            $initiator = $user ?? $shop->owner ?? User::first();
            $token = $this->tokenService->generateToken($shop, $initiator, $this->name(), $actionParams);

            $reductionText = $type === 'percentage' ? "-{$value}%" : "-" . number_format($value, 0, ',', ' ') . " {$shop->currency}";
            $conditionText = $minAmount ? " (dès " . number_format($minAmount, 0, ',', ' ') . " {$shop->currency} d'achat)" : '';
            $validityText = $daysValid ? " valable {$daysValid} jours" : '';

            return [
                'status' => 'action_required',
                'action_type' => $this->name(),
                'action_token' => $token,
                'promo_payload' => $actionParams,
                'preview' => [
                    'code' => $code,
                    'reduction' => $reductionText,
                    'min_order' => $minAmount,
                    'max_uses' => $maxUses,
                    'validity' => $validityText ?: 'Sans date limite',
                ],
                'message' => "Confirmation requise : Souhaitez-vous créer le code promo \"{$code}\" ({$reductionText}{$conditionText}{$validityText}) ?",
            ];
        }

        // Validation HMAC
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

        // Création du code promo
        $discountCode = DiscountCode::withoutGlobalScopes()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'code' => $code,
            ],
            [
                'type' => $type,
                'value' => $value,
                'min_order_amount' => $minAmount,
                'max_uses' => $maxUses,
                'starts_at' => now(),
                'expires_at' => $expiresAt,
                'is_active' => true,
            ]
        );

        $reductionText = $type === 'percentage' ? "-{$value}%" : "-" . number_format($value, 0, ',', ' ') . " {$shop->currency}";

        return [
            'status' => 'success',
            'code_id' => $discountCode->id,
            'code' => $discountCode->code,
            'reduction' => $reductionText,
            'summary' => "Le code promo « {$discountCode->code} » ({$reductionText}) a été créé et activé pour votre boutique !",
        ];
    }
}
