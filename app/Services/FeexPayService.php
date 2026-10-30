<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FeexPayService
{
    protected string $shopId;
    protected string $token;
    protected string $mode;
    protected string $baseUrl;
    protected string $currency;

    public function __construct()
    {
        $this->shopId = (string) (config('feexpay.shop_id') ?: env('FEEXPAY_SHOP_ID', '698dd650a7ffb88696f5id86'));
        $this->token = (string) (config('feexpay.token') ?: env('FEEXPAY_TOKEN', 'test_Hg7Kjl3ZAM63UuIUpuudD9nKuu3ZAM67Kjl3Uuhn'));
        $this->mode = strtoupper((string) (config('feexpay.mode') ?: env('FEEXPAY_MODE', 'SANDBOX')));
        $this->baseUrl = rtrim((string) (config('feexpay.base_url') ?: env('FEEXPAY_BASE_URL', 'https://api-v2.feexpay.me')), '/');
        $this->currency = (string) (config('feexpay.currency') ?: 'XOF');
    }

    public function getShopId(): string
    {
        return $this->shopId;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getMode(): string
    {
        return $this->mode === 'LIVE' ? 'LIVE' : 'SANDBOX';
    }

    public function isSandbox(): bool
    {
        return $this->getMode() === 'SANDBOX';
    }

    public function getSdkUrl(): string
    {
        return 'https://api-v2.feexpay.me/feexpay-javascript-sdk/index.js';
    }

    /**
     * Vérifie le statut d'une transaction directement auprès de l'API FeexPay
     */
    public function checkTransactionStatus(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Accept' => 'application/json',
            ])->timeout(10)->get("{$this->baseUrl}/api/transactions/public/single/status/{$reference}");

            if ($response->successful()) {
                return $response->json();
            }

            // Fallback sur l'endpoint d'intégration classique
            $fallback = Http::withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Accept' => 'application/json',
            ])->timeout(10)->get("{$this->baseUrl}/api/transactions/getrequesttopay/integration/{$reference}");

            if ($fallback->successful()) {
                return $fallback->json();
            }

            return ['status' => 'UNKNOWN', 'raw' => $response->body()];
        } catch (\Throwable $e) {
            Log::error("FeexPay checkTransactionStatus error: " . $e->getMessage());
            return ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }

    /**
     * Initialise un paiement Mobile Money direct (Server-to-Server)
     */
    public function initiateMobilePayment(array $data): array
    {
        $payload = [
            'phoneNumber' => preg_replace('/\D/', '', (string) ($data['phone'] ?? '')),
            'amount' => (int) $data['amount'],
            'reseau' => strtoupper((string) ($data['network'] ?? 'TMONEY')),
            'token' => $this->token,
            'shop' => $this->shopId,
            'first_name' => $data['customer_name'] ?? 'Client',
            'email' => $data['customer_email'] ?? 'client@zanika.shop',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(15)->post("{$this->baseUrl}/api/transactions/requesttopay/integration", $payload);

            return $response->json() ?? ['status' => 'FAILED', 'message' => 'Réponse vide de FeexPay'];
        } catch (\Throwable $e) {
            Log::error("FeexPay initiateMobilePayment exception: " . $e->getMessage());
            return ['status' => 'FAILED', 'message' => $e->getMessage()];
        }
    }

    /**
     * Valide et marque une commande comme payée suite à confirmation FeexPay
     */
    public function handleOrderPaymentSuccess(Order $order, string $transactionRef, ?string $payerPhone = null): void
    {
        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'paid_at' => now(),
            'status' => $order->status === Order::STATUS_PENDING ? Order::STATUS_CONFIRMED : $order->status,
        ]);

        /** @var Payment|null $payment */
        $payment = $order->payments()->latest()->first();
        if ($payment) {
            $payment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'transaction_id' => $transactionRef,
                'payer_phone' => $payerPhone ?: $payment->payer_phone,
            ]);
        }

        $order->statusHistories()->create([
            'from_status' => Order::STATUS_PENDING,
            'to_status' => $order->status,
            'author_type' => 'system',
            'comment' => "Paiement en ligne validé avec succès par FeexPay (Réf: {$transactionRef}).",
        ]);
    }

    /**
     * Valide et active un abonnement suite à confirmation FeexPay
     */
    public function handleSubscriptionPaymentSuccess(Subscription $subscription, string $transactionRef, ?\App\Models\Plan $newPlan = null): void
    {
        $plan = $newPlan ?? $subscription->plan;

        $currentEnd = ($subscription->ends_at && $subscription->ends_at->isFuture())
            ? $subscription->ends_at
            : now();

        $subscription->update([
            'plan_id' => $plan ? $plan->id : $subscription->plan_id,
            'status' => 'active',
            'starts_at' => $subscription->starts_at ?: now(),
            'ends_at' => $currentEnd->copy()->addMonth(),
            'renews_at' => $currentEnd->copy()->addMonth(),
        ]);

        Log::info("Abonnement #{$subscription->id} activé/renouvelé avec succès via FeexPay (Réf: {$transactionRef}).");
    }
}
