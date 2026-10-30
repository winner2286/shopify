<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\FeexPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        Log::info("FeexPay Webhook received:", $payload);

        $reference = $payload['reference'] ?? ($payload['transref'] ?? null);
        $status = strtoupper($payload['status'] ?? '');
        $customId = $payload['custom_id'] ?? ($payload['customId'] ?? null);

        if (! $reference) {
            return response()->json(['success' => false, 'message' => 'Missing reference'], 400);
        }

        if ($status === 'SUCCESSFUL' || $status === 'SUCCESS' || $status === 'PAID') {
            // 1. Commande de boutique client (Ex: customId = "ORDER:123" ou "CMD-...")
            if ($customId && str_starts_with($customId, 'ORDER:')) {
                $orderId = (int) substr($customId, 6);
                $order = Order::withoutGlobalScopes()->find($orderId);
                if ($order && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
                    $this->feexPayService->handleOrderPaymentSuccess($order, $reference, $payload['phoneNumber'] ?? null);
                }
            } elseif ($customId && str_starts_with($customId, 'SUB:')) {
                // 2. Renouvellement d'abonnement SaaS Zanika (Ex: customId = "SUB:45" ou "SUB:45:2")
                $parts = explode(':', substr($customId, 4));
                $subId = (int) ($parts[0] ?? 0);
                $planId = isset($parts[1]) ? (int) $parts[1] : null;

                $sub = Subscription::find($subId);
                $plan = $planId ? Plan::find($planId) : null;
                if ($sub) {
                    $this->feexPayService->handleSubscriptionPaymentSuccess($sub, $reference, $plan);
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Webhook processed']);
    }

    /**
     * Page de redirection / retour après paiement FeexPay
     */
    public function callback(Request $request)
    {
        $reference = $request->input('reference') ?? $request->input('transref');
        $customId = $request->input('custom_id') ?? $request->input('customId');
        $status = strtoupper($request->input('status', ''));

        Log::info("FeexPay Callback return:", $request->all());

        if ($customId && str_starts_with($customId, 'ORDER:')) {
            $orderId = (int) substr($customId, 6);
            $order = Order::withoutGlobalScopes()->with('shop')->find($orderId);
            if ($order) {
                if ($status === 'SUCCESSFUL' || $status === 'SUCCESS' || $status === 'PAID') {
                    $this->feexPayService->handleOrderPaymentSuccess($order, $reference ?: 'FP-' . time());
                    return redirect()->route('storefront.order.show', [
                        'subdomain' => $order->shop->subdomain,
                        'orderNumber' => $order->order_number,
                    ])->with('status', '🎉 Votre paiement en ligne FeexPay a été validé avec succès !');
                }

                return redirect()->route('storefront.order.show', [
                    'subdomain' => $order->shop->subdomain,
                    'orderNumber' => $order->order_number,
                ])->with('warning', 'Le paiement est en cours de traitement ou n\'a pas pu aboutir.');
            }
        }

        if ($customId && str_starts_with($customId, 'SUB:')) {
            $parts = explode(':', substr($customId, 4));
            $subId = (int) ($parts[0] ?? 0);
            $planId = isset($parts[1]) ? (int) $parts[1] : null;

            $sub = Subscription::find($subId);
            $plan = $planId ? Plan::find($planId) : null;

            if ($sub && ($status === 'SUCCESSFUL' || $status === 'SUCCESS' || $status === 'PAID')) {
                $this->feexPayService->handleSubscriptionPaymentSuccess($sub, $reference ?: 'FP-' . time(), $plan);
                return redirect()->route('dashboard')->with('status', '🎉 Félicitations ! Votre forfait Zanika ' . ($plan ? $plan->name : '') . ' a été activé avec succès.');
            }
            return redirect()->route('dashboard')->with('warning', 'Paiement en cours de traitement.');
        }

        return redirect()->route('home')->with('status', 'Paiement FeexPay traité.');
    }

    /**
     * Page de paiement d'un forfait commerçant via FeexPay
     */
    public function subscriptionCheckout(Request $request, Plan $plan)
    {
        $user = $request->user();
        $shop = $user->shops()->latest()->first();

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
        $ref = 'SIM-SUB-' . strtoupper(bin2hex(random_bytes(4)));
        $this->feexPayService->handleSubscriptionPaymentSuccess($subscription, $ref, $plan);

        return redirect()->route('dashboard')->with('status', "🎉 Simulation réussie ! Votre forfait {$plan->name} ({$plan->monthly_price} FCFA) a été activé avec succès pour 1 mois supplémentaire.");
    }
}
