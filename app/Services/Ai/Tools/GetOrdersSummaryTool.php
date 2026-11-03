<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use Carbon\Carbon;

class GetOrdersSummaryTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_orders_summary';
    }

    public function description(): string
    {
        return 'Fournit une synthèse complète des commandes, du chiffre d\'affaires et du suivi Cash on Delivery (COD) sur une période (aujourd\'hui, cette semaine, ce mois ou tout).';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['today', 'yesterday', 'this_week', 'this_month', 'all'],
                    'description' => 'Période d\'analyse (défaut: this_week).',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['all', 'pending', 'confirmed', 'shipped', 'delivered', 'cancelled'],
                    'description' => 'Filtrer par statut spécifique.',
                ],
            ],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(Shop $shop, array $arguments, ?User $user = null): array
    {
        $period = $arguments['period'] ?? 'this_week';
        $status = $arguments['status'] ?? 'all';

        $query = Order::where('shop_id', $shop->id);

        match ($period) {
            'today' => $query->whereDate('created_at', Carbon::today()),
            'yesterday' => $query->whereDate('created_at', Carbon::yesterday()),
            'this_week' => $query->where('created_at', '>=', Carbon::now()->startOfWeek()),
            'this_month' => $query->where('created_at', '>=', Carbon::now()->startOfMonth()),
            default => null,
        };

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->with('items')->get();

        $totalOrders = $orders->count();
        $deliveredOrders = $orders->where('status', Order::STATUS_DELIVERED);
        $pendingOrders = $orders->where('status', Order::STATUS_PENDING);
        $shippedOrders = $orders->where('status', Order::STATUS_SHIPPED);
        $cancelledOrders = $orders->where('status', Order::STATUS_CANCELLED);

        // Chiffre d'affaires encaissé (commandes payées ou livrées)
        $revenueDelivered = $deliveredOrders->sum('total');
        $potentialRevenuePending = $orders->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_SHIPPED])->sum('total');

        // Spécificités Cash on Delivery (COD)
        $codOrders = $orders->where('payment_method', Order::PAYMENT_CASH);
        $codPendingCash = $codOrders->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_SHIPPED])->sum('total');

        $cancellationRate = $totalOrders > 0
            ? round(($cancelledOrders->count() / $totalOrders) * 100, 1)
            : 0;

        return [
            'period' => $period,
            'currency' => $shop->currency,
            'summary' => [
                'total_orders' => $totalOrders,
                'revenue_delivered_fcfa' => $revenueDelivered,
                'potential_revenue_fcfa' => $potentialRevenuePending,
                'cancellation_rate_percent' => $cancellationRate,
            ],
            'by_status' => [
                'pending' => $pendingOrders->count(),
                'confirmed' => $orders->where('status', Order::STATUS_CONFIRMED)->count(),
                'shipped' => $shippedOrders->count(),
                'delivered' => $deliveredOrders->count(),
                'cancelled' => $cancelledOrders->count(),
            ],
            'cod_metrics' => [
                'total_cod_orders' => $codOrders->count(),
                'cod_pending_cash_fcfa' => $codPendingCash,
                'cod_shipped_in_transit' => $codOrders->where('status', Order::STATUS_SHIPPED)->count(),
            ],
            'urgent_action_needed' => [
                'pending_orders_to_confirm' => $pendingOrders->map(fn($o) => [
                    'order_number' => $o->order_number,
                    'customer' => $o->customer_name,
                    'phone' => $o->customer_phone,
                    'total' => $o->total,
                    'created_at' => $o->created_at->diffForHumans(),
                ])->take(5)->values()->all(),
            ],
        ];
    }
}
