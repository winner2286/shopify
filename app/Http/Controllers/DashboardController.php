<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;
        $productsCount = $shop ? $shop->products()->where('status', '!=', 'archived')->count() : 0;
        $lowStockCount = $shop ? $shop->products()->where('track_stock', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold')->count() : 0;
        $outOfStockCount = $shop ? $shop->products()->where('track_stock', true)->where('stock', '<=', 0)->count() : 0;

        $ordersCount = $shop ? $shop->orders()->count() : 0;
        $pendingOrdersCount = $shop ? $shop->orders()->where('status', 'pending')->count() : 0;
        $totalRevenue = $shop ? (int) $shop->orders()->where('status', '!=', 'cancelled')->sum('total') : 0;
        $recentOrders = $shop ? $shop->orders()->take(5)->get() : collect();

        // Checklist de démarrage (I-01) : dynamique
        $checklist = [
            ['label' => 'Créer ma boutique avec l\'IA', 'done' => $shop && $shop->name !== null],
            ['label' => 'Ajouter un premier produit', 'done' => $productsCount > 0],
            ['label' => 'Activer un moyen de paiement', 'done' => $shop !== null],
            ['label' => 'Publier et partager le lien', 'done' => $shop && $shop->status === 'published'],
            ['label' => 'Générer une publicité', 'done' => false],
        ];

        return view('dashboard', compact(
            'user', 'shop', 'checklist', 'productsCount', 'lowStockCount',
            'outOfStockCount', 'ordersCount', 'pendingOrdersCount', 'totalRevenue', 'recentOrders'
        ));
    }
}
