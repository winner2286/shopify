<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    /**
     * I-01, I-02 : Tableau de bord des statistiques et alertes
     */
    public function index(Request $request)
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return redirect()->route('dashboard');
        }

        // Métriques globales (RG-10 : montants en FCFA)
        $totalOrdersCount = $shop->orders()->count();
        $deliveredOrdersCount = $shop->orders()->where('status', Order::STATUS_DELIVERED)->count();
        $totalRevenue = (int) $shop->orders()->where('status', '!=', Order::STATUS_CANCELLED)->sum('total');
        $averageCart = $totalOrdersCount > 0 ? (int) round($totalRevenue / $totalOrdersCount) : 0;
        $totalCustomersCount = $shop->customers()->count();

        // Évolution des ventes sur les 7 derniers jours (données pour graphique SVG léger)
        $dailySales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $amount = (int) $shop->orders()
                ->whereDate('created_at', $date)
                ->where('status', '!=', Order::STATUS_CANCELLED)
                ->sum('total');

            $dailySales[] = [
                'day' => $date->translatedFormat('D d'),
                'amount' => $amount,
            ];
        }

        $maxDaily = max(array_column($dailySales, 'amount')) ?: 1;

        // Top 5 des produits les plus vendus
        $topProducts = OrderItem::select('product_title', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_price) as total_amount'))
            ->whereHas('order', fn ($q) => $q->where('shop_id', $shop->id)->where('status', '!=', Order::STATUS_CANCELLED))
            ->groupBy('product_title')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        // Répartition par mode de paiement
        $paymentMethods = Order::select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as amount'))
            ->where('shop_id', $shop->id)
            ->groupBy('payment_method')
            ->get();

        // Alertes de stock (CDC I-02)
        $outOfStock = $shop->products()->where('track_stock', true)->where('stock', '<=', 0)->get();
        $lowStock = $shop->products()->where('track_stock', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold')->get();

        return view('analytics.index', compact(
            'shop', 'totalOrdersCount', 'deliveredOrdersCount', 'totalRevenue',
            'averageCart', 'totalCustomersCount', 'dailySales', 'maxDaily',
            'topProducts', 'paymentMethods', 'outOfStock', 'lowStock'
        ));
    }

    /**
     * I-03 : Export CSV des statistiques de ventes
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $shop = $request->user()->shop;
        $orders = $shop->orders()->with('items')->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="statistiques-ventes-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($handle, ['Date', 'N° Commande', 'Client', 'Articles', 'Total (FCFA)', 'Paiement', 'Statut'], ';');

            foreach ($orders as $o) {
                fputcsv($handle, [
                    $o->created_at->format('d/m/Y H:i'),
                    $o->order_number,
                    $o->customer_name,
                    $o->items->sum('quantity'),
                    $o->total,
                    $o->payment_method_label,
                    $o->status_label,
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
