<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGeneration;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformAdminController extends Controller
{
    private function checkAdmin(Request $request): void
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Accès réservé aux administrateurs de la plateforme Zanika.');
        }
    }

    /**
     * J-01 : Tableau de bord super-admin de la plateforme
     */
    public function dashboard(Request $request)
    {
        $this->checkAdmin($request);

        $totalMerchants = User::where('role', User::ROLE_MERCHANT)->count();
        $totalShops = Shop::count();
        $publishedShops = Shop::where('status', Shop::PUBLISHED)->count();
        $totalProducts = Product::withoutGlobalScopes()->count();
        $totalOrders = Order::withoutGlobalScopes()->count();
        $totalGmv = (int) Order::withoutGlobalScopes()->where('status', '!=', Order::STATUS_CANCELLED)->sum('total');

        // Métriques IA globales (CDC J-04)
        $aiCallsCount = AiGeneration::withoutGlobalScopes()->count();
        $aiTotalCostFcfa = (int) AiGeneration::withoutGlobalScopes()->sum('cost_estimated_fcfa');
        $aiTotalTokens = (int) AiGeneration::withoutGlobalScopes()->sum('prompt_tokens') + (int) AiGeneration::withoutGlobalScopes()->sum('completion_tokens');

        $recentShops = Shop::with('owner')->latest()->take(6)->get();

        return view('admin.dashboard', compact(
            'totalMerchants', 'totalShops', 'publishedShops', 'totalProducts',
            'totalOrders', 'totalGmv', 'aiCallsCount', 'aiTotalCostFcfa',
            'aiTotalTokens', 'recentShops'
        ));
    }

    /**
     * J-02 : Annuaire et gestion des boutiques
     */
    public function shops(Request $request)
    {
        $this->checkAdmin($request);

        $query = Shop::with(['owner', 'subscription.plan'])
            ->withCount(['products', 'orders'])
            ->latest();

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%")
                  ->orWhereHas('owner', fn ($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $shops = $query->paginate(15)->withQueryString();

        return view('admin.shops', compact('shops', 'search'));
    }

    /**
     * J-02 : Suspendre ou rétablir une boutique
     */
    public function toggleSuspend(Request $request, Shop $shop): RedirectResponse
    {
        $this->checkAdmin($request);

        if ($shop->status === Shop::SUSPENDED) {
            $shop->update(['status' => Shop::PUBLISHED]);
            $msg = "La boutique « {$shop->name} » a été réactivée avec succès.";
        } else {
            $shop->update(['status' => Shop::SUSPENDED]);
            $msg = "La boutique « {$shop->name} » a été suspendue. La vitrine et l'accès sont bloqués.";
        }

        return back()->with('status', $msg);
    }

    /**
     * J-04 : Journal de consommation et d'audit IA
     */
    public function aiUsage(Request $request)
    {
        $this->checkAdmin($request);

        $totalCalls = AiGeneration::withoutGlobalScopes()->count();
        $totalCostFcfa = (int) AiGeneration::withoutGlobalScopes()->sum('cost_estimated_fcfa');
        $totalPromptTokens = (int) AiGeneration::withoutGlobalScopes()->sum('prompt_tokens');
        $totalCompletionTokens = (int) AiGeneration::withoutGlobalScopes()->sum('completion_tokens');

        $generations = AiGeneration::withoutGlobalScopes()
            ->with('shop')
            ->latest()
            ->paginate(20);

        return view('admin.ai_usage', compact(
            'totalCalls', 'totalCostFcfa', 'totalPromptTokens',
            'totalCompletionTokens', 'generations'
        ));
    }

    /**
     * J-05 : Gestion des formules d'abonnements
     */
    public function plans(Request $request)
    {
        $this->checkAdmin($request);

        $plans = Plan::withCount('subscriptions')->get();

        return view('admin.plans', compact('plans'));
    }
}
