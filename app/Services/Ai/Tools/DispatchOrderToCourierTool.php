<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

class DispatchOrderToCourierTool implements AiToolInterface
{
    public function name(): string
    {
        return 'dispatch_order_to_courier';
    }

    public function description(): string
    {
        return 'Génère la feuille de route et le message WhatsApp pour affecter une commande à un coursier moto, et permet de passer le statut à Confirmée ou Expédiée.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_id' => [
                    'type' => 'integer',
                    'description' => 'ID numérique de la commande.',
                ],
                'order_number' => [
                    'type' => 'string',
                    'description' => 'Numéro de la commande (ex: ZNK-...).',
                ],
                'courier_name' => [
                    'type' => 'string',
                    'description' => 'Nom ou prénom du livreur habituel (optionnel).',
                ],
                'mark_as_confirmed' => [
                    'type' => 'boolean',
                    'description' => 'Si true, passe automatiquement le statut de la commande à "confirmed" si elle était "pending".',
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
        $orderId = (int) ($arguments['order_id'] ?? 0);
        $orderNumber = trim($arguments['order_number'] ?? '');
        $courierName = trim($arguments['courier_name'] ?? 'Livreur');
        $markConfirmed = ! empty($arguments['mark_as_confirmed']);

        $query = Order::withoutGlobalScopes()->where('shop_id', $shop->id);
        if ($orderId > 0) {
            $query->where('id', $orderId);
        } elseif (! empty($orderNumber)) {
            $query->where('order_number', \App\Support\SearchHelper::likeOperator(), "%{$orderNumber}%");
        } else {
            // Prend la dernière commande en attente
            $query->where('status', Order::STATUS_PENDING)->latest();
        }

        $order = $query->with('items')->first();

        if (! $order) {
            return [
                'error' => "Commande introuvable pour cette boutique.",
            ];
        }

        // Passage automatique à confirmée si demandé
        if ($markConfirmed && $order->status === Order::STATUS_PENDING) {
            $order->status = Order::STATUS_CONFIRMED;
            $order->save();
        }

        // Construction du message WhatsApp prêt à envoyer au livreur
        $itemsText = $order->items->map(fn($i) => "• {$i->product_title} x{$i->quantity}")->implode("\n");
        $address = $order->delivery_address ?: ($order->delivery_zone_name ?: 'À préciser avec le client');
        $amountToCollect = number_format($order->total, 0, ',', ' ') . " {$shop->currency}";

        $whatsappText = "📦 *COURSE DE LIVRAISON ZANIKA*\n";
        $whatsappText .= "Commande : *{$order->order_number}*\n";
        $whatsappText .= "Client : *{$order->customer_name}*\n";
        $whatsappText .= "Téléphone : {$order->customer_phone}\n";
        $whatsappText .= "Zone / Repère : {$address}\n\n";
        $whatsappText .= "Articles :\n{$itemsText}\n\n";
        $whatsappText .= "💰 *Montant à encaisser : {$amountToCollect}*\n";
        $whatsappText .= "Merci de confirmer la prise en charge !";

        $whatsappUrl = "https://wa.me/?text=" . urlencode($whatsappText);

        return [
            'status' => 'success',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'address' => $address,
            'total_amount' => $amountToCollect,
            'order_status' => $order->status,
            'whatsapp_message' => $whatsappText,
            'whatsapp_url' => $whatsappUrl,
            'summary' => "Feuille de route générée pour la commande {$order->order_number}. Le montant à encaisser est de {$amountToCollect} auprès de {$order->customer_name}.",
        ];
    }
}
