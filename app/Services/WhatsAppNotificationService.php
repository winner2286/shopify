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
            . "👉 Traiter la commande sur Zanika :\n"
            . "{$orderUrl}\n\n"
            . "🚀 _Zanika — Le commerce à votre manière_";
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
     * Formate la fiche de mission de livraison transmise au livreur (moto / Zémidjan).
     *
     * @param array{driver_phone?: ?string, courier_fee?: ?int, notes?: ?string} $custom
     */
    public function formatDeliveryMission(Order $order, array $custom = []): string
    {
        $shop = $order->shop;
        $dateStr = $order->created_at ? $order->created_at->format('d/m/Y à H:i') : date('d/m/Y à H:i');

        // Articles
        $itemsText = '';
        foreach ($order->items as $item) {
            $itemsText .= "• {$item->quantity}x *{$item->product_title}*\n";
        }

        // Point de retrait
        $shopAddress = $shop->address ?: 'Non précisée (contacter la boutique)';
        $shopPhone = $shop->phone ?: ($shop->whatsapp ?: 'N/A');

        // Point de livraison
        $deliveryZone = $order->delivery_zone_name ?: 'Zone non définie';
        $deliveryAddress = $order->delivery_address ?: 'Adresse standard';
        $customerNotes = $order->delivery_notes ? "\n💬 *Note client :* {$order->delivery_notes}" : '';
        $driverNotes = ! empty($custom['notes']) ? "\n⚠️ *Consigne commerçant :* {$custom['notes']}" : '';

        // Détection de lien Google Maps dans l'adresse
        $mapsLink = '';
        if (preg_match('/https?:\/\/(?:www\.)?(?:google\.com\/maps|maps\.google\.com|maps\.app\.goo\.gl)[^\s\)]+/', $deliveryAddress, $matches)) {
            $mapsLink = "\n🗺️ *GPS Client :* {$matches[0]}";
        }

        // Gestion de l'encaissement
        $totalFormatted = number_format($order->total, 0, ',', ' ');
        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            $paymentSection = "✅ *COMMANDE DÉJÀ PAYÉE EN LIGNE*\n"
                . "👉 *Ne rien encaisser au client pour la marchandise.*";
        } else {
            $paymentSection = "💵 *MONTANT À ENCAISSER DU CLIENT : {$totalFormatted} FCFA*\n"
                . "👉 *À reverser intégralement à la boutique lors du retour.*";
        }

        // Frais de course livreur
        $courierFee = isset($custom['courier_fee']) && $custom['courier_fee'] !== null
            ? (int) $custom['courier_fee']
            : (int) $order->shipping_cost;
        $courierFeeText = $courierFee > 0 ? number_format($courierFee, 0, ',', ' ') . ' FCFA' : 'À convenir avec la boutique';

        return "🛵 *MISSION DE LIVRAISON — {$shop->name}*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "🔖 Commande : *#{$order->order_number}*\n"
            . "📅 Date : {$dateStr}\n\n"
            . "📍 *POINT DE RETRAIT (Boutique) :*\n"
            . "• Boutique : *{$shop->name}*\n"
            . "• Adresse : {$shopAddress}\n"
            . "• Contact boutique : *{$shopPhone}*\n\n"
            . "🏁 *POINT DE LIVRAISON (Client) :*\n"
            . "• Destinataire : *{$order->customer_name}*\n"
            . "• Téléphone : *{$order->customer_phone}* 📲\n"
            . "• Quartier / Zone : *{$deliveryZone}*\n"
            . "• Adresse : {$deliveryAddress}"
            . "{$mapsLink}"
            . "{$customerNotes}"
            . "{$driverNotes}\n\n"
            . "📦 *COLIS :*\n"
            . "{$itemsText}\n"
            . "💰 *ENCAISSEMENT :*\n"
            . "{$paymentSection}\n"
            . "🛵 *Frais de course livreur :* {$courierFeeText}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "⚠️ *Consigne :* Vérifiez le colis au départ et appelez le client avant d'arriver à destination ! Merci 🙏";
    }

    /**
     * Génère le lien WhatsApp direct vers le livreur avec la fiche de mission pré-remplie.
     */
    public function getDeliveryDispatchWhatsAppUrl(Order $order, ?string $driverPhone = null, array $custom = []): string
    {
        $message = $this->formatDeliveryMission($order, $custom);
        $encodedMessage = urlencode($message);

        $digits = null;
        if ($driverPhone) {
            $digits = preg_replace('/\D+/', '', $driverPhone);
            if (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            }
            if (strlen($digits) === 8) {
                $digits = '228' . $digits;
            }
        }

        if ($digits) {
            return "https://wa.me/{$digits}?text={$encodedMessage}";
        }

        return "https://api.whatsapp.com/send?text={$encodedMessage}";
    }

    /**
     * Formate le reçu / facture envoyé au client sur WhatsApp.
     */
    public function formatCustomerInvoiceWhatsApp(Order $order): string
    {
        $shop = $order->shop;
        $dateStr = $order->created_at ? $order->created_at->format('d/m/Y') : date('d/m/Y');
        $totalFormatted = number_format($order->total, 0, ',', ' ');
        $subtotal = number_format($order->subtotal, 0, ',', ' ');
        $shipping = $order->shipping_cost === 0 ? 'Offerte' : number_format($order->shipping_cost, 0, ',', ' ') . ' FCFA';

        $itemsText = '';
        foreach ($order->items as $item) {
            $unitPrice = number_format($item->unit_price, 0, ',', ' ');
            $totalPrice = number_format($item->total_price, 0, ',', ' ');
            $itemsText .= "• {$item->quantity}x *{$item->product_title}* : {$totalPrice} FCFA\n";
        }

        $paymentStatus = $order->payment_status === Order::PAYMENT_STATUS_PAID
            ? "✅ *RÉGLÉ (Payé)*"
            : "⏳ *À PAYER À LA LIVRAISON*";

        $discountLine = ($order->discount_amount ?? 0) > 0
            ? "🏷️ Code promo ({$order->discount_code}) : -" . number_format($order->discount_amount, 0, ',', ' ') . " FCFA\n"
            : '';
        $receiptUrl = $order->publicReceiptUrl();

        return "🧾 *REÇU DE COMMANDE — {$shop->name}*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "🔖 N° Reçu : *#REC-{$order->order_number}*\n"
            . "📅 Date : {$dateStr}\n"
            . "👤 Client : *{$order->customer_name}*\n\n"
            . "📦 *DÉTAIL DES ACHATS :*\n"
            . "{$itemsText}\n"
            . "💰 Sous-total : {$subtotal} FCFA\n"
            . "🚚 Livraison ({$order->delivery_zone_name}) : {$shipping}\n"
            . $discountLine
            . "────────────────────\n"
            . "💵 *TOTAL NET : {$totalFormatted} FCFA*\n"
            . "💳 Mode : {$order->payment_method_label}\n"
            . "📌 Statut : {$paymentStatus}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "👉 *Consulter et télécharger votre reçu officiel certifié :*\n"
            . "{$receiptUrl}\n\n"
            . "Merci pour votre achat chez *{$shop->name}* ! À très bientôt ! ✨";
    }

    /**
     * Génère l'URL WhatsApp pour transmettre le reçu au client.
     */
    public function getCustomerInvoiceWhatsAppUrl(Order $order): ?string
    {
        $phoneDigits = preg_replace('/\D+/', '', $order->customer_phone);
        if (str_starts_with($phoneDigits, '00')) {
            $phoneDigits = substr($phoneDigits, 2);
        }
        if (strlen($phoneDigits) === 8) {
            $phoneDigits = '228' . $phoneDigits;
        }

        $message = $this->formatCustomerInvoiceWhatsApp($order);

        if ($phoneDigits) {
            return "https://wa.me/{$phoneDigits}?text=" . urlencode($message);
        }

        return "https://api.whatsapp.com/send?text=" . urlencode($message);
    }
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
