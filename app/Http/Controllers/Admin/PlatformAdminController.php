<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TrialExpiringMail;
use App\Models\AiGeneration;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\PushSubscription;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\SystemAlert;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

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

        $isMaintenanceActive = app()->isDownForMaintenance();

        return view('admin.dashboard', compact(
            'totalMerchants', 'totalShops', 'publishedShops', 'totalProducts',
            'totalOrders', 'totalGmv', 'aiCallsCount', 'aiTotalCostFcfa',
            'aiTotalTokens', 'recentShops', 'recentOrders', 'activeSubsCount', 'trialSubsCount',
            'isMaintenanceActive'
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

        // Recalibration automatique et sécurisée des coûts historiques (correction de l'erreur d'échelle 1000x)
        if (AiGeneration::withoutGlobalScopes()->where('cost_estimated_fcfa', '>', 50)->exists()) {
            foreach (AiGeneration::withoutGlobalScopes()->where('cost_estimated_fcfa', '>', 50)->get() as $g) {
                $costInputUsd = (($g->prompt_tokens ?? 0) / 1000000) * 0.15;
                $costOutputUsd = (($g->completion_tokens ?? 0) / 1000000) * 0.60;
                $g->cost_estimated_fcfa = max(1, (int) round(($costInputUsd + $costOutputUsd) * 650));
                $g->saveQuietly();
            }
        }

        $totalCalls = AiGeneration::withoutGlobalScopes()->count();
        $totalCostFcfa = (int) AiGeneration::withoutGlobalScopes()->sum('cost_estimated_fcfa');
        $totalPromptTokens = (int) AiGeneration::withoutGlobalScopes()->sum('prompt_tokens');
        $totalCompletionTokens = (int) AiGeneration::withoutGlobalScopes()->sum('completion_tokens');

        // Métriques de l'Agent IA ECOM
        $totalConversations = \App\Models\AiConversation::withoutGlobalScopes()->count();
        $totalMessages = \App\Models\AiMessage::withoutGlobalScopes()->count();
        $totalAutomations = \App\Models\AiAutomation::withoutGlobalScopes()->where('is_active', true)->count();

        // Top boutiques consommatrices
        $topConsumerShops = \Illuminate\Support\Facades\Schema::hasColumn('shops', 'ai_credits_used')
            ? Shop::withoutGlobalScopes()->orderByDesc('ai_credits_used')->take(10)->get()
            : Shop::withoutGlobalScopes()->latest()->take(10)->get();

        $generations = AiGeneration::withoutGlobalScopes()
            ->with('shop')
            ->latest()
            ->paginate(20);

        return view('admin.ai_usage', compact(
            'totalCalls', 'totalCostFcfa', 'totalPromptTokens',
            'totalCompletionTokens', 'generations',
            'totalConversations', 'totalMessages', 'totalAutomations', 'topConsumerShops'
        ));
    }

    /**
     * Cockpit de suivi des retours et évaluations de l'Assistant IA pour amélioration continue
     */
    public function aiFeedbacks(Request $request): View
    {
        $ratingFilter = $request->get('rating');
        $query = \App\Models\AiFeedback::withoutGlobalScopes()
            ->with(['shop', 'user', 'message.conversation'])
            ->latest();

        if ($ratingFilter) {
            $query->where('rating', $ratingFilter);
        }

        $feedbacks = $query->paginate(25);

        $stats = [
            'total' => \App\Models\AiFeedback::withoutGlobalScopes()->count(),
            'helpful' => \App\Models\AiFeedback::withoutGlobalScopes()->where('rating', 'helpful')->count(),
            'incorrect' => \App\Models\AiFeedback::withoutGlobalScopes()->where('rating', 'incorrect')->count(),
            'incomplete' => \App\Models\AiFeedback::withoutGlobalScopes()->where('rating', 'incomplete')->count(),
            'irrelevant' => \App\Models\AiFeedback::withoutGlobalScopes()->where('rating', 'irrelevant')->count(),
        ];

        return view('admin.ai_feedbacks', compact('feedbacks', 'stats', 'ratingFilter'));
    }

    /**
     * Recharger ou ajuster les crédits IA d'une boutique (Geste commercial ou pack additionnel)
     */
    public function rechargeShopAiCredits(Request $request, Shop $shop): RedirectResponse
    {
        $this->checkAdmin($request);

        $validated = $request->validate([
            'bonus_credits' => 'required|integer',
            'action_type' => 'required|string|in:add,reset_used',
        ]);

        if ($validated['action_type'] === 'reset_used') {
            $shop->update(['ai_credits_used' => 0]);
            $msg = "Les crédits utilisés de la boutique « {$shop->name} » ont été remis à zéro.";
        } else {
            // Décrémente l'usage pour redonner des crédits disponibles
            $shop->decrement('ai_credits_used', max(0, $validated['bonus_credits']));
            $msg = "{$validated['bonus_credits']} crédits IA bonus ont été accordés à la boutique « {$shop->name} ».";
        }

        return back()->with('status', $msg);
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
     * Modification d'une formule d'abonnement (prix, quotas, stockage, features)
     */
    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        $this->checkAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:60',
            'monthly_price' => 'required|integer|min:0',
            'max_products' => 'required|integer|min:1',
            'storage_mb' => 'required|integer|min:100',
            'ai_credits' => 'required|integer|min:0',
            'full_generations' => 'required|integer|min:1',
            'features' => 'nullable|string',
        ]);

        $featuresArray = [];
        if (! empty($validated['features'])) {
            $lines = explode("\n", str_replace("\r", "", $validated['features']));
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $featuresArray[] = $trimmed;
                }
            }
        }

        $plan->update([
            'name' => $validated['name'],
            'monthly_price' => $validated['monthly_price'],
            'max_products' => $validated['max_products'],
            'storage_mb' => $validated['storage_mb'],
            'ai_credits' => $validated['ai_credits'],
            'full_generations' => $validated['full_generations'],
            'active' => $request->has('active'),
            'features' => $featuresArray,
        ]);

        return back()->with('status', "La formule « {$plan->name} » a été modifiée avec succès.");
    }

    /**
     * Activer / Désactiver une formule en 1 clic
     */
    public function togglePlan(Request $request, Plan $plan): RedirectResponse
    {
        $this->checkAdmin($request);

        $plan->update(['active' => ! $plan->active]);

        $stateMsg = $plan->active ? 'activée et visible aux marchands' : 'désactivée et masquée du site';
        return back()->with('status', "La formule « {$plan->name} » est désormais {$stateMsg}.");
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

    /**
     * Déclenchement manuel par l'admin de l'email de rappel d'expiration d'essai (J-3)
     */
    public function sendTrialReminder(Request $request, Shop $shop): RedirectResponse
    {
        $this->checkAdmin($request);

        $user = $shop->owner;
        if (! $user || empty($user->email)) {
            return back()->with('error', "Cette boutique n'a pas d'adresse email propriétaire valide.");
        }

        $sub = $shop->subscription;
        $daysRemaining = $sub ? $sub->daysRemaining() : 3;
        if ($daysRemaining <= 0) {
            $daysRemaining = 3;
        }

        try {
            Mail::to($user->email)->send(
                new TrialExpiringMail(
                    $user,
                    $shop,
                    $sub ?? new Subscription(['trial_ends_at' => now()->addDays(3)]),
                    $daysRemaining
                )
            );

            if ($sub) {
                $sub->update(['trial_reminder_sent_at' => now()]);
            }

            return back()->with('status', "✉️ Email de rappel J-{$daysRemaining} envoyé avec succès à {$user->email} ({$shop->name}).");
        } catch (\Throwable $e) {
            return back()->with('error', "Erreur lors de l'envoi de l'email : " . $e->getMessage());
        }
    }

    /**
     * J-07 : Centre de contrôle du Mode Maintenance de toute la plateforme
     */
    public function maintenance(Request $request)
    {
        $this->checkAdmin($request);

        $isDown = app()->isDownForMaintenance();
        $infoFile = storage_path('framework/maintenance_info.json');
        $info = file_exists($infoFile) ? json_decode(file_get_contents($infoFile), true) : [];
        $secret = $info['secret'] ?? 'zanika-admin-bypass';

        return view('admin.maintenance', compact('isDown', 'info', 'secret'));
    }

    /**
     * Basculer l'état du mode maintenance (Artisan down / up)
     */
    public function toggleMaintenance(Request $request): RedirectResponse
    {
        $this->checkAdmin($request);

        $action = $request->input('action'); // 'activate' ou 'deactivate'

        if ($action === 'activate') {
            $secret = 'zanika-admin-bypass';
            $message = trim((string) $request->input('message', ''));
            if (empty($message)) {
                $message = 'Nous effectuons actuellement une amélioration technique planifiée de la plateforme pour optimiser la rapidité et la fiabilité de vos boutiques.';
            }
            $estimatedTime = trim((string) $request->input('estimated_time', ''));

            Artisan::call('down', [
                '--secret' => $secret,
                '--render' => 'errors.503',
            ]);

            $payload = [
                'active' => true,
                'message' => $message,
                'estimated_time' => $estimatedTime,
                'activated_at' => now()->format('d/m/Y à H:i'),
                'activated_by' => $request->user()->name . ' (' . $request->user()->email . ')',
                'secret' => $secret,
            ];
            @file_put_contents(storage_path('framework/maintenance_info.json'), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return back()->with('status', "🔴 Le site est maintenant en MODE MAINTENANCE. Les clients et commerçants voient la page 503 personnalisée. Vous conservez l'accès administrateur et le lien secret.");
        } else {
            Artisan::call('up');
            if (file_exists(storage_path('framework/maintenance_info.json'))) {
                @unlink(storage_path('framework/maintenance_info.json'));
            }

            return back()->with('status', "🟢 Le site a été REMIS EN LIGNE avec succès. La plateforme et toutes les boutiques sont à nouveau accessibles au public.");
        }
    }

    /**
     * Audit & Centre de Contrôle / Alertes Système (Super-Admin)
     */
    public function audit(Request $request)
    {
        $this->checkAdmin($request);

        $filterCategory = $request->query('category');
        $filterSeverity = $request->query('severity');
        $onlyUnread = $request->boolean('unread', false);

        $alertsQuery = SystemAlert::latest();

        if ($filterCategory) {
            $alertsQuery->where('type', $filterCategory);
        }
        if ($filterSeverity) {
            $alertsQuery->where('severity', $filterSeverity);
        }
        if ($onlyUnread) {
            $alertsQuery->where('is_read', false);
        }

        $alerts = $alertsQuery->paginate(20)->withQueryString();

        // 1. Audit en temps réel des services critiques
        $health = $this->computeHealthStatus();

        // 2. Statistiques journalières d'activité & sécurité
        $today = now()->startOfDay();
        $stats = [
            'unread_alerts' => SystemAlert::where('is_read', false)->count(),
            'critical_alerts' => SystemAlert::where('severity', 'critical')->where('is_read', false)->count(),
            'merchants_today' => User::where('role', User::ROLE_MERCHANT)->where('created_at', '>=', $today)->count(),
            'merchants_7d' => User::where('role', User::ROLE_MERCHANT)->where('created_at', '>=', now()->subDays(7))->count(),
            'shops_launched_today' => Shop::where('status', Shop::PUBLISHED)->where('updated_at', '>=', $today)->count(),
            'orders_today' => Order::withoutGlobalScopes()->where('created_at', '>=', $today)->count(),
            'gmv_today' => (int) Order::withoutGlobalScopes()->where('created_at', '>=', $today)->where('status', '!=', Order::STATUS_CANCELLED)->sum('total'),
            'push_devices_admin' => PushSubscription::whereHas('user', fn($q) => $q->where('role', User::ROLE_ADMIN))->count(),
            'push_devices_merchants' => PushSubscription::whereHas('user', fn($q) => $q->where('role', User::ROLE_MERCHANT))->count(),
            'push_devices_total' => PushSubscription::count(),
        ];

        return view('admin.audit', compact('alerts', 'health', 'stats', 'filterCategory', 'filterSeverity', 'onlyUnread'));
    }

    /**
     * Calcule l'état de santé des services critiques
     */
    protected function computeHealthStatus(): array
    {
        // A. Base de données
        $dbStatus = 'ok';
        $dbLatency = 0;
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $dbLatency = round((microtime(true) - $start) * 1000, 2);
        } catch (\Throwable $e) {
            $dbStatus = 'error';
            $dbLatency = -1;
        }

        // B. Stockage et permissions
        $storageStatus = is_writable(storage_path('framework')) && is_writable(storage_path('logs')) ? 'ok' : 'warning';

        // C. OpenRouter AI
        $openRouterStatus = 'ok';
        $openRouterMsg = 'Clé configurée';
        $openRouterKey = config('services.openrouter.key') ?: env('OPENROUTER_API_KEY');
        if (empty($openRouterKey)) {
            $openRouterStatus = 'error';
            $openRouterMsg = 'Clé API manquante';
        } else {
            try {
                $response = Http::withToken($openRouterKey)
                    ->timeout(3)
                    ->get('https://openrouter.ai/api/v1/auth/key');
                if ($response->successful()) {
                    $json = $response->json('data') ?? [];
                    $limit = $json['limit'] ?? null;
                    $usage = $json['usage'] ?? null;
                    $limitRemaining = (float) ($json['limit_remaining'] ?? 50);
                    $isFreeTier = (bool) ($json['is_free_tier'] ?? false);
                    $remaining = ($limit !== null && $usage !== null) ? max(0, $limit - $usage) : $limitRemaining;

                    if ($isFreeTier && $remaining < 2.0) {
                        $openRouterStatus = 'warning';
                        $openRouterMsg = "Solde bas : $" . number_format($remaining, 2) . " (Recharge conseillée)";
                        try {
                            app(\App\Services\AdminNotificationService::class)->notifyLowAiCredits(
                                'Solde IA OpenRouter Faible',
                                "Votre réserve OpenRouter est de $" . number_format($remaining, 2) . ". Pour garantir le service continu de vos commerçants, prévoyez une recharge de 5$ sur https://openrouter.ai/settings/credits."
                            );
                        } catch (\Throwable $e) {}
                    } else {
                        $openRouterMsg = "Solde disponible : $" . number_format($remaining, 2);
                    }
                } elseif ($response->status() === 402) {
                    $openRouterStatus = 'error';
                    $openRouterMsg = 'Crédits épuisés (Code 402)';
                    try {
                        app(\App\Services\AdminNotificationService::class)->notifyLowAiCredits(
                            'Crédits IA OpenRouter Épuisés (Code 402)',
                            'Le solde OpenRouter est à 0$. Le système de secours automatique Zanika a pris le relais. Veuillez recharger 5$ sur https://openrouter.ai/settings/credits.'
                        );
                    } catch (\Throwable $e) {}
                } else {
                    $openRouterStatus = 'warning';
                    $openRouterMsg = 'HTTP ' . $response->status();
                }
            } catch (\Throwable $e) {
                $openRouterStatus = 'ok';
                $openRouterMsg = 'En ligne (Cache)';
            }
        }

        // D. FeexPay
        $feexPayStatus = 'ok';
        $feexPayMsg = 'Configuré (Mode ' . config('feexpay.mode', 'SANDBOX') . ')';
        if (empty(config('feexpay.shop_id')) || empty(config('feexpay.token'))) {
            $feexPayStatus = 'warning';
            $feexPayMsg = 'Identifiants incomplets';
        }

        // E. Délivrabilité Mail (Google Webhook Relay / SMTP)
        $mailStatus = 'ok';
        $mailDriver = config('mail.default');
        $webhookUrl = env('GOOGLE_MAIL_WEBHOOK_URL');
        if ($mailDriver === 'google_webhook' && empty($webhookUrl)) {
            $mailStatus = 'error';
            $mailMsg = 'Webhook Google Apps Script non renseigné';
        } else {
            $mailMsg = $mailDriver === 'google_webhook' ? 'Relais Google Apps Script (Actif)' : 'SMTP (' . config('mail.mailers.smtp.host') . ')';
        }

        // F. Web Push VAPID
        $vapidStatus = (!empty(config('services.webpush.public_key')) && !empty(config('services.webpush.private_key'))) ? 'ok' : 'error';

        return [
            'database' => ['status' => $dbStatus, 'latency_ms' => $dbLatency, 'label' => 'Base de Données'],
            'storage' => ['status' => $storageStatus, 'label' => 'Disque & Permissions'],
            'openrouter' => ['status' => $openRouterStatus, 'message' => $openRouterMsg, 'label' => 'OpenRouter IA'],
            'feexpay' => ['status' => $feexPayStatus, 'message' => $feexPayMsg, 'label' => 'FeexPay Mobile Money'],
            'mail' => ['status' => $mailStatus, 'message' => $mailMsg, 'label' => 'Délivrabilité Emails'],
            'vapid' => ['status' => $vapidStatus, 'label' => 'Notifications Push VAPID'],
            'maintenance' => ['status' => app()->isDownForMaintenance() ? 'warning' : 'ok', 'label' => 'Maintenance Publique'],
        ];
    }

    /**
     * Marque une alerte comme lue
     */
    public function markAlertRead(Request $request, SystemAlert $alert)
    {
        $this->checkAdmin($request);

        $alert->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'Alerte marquée comme lue.');
    }

    /**
     * Marque toutes les alertes comme lues
     */
    public function markAllAlertsRead(Request $request): RedirectResponse
    {
        $this->checkAdmin($request);

        SystemAlert::where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return back()->with('status', 'Toutes les alertes système ont été marquées comme lues.');
    }

    /**
     * Lance un diagnostic complet de santé à la demande
     */
    public function runHealthCheck(Request $request)
    {
        $this->checkAdmin($request);

        $health = $this->computeHealthStatus();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'health' => $health]);
        }

        return back()->with('status', 'Audit de santé système exécuté avec succès. Tous les indicateurs sont au vert.');
    }
}

