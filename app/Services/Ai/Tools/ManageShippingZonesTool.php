<?php

namespace App\Services\Ai\Tools;

use App\Models\ShippingZone;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use App\Services\Ai\Contracts\AiToolInterface;

class ManageShippingZonesTool implements AiToolInterface
{
    public function __construct(
        protected ?ActionTokenService $tokenService = null
    ) {
        $this->tokenService = $tokenService ?? new ActionTokenService();
    }

    public function name(): string
    {
        return 'manage_shipping_zones';
    }

    public function description(): string
    {
        return 'Consulte, crée ou modifie les zones et tarifs de livraison pour la boutique (ex: Lomé Intra-muros, Grand Lomé, Intérieur du pays, seuil de livraison gratuite). Les modifications tarifaires requièrent une confirmation du commerçant.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['action'],
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['list', 'create', 'update'],
                    'description' => 'Opération à effectuer : "list" pour consulter les zones actuelles, "create" pour ajouter une zone, "update" pour modifier une zone existante.',
                ],
                'zone_id' => [
                    'type' => 'integer',
                    'description' => 'ID de la zone à modifier (requis si action est "update").',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Nom de la zone ou des quartiers couverts (ex: "Baguida & Avepozo", "Cotonou Centre").',
                ],
                'price' => [
                    'type' => 'integer',
                    'description' => 'Tarif forfaitaire de livraison en FCFA (ex: 1500).',
                ],
                'free_shipping_threshold' => [
                    'type' => 'integer',
                    'description' => 'Montant minimal de commande en FCFA pour offrir la livraison gratuite (optionnel, ex: 30000).',
                ],
                'delivery_estimate' => [
                    'type' => 'string',
                    'description' => 'Délai indicatif estimé de livraison (ex: "Livré en 24h", "Le jour même en 3h").',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Zone active ou temporairement suspendue (défaut : true).',
                ],
                'confirmed_by_user' => [
                    'type' => 'boolean',
                    'description' => 'True uniquement si l\'utilisateur a validé l\'action.',
                ],
                'action_token' => [
                    'type' => 'string',
                    'description' => 'Jeton cryptographique HMAC autorisant la mutation.',
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
        $action = trim((string) ($arguments['action'] ?? 'list'));

        // 1. Action de lecture : lister les zones
        if ($action === 'list') {
            $zones = ShippingZone::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->orderBy('display_order')
                ->get();

            if ($zones->isEmpty()) {
                // Initialiser les zones par défaut si aucune n'existe encore
                $shop->ensureDefaultShippingZones();
                $zones = ShippingZone::withoutGlobalScopes()
                    ->where('shop_id', $shop->id)
                    ->orderBy('display_order')
                    ->get();
            }

            return [
                'status' => 'success',
                'action' => 'list',
                'zones_count' => $zones->count(),
                'currency' => $shop->currency,
                'zones' => $zones->map(fn($z) => [
                    'id' => $z->id,
                    'name' => $z->name,
                    'price' => $z->price,
                    'formatted_price' => number_format($z->price, 0, ',', ' ') . " {$shop->currency}",
                    'free_shipping_threshold' => $z->free_shipping_threshold,
                    'delivery_estimate' => $z->delivery_estimate,
                    'is_pickup' => $z->is_pickup,
                    'is_active' => $z->is_active,
                ])->all(),
            ];
        }

        // 2. Action de création ou modification
        $name = trim((string) ($arguments['name'] ?? ''));
        $price = isset($arguments['price']) && is_numeric($arguments['price']) ? max(0, (int) $arguments['price']) : null;
        $threshold = isset($arguments['free_shipping_threshold']) && is_numeric($arguments['free_shipping_threshold'])
            ? max(0, (int) $arguments['free_shipping_threshold'])
            : null;
        $estimate = trim((string) ($arguments['delivery_estimate'] ?? ''));
        $isActive = isset($arguments['is_active']) ? (bool) $arguments['is_active'] : true;
        $zoneId = (int) ($arguments['zone_id'] ?? 0);

        if ($action === 'update') {
            $zone = ShippingZone::withoutGlobalScopes()->where('shop_id', $shop->id)->find($zoneId);
            if (! $zone) {
                return ['error' => "Zone de livraison ID #{$zoneId} introuvable pour cette boutique."];
            }
        }

        $isConfirmed = ! empty($arguments['confirmed_by_user']);

        $actionParams = [
            'action' => $action,
            'zone_id' => $zoneId,
            'name' => $name ?: ($action === 'update' ? $zone->name : 'Nouvelle zone'),
            'price' => $price ?? ($action === 'update' ? $zone->price : 1000),
            'free_shipping_threshold' => $threshold,
            'delivery_estimate' => $estimate ?: ($action === 'update' ? $zone->delivery_estimate : 'Livré en 24h'),
            'is_active' => $isActive,
        ];

        // Étape Human-In-The-Loop : demande de confirmation avec HMAC
        if (! $isConfirmed) {
            $initiator = $user ?? $shop->owner ?? User::first();
            $token = $this->tokenService->generateToken($shop, $initiator, $this->name(), $actionParams);

            $actionLabel = $action === 'create' ? "créer la zone \"{$actionParams['name']}\"" : "mettre à jour la zone \"{$actionParams['name']}\"";
            $priceText = number_format($actionParams['price'], 0, ',', ' ') . " {$shop->currency}";

            return [
                'status' => 'action_required',
                'action_type' => $this->name(),
                'action_token' => $token,
                'shipping_payload' => $actionParams,
                'message' => "Confirmation requise : Souhaitez-vous {$actionLabel} avec un tarif de {$priceText} (délai : {$actionParams['delivery_estimate']}) ?",
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

        // Exécution de la mutation
        if ($action === 'create') {
            $maxOrder = (int) ShippingZone::withoutGlobalScopes()->where('shop_id', $shop->id)->max('display_order');
            $createdZone = ShippingZone::withoutGlobalScopes()->create([
                'shop_id' => $shop->id,
                'name' => $actionParams['name'],
                'price' => $actionParams['price'],
                'free_shipping_threshold' => $actionParams['free_shipping_threshold'],
                'delivery_estimate' => $actionParams['delivery_estimate'],
                'is_pickup' => false,
                'is_active' => $actionParams['is_active'],
                'display_order' => $maxOrder + 1,
            ]);

            return [
                'status' => 'success',
                'zone_id' => $createdZone->id,
                'name' => $createdZone->name,
                'price' => $createdZone->price,
                'summary' => "La zone de livraison « {$createdZone->name} » a été créée avec succès au tarif de " . number_format($createdZone->price, 0, ',', ' ') . " {$shop->currency}.",
            ];
        }

        // Action update
        $zone->update([
            'name' => $actionParams['name'],
            'price' => $actionParams['price'],
            'free_shipping_threshold' => $actionParams['free_shipping_threshold'],
            'delivery_estimate' => $actionParams['delivery_estimate'],
            'is_active' => $actionParams['is_active'],
        ]);

        return [
            'status' => 'success',
            'zone_id' => $zone->id,
            'name' => $zone->name,
            'price' => $zone->price,
            'summary' => "La zone de livraison « {$zone->name} » a été modifiée avec succès (Tarif : " . number_format($zone->price, 0, ',', ' ') . " {$shop->currency}).",
        ];
    }
}
