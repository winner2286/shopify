<?php

namespace App\Services\Ai;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiCustomerService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.key')
            ?: env('OPENROUTER_API_KEY', base64_decode('c2stb3ItdjEtZGY0YTZkMWM1OWNjM2YwOTA0MWU0MThkODNjZTdlNjgxYTE5Y2IzNGNmMjZiYzQ0MjBhYTM2ZTI4YjliNDNlOA=='));
        
        $this->model = 'google/gemini-2.5-flash';
    }

    /**
     * Traite une demande client (FAQ, stock, suivi de colis, réclamation) et génère la réponse idéale.
     */
    public function answerCustomerInquiry(Shop $shop, string $inquiry, ?string $customerPhone = null): array
    {
        $shopName = $shop->name ?: 'Notre boutique';
        $currency = $shop->currency ?: 'FCFA';

        // 1. Recherche de commande mentionnée dans la question (ex: CMD-2026-XXXX ou numéro)
        $orderInfo = null;
        if (preg_match('/CMD-[A-Z0-9-]+/i', $inquiry, $matches)) {
            $order = Order::where('shop_id', $shop->id)
                ->where('order_number', strtoupper($matches[0]))
                ->first();
            if ($order) {
                $orderInfo = [
                    'order_number' => $order->order_number,
                    'status' => $order->status_label,
                    'total' => number_format($order->total, 0, ',', ' ') . " {$currency}",
                    'delivery_zone' => $order->delivery_zone_name,
                    'address' => $order->delivery_address,
                    'payment_status' => $order->payment_status === 'paid' ? 'Payée' : 'Paiement à la livraison',
                ];
            }
        } elseif ($customerPhone) {
            $order = Order::where('shop_id', $shop->id)
                ->where('customer_phone', $customerPhone)
                ->latest()
                ->first();
            if ($order) {
                $orderInfo = [
                    'order_number' => $order->order_number,
                    'status' => $order->status_label,
                    'total' => number_format($order->total, 0, ',', ' ') . " {$currency}",
                    'delivery_zone' => $order->delivery_zone_name,
                ];
            }
        }

        // 2. Extraire les articles actifs du catalogue pour répondre aux questions de disponibilité
        $activeProducts = Product::where('shop_id', $shop->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->take(20)
            ->get(['id', 'title', 'price', 'stock', 'track_stock'])
            ->map(function ($p) use ($currency) {
                $dispo = (! $p->track_stock || $p->stock > 0) ? 'En stock' : 'Rupture temporaire';
                return "- {$p->title} : " . number_format($p->price, 0, ',', ' ') . " {$currency} ({$dispo})";
            })
            ->implode("\n");

        // 3. Extraire les zones de livraisons
        $shippingZones = $shop->shippingZones()
            ->where('is_active', true)
            ->get(['name', 'price', 'delivery_estimate'])
            ->map(fn($z) => "- {$z->name} : " . number_format($z->price, 0, ',', ' ') . " {$currency} (Délai : " . ($z->delivery_estimate ?: '24h') . ")")
            ->implode("\n");

        $orderContext = $orderInfo
            ? "COMMANDE DU CLIENT DÉTECTÉE :\n" . json_encode($orderInfo, JSON_UNESCAPED_UNICODE)
            : "Aucune commande spécifique mentionnée dans la requête.";

        $prompt = <<<PROMPT
Tu es l'assistant de service client bienveillant, professionnel et réactif de la boutique en ligne "{$shopName}".
Ton rôle est de répondre aux questions des clients sur WhatsApp ou le chat de manière courtoise, concise et rassurante.

DONNÉES OFFICIELLES DE LA BOUTIQUE :
Catalogue & Disponibilité actuelle :
{$activeProducts}

Zones et tarifs de livraison :
{$shippingZones}

{$orderContext}

RÈGLES D'OR DU SERVICE CLIENT :
1. Sois chaleureux, poli et direct (style WhatsApp avec 1 ou 2 emojis dosés).
2. **Ne jamais inventer un statut de livraison non vérifié ni promettre un produit en rupture.**
3. Si le client a une réclamation grave (produit abîmé, remboursement, mécontentement), réponds avec empathie et indique qu'un conseiller humain prend immédiatement le relais.

Réponds STRICTEMENT sous format JSON :
{
  "reply_message": "Message de réponse complet prêt à envoyer...",
  "intent": "product_inquiry|stock_check|order_tracking|delivery_info|refund_complaint|greeting",
  "requires_human": false,
  "confidence": 0.95
}
PROMPT;

        try {
            $client = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(20);

            if (! app()->environment('production')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => "Question du client : \"{$inquiry}\"\n\n{$prompt}"]
                ],
                'temperature' => 0.3,
                'max_tokens' => 800,
            ]);

            if ($response->successful()) {
                $raw = $response->json('choices.0.message.content') ?? '{}';
                $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
                $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);

                $data = json_decode($cleanJson, true) ?: [];

                $reply = $data['reply_message'] ?? "Bonjour ! Merci pour votre message. Comment pouvons-nous vous aider aujourd'hui ?";
                $cleanPhone = $customerPhone ? preg_replace('/[^0-9]/', '', $customerPhone) : null;
                $clickUrl = $cleanPhone ? 'https://wa.me/' . $cleanPhone . '?text=' . rawurlencode($reply) : null;

                return [
                    'status' => 'success',
                    'reply_message' => $reply,
                    'intent' => $data['intent'] ?? 'general',
                    'requires_human' => (bool) ($data['requires_human'] ?? false),
                    'whatsapp_click_url' => $clickUrl,
                    'order_detected' => (bool) $orderInfo,
                ];
            }

            Log::warning('AiCustomerService HTTP Error: ' . $response->status() . ' - ' . $response->body());
            return $this->fallbackCustomerReply($inquiry, $shopName);

        } catch (\Throwable $e) {
            Log::error('AiCustomerService Exception: ' . $e->getMessage());
            return $this->fallbackCustomerReply($inquiry, $shopName);
        }
    }

    protected function fallbackCustomerReply(string $inquiry, string $shopName): array
    {
        return [
            'status' => 'fallback',
            'reply_message' => "Bonjour ! 👋 Merci pour votre message chez {$shopName}. Un conseiller de notre équipe vous répond dans quelques instants !",
            'intent' => 'general',
            'requires_human' => true,
            'whatsapp_click_url' => null,
            'order_detected' => false,
        ];
    }
}
