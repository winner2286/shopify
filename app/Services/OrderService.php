<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use Exception;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        protected CartService $cartService,
        protected WhatsAppNotificationService $whatsAppService
    ) {}

    /**
     * Crée une nouvelle commande depuis le panier (CDC D-05)
     *
     * @throws Exception
     */
    public function createFromCart(Shop $shop, array $data): Order
    {
        $cart = $this->cartService->get($shop);

        if (empty($cart)) {
            throw new Exception('Votre panier est vide.');
        }

        /** @var ShippingZone|null $zone */
        $zone = ShippingZone::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('is_active', true)
            ->find($data['shipping_zone_id']);

        if (! $zone) {
            throw new Exception('Zone de livraison non valide.');
        }

        // 1. Vérification stricte des stocks disponibles (CDC D-04, D-05)
        foreach ($cart as $productId => $item) {
            /** @var Product|null $product */
            $product = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', Product::STATUS_ACTIVE)
                ->find($productId);

            if (! $product) {
                throw new Exception("L'article « {$item['title']} » n'est plus disponible.");
            }

            if ($product->track_stock && $product->stock < $item['quantity']) {
                throw new Exception("Stock insuffisant pour « {$product->title} ». Disponible : {$product->stock} exemplaire(s).");
            }
        }

        return DB::transaction(function () use ($shop, $cart, $zone, $data) {
            $subtotal = $this->cartService->getSubtotal($shop);
            $shippingCost = $zone->calculateFee($subtotal);
            $total = $subtotal + $shippingCost;

            // 2. Gestion de la fiche client (CDC E-04)
            $phone = trim($data['customer_phone']);
            $customer = Customer::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('phone', $phone)
                ->first();

            if (! $customer) {
                $customer = Customer::withoutGlobalScopes()->create([
                    'shop_id' => $shop->id,
                    'name' => $data['customer_name'],
                    'phone' => $phone,
                    'email' => $data['customer_email'] ?? null,
                    'city' => $zone->name,
                    'address' => $data['delivery_address'] ?? null,
                    'total_orders_count' => 1,
                    'total_spent_fcfa' => $total,
                    'last_order_at' => now(),
                ]);
            } else {
                $customer->update([
                    'name' => $data['customer_name'],
                    'email' => $data['customer_email'] ?? $customer->email,
                    'address' => $data['delivery_address'] ?? $customer->address,
                    'total_orders_count' => $customer->total_orders_count + 1,
                    'total_spent_fcfa' => $customer->total_spent_fcfa + $total,
                    'last_order_at' => now(),
                ]);
            }

            // 3. Création de la commande (CDC E-01, D-05)
            $orderNumber = Order::generateOrderNumber($shop);

            $order = Order::withoutGlobalScopes()->create([
                'shop_id' => $shop->id,
                'customer_id' => $customer->id,
                'order_number' => $orderNumber,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $phone,
                'customer_email' => $data['customer_email'] ?? null,
                'shipping_zone_id' => $zone->id,
                'delivery_zone_name' => $zone->name,
                'delivery_address' => $zone->is_pickup ? 'Retrait en boutique' : ($data['delivery_address'] ?? 'Non spécifiée'),
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'payment_method' => $data['payment_method'],
                'payment_status' => Order::PAYMENT_STATUS_PENDING,
                'status' => Order::STATUS_PENDING,
            ]);

            // 4. Création des lignes de commande et décrémentation des stocks (CDC C-04, E-01)
            foreach ($cart as $productId => $item) {
                $lineTotal = (int) $item['price'] * (int) $item['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'product_title' => $item['title'],
                    'unit_price' => (int) $item['price'],
                    'quantity' => (int) $item['quantity'],
                    'total_price' => $lineTotal,
                ]);

                // Décrémentation du stock avec traçabilité
                /** @var Product|null $product */
                $product = Product::withoutGlobalScopes()->find($productId);
                if ($product && $product->track_stock) {
                    $product->decrement('stock', $item['quantity']);
                    $newStock = $product->fresh()->stock;

                    StockMovement::withoutGlobalScopes()->create([
                        'product_id' => $product->id,
                        'user_id' => null,
                        'quantity_change' => -1 * abs((int) $item['quantity']),
                        'new_stock' => $newStock,
                        'reason' => 'order',
                        'comment' => "Vente commande #{$order->order_number}",
                    ]);
                }
            }

            // 5. Initialisation du paiement (CDC Module F)
            $paymentRef = 'PAY-' . date('Ymd') . '-' . strtoupper(Str::random(8));
            Payment::withoutGlobalScopes()->create([
                'shop_id' => $shop->id,
                'order_id' => $order->id,
                'payment_reference' => $paymentRef,
                'method' => $data['payment_method'],
                'amount' => $total,
                'currency' => 'XOF',
                'status' => Payment::STATUS_PENDING,
                'payer_phone' => $phone,
                'expires_at' => now()->addMinutes(15), // CDC F-01 : compte à rebours 15 min
            ]);

            // 6. Historique initial d'audit
            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => Order::STATUS_PENDING,
                'author_type' => 'customer',
                'comment' => "Commande passée par {$order->customer_name} via la vitrine.",
            ]);

            // 7. Vidage du panier
            $this->cartService->clear($shop);

            return $order;
        });

        // 8. Notification WhatsApp automatique du commerçant (CDC Module D/E)
        try {
            $this->whatsAppService->sendOrderAlertToMerchant($order);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Notification WhatsApp échouée pour #{$order->order_number} : " . $e->getMessage());
        }

        return $order;
    }

    /**
     * Annule une commande et restitue les stocks automatiquement (CDC E-05)
     *
     * @throws Exception
     */
    public function cancelOrder(Order $order, string $reason, ?User $user = null): void
    {
        if (! $order->canBeCancelled()) {
            throw new Exception("Cette commande ne peut plus être annulée car son statut est déjà « {$order->status_label} ».");
        }

        DB::transaction(function () use ($order, $reason, $user) {
            $fromStatus = $order->status;

            // Restitution automatique du stock pour chaque article
            foreach ($order->items as $item) {
                $product = Product::withoutGlobalScopes()->find($item->product_id);
                if ($product && $product->track_stock) {
                    $product->increment('stock', $item->quantity);
                    $newStock = $product->fresh()->stock;

                    StockMovement::withoutGlobalScopes()->create([
                        'product_id' => $product->id,
                        'user_id' => $user?->id,
                        'quantity_change' => (int) $item->quantity,
                        'new_stock' => $newStock,
                        'reason' => 'cancellation',
                        'comment' => "Restitution stock - Annulation #{$order->order_number} : {$reason}",
                    ]);
                }
            }

            // Mise à jour de la commande
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            // Tracement audit (CDC E-02)
            $order->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => Order::STATUS_CANCELLED,
                'author_type' => $user ? 'merchant' : 'system',
                'author_id' => $user?->id,
                'comment' => "Motif d'annulation : {$reason}",
            ]);
        });
    }

    /**
     * Confirme un paiement Mobile Money (simulation ou webhook agrégateur, CDC F-01)
     */
    public function confirmPayment(Order $order, string $operatorReference = 'TXN-AUTO'): void
    {
        DB::transaction(function () use ($order, $operatorReference) {
            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'paid_at' => now(),
                'status' => Order::STATUS_CONFIRMED,
            ]);

            /** @var Payment|null $payment */
            $payment = $order->payments()->latest()->first();
            if ($payment) {
                $payment->update([
                    'status' => Payment::STATUS_PAID,
                    'paid_at' => now(),
                    'operator_reference' => $operatorReference,
                ]);
            }

            $order->statusHistories()->create([
                'from_status' => Order::STATUS_PENDING,
                'to_status' => Order::STATUS_CONFIRMED,
                'author_type' => 'system',
                'comment' => "Paiement confirmé par l'opérateur (Réf: {$operatorReference}).",
            ]);
        });
    }
}
