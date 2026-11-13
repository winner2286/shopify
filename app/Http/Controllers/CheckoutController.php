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

        $appliedPromo = null;
        $discountAmount = 0;
        $sessionCode = trim((string) session("checkout_promo_{$shop->id}", ''));
        if ($sessionCode !== '') {
            $candidate = \App\Models\DiscountCode::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->whereRaw('UPPER(code) = ?', [strtoupper($sessionCode)])
                ->first();

            if ($candidate && $candidate->isValid($subtotal)) {
                $appliedPromo = $candidate;
                $discountAmount = $candidate->calculateDiscount($subtotal);
            } else {
                session()->forget("checkout_promo_{$shop->id}");
            }
        }

        return view('storefront.checkout', compact('shop', 'cart', 'zones', 'subtotal', 'cartCount', 'appliedPromo', 'discountAmount'));
    }

    /**
     * Applique un code promo en session sur le tunnel de commande
     */
    public function applyPromoCode(Request $request, string $subdomain): RedirectResponse
    {
        $shop = $this->resolveShop($subdomain);
        $subtotal = $this->cartService->getSubtotal($shop);

        $request->validate([
            'promo_code' => ['required', 'string', 'max:50'],
        ], [
            'promo_code.required' => 'Veuillez saisir un code promo.',
        ]);

        $codeInput = strtoupper(trim((string) $request->input('promo_code')));

        $discount = \App\Models\DiscountCode::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->whereRaw('UPPER(code) = ?', [$codeInput])
            ->first();

        if (! $discount || ! $discount->isValid($subtotal)) {
            session()->forget("checkout_promo_{$shop->id}");

            if ($discount && $discount->min_order_amount && $subtotal < $discount->min_order_amount) {
                $minFormatted = number_format($discount->min_order_amount, 0, ',', ' ') . ' ' . $shop->currencyLabel();
                return back()->withInput()->with('promo_error', "Minimum d'achat requis : {$minFormatted}.");
            }

            return back()->withInput()->with('promo_error', "Le code promo « {$codeInput} » est invalide, expiré ou épuisé.");
        }

        session(["checkout_promo_{$shop->id}" => $discount->code]);

        return back()->withInput()->with('promo_status', "Code « {$discount->code} » appliqué (-" . number_format($discount->calculateDiscount($subtotal), 0, ',', ' ') . ' ' . $shop->currencyLabel() . ').');
    }

    /**
     * Retire le code promo actif du panier
     */
    public function removePromoCode(Request $request, string $subdomain): RedirectResponse
    {
        $shop = $this->resolveShop($subdomain);
        session()->forget("checkout_promo_{$shop->id}");

        return back()->withInput()->with('promo_status', 'Code promo retiré.');
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
            'payment_method' => ['required', 'in:cash_on_delivery,tmoney,flooz,card,geniuspay,feexpay'],
            'promo_code' => ['nullable', 'string', 'max:50'],
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
            if (str_contains($e->getMessage(), 'code promo')) {
                session()->forget("checkout_promo_{$shop->id}");
            }

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
        if (! app()->environment('local', 'testing')) {
            abort(403, 'La simulation de paiement est uniquement disponible en environnement de test.');
        }

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
                ->where('customer_phone', \App\Support\SearchHelper::likeOperator(), "%{$phone}%")
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
