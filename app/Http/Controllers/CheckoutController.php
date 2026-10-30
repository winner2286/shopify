<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\OrderService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected OrderService $orderService
    ) {}

    private function resolveShop(string $subdomain): Shop
    {
        /** @var Shop|null $shop */
        $shop = Shop::where('subdomain', strtolower($subdomain))->first();

        if (! $shop) {
            abort(404, 'Boutique introuvable.');
        }

        if ($shop->status === Shop::SUSPENDED) {
            abort(403, 'Cette boutique est suspendue.');
        }

        return $shop;
    }

    /**
     * D-05 : Page de commande (Checkout)
     */
    public function show(Request $request, string $subdomain)
    {
        $shop = $this->resolveShop($subdomain);
        $cart = $this->cartService->get($shop);

        if (empty($cart)) {
            return redirect()->route('storefront.cart', $shop->subdomain)
                ->with('error', 'Votre panier est vide. Veuillez ajouter des articles avant de commander.');
        }

        // Assure que les zones de livraison types existent
        $shop->ensureDefaultShippingZones();

        $zones = $shop->shippingZones()->where('is_active', true)->get();
        $subtotal = $this->cartService->getSubtotal($shop);
        $cartCount = $this->cartService->getItemsCount($shop);

        return view('storefront.checkout', compact('shop', 'cart', 'zones', 'subtotal', 'cartCount'));
    }

    /**
     * D-05 : Traitement de la commande
     */
    public function process(Request $request, string $subdomain): RedirectResponse
    {
        $shop = $this->resolveShop($subdomain);

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:100'],
            'shipping_zone_id' => ['required', 'integer'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'in:cash_on_delivery,tmoney,flooz,card,feexpay'],
        ], [
            'customer_name.required' => 'Veuillez saisir votre nom complet.',
            'customer_phone.required' => 'Le numéro de téléphone est obligatoire pour la livraison.',
            'shipping_zone_id.required' => 'Veuillez sélectionner une zone de livraison.',
            'payment_method.required' => 'Veuillez choisir un moyen de paiement.',
        ]);

        try {
            $order = $this->orderService->createFromCart($shop, $validated);

            return redirect()->route('storefront.order.show', [
                'subdomain' => $shop->subdomain,
                'orderNumber' => $order->order_number,
            ])->with('status', 'Votre commande a été enregistrée avec succès !');

        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * D-05, F-01 : Page de confirmation et instructions de paiement
     */
    public function showOrder(Request $request, string $subdomain, string $orderNumber)
    {
        $shop = $this->resolveShop($subdomain);

        /** @var Order|null $order */
        $order = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('order_number', $orderNumber)
            ->with(['items', 'payments', 'statusHistories'])
            ->first();

        if (! $order) {
            abort(404, 'Commande introuvable.');
        }

        $payment = $order->payments->first();
        $cartCount = $this->cartService->getItemsCount($shop);

        // Lien direct WhatsApp commerçant enrichi pour confirmation
        $waUrl = app(\App\Services\WhatsAppNotificationService::class)->getCustomerToMerchantWhatsAppUrl($order);

        return view('storefront.order_confirmation', compact('shop', 'order', 'payment', 'cartCount', 'waUrl'));
    }

    /**
     * F-01 : Simulation de la validation Mobile Money (T-Money / Flooz)
     */
    public function simulateMobileMoney(Request $request, string $subdomain, string $orderNumber): RedirectResponse
    {
        $shop = $this->resolveShop($subdomain);

        /** @var Order|null $order */
        $order = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order) {
            abort(404);
        }

        $txnId = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(md5((string) microtime()), 0, 8));
        $this->orderService->confirmPayment($order, $txnId);

        return back()->with('status', 'Paiement Mobile Money validé avec succès ! Votre commande est désormais confirmée.');
    }

    /**
     * D-06 : Page de suivi de commande
     */
    public function tracking(Request $request, string $subdomain)
    {
        $shop = $this->resolveShop($subdomain);
        $order = null;
        $orderNumber = trim((string) $request->input('numero'));
        $phone = trim((string) $request->input('telephone'));

        if ($orderNumber && $phone) {
            $order = Order::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('order_number', $orderNumber)
                ->where('customer_phone', 'like', "%{$phone}%")
                ->with(['items', 'statusHistories'])
                ->first();

            if (! $order) {
                session()->flash('error', 'Aucune commande ne correspond à ce numéro et téléphone.');
            }
        }

        $cartCount = $this->cartService->getItemsCount($shop);

        return view('storefront.tracking', compact('shop', 'order', 'orderNumber', 'phone', 'cartCount'));
    }
}
