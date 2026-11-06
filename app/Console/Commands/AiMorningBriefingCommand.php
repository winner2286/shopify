<?php

namespace App\Console\Commands;

use App\Models\AiProactiveInsight;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Ai\AiProactiveAdvisorService;
use App\Services\Ai\AiShopMemoryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AiMorningBriefingCommand extends Command
{
    /**
     * Nom et signature de la commande console.
     */
    protected $signature = 'ai:morning-briefing
                            {--shop= : ID ou sous-domaine spécifique d\'une boutique}
                            {--dry-run : Exécute le calcul et l\'affichage sans enregistrer en base de données}';

    /**
     * Description de la commande console.
     */
    protected $description = 'Génère le briefing matinal proactif du Copilote IA Zanika (ventes de la veille, alertes stock, commandes COD à expédier, priorités du jour).';

    public function __construct(
        protected ?AiProactiveAdvisorService $advisorService = null,
        protected ?AiShopMemoryService $memoryService = null
    ) {
        parent::__construct();
        $this->advisorService = $advisorService ?? new AiProactiveAdvisorService();
        $this->memoryService = $memoryService ?? new AiShopMemoryService();
    }

    public function handle(): int
    {
        $this->info("==========================================================");
        $this->info("   🌅 ZANIKA AI COPILOT — BRIEFING MATINAL PROACTIF     ");
        $this->info("==========================================================");

        $shopIdentifier = $this->option('shop');
        $isDryRun = (bool) $this->option('dry-run');

        $query = Shop::query();
        if ($shopIdentifier) {
            if (is_numeric($shopIdentifier)) {
                $query->where('id', (int) $shopIdentifier);
            } else {
                $query->where('subdomain', $shopIdentifier)->orWhere('slug', $shopIdentifier);
            }
        }

        $shops = $query->get();

        if ($shops->isEmpty()) {
            $this->warn("Aucune boutique trouvée pour le briefing.");
            return 0;
        }

        $processed = 0;

        foreach ($shops as $shop) {
            $this->line("\n----------------------------------------------------------");
            $this->info("🏪 Boutique : {$shop->name} (#{$shop->id} - {$shop->currency})");

            $briefing = $this->generateBriefingForShop($shop);

            // Affichage dans la console
            $this->line($briefing['formatted_console']);

            if (! $isDryRun) {
                // 1. Sauvegarde dans les insights proactifs
                AiProactiveInsight::withoutGlobalScopes()->updateOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'insight_type' => 'morning_briefing',
                        'is_resolved' => false,
                    ],
                    [
                        'severity' => $briefing['has_urgent_issues'] ? AiProactiveInsight::SEVERITY_WARNING : AiProactiveInsight::SEVERITY_INFO,
                        'title' => "Briefing matinal du " . now()->format('d/m/Y'),
                        'explanation' => $briefing['summary_text'],
                        'suggested_action' => [
                            'action' => 'review_morning_priorities',
                            'label' => "Consulter les commandes en attente et les stocks critiques",
                        ],
                        'is_dismissed' => false,
                    ]
                );

                // 2. Synchronisation de la mémoire boutique
                $this->memoryService->storeMemory(
                    $shop,
                    'past_recommendation',
                    'dernier_briefing_matinal',
                    "Briefing du " . now()->format('d/m/Y') . " : " . StrLimit($briefing['summary_text'], 140),
                    1.0,
                    'ai_morning_briefing'
                );
            }

            $processed++;
        }

        $this->info("\n==========================================================");
        $this->info("✅ Briefing matinal terminé pour {$processed} boutique(s)." . ($isDryRun ? " (Mode DRY-RUN)" : ""));
        $this->info("==========================================================");

        return 0;
    }

    /**
     * Calcule et assemble les métriques du briefing pour une boutique.
     *
     * @return array<string, mixed>
     */
    public function generateBriefingForShop(Shop $shop): array
    {
        $currency = $shop->currency ?: 'FCFA';
        $now = Carbon::now();
        $yesterdayStart = $now->copy()->subDay()->startOfDay();
        $yesterdayEnd = $now->copy()->subDay()->endOfDay();

        // 1. Ventes et activité de la veille
        $yesterdayOrders = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->whereNotIn('status', [Order::STATUS_CANCELLED])
            ->get();

        $yesterdayCount = $yesterdayOrders->count();
        $yesterdayRevenue = (int) $yesterdayOrders->sum('total');

        // 2. Commandes en attente de traitement aujourd'hui
        $pendingOrders = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Order::STATUS_PENDING)
            ->get();

        $pendingCount = $pendingOrders->count();
        $codPendingCount = $pendingOrders->where('payment_method', 'cod')->count();

        // 3. Alertes stocks
        $criticalStocks = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('track_stock', true)
            ->where(function ($q) {
                $q->where('stock', '<=', 0)
                  ->orWhereColumn('stock', '<=', 'low_stock_threshold');
            })
            ->get();

        $outOfStockCount = $criticalStocks->where('stock', '<=', 0)->count();
        $lowStockCount = $criticalStocks->where('stock', '>', 0)->count();

        // 4. Analyse proactive
        $insights = $this->advisorService->syncInsights($shop);
        $hasUrgentIssues = $outOfStockCount > 0 || $codPendingCount >= 3 || $insights->contains(fn($i) => $i->severity === AiProactiveInsight::SEVERITY_CRITICAL);

        // Assemblage des textes
        $lines = [];
        $lines[] = "📊 Ventes de la veille : {$yesterdayCount} commande(s) pour " . number_format($yesterdayRevenue, 0, ',', ' ') . " {$currency}";
        $lines[] = "📦 À expédier aujourd'hui : {$pendingCount} commande(s) en attente (dont {$codPendingCount} en Paiement à la livraison / COD)";

        if ($outOfStockCount > 0 || $lowStockCount > 0) {
            $lines[] = "⚠️  Stocks : {$outOfStockCount} produit(s) en rupture totale, {$lowStockCount} sous le seuil d'alerte";
        } else {
            $lines[] = "✅ Stocks : Tous les articles sont approvisionnés";
        }

        if ($insights->isNotEmpty()) {
            $topInsight = $insights->first();
            $lines[] = "💡 Conseil proactif du jour : {$topInsight->title}";
        }

        $summaryText = implode(" | ", $lines);
        $formattedConsole = implode("\n", $lines);

        return [
            'yesterday_count' => $yesterdayCount,
            'yesterday_revenue' => $yesterdayRevenue,
            'pending_count' => $pendingCount,
            'cod_pending_count' => $codPendingCount,
            'out_of_stock_count' => $outOfStockCount,
            'low_stock_count' => $lowStockCount,
            'has_urgent_issues' => $hasUrgentIssues,
            'summary_text' => $summaryText,
            'formatted_console' => $formattedConsole,
            'currency' => $currency,
        ];
    }
}

// Fonction utilitaire de troncature pour le script console
function StrLimit(string $value, int $limit = 100): string
{
    return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit) . '...';
}
