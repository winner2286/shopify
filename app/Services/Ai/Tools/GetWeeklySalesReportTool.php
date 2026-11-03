<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetWeeklySalesReportTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_weekly_sales_report';
    }

    public function description(): string
    {
        return 'Génère un rapport d\'activité complet des ventes de la semaine (7 derniers jours) : CA global, panier moyen, volume de commandes, état des livraisons et recommandations prioritaires.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(Shop $shop, array $arguments, ?User $user = null): array
    {
        $now = Carbon::now();
        $start = $now->copy()->subDays(6)->startOfDay();
        $end = $now->copy()->endOfDay();

        $orders = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalOrders = $orders->count();
        $validOrders = $orders->whereNotIn('status', [Order::STATUS_CANCELLED]);
        $revenue = (int) $validOrders->sum('total');
        $averageBasket = $validOrders->count() > 0 ? (int) round($revenue / $validOrders->count()) : 0;

        $deliveredCount = $orders->where('status', Order::STATUS_DELIVERED)->count();
        $pendingCount = $orders->where('status', Order::STATUS_PENDING)->count();
        $shippedCount = $orders->where('status', Order::STATUS_SHIPPED)->count();
        $cancelledCount = $orders->where('status', Order::STATUS_CANCELLED)->count();

        // Top 3 articles vendus sur la semaine
        $topArticles = OrderItem::withoutGlobalScopes()
            ->whereHas('order', function ($q) use ($shop, $start, $end) {
                $q->where('shop_id', $shop->id)
                  ->whereBetween('created_at', [$start, $end])
                  ->whereNotIn('status', [Order::STATUS_CANCELLED]);
            })
            ->select('product_title', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_price) as total_revenue'))
            ->groupBy('product_title')
            ->orderByDesc('total_qty')
            ->take(3)
            ->get()
            ->map(fn($item) => [
                'title' => $item->product_title,
                'quantity' => (int) $item->total_qty,
                'revenue_fcfa' => (int) $item->total_revenue,
            ])
            ->toArray();

        return [
            'status' => 'success',
            'period' => "Du " . $start->format('d/m') . " au " . $end->format('d/m/Y'),
            'revenue_fcfa' => $revenue,
            'orders_count' => $totalOrders,
            'average_basket_fcfa' => $averageBasket,
            'breakdown_by_status' => [
                'delivered' => $deliveredCount,
                'shipped' => $shippedCount,
                'pending' => $pendingCount,
                'cancelled' => $cancelledCount,
            ],
            'top_products' => $topArticles,
            'summary' => "Chiffre d'affaires de la semaine : " . number_format($revenue, 0, ',', ' ') . " {$shop->currency} sur {$totalOrders} commande(s) (panier moyen: " . number_format($averageBasket, 0, ',', ' ') . " {$shop->currency}).",
        ];
    }
}
