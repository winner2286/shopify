<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

class DraftCustomerMessageTool implements AiToolInterface
{
    public function name(): string
    {
        return 'draft_customer_message';
    }

    public function description(): string
    {
        return 'Rédige un message WhatsApp professionnel et chaleureux prêt à être envoyé à un client (confirmation de commande COD, avis d\'expédition, relance ou réponse SAV).';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['purpose', 'customer_name'],
            'properties' => [
                'purpose' => [
                    'type' => 'string',
                    'enum' => [
                        'order_confirmation_cod',
                        'order_shipped_delivery',
                        'abandoned_cart_reminder',
                        'customer_satisfaction',
                        'custom_inquiry',
                    ],
                    'description' => 'Objectif du message.',
                ],
                'customer_name' => [
                    'type' => 'string',
                    'description' => 'Nom ou prénom du client.',
                ],
                'order_number' => [
                    'type' => 'string',
                    'description' => 'Numéro de commande si applicable.',
                ],
                'additional_details' => [
                    'type' => 'string',
                    'description' => 'Informations spécifiques complémentaires (ex: nom du livreur, heure de passage, montant exact).',
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
        $purpose = $arguments['purpose'] ?? 'order_confirmation_cod';
        $customerName = trim($arguments['customer_name'] ?? 'Cher client');
        $orderNumber = $arguments['order_number'] ?? null;
        $details = $arguments['additional_details'] ?? '';

        $shopName = $shop->name ?: 'Notre boutique';

        $order = null;
        if ($orderNumber) {
            $order = Order::where('shop_id', $shop->id)
                ->where('order_number', $orderNumber)
                ->first();
        }

        $totalFormatted = $order ? number_format($order->total, 0, ',', ' ') . ' ' . $shop->currency : '';
        $zone = $order ? ($order->delivery_zone_name ?: 'votre adresse') : '';

        $message = match ($purpose) {
            'order_confirmation_cod' => "Bonjour {$customerName} ! 👋\n\nMerci pour votre commande sur *{$shopName}* (Réf : *{$orderNumber}*).\n\n📦 *Récapitulatif* :\n- Montant à payer à la livraison : *{$totalFormatted}*\n- Lieu de livraison : {$zone}\n\nPour préparer votre colis et assigner votre livreur, merci de nous confirmer par un simple \"OUI\" que vous êtes bien disponible pour réceptionner votre commande.\n\nÀ très vite ! ✨",

            'order_shipped_delivery' => "Bonjour {$customerName} ! 🛵\n\nBonne nouvelle : votre commande *{$orderNumber}* chez *{$shopName}* est en cours d'acheminement !\n\n{$details}\n\nMerci de préparer l'appoint en espèces ({$totalFormatted}) ou votre compte Mobile Money lors de la remise en main propre.\n\nMerci pour votre confiance !",

            'abandoned_cart_reminder' => "Bonjour {$customerName} ! 😊\n\nVous avez repéré des articles coup de cœur sur *{$shopName}*, mais votre panier n'a pas encore été finalisé.\n\nNos stocks partent vite ! Voulez-vous qu'on vous réserve vos articles ou avez-vous une question sur la livraison ?\n\nNous restons à votre entière disposition !",

            default => "Bonjour {$customerName} !\n\nMerci pour votre message concernant *{$shopName}*.\n\n{$details}\n\nNous restons disponibles pour vous servir au mieux.\nExcellente journée !",
        };

        return [
            'purpose' => $purpose,
            'customer_name' => $customerName,
            'formatted_whatsapp_message' => $message,
            'whatsapp_click_to_chat_url' => $order && $order->customer_phone
                ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $order->customer_phone) . '?text=' . rawurlencode($message)
                : null,
        ];
    }
}
