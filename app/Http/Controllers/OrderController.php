<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\WhatsAppNotificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected WhatsAppNotificationService $whatsAppService
    ) {}

    /**
     * E-01 : Liste des commandes avec filtres et compteurs
     */
    public function index(Request $request)
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('dashboard');
        }

        $query = Order::with(['items', 'customer'])->latest();

        // Compteurs par statut
        $counts = [
            'all' => Order::count(),
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'confirmed' => Order::where('status', Order::STATUS_CONFIRMED)->count(),
            'shipped' => Order::where('status', Order::STATUS_SHIPPED)->count(),
            'delivered' => Order::where('status', Order::STATUS_DELIVERED)->count(),
            'cancelled' => Order::where('status', Order::STATUS_CANCELLED)->count(),
        ];

        // Filtre par statut
        $status = $request->input('statut');
        if ($status && in_array($status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        }

        // Filtre par moyen de paiement
        if ($method = $request->input('paiement')) {
            $query->where('payment_method', $method);
        }

        // Recherche par numéro de commande, nom ou téléphone client
        $search = trim((string) $request->input('q'));
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(15)->withQueryString();

        return view('orders.index', compact('shop', 'orders', 'counts', 'status', 'search'));
    }

    /**
     * Polling temps réel pour les alertes sonores et notifications de nouvelles commandes
     */
    public function checkNewOrders(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;
        if (! $shop) {
            return response()->json([
                'has_new' => false,
                'count' => 0,
                'latest_id' => 0,
                'pending_total' => 0,
                'order' => null,
            ]);
        }

        $sinceId = (int) $request->query('since_id', 0);

        $query = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id);

        if ($sinceId > 0) {
            $query->where('id', '>', $sinceId);
        } else {
            // Premier chargement : commandes passées au cours des 2 dernières minutes
            $query->where('created_at', '>=', now()->subMinutes(2));
        }

        $newOrders = $query->orderBy('id', 'desc')->with('items')->get();
        $latest = $newOrders->first();

        $pendingTotal = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Order::STATUS_PENDING)
            ->count();

        $highestId = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->max('id') ?? 0;

        return response()->json([
            'has_new' => $newOrders->isNotEmpty(),
            'count' => $newOrders->count(),
            'latest_id' => $highestId,
            'pending_total' => $pendingTotal,
            'order' => $latest ? [
                'id' => $latest->id,
                'order_number' => $latest->order_number,
                'customer_name' => $latest->customer_name,
                'customer_phone' => $latest->customer_phone,
                'total' => $latest->total,
                'total_formatted' => number_format($latest->total, 0, ',', ' ') . ' FCFA',
                'delivery_zone' => $latest->delivery_zone_name,
                'items_count' => $latest->items->sum('quantity'),
                'url' => route('orders.show', $latest),
                'created_at' => $latest->created_at->format('H:i'),
            ] : null,
        ]);
    }

    /**
     * E-02 : Détail complet et traitement d'une commande
     */
    public function show(Request $request, Order $order)
    {
        $shop = $request->user()->shop;

        // Isolation de la boutique (RG-02)
        if ($order->shop_id !== $shop->id) {
            abort(403);
        }

        $order->load(['items.product', 'customer', 'payments', 'statusHistories', 'shippingZone']);

        // Formatage du lien WhatsApp client
        $phoneDigits = preg_replace('/\D+/', '', $order->customer_phone);
        if (str_starts_with($phoneDigits, '00')) {
            $phoneDigits = substr($phoneDigits, 2);
        }
        if (strlen($phoneDigits) === 8) {
            $phoneDigits = '228' . $phoneDigits;
        }

        $waMessage = urlencode("Bonjour {$order->customer_name}, c'est votre boutique {$shop->name} concernant votre commande {$order->order_number}.");
        $customerWaUrl = $phoneDigits ? "https://wa.me/{$phoneDigits}?text={$waMessage}" : null;

        // Liens et message WhatsApp commerçant / livreur
        $merchantWaUrl = $this->whatsAppService->getMerchantWhatsAppUrl($order);
        $orderAlertText = $this->whatsAppService->formatMerchantAlert($order);

        return view('orders.show', compact('shop', 'order', 'customerWaUrl', 'merchantWaUrl', 'orderAlertText'));
    }

    /**
     * Changement de statut (CDC E-02 : pending -> confirmed -> shipped -> delivered)
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $shop = $request->user()->shop;
        if ($order->shop_id !== $shop->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,shipped,delivered'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        $order->recordStatusChange(
            $validated['status'],
            'merchant',
            $request->user()->id,
            $validated['comment'] ?? "Statut mis à jour par {$request->user()->name}"
        );

        return back()->with('status', "Le statut de la commande a été mis à jour vers « {$order->fresh()->status_label} ».");
    }

    /**
     * Marque le paiement comme reçu (ex: paiement à la livraison)
     */
    public function markAsPaid(Request $request, Order $order): RedirectResponse
    {
        $shop = $request->user()->shop;
        if ($order->shop_id !== $shop->id) {
            abort(403);
        }

        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'paid_at' => now(),
        ]);

        /** @var \App\Models\Payment|null $payment */
        $payment = $order->payments()->latest()->first();
        if ($payment) {
            $payment->update([
                'status' => \App\Models\Payment::STATUS_PAID,
                'paid_at' => now(),
            ]);
        }

        $order->statusHistories()->create([
            'from_status' => $order->status,
            'to_status' => $order->status,
            'author_type' => 'merchant',
            'author_id' => $request->user()->id,
            'comment' => 'Paiement encaissé et validé par le commerçant.',
        ]);

        return back()->with('status', 'Paiement marqué comme encaissé.');
    }

    /**
     * E-05 : Annulation d'une commande avec motif obligatoire et restitution des stocks
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $shop = $request->user()->shop;
        if ($order->shop_id !== $shop->id) {
            abort(403);
        }

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:255'],
        ], [
            'cancellation_reason.required' => 'Le motif d\'annulation est obligatoire (CDC E-05).',
        ]);

        try {
            $this->orderService->cancelOrder($order, $validated['cancellation_reason'], $request->user());

            return back()->with('status', 'La commande a été annulée et les articles ont été automatiquement restitués au stock.');

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * E-01 : Export CSV des commandes filtrées
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $shop = $request->user()->shop;
        $orders = Order::with(['items'])->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="commandes-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Numéro Commande', 'Date', 'Nom Client', 'Téléphone', 'Zone Livraison',
                'Adresse', 'Articles', 'Sous-total (FCFA)', 'Livraison (FCFA)', 'Total (FCFA)',
                'Moyen Paiement', 'Statut Paiement', 'Statut Commande',
            ], ';');

            foreach ($orders as $o) {
                $itemsDesc = $o->items->map(fn ($it) => "{$it->quantity}x {$it->product_title}")->join(' | ');

                fputcsv($handle, [
                    $o->order_number,
                    $o->created_at->format('d/m/Y H:i'),
                    $o->customer_name,
                    $o->customer_phone,
                    $o->delivery_zone_name,
                    $o->delivery_address,
                    $itemsDesc,
                    $o->subtotal,
                    $o->shipping_cost,
                    $o->total,
                    $o->payment_method_label,
                    $o->payment_status_label,
                    $o->status_label,
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
