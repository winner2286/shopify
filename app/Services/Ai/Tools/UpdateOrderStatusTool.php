<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\ActionTokenService;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\OrderService;

class UpdateOrderStatusTool implements AiToolInterface
{
    public function __construct(
        protected ?OrderService $orderService = null,
        protected ?ActionTokenService $tokenService = null
    ) {
        $this->orderService = $orderService ?? app(OrderService::class);
        $this->tokenService = $tokenService ?? new ActionTokenService();
    }

    public function name(): string
    {
        return 'update_order_status';
    }

    public function description(): string
    {
        return 'Met à jour le statut d\'une commande (confirmation, expédition, livraison, annulation sécurisée avec remise en stock automatique). ATTENTION : L\'annulation nécessite obligatoirement une confirmation formelle du commerçant.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['new_status'],
            'properties' => [
                'order_id' => [
                    'type' => 'integer',
                    'description' => 'ID numérique de la commande (ex: 42).',
                ],
                'order_number' => [
                    'type' => 'string',
                    'description' => 'Numéro de commande officiel Zanika (ex: "ZNK-20261026-ABCD").',
                ],
                'new_status' => [
                    'type' => 'string',
                    'enum' => ['confirmed', 'shipped', 'delivered', 'cancelled'],
                    'description' => 'Nouveau statut métier cible pour la commande.',
                ],
                'reason' => [
                    'type' => 'string',
                    'description' => 'Motif d\'annulation obligatoire si le statut cible est "cancelled" (ex: "Client injoignable", "Refus à la livraison").',
                ],
                'confirmed_by_user' => [
                    'type' => 'boolean',
                    'description' => 'True uniquement si l\'utilisateur a formellement validé l\'action.',
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
        $orderId = (int) ($arguments['order_id'] ?? 0);
        $orderNumber = trim((string) ($arguments['order_number'] ?? ''));
        $newStatus = trim((string) ($arguments['new_status'] ?? ''));
        $reason = trim((string) ($arguments['reason'] ?? ''));

        // 1. Recherche stricte de la commande avec isolation multi-tenant (shop_id)
        $query = Order::withoutGlobalScopes()->where('shop_id', $shop->id);
        if ($orderId > 0) {
            $query->where('id', $orderId);
        } elseif (! empty($orderNumber)) {
            $query->where('order_number', 'like', "%{$orderNumber}%");
        } else {
            return ['error' => 'Veuillez spécifier l\'ID ou le numéro officiel de la commande.'];
        }

        $order = $query->with(['items', 'customer'])->first();

        if (! $order) {
            return ['error' => "Commande introuvable pour la boutique #{$shop->id}."];
        }

        $validStatuses = [
            Order::STATUS_CONFIRMED,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ];

        if (! in_array($newStatus, $validStatuses, true)) {
            return ['error' => "Statut '{$newStatus}' invalide. Statuts autorisés : " . implode(', ', $validStatuses)];
        }

        if ($order->status === $newStatus) {
            return [
                'status' => 'unchanged',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'current_status' => $order->status,
                'message' => "La commande {$order->order_number} est déjà au statut « {$order->status_label} ».",
            ];
        }

        // 2. Contrôle des transitions d'états autorisées
        $allowedTransitions = [
            Order::STATUS_PENDING => [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED],
            Order::STATUS_CONFIRMED => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
            Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
            Order::STATUS_DELIVERED => [],
            Order::STATUS_CANCELLED => [],
        ];

        $possibleNext = $allowedTransitions[$order->status] ?? [];

        if (! in_array($newStatus, $possibleNext, true)) {
            $currentLabel = $order->status_label;
            return [
                'error' => "Transition impossible : une commande au statut « {$currentLabel} » ne peut pas passer directement à « " . ucfirst($newStatus) . " ».",
            ];
        }

        $isConfirmed = ! empty($arguments['confirmed_by_user']);
        $requiresValidation = ($newStatus === Order::STATUS_CANCELLED);

        // 3. Procédure Human-In-The-Loop obligatoire pour l'annulation
        if ($requiresValidation && ! $isConfirmed) {
            $actionParams = [
                'order_id' => $order->id,
                'new_status' => $newStatus,
                'reason' => $reason ?: 'Annulation demandée par le commerçant',
            ];

            $initiator = $user ?? $shop->owner ?? User::first();
            $token = $this->tokenService->generateToken($shop, $initiator, $this->name(), $actionParams);

            return [
                'status' => 'action_required',
                'action_type' => $this->name(),
                'action_token' => $token,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'new_status' => $newStatus,
                'reason' => $actionParams['reason'],
                'message' => "Confirmation requise : Souhaitez-vous réellement annuler la commande {$order->order_number} de {$order->customer_name} ? Les articles seront automatiquement remis en stock.",
            ];
        }

        // 4. Si confirmation reçue pour annulation, validation du token HMAC
        if ($requiresValidation) {
            $token = $arguments['action_token'] ?? '';
            $actionParams = [
                'order_id' => $order->id,
                'new_status' => $newStatus,
                'reason' => $reason ?: 'Annulation demandée par le commerçant',
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
        }

        // 5. Exécution sécurisée
        if ($newStatus === Order::STATUS_CANCELLED) {
            $cancelReason = $reason ?: 'Annulation via Copilote IA';
            $this->orderService->cancelOrder($order, $cancelReason, $user);

            return [
                'status' => 'success',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'previous_status' => $order->getOriginal('status'),
                'new_status' => Order::STATUS_CANCELLED,
                'summary' => "La commande {$order->order_number} a été annulée avec succès. Motif : « {$cancelReason} ». Les articles ont été réintégrés au stock physique disponible.",
            ];
        }

        // Transitions de progression (confirmed, shipped, delivered)
        $previousStatus = $order->status;
        $order->status = $newStatus;

        if ($newStatus === Order::STATUS_DELIVERED && $order->payment_method === Order::PAYMENT_CASH) {
            $order->payment_status = Order::PAYMENT_STATUS_PAID;
            $order->paid_at = now();
        }

        $order->save();

        // Enregistrement de l'historique d'audit
        $order->statusHistories()->create([
            'from_status' => $previousStatus,
            'to_status' => $newStatus,
            'author_type' => $user ? 'merchant' : 'system',
            'author_id' => $user?->id,
            'comment' => "Mise à jour de statut opérée par le Copilote IA Zanika",
        ]);

        return [
            'status' => 'success',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'previous_status' => $previousStatus,
            'new_status' => $order->status,
            'new_status_label' => $order->status_label,
            'summary' => "La commande {$order->order_number} est désormais passée au statut « {$order->status_label} ».",
        ];
    }
}
