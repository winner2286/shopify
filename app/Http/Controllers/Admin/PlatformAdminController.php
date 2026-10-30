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

use App\Models\Subscription;

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

        // Métriques Abonnements
        $activeSubsCount = Subscription::where('status', 'active')->count();
        $trialSubsCount = Subscription::where('status', 'trial')->count();

        // Métriques IA globales (CDC J-04)
        $aiCallsCount = AiGeneration::withoutGlobalScopes()->count();
        $aiTotalCostFcfa = (int) AiGeneration::withoutGlobalScopes()->sum('cost_estimated_fcfa');
        $aiTotalTokens = (int) AiGeneration::withoutGlobalScopes()->sum('prompt_tokens') + (int) AiGeneration::withoutGlobalScopes()->sum('completion_tokens');

        $recentShops = Shop::with(['owner', 'subscription.plan'])->latest()->take(6)->get();
        $recentOrders = Order::withoutGlobalScopes()->with(['shop'])->latest()->take(6)->get();

        return view('admin.dashboard', compact(
            'totalMerchants', 'totalShops', 'publishedShops', 'totalProducts',
            'totalOrders', 'totalGmv', 'aiCallsCount', 'aiTotalCostFcfa',
            'aiTotalTokens', 'recentShops', 'recentOrders', 'activeSubsCount', 'trialSubsCount'
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

    /**
     * Mode Impersonation : Se connecter en 1 clic dans l'espace d'un commerçant (Support Client)
     */
    public function impersonate(Request $request, Shop $shop): RedirectResponse
    {
        $this->checkAdmin($request);

        if (! $shop->owner) {
            return back()->with('error', 'Impossible de se connecter : cette boutique n\'a pas de propriétaire.');
        }

        // Enregistrer l'ID de l'administrateur en session
        session(['admin_impersonator_id' => $request->user()->id]);

        // Connecter l'admin en tant que le marchand
        auth()->login($shop->owner);

        return redirect()->route('dashboard')->with('status', "🎛️ Mode Support Client activé pour la boutique « {$shop->name} » ({$shop->owner->name}).");
    }

    /**
     * Quitter le mode support et restaurer la session Super-Administrateur
     */
    public function leaveImpersonate(Request $request): RedirectResponse
    {
        $adminId = session('admin_impersonator_id');
        if (! $adminId) {
            return redirect()->route('dashboard');
        }

        $admin = User::find($adminId);
        if ($admin && $admin->isAdmin()) {
            auth()->login($admin);
            session()->forget('admin_impersonator_id');
            return redirect()->route('admin.shops')->with('status', "Vous avez quitté le mode support et êtes revenu sur votre espace Super-Admin.");
        }

        return redirect()->route('dashboard');
    }

    /**
     * J-06 : Suivi et gestion globale des abonnements et paiements SaaS
     */
    public function subscriptions(Request $request)
    {
        $this->checkAdmin($request);

        $plans = Plan::all();

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

        if ($statusFilter = $request->input('status')) {
            if ($statusFilter === 'trial') {
                $query->whereHas('subscription', fn ($q) => $q->where('status', 'trial'));
            } elseif ($statusFilter === 'active') {
                $query->whereHas('subscription', fn ($q) => $q->where('status', 'active'));
            } elseif ($statusFilter === 'expired') {
                $query->whereHas('subscription', fn ($q) => $q->where('status', 'expired'));
            }
        }

        $shops = $query->paginate(15)->withQueryString();

        $totalActive = Subscription::where('status', 'active')->count();
        $totalTrial = Subscription::where('status', 'trial')->count();
        $totalExpired = Subscription::where('status', 'expired')->count();

        // Chiffre d'Affaires Récurrent Mensuel (MRR) estimé
        $mrrEstimated = 0;
        foreach (Subscription::where('status', 'active')->with('plan')->get() as $sub) {
            $mrrEstimated += (int) ($sub->plan?->monthly_price ?: 0);
        }

        return view('admin.subscriptions', compact(
            'shops', 'plans', 'totalActive', 'totalTrial', 'totalExpired',
            'mrrEstimated', 'search', 'statusFilter'
        ));
    }

    /**
     * Modification manuelle de l'abonnement d'un commerçant
     */
    public function updateSubscription(Request $request, Shop $shop): RedirectResponse
    {
        $this->checkAdmin($request);

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'status' => 'required|in:trial,active,expired,cancelled',
            'extend_days' => 'nullable|integer|min:0|max:365',
        ]);

        $sub = $shop->subscription;
        if (! $sub) {
            $sub = Subscription::create([
                'shop_id' => $shop->id,
                'plan_id' => $validated['plan_id'],
                'status' => $validated['status'],
                'starts_at' => now(),
                'trial_ends_at' => now()->addDays(14),
            ]);
        } else {
            $updateData = [
                'plan_id' => $validated['plan_id'],
                'status' => $validated['status'],
            ];
            if (! empty($validated['extend_days'])) {
                $baseDate = ($sub->trial_ends_at && $sub->trial_ends_at->isFuture()) ? $sub->trial_ends_at : now();
                $updateData['trial_ends_at'] = $baseDate->copy()->addDays((int) $validated['extend_days']);
            }
            $sub->update($updateData);
        }

        return back()->with('status', "L'abonnement de la boutique « {$shop->name} » a été mis à jour avec succès.");
    }
}
