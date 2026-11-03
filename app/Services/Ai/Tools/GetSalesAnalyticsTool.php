<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetSalesAnalyticsTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_sales_analytics';
    }

    public function description(): string
    {
        return 'Analyse détaillée des performances commerciales de la boutique : Chiffre d\'affaires réel, panier moyen, top articles vendus, taux de livraison, taux d\'annulation et diagnostic.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days' => [
                    'type' => 'integer',
                    'description' => 'Nombre de jours d\'historique à analyser (7, 14, 30, ou 90 jours. Défaut 30).',
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
        $days = min(max((int) ($arguments['days'] ?? 30), 1), 180);
        $since = Carbon::now()->subDays($days);

        $orders = Order::where('shop_id', $shop->id)
            ->where('created_at', '>=', $since)
            ->get();

        $totalOrdersCount = $orders->count();
        $deliveredOrders = $orders->where('status', Order::STATUS_DELIVERED);
        $cancelledOrders = $orders->where('status', Order::STATUS_CANCELLED);
        $inProgressOrders = $orders->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_SHIPPED]);

        $revenueEncaissé = $deliveredOrders->sum('total');
        $potentialRevenue = $inProgressOrders->sum('total');

        $averageBasket = $deliveredOrders->count() > 0
            ? round($revenueEncaissé / $deliveredOrders->count())
            : ($totalOrdersCount > 0 ? round($orders->sum('total') / $totalOrdersCount) : 0);

        $deliverySuccessRate = $totalOrdersCount > 0
            ? round(($deliveredOrders->count() / $totalOrdersCount) * 100, 1)
            : 0;

        $cancellationRate = $totalOrdersCount > 0
            ? round(($cancelledOrders->count() / $totalOrdersCount) * 100, 1)
            : 0;

        // Répartition par moyen de paiement
        $paymentDistribution = $orders->groupBy('payment_method')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_fcfa' => $group->sum('total'),
            ];
        });

        // Top 5 des produits les plus vendus
        $orderIds = $orders->pluck('id');
        $topProducts = OrderItem::whereIn('order_id', $orderIds)
            ->select('product_title', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_price) as total_sales'))
            ->groupBy('product_title')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        return [
            'analyzed_period_days' => $days,
            'currency' => $shop->currency,
            'kpis' => [
                'revenue_collected_fcfa' => $revenueEncaissé,
                'potential_revenue_in_delivery_fcfa' => $potentialRevenue,
                'total_orders' => $totalOrdersCount,
                'delivered_orders' => $deliveredOrders->count(),
                'in_progress_orders' => $inProgressOrders->count(),
                'cancelled_orders' => $cancelledOrders->count(),
                'average_basket_fcfa' => $averageBasket,
                'delivery_success_rate_percent' => $deliverySuccessRate,
                'cancellation_rate_percent' => $cancellationRate,
            ],
            'top_selling_products' => $topProducts->map(fn($tp) => [
                'product' => $tp->product_title,
                'units_sold' => (int) $tp->total_qty,
                'sales_fcfa' => (int) $tp->total_sales,
            ])->all(),
            'payment_channels' => $paymentDistribution->all(),
        ];
    }
}
