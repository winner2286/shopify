<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
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
        $this->shopId = (string) (config('feexpay.shop_id') ?: '');
        $this->token = (string) (config('feexpay.token') ?: '');
        $this->mode = strtoupper((string) (config('feexpay.mode') ?: 'SANDBOX'));
        $this->baseUrl = rtrim((string) (config('feexpay.base_url') ?: 'https://api-v2.feexpay.me'), '/');
        $this->currency = (string) (config('feexpay.currency') ?: 'XOF');
    }

    public function getShopId(): string
    {
        return (string) (config('feexpay.shop_id') ?: $this->shopId);
    }

    public function getToken(): string
    {
        return (string) (config('feexpay.token') ?: $this->token);
    }

    public function getMode(): string
    {
        $mode = strtoupper((string) (config('feexpay.mode') ?: $this->mode));

        return $mode === 'LIVE' ? 'LIVE' : 'SANDBOX';
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
        if (empty($this->token)) {
            return ['status' => 'ERROR', 'error' => 'FEEXPAY_TOKEN non configuré'];
        }

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
            Log::error('FeexPay checkTransactionStatus error: '.$e->getMessage());

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
            Log::error('FeexPay initiateMobilePayment exception: '.$e->getMessage());

            return ['status' => 'FAILED', 'message' => $e->getMessage()];
        }
    }

    /**
     * Valide et marque une commande comme payée suite à confirmation FeexPay
     */
    public function handleOrderPaymentSuccess(Order $order, string $transactionRef, ?string $payerPhone = null): void
    {
        $this->processTransaction($transactionRef, 'ORDER:'.$order->id, function () use ($order, $transactionRef, $payerPhone) {
            $lockedOrder = Order::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->setRawAttributes($lockedOrder->getAttributes(), true);
            if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
                return;
            }

            $previousStatus = $order->status;

            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'paid_at' => now(),
                'status' => $order->status === Order::STATUS_PENDING ? Order::STATUS_CONFIRMED : $order->status,
            ]);

            /** @var Payment|null $payment */
            $payment = $order->payments()->withoutGlobalScopes()->latest()->first();
            if ($payment) {
                $payment->update([
                    'status' => Payment::STATUS_PAID,
                    'paid_at' => now(),
                    'operator_reference' => $transactionRef,
                    'payer_phone' => $payerPhone ?: $payment->payer_phone,
                ]);
            } else {
                Payment::withoutGlobalScopes()->create([
                    'shop_id' => $order->shop_id,
                    'order_id' => $order->id,
                    'payment_reference' => 'PAY-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4))),
                    'method' => $order->payment_method ?: Order::PAYMENT_FEEXPAY,
                    'amount' => (int) round((float) $order->total),
                    'currency' => $order->currency ?: 'XOF',
                    'status' => Payment::STATUS_PAID,
                    'operator_reference' => $transactionRef,
                    'payer_phone' => $payerPhone ?: $order->customer_phone,
                    'paid_at' => now(),
                ]);
            }

            $order->statusHistories()->create([
                'from_status' => $previousStatus,
                'to_status' => $order->status,
                'author_type' => 'system',
                'comment' => "Paiement en ligne validé avec succès par GeniusPay (Réf: {$transactionRef}).",
            ]);
        });
    }

    /**
     * Valide et active un abonnement suite à confirmation GeniusPay
     */
    public function handleSubscriptionPaymentSuccess(Subscription $subscription, string $transactionRef, ?Plan $newPlan = null): void
    {
        $target = 'SUB:'.$subscription->id.':'.($newPlan?->id ?? $subscription->plan_id);
        $this->processTransaction($transactionRef, $target, function () use ($subscription, $transactionRef, $newPlan) {
            $subscription->shop?->owner()->lockForUpdate()->first();
            $lockedSubscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $subscription->setRawAttributes($lockedSubscription->getAttributes(), true);
            $subscription->unsetRelation('plan');

            $plan = $newPlan ?? $subscription->plan;
            $convertingTrial = $subscription->monthly_price === null && $subscription->trial_ends_at !== null;

            $currentEnd = in_array($subscription->status, ['active', 'grace'], true) && $subscription->ends_at?->isFuture()
                ? $subscription->ends_at
                : now();
            $periodEnd = $currentEnd->copy()->addMonthNoOverflow();

            $subscription->update([
                'plan_id' => $plan ? $plan->id : $subscription->plan_id,
                'monthly_price' => $plan ? $subscription->monthlyPriceFor($plan) : $subscription->monthly_price,
                'status' => 'active',
                'starts_at' => $subscription->starts_at ?: now(),
                'trial_ends_at' => null,
                'ends_at' => $periodEnd,
                'renews_at' => $periodEnd,
            ]);

            // Réapprovisionnement des crédits IA Système et Photoroom pour le nouveau cycle de facturation
            $shop = $subscription->shop;
            if ($shop) {
                $subscription->synchronizeAccount();
                if ($convertingTrial) {
                    $shop->resetAccountCreditUsage();
                }
                $shop->unsetRelation('subscription');
                $shop->unsetRelation('subscriptions');
                $shop->owner?->unsetRelation('shop');
                $shop->owner?->unsetRelation('active_shop');
            }

            Log::info("Abonnement #{$subscription->id} activé/renouvelé avec succès via GeniusPay (Réf: {$transactionRef}).");
        });
    }

    private function processTransaction(string $reference, string $target, \Closure $apply): void
    {
        if (trim($reference) === '') {
            throw new \InvalidArgumentException('Une référence de paiement est obligatoire.');
        }

        DB::transaction(function () use ($reference, $target, $apply) {
            DB::table('payment_transactions')->insertOrIgnore([
                'provider' => 'feexpay', 'reference' => $reference, 'target' => $target,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $transaction = DB::table('payment_transactions')->where('provider', 'feexpay')
                ->where('reference', $reference)->lockForUpdate()->first();

            if (! $transaction || $transaction->target !== $target) {
                throw new \RuntimeException('Cette référence de paiement appartient à une autre opération.');
            }
            if ($transaction->processed_at !== null) {
                return;
            }

            $apply();
            DB::table('payment_transactions')->where('id', $transaction->id)
                ->update(['processed_at' => now(), 'updated_at' => now()]);
        }, 3);
    }
}
