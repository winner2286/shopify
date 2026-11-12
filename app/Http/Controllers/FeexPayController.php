<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\FeexPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FeexPayController extends Controller
{
    public function __construct(
        protected FeexPayService $feexPayService
    ) {}

    /**
     * Webhook de notification asynchrone envoyé par FeexPay
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $reference = trim((string) ($payload['reference'] ?? ($payload['transref'] ?? '')));
        $customId = trim((string) ($payload['custom_id'] ?? ($payload['customId'] ?? '')));

        Log::info('FeexPay Webhook received', [
            'reference' => $reference,
            'custom_id' => $customId,
        ]);

        if ($reference === '' || $customId === '') {
            return response()->json(['success' => false, 'message' => 'Missing reference or custom_id'], 400);
        }

        if (str_starts_with($customId, 'ORDER:')) {
            $orderId = (int) substr($customId, 6);
            $order = Order::withoutGlobalScopes()->find($orderId);
            if (! $order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            $verification = $this->verifyApprovedTransaction($reference, $customId, (int) round((float) $order->total));
            if (! $verification) {
                return response()->json(['success' => false, 'message' => 'Transaction FeexPay non vérifiée'], 422);
            }

            if ($order->payment_status !== Order::PAYMENT_STATUS_PAID) {
                $payerPhone = $verification['phoneNumber'] ?? ($payload['phoneNumber'] ?? null);
                $this->feexPayService->handleOrderPaymentSuccess($order, $reference, $payerPhone);
            }

            return response()->json(['success' => true, 'message' => 'Webhook processed']);
        }

        if (str_starts_with($customId, 'SUB:')) {
            $parts = explode(':', substr($customId, 4));
            $subId = (int) ($parts[0] ?? 0);
            $planId = isset($parts[1]) ? (int) $parts[1] : null;

            $sub = Subscription::find($subId);
            $plan = $planId ? Plan::find($planId) : $sub?->plan;
            if (! $sub || ! $plan) {
                return response()->json(['success' => false, 'message' => 'Subscription not found'], 404);
            }

            $verification = $this->verifyApprovedTransaction($reference, $customId, (int) round((float) $plan->monthly_price));
            if (! $verification) {
                return response()->json(['success' => false, 'message' => 'Transaction FeexPay non vérifiée'], 422);
            }

            $this->feexPayService->handleSubscriptionPaymentSuccess($sub, $reference, $plan);

            return response()->json(['success' => true, 'message' => 'Webhook processed']);
        }

        return response()->json(['success' => false, 'message' => 'Unsupported custom_id'], 422);
    }

    /**
     * Page de redirection / retour après paiement FeexPay
     */
    public function callback(Request $request)
    {
        $reference = trim((string) ($request->input('reference') ?? $request->input('transref') ?? ''));
        $customId = trim((string) ($request->input('custom_id') ?? $request->input('customId') ?? ''));

        Log::info('FeexPay Callback return', [
            'reference' => $reference,
            'custom_id' => $customId,
        ]);

        if ($customId !== '' && str_starts_with($customId, 'ORDER:')) {
            $orderId = (int) substr($customId, 6);
            $order = Order::withoutGlobalScopes()->with('shop')->find($orderId);
            if ($order) {
                if ($reference !== '') {
                    $verification = $this->verifyApprovedTransaction($reference, $customId, (int) round((float) $order->total));
                    if ($verification) {
                        $this->feexPayService->handleOrderPaymentSuccess($order, $reference, $verification['phoneNumber'] ?? null);

                        return redirect()->route('storefront.order.show', [
                            'subdomain' => $order->shop->subdomain,
                            'orderNumber' => $order->order_number,
                        ])->with('status', '🎉 Votre paiement en ligne FeexPay a été validé avec succès !');
                    }
                }

                return redirect()->route('storefront.order.show', [
                    'subdomain' => $order->shop->subdomain,
                    'orderNumber' => $order->order_number,
                ])->with('warning', 'Le paiement est en cours de traitement ou n\'a pas pu être vérifié auprès de FeexPay.');
            }
        }

        if ($customId !== '' && str_starts_with($customId, 'SUB:')) {
            $parts = explode(':', substr($customId, 4));
            $subId = (int) ($parts[0] ?? 0);
            $planId = isset($parts[1]) ? (int) $parts[1] : null;

            $sub = Subscription::find($subId);
            $plan = $planId ? Plan::find($planId) : $sub?->plan;

            if ($sub && $plan && $reference !== '') {
                $verification = $this->verifyApprovedTransaction($reference, $customId, (int) round((float) $plan->monthly_price));
                if ($verification) {
                    $this->feexPayService->handleSubscriptionPaymentSuccess($sub, $reference, $plan);

                    return redirect()->route('dashboard')->with('status', '🎉 Félicitations ! Votre forfait Zanika ' . $plan->name . ' a été activé avec succès.');
                }
            }

            return redirect()->route('dashboard')->with('warning', 'Paiement en cours de traitement ou non encore confirmé.');
        }

        return redirect()->route('home')->with('status', 'Retour FeexPay traité.');
    }

    /**
     * Vérifie la transaction directement auprès de l'API FeexPay et empêche la réutilisation d'une référence.
     */
    private function verifyApprovedTransaction(string $reference, string $expectedCustomId, int $expectedAmount): ?array
    {
        if ($reference === '') {
            return null;
        }

        $boundTarget = Cache::get("feexpay_tx_target:{$reference}");
        if ($boundTarget !== null && $boundTarget !== $expectedCustomId) {
            Log::warning('FeexPay replay attempt blocked', [
                'reference' => $reference,
                'expected' => $expectedCustomId,
                'bound' => $boundTarget,
            ]);

            return null;
        }

        if (str_starts_with($expectedCustomId, 'ORDER:')) {
            $orderId = (int) substr($expectedCustomId, 6);
            if (Payment::withoutGlobalScopes()->where('operator_reference', $reference)->where('order_id', '!=', $orderId)->exists()) {
                Log::warning('FeexPay order reference reuse blocked', ['reference' => $reference, 'order_id' => $orderId]);

                return null;
            }
        }

        $verification = $this->feexPayService->checkTransactionStatus($reference);
        $status = strtoupper((string) ($verification['status'] ?? ($verification['data']['status'] ?? '')));
        if (! in_array($status, ['SUCCESSFUL', 'SUCCESS', 'PAID'], true)) {
            return null;
        }

        $verifiedCustomId = $verification['custom_id']
            ?? $verification['customId']
            ?? ($verification['data']['custom_id'] ?? ($verification['data']['customId'] ?? null));
        if ($verifiedCustomId !== null && (string) $verifiedCustomId !== '' && (string) $verifiedCustomId !== $expectedCustomId) {
            Log::warning('FeexPay custom_id mismatch', [
                'reference' => $reference,
                'expected' => $expectedCustomId,
                'actual' => $verifiedCustomId,
            ]);

            return null;
        }

        $verifiedAmount = $verification['amount'] ?? ($verification['data']['amount'] ?? null);
        if ($expectedAmount > 0 && ($verifiedAmount === null || (int) $verifiedAmount < $expectedAmount)) {
            Log::warning('FeexPay amount mismatch', [
                'reference' => $reference,
                'expected' => $expectedAmount,
                'actual' => $verifiedAmount,
            ]);

            return null;
        }

        Cache::put("feexpay_tx_target:{$reference}", $expectedCustomId, now()->addYears(2));

        return $verification;
    }

    /**
     * Page de paiement d'un forfait commerçant via FeexPay
     */
    public function subscriptionCheckout(Request $request, Plan $plan)
    {
        $user = $request->user();
        $shop = $user->shop;

        if (! $shop) {
            return redirect()->route('dashboard')->with('error', 'Veuillez créer une boutique avant de souscrire à un forfait.');
        }

        $subscription = $shop->subscription ?: Subscription::create([
            'shop_id' => $shop->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        return view('subscription.checkout', [
            'plan' => $plan,
            'shop' => $shop,
            'subscription' => $subscription,
        ]);
    }

    /**
     * Validation immédiate en mode simulation Sandbox pour l'abonnement
     */
    public function simulateSubscriptionPayment(Request $request, Subscription $subscription, Plan $plan): RedirectResponse
    {
        if (! app()->environment('local', 'testing') || ! $this->feexPayService->isSandbox()) {
            abort(403, 'La simulation de paiement est désactivée en production.');
        }

        $shop = $request->user()?->shop;
        if (! $shop || (int) $subscription->shop_id !== (int) $shop->id) {
            abort(403, 'Accès non autorisé à cet abonnement.');
        }

        $ref = 'SIM-SUB-' . strtoupper(bin2hex(random_bytes(4)));
        $this->feexPayService->handleSubscriptionPaymentSuccess($subscription, $ref, $plan);

        return redirect()->route('dashboard')->with('status', "🎉 Simulation réussie ! Votre forfait {$plan->name} ({$plan->monthly_price} FCFA) a été activé avec succès pour 1 mois supplémentaire.");
    }
}
