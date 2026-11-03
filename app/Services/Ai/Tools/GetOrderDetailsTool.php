<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

class GetOrderDetailsTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_order_details';
    }

    public function description(): string
    {
        return 'Récupère les détails complets d\'une commande spécifique (numéro de commande, client, téléphone, articles commandés, adresse de livraison, montant total, mode de paiement, statut actuel).';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['order_identifier'],
            'properties' => [
                'order_identifier' => [
                    'type' => 'string',
                    'description' => 'Numéro de commande (ex: CMD-2026-XXXX) ou identifiant ID numérique.',
                ],
            ],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(Shop $shop, array $arguments, ?User $user = null): array
    {
        $identifier = trim($arguments['order_identifier'] ?? '');

        $order = Order::where('shop_id', $shop->id)
            ->where(function ($q) use ($identifier) {
                $q->where('order_number', $identifier);
                if (is_numeric($identifier)) {
                    $q->orWhere('id', (int) $identifier);
                }
            })
            ->with(['items.product', 'shippingZone'])
            ->first();

        if (! $order) {
            return [
                'error' => "Aucune commande trouvée avec l'identifiant '{$identifier}' pour cette boutique.",
            ];
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => $order->status_label,
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'email' => $order->customer_email,
            ],
            'delivery' => [
                'zone' => $order->delivery_zone_name ?: ($order->shippingZone?->name ?? 'Zone standard'),
                'address' => $order->delivery_address,
                'notes' => $order->delivery_notes,
                'shipping_cost' => $order->shipping_cost,
            ],
            'payment' => [
                'method' => $order->payment_method,
                'method_label' => $order->payment_method_label,
                'status' => $order->payment_status,
                'paid_at' => $order->paid_at?->format('d/m/Y H:i'),
            ],
            'financials' => [
                'subtotal' => $order->subtotal,
                'shipping_cost' => $order->shipping_cost,
                'total' => $order->total,
                'currency' => $shop->currency,
            ],
            'items' => $order->items->map(fn($item) => [
                'title' => $item->product_title ?: ($item->product?->title ?? 'Article'),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ])->all(),
            'created_at' => $order->created_at->format('d/m/Y H:i'),
            'cancellation_reason' => $order->cancellation_reason,
        ];
    }
}
