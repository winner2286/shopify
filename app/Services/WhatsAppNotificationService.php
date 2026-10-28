<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppNotificationService
{
    /**
     * Formate le message d'alerte complet envoyé au commerçant lors d'une nouvelle commande.
     */
    public function formatMerchantAlert(Order $order): string
    {
        $shop = $order->shop;
        $dateStr = $order->created_at ? $order->created_at->format('d/m/Y à H:i') : date('d/m/Y à H:i');

        // Liste des articles
        $itemsText = '';
        foreach ($order->items as $item) {
            $unitPrice = number_format($item->unit_price, 0, ',', ' ');
            $totalPrice = number_format($item->total_price, 0, ',', ' ');
            $itemsText .= "• {$item->quantity}x *{$item->product_title}* ({$totalPrice} FCFA)\n";
        }

        $subtotal = number_format($order->subtotal, 0, ',', ' ');
        $shipping = $order->shipping_cost === 0 ? 'Gratuit' : number_format($order->shipping_cost, 0, ',', ' ') . ' FCFA';
        $total = number_format($order->total, 0, ',', ' ');
        $notes = $order->delivery_notes ? "\n📝 *Note client :* {$order->delivery_notes}" : '';
        $orderUrl = route('orders.show', $order);

        return "🛍️ *NOUVELLE COMMANDE REÇUE !*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "🏪 Boutique : *{$shop->name}*\n"
            . "🔖 N° Commande : *#{$order->order_number}*\n"
            . "📅 Date : {$dateStr}\n\n"
            . "👤 *CLIENT :*\n"
            . "• Nom : *{$order->customer_name}*\n"
            . "• Téléphone : *{$order->customer_phone}*\n"
            . "• Lieu : *{$order->delivery_zone_name}*\n"
            . "• Adresse : {$order->delivery_address}"
            . "{$notes}\n\n"
            . "📦 *ARTICLES COMMANDÉS :*\n"
            . "{$itemsText}"
            . "────────────────────\n"
            . "💰 Sous-total : {$subtotal} FCFA\n"
            . "🚚 Livraison : {$shipping}\n"
            . "💵 *TOTAL À ENCAISSER : {$total} FCFA*\n"
            . "💳 Mode de règlement : *{$order->payment_method_label}*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "👉 Traiter la commande sur Boutiq :\n"
            . "{$orderUrl}\n\n"
            . "🚀 _Boutiq — Votre commerce tout-en-un_";
    }

    /**
     * Formate le message pré-rempli envoyé par le client au commerçant sur WhatsApp.
     */
    public function formatCustomerToMerchantMessage(Order $order): string
    {
        $shop = $order->shop;
        $total = number_format($order->total, 0, ',', ' ');

        return "Bonjour *{$shop->name}* ! 👋\n\n"
            . "Je viens de passer la commande *#{$order->order_number}* d'un montant de *{$total} FCFA* sur votre boutique en ligne.\n\n"
            . "👤 *Mes coordonnées :*\n"
            . "• Nom : {$order->customer_name}\n"
            . "• Téléphone : {$order->customer_phone}\n"
            . "• Livraison : {$order->delivery_zone_name} ({$order->delivery_address})\n"
            . "• Règlement : {$order->payment_method_label}\n\n"
            . "Merci de me confirmer la prise en compte de ma commande et le délai de livraison ! 🙏";
    }

    /**
     * Génère l'URL WhatsApp directe vers le numéro de la boutique avec le récapitulatif.
     */
    public function getMerchantWhatsAppUrl(Order $order): ?string
    {
        $shop = $order->shop;
        $phoneDigits = $shop->whatsapp_formatted;

        if (! $phoneDigits) {
            return null;
        }

        $message = $this->formatMerchantAlert($order);

        return "https://wa.me/{$phoneDigits}?text=" . urlencode($message);
    }

    /**
     * Génère l'URL WhatsApp du client vers la boutique pour confirmation rapide.
     */
    public function getCustomerToMerchantWhatsAppUrl(Order $order): ?string
    {
        $shop = $order->shop;
        $phoneDigits = $shop->whatsapp_formatted;

        if (! $phoneDigits) {
            return null;
        }

        $message = $this->formatCustomerToMerchantMessage($order);

        return "https://wa.me/{$phoneDigits}?text=" . urlencode($message);
    }

    /**
     * Envoie automatiquement l'alerte WhatsApp au commerçant via l'API/Webhook configuré ou journalise l'envoi.
     *
     * @return array{success: bool, mode: string, phone: ?string, error: ?string}
     */
    public function sendOrderAlertToMerchant(Order $order): array
    {
        $shop = $order->shop;
        $phone = $shop->whatsapp_formatted ?: $shop->whatsapp;

        if (! $phone) {
            Log::info("[WhatsApp Alert] Aucun numéro WhatsApp renseigné pour la boutique '{$shop->name}' (ID: {$shop->id}). Alerte non transmise.");

            return [
                'success' => false,
                'mode' => 'none',
                'phone' => null,
                'error' => 'Numéro WhatsApp non configuré',
            ];
        }

        $message = $this->formatMerchantAlert($order);
        $webhookUrl = env('WHATSAPP_NOTIFICATION_WEBHOOK') ?: config('services.whatsapp.webhook_url');
        $apiUrl = env('WHATSAPP_API_URL');
        $apiToken = env('WHATSAPP_API_TOKEN');

        // 1. Envoi via Passerelle / Webhook WhatsApp (si configuré en production)
        if ($webhookUrl) {
            try {
                $response = Http::timeout(4)->post($webhookUrl, [
                    'event' => 'order.created',
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'recipient' => $phone,
                    'message' => $message,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                ]);

                if ($response->successful()) {
                    Log::info("[WhatsApp Webhook] Alerte envoyée avec succès pour la commande #{$order->order_number} à {$phone}");

                    return ['success' => true, 'mode' => 'webhook', 'phone' => $phone, 'error' => null];
                }

                Log::warning("[WhatsApp Webhook] Réponse non-succès du webhook : " . $response->body());
            } catch (Throwable $e) {
                Log::error("[WhatsApp Webhook] Erreur lors de l'appel webhook : " . $e->getMessage());
            }
        } elseif ($apiUrl && $apiToken) {
            // Intégration API REST (UltraMsg, GreenAPI, Cloud API, etc.)
            try {
                $response = Http::timeout(4)->withToken($apiToken)->post($apiUrl, [
                    'to' => $phone,
                    'body' => $message,
                ]);

                if ($response->successful()) {
                    Log::info("[WhatsApp API] Alerte transmise via API à {$phone} pour #{$order->order_number}");

                    return ['success' => true, 'mode' => 'api', 'phone' => $phone, 'error' => null];
                }
            } catch (Throwable $e) {
                Log::error("[WhatsApp API] Exception lors de l'appel API : " . $e->getMessage());
            }
        }

        // 2. Mode local / développement / log d'audit systématique
        Log::info("[WhatsApp Notification Simuler/Local] Alerte commande #{$order->order_number} adressée au WhatsApp {$phone} de la boutique '{$shop->name}'. Message :\n{$message}");

        return [
            'success' => true,
            'mode' => 'log_simulated',
            'phone' => $phone,
            'error' => null,
        ];
    }
}
