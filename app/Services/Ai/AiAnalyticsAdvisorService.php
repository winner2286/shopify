<?php

namespace App\Services\Ai;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Providers\GroqLlmProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiAnalyticsAdvisorService
{
    protected LlmProviderInterface $llm;

    public function __construct(?LlmProviderInterface $llm = null)
    {
        $this->llm = $llm ?? app(LlmProviderInterface::class);
    }

    /**
     * Compare les performances commerciales entre deux périodes consécutives.
     *
     * @param Shop $shop
     * @param string $period '7d', '30d', ou 'month'
     * @return array
     */
    public function comparePeriods(Shop $shop, string $period = '7d'): array
    {
        $now = Carbon::now();

        switch ($period) {
            case 'month':
                $currentStart = $now->copy()->startOfMonth();
                $currentEnd = $now->copy();
                $previousStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $previousEnd = $previousStart->copy()->addDays($currentStart->diffInDays($currentEnd));
                $label = 'Mois en cours vs Mois précédent';
                break;

            case '30d':
                $currentStart = $now->copy()->subDays(29)->startOfDay();
                $currentEnd = $now->copy()->endOfDay();
                $previousStart = $now->copy()->subDays(59)->startOfDay();
                $previousEnd = $now->copy()->subDays(30)->endOfDay();
                $label = '30 derniers jours vs 30 jours précédents';
                break;

            case '7d':
            default:
                $period = '7d';
                $currentStart = $now->copy()->subDays(6)->startOfDay();
                $currentEnd = $now->copy()->endOfDay();
                $previousStart = $now->copy()->subDays(13)->startOfDay();
                $previousEnd = $now->copy()->subDays(7)->endOfDay();
                $label = '7 derniers jours vs 7 jours précédents';
                break;
        }

        $currentData = $this->aggregatePeriodMetrics($shop, $currentStart, $currentEnd);
        $previousData = $this->aggregatePeriodMetrics($shop, $previousStart, $previousEnd);

        // Calcul des évolutions relatives (%)
        $revenueGrowth = $this->calcPercentageChange($currentData['revenue_fcfa'], $previousData['revenue_fcfa']);
        $ordersGrowth = $this->calcPercentageChange($currentData['orders_count'], $previousData['orders_count']);
        $aovGrowth = $this->calcPercentageChange($currentData['average_basket_fcfa'], $previousData['average_basket_fcfa']);
        $deliveredGrowth = $this->calcPercentageChange($currentData['delivered_count'], $previousData['delivered_count']);

        // Analyse des variations par produit (Top Gainers vs Decliners)
        $productDynamics = $this->compareProductPerformances($shop, $currentStart, $currentEnd, $previousStart, $previousEnd);

        // Diagnostic de la trajectoire
        $trajectory = $this->determineTrajectoryVerdict($revenueGrowth, $ordersGrowth, $currentData['delivered_count']);

        return [
            'period' => $period,
            'period_label' => $label,
            'current' => array_merge($currentData, [
                'start_formatted' => $currentStart->translatedFormat('d M'),
                'end_formatted' => $currentEnd->translatedFormat('d M Y'),
            ]),
            'previous' => array_merge($previousData, [
                'start_formatted' => $previousStart->translatedFormat('d M'),
                'end_formatted' => $previousEnd->translatedFormat('d M Y'),
            ]),
            'growth' => [
                'revenue_pct' => $revenueGrowth,
                'revenue_diff_fcfa' => $currentData['revenue_fcfa'] - $previousData['revenue_fcfa'],
                'orders_pct' => $ordersGrowth,
                'orders_diff' => $currentData['orders_count'] - $previousData['orders_count'],
                'aov_pct' => $aovGrowth,
                'aov_diff_fcfa' => $currentData['average_basket_fcfa'] - $previousData['average_basket_fcfa'],
                'delivered_pct' => $deliveredGrowth,
            ],
            'gainers' => $productDynamics['gainers'],
            'decliners' => $productDynamics['decliners'],
            'trajectory' => $trajectory,
        ];
    }

    /**
     * Génère un diagnostic stratégique complet (Stock dormant, Ruptures imminentes, Risque COD, Santé globale).
     *
     * @param Shop $shop
     * @param bool $withLlmAdvice Si vrai, consulte le LLM pour un avis exécutif rédigé
     * @return array
     */
    public function generateBusinessDiagnosis(Shop $shop, bool $withLlmAdvice = false): array
    {
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $fourteenDaysAgo = $now->copy()->subDays(14);

        // 1. Détection du stock dormant / capital immobilisé
        $soldProductTitlesLast30Days = OrderItem::whereHas('order', function ($q) use ($shop, $thirtyDaysAgo) {
            $q->where('shop_id', $shop->id)
                ->where('status', '!=', Order::STATUS_CANCELLED)
                ->where('created_at', '>=', $thirtyDaysAgo);
        })->pluck('product_title')->unique()->toArray();

        $activeProducts = Product::where('shop_id', $shop->id)
            ->where('status', 'active')
            ->get();

        $deadStockProducts = [];
        $sleepingCapitalTotal = 0;

        foreach ($activeProducts as $product) {
            if ($product->stock > 0 && ! in_array($product->title, $soldProductTitlesLast30Days)) {
                $itemCapital = $product->stock * $product->price;
                $sleepingCapitalTotal += $itemCapital;
                $deadStockProducts[] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'stock' => $product->stock,
                    'price' => $product->price,
                    'capital_immobilise_fcfa' => $itemCapital,
                    'suggestion' => 'Promo flash -15% ou pack duo avec produit star',
                ];
            }
        }

        // Trier par capital immobilisé décroissant
        usort($deadStockProducts, fn($a, $b) => $b['capital_immobilise_fcfa'] <=> $a['capital_immobilise_fcfa']);
        $deadStockSummary = array_slice($deadStockProducts, 0, 5);

        // 2. Détection des ruptures de stock imminentes (Best-sellers à risque)
        $recentSales = OrderItem::select('product_title', DB::raw('SUM(quantity) as qty_sold'))
            ->whereHas('order', function ($q) use ($shop, $fourteenDaysAgo) {
                $q->where('shop_id', $shop->id)
                    ->where('status', '!=', Order::STATUS_CANCELLED)
                    ->where('created_at', '>=', $fourteenDaysAgo);
            })
            ->groupBy('product_title')
            ->get()
            ->keyBy('product_title');

        $restockUrgency = [];
        foreach ($activeProducts as $product) {
            if (! $product->track_stock) {
                continue;
            }

            $sales14d = $recentSales->get($product->title)?->qty_sold ?? 0;
            $dailyVelocity = $sales14d / 14;

            $isLowStock = $product->stock <= $product->low_stock_threshold;
            $daysLeft = $dailyVelocity > 0 ? round($product->stock / $dailyVelocity, 1) : 999;

            if ($product->stock <= 0 || $isLowStock || ($dailyVelocity > 0 && $daysLeft <= 4)) {
                $urgencyLevel = $product->stock <= 0 ? 'rupture_totale' : ($product->stock <= 2 || $daysLeft <= 2 ? 'critique' : 'eleve');
                $potentialLoss7d = (int) round($dailyVelocity * 7 * $product->price);

                $restockUrgency[] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'current_stock' => $product->stock,
                    'threshold' => $product->low_stock_threshold,
                    'sold_14d' => (int) $sales14d,
                    'days_remaining_est' => $daysLeft < 999 ? $daysLeft : 0,
                    'urgency' => $urgencyLevel,
                    'potential_loss_7d_fcfa' => $potentialLoss7d,
                ];
            }
        }

        usort($restockUrgency, function ($a, $b) {
            $levels = ['rupture_totale' => 3, 'critique' => 2, 'eleve' => 1];
            return ($levels[$b['urgency']] ?? 0) <=> ($levels[$a['urgency']] ?? 0);
        });

        // 3. Analyse du risque COD (Paiement à la livraison)
        $allOrders30d = Order::where('shop_id', $shop->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->get();

        $codOrders = $allOrders30d->where('payment_method', 'cash_on_delivery');
        $prepaidOrders = $allOrders30d->where('payment_method', '!=', 'cash_on_delivery');

        $codCount = $codOrders->count();
        $codDelivered = $codOrders->where('status', Order::STATUS_DELIVERED)->count();
        $codCancelled = $codOrders->where('status', Order::STATUS_CANCELLED)->count();
        $codSuccessRate = $codCount > 0 ? round(($codDelivered / $codCount) * 100, 1) : 0;
        $codCancelRate = $codCount > 0 ? round(($codCancelled / $codCount) * 100, 1) : 0;

        $prepaidCount = $prepaidOrders->count();
        $prepaidDelivered = $prepaidOrders->where('status', Order::STATUS_DELIVERED)->count();
        $prepaidSuccessRate = $prepaidCount > 0 ? round(($prepaidDelivered / $prepaidCount) * 100, 1) : 0;

        // Frais logistiques estimés perdus sur annulations COD (frais de port moyen * annulations)
        $lostShippingOnCod = (int) $codOrders->where('status', Order::STATUS_CANCELLED)->sum('shipping_cost');

        // 3b. Analyse causale de rentabilité (Panier moyen, frais de port, remises)
        $nonCancelled30d = $allOrders30d->where('status', '!=', Order::STATUS_CANCELLED);
        $avgBasket30d = $nonCancelled30d->count() > 0 ? (int) round($nonCancelled30d->sum('total') / $nonCancelled30d->count()) : 0;
        $avgShipping30d = $nonCancelled30d->count() > 0 ? (int) round($nonCancelled30d->sum('shipping_cost') / $nonCancelled30d->count()) : 0;
        $discountedProductsCount = $activeProducts->filter(fn($p) => $p->compare_at_price && $p->compare_at_price > $p->price)->count();

        // 4. Calcul du score de santé global du business (0 à 100)
        $healthScore = $this->calculateHealthScore(
            $allOrders30d->count(),
            $codSuccessRate,
            count($restockUrgency),
            count($deadStockProducts)
        );

        $result = [
            'shop_name' => $shop->name,
            'currency' => $shop->currency,
            'health_score' => $healthScore,
            'dead_stock' => [
                'total_items_count' => count($deadStockProducts),
                'total_capital_immobilise_fcfa' => $sleepingCapitalTotal,
                'items' => $deadStockSummary,
            ],
            'restock_urgency' => [
                'critical_items_count' => count($restockUrgency),
                'items' => array_slice($restockUrgency, 0, 5),
            ],
            'cod_diagnosis' => [
                'cod_share_percent' => $allOrders30d->count() > 0 ? round(($codCount / $allOrders30d->count()) * 100, 1) : 0,
                'cod_total_orders' => $codCount,
                'cod_success_rate' => $codSuccessRate,
                'cod_cancel_rate' => $codCancelRate,
                'prepaid_success_rate' => $prepaidSuccessRate,
                'estimated_lost_shipping_fcfa' => $lostShippingOnCod,
            ],
            'profitability_diagnosis' => [
                'average_basket_fcfa' => $avgBasket30d,
                'average_shipping_charged_fcfa' => $avgShipping30d,
                'discounted_products_count' => $discountedProductsCount,
                'causes_analysis' => [
                    'shipping_margin_leak' => $avgShipping30d < 1200 ? 'Attention : frais de livraison facturés bas (< 1 200 FCFA), risque de subventionner le coursier sur votre marge.' : 'Frais de livraison cohérents.',
                    'cod_return_erosion' => $codCancelRate > 15 ? "Alerte : taux d'annulation COD élevé ({$codCancelRate}%), causant une perte logistique sèche." : 'Taux de refus COD maîtrisé.',
                    'low_aov_pressure' => $avgBasket30d > 0 && $avgBasket30d < 10000 ? 'Panier moyen faible (< 10 000 FCFA) : privilégiez les offres Pack Duo pour amortir les coûts de livraison et publicité.' : 'Panier moyen sain.',
                ],
            ],
            'strategic_verdict' => $this->getStrategicVerdictText($healthScore, $sleepingCapitalTotal, count($restockUrgency)),
        ];

        // 5. Génération d'avis IA exécutif rédigé si demandé
        if ($withLlmAdvice) {
            $result['ai_executive_briefing'] = $this->generateLlmExecutiveBriefing($shop, $result);
        }

        return $result;
    }

    /**
     * Agrège les indicateurs pour une période donnée.
     */
    protected function aggregatePeriodMetrics(Shop $shop, Carbon $start, Carbon $end): array
    {
        $orders = Order::where('shop_id', $shop->id)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalOrders = $orders->count();
        $nonCancelled = $orders->where('status', '!=', Order::STATUS_CANCELLED);
        $delivered = $orders->where('status', Order::STATUS_DELIVERED);
        $cancelled = $orders->where('status', Order::STATUS_CANCELLED);

        $revenue = (int) $nonCancelled->sum('total');
        $averageBasket = $nonCancelled->count() > 0 ? (int) round($revenue / $nonCancelled->count()) : 0;
        $deliveryRate = $nonCancelled->count() > 0 ? round(($delivered->count() / $nonCancelled->count()) * 100, 1) : 0;
        $cancellationRate = $totalOrders > 0 ? round(($cancelled->count() / $totalOrders) * 100, 1) : 0;

        return [
            'orders_count' => $totalOrders,
            'delivered_count' => $delivered->count(),
            'cancelled_count' => $cancelled->count(),
            'revenue_fcfa' => $revenue,
            'average_basket_fcfa' => $averageBasket,
            'delivery_rate_percent' => $deliveryRate,
            'cancellation_rate_percent' => $cancellationRate,
        ];
    }

    /**
     * Compare les ventes par produit entre les deux périodes pour isoler Gainers & Decliners.
     */
    protected function compareProductPerformances(Shop $shop, Carbon $curStart, Carbon $curEnd, Carbon $prevStart, Carbon $prevEnd): array
    {
        $getCurrent = OrderItem::select('product_title', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(total_price) as revenue'))
            ->whereHas('order', fn($q) => $q->where('shop_id', $shop->id)->where('status', '!=', Order::STATUS_CANCELLED)->whereBetween('created_at', [$curStart, $curEnd]))
            ->groupBy('product_title')
            ->get()
            ->keyBy('product_title');

        $getPrevious = OrderItem::select('product_title', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(total_price) as revenue'))
            ->whereHas('order', fn($q) => $q->where('shop_id', $shop->id)->where('status', '!=', Order::STATUS_CANCELLED)->whereBetween('created_at', [$prevStart, $prevEnd]))
            ->groupBy('product_title')
            ->get()
            ->keyBy('product_title');

        $allTitles = $getCurrent->keys()->merge($getPrevious->keys())->unique();

        $diffs = [];
        foreach ($allTitles as $title) {
            $curQty = (int) ($getCurrent->get($title)?->qty ?? 0);
            $curRev = (int) ($getCurrent->get($title)?->revenue ?? 0);
            $prevQty = (int) ($getPrevious->get($title)?->qty ?? 0);
            $prevRev = (int) ($getPrevious->get($title)?->revenue ?? 0);

            $diffQty = $curQty - $prevQty;
            $diffRev = $curRev - $prevRev;

            $diffs[] = [
                'title' => $title,
                'current_qty' => $curQty,
                'previous_qty' => $prevQty,
                'diff_qty' => $diffQty,
                'current_rev' => $curRev,
                'previous_rev' => $prevRev,
                'diff_rev_fcfa' => $diffRev,
            ];
        }

        // Gainers : triés par gain de CA
        $gainers = array_filter($diffs, fn($d) => $d['diff_rev_fcfa'] > 0);
        usort($gainers, fn($a, $b) => $b['diff_rev_fcfa'] <=> $a['diff_rev_fcfa']);

        // Decliners : triés par perte de CA
        $decliners = array_filter($diffs, fn($d) => $d['diff_rev_fcfa'] < 0);
        usort($decliners, fn($a, $b) => $a['diff_rev_fcfa'] <=> $b['diff_rev_fcfa']);

        return [
            'gainers' => array_slice($gainers, 0, 4),
            'decliners' => array_slice($decliners, 0, 4),
        ];
    }

    protected function calcPercentageChange(float|int $current, float|int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    protected function determineTrajectoryVerdict(float $revenueGrowth, float $ordersGrowth, int $deliveredCount): array
    {
        if ($revenueGrowth >= 20.0) {
            return [
                'status' => 'excellent',
                'label' => 'Croissance Forte 🚀',
                'badge_class' => 'badge-ok',
                'description' => "Excellente dynamique (+{$revenueGrowth}% de CA). Votre acquisition commerciale porte ses fruits.",
            ];
        } elseif ($revenueGrowth >= 5.0) {
            return [
                'status' => 'good',
                'label' => 'Croissance Modérée 📈',
                'badge_class' => 'badge-ok',
                'description' => "Trajectoire positive (+{$revenueGrowth}%). Maintenez vos actions de promotion.",
            ];
        } elseif ($revenueGrowth >= -5.0) {
            return [
                'status' => 'stable',
                'label' => 'Activité Stable ⚖️',
                'badge_class' => 'badge-info',
                'description' => "Chiffre d'affaires stable ({$revenueGrowth}%). Opportunité d'activer des offres promotionnelles ou de relancer vos clients.",
            ];
        } elseif ($revenueGrowth >= -20.0) {
            return [
                'status' => 'warning',
                'label' => 'Ralentissement 📉',
                'badge_class' => 'badge-warn',
                'description' => "Recul de {$revenueGrowth}% du CA. Inspectez vos ruptures de stocks et relancez votre communauté.",
            ];
        } else {
            return [
                'status' => 'danger',
                'label' => 'Baisse Critique ⚠️',
                'badge_class' => 'badge-danger',
                'description' => "Forte contraction du CA ({$revenueGrowth}%). Priorité urgente au réapprovisionnement et aux promotions flash.",
            ];
        }
    }

    protected function calculateHealthScore(int $ordersCount, float $codSuccessRate, int $criticalStockCount, int $deadStockCount): int
    {
        $score = 70; // Base médiane

        // Bonus / Malus volume de commandes
        if ($ordersCount >= 30) $score += 15;
        elseif ($ordersCount >= 10) $score += 8;
        elseif ($ordersCount === 0) $score -= 20;

        // Bonus / Malus taux de livraison COD
        if ($codSuccessRate >= 80) $score += 15;
        elseif ($codSuccessRate >= 65) $score += 5;
        elseif ($codSuccessRate < 50 && $codSuccessRate > 0) $score -= 15;

        // Malus ruptures critiques
        $score -= min($criticalStockCount * 6, 20);

        // Malus stock dormant excessif
        $score -= min($deadStockCount * 2, 12);

        return max(10, min(100, $score));
    }

    protected function getStrategicVerdictText(int $score, int $sleepingCapital, int $ruptureCount): string
    {
        if ($score >= 80) {
            return "Excellente santé commerciale globale ($score/100). Votre boutique performe bien. Concentrez-vous sur le réassort régulier et la fidélisation WhatsApp.";
        } elseif ($score >= 60) {
            return "Bonne dynamique ($score/100), mais du capital est piégé (" . number_format($sleepingCapital, 0, ',', ' ') . " FCFA en stock dormant) et $ruptureCount article(s) demandent un réassort immédiat.";
        } else {
            return "Attention requise ($score/100) : des goulots d'étranglement ralentissent votre croissance. Une action rapide sur les stocks et les confirmations de commandes est conseillée.";
        }
    }

    /**
     * Appelle le LLM pour rédiger un brief exécutif stratégique direct.
     */
    protected function generateLlmExecutiveBriefing(Shop $shop, array $diagnosis): string
    {
        $memoryContext = app(AiShopMemoryService::class)->formatForPrompt($shop);

        $prompt = "Tu es le Directeur Commercial & Stratégique IA de la boutique '{$shop->name}'.
{$memoryContext}

Voici les métriques réelles actuelles :
- Score de santé : {$diagnosis['health_score']}/100
- Capital dormant immobilisé : " . number_format($diagnosis['dead_stock']['total_capital_immobilise_fcfa'], 0, ',', ' ') . " FCFA ({$diagnosis['dead_stock']['total_items_count']} articles sans vente depuis 30j)
- Produits en rupture ou urgence réassort : {$diagnosis['restock_urgency']['critical_items_count']}
- Part du Cash on Delivery : {$diagnosis['cod_diagnosis']['cod_share_percent']}% avec un taux de livraison de {$diagnosis['cod_diagnosis']['cod_success_rate']}% et d'annulation de {$diagnosis['cod_diagnosis']['cod_cancel_rate']}%
- Frais de port perdus estimés sur annulations COD : " . number_format($diagnosis['cod_diagnosis']['estimated_lost_shipping_fcfa'], 0, ',', ' ') . " FCFA

Rédige un briefing exécutif ultra-clair pour le commerçant avec EXACTEMENT ce format markdown :
### 🎯 3 Priorités Stratégiques Immédiates
1. **[Titre priorité 1]** : Explication chiffrée et action concrète à faire dès aujourd'hui.
2. **[Titre priorité 2]** : Explication et tactique.
3. **[Titre priorité 3]** : Explication et tactique.

### 💡 Idée d'Opération Commerciale de la Semaine
(Propose une opération concrète : promo flash, pack duo, ou message WhatsApp spécifique pour débloquer du chiffre d'affaires rapidement).";

        try {
            $response = $this->llm->chat([
                ['role' => 'system', 'content' => 'Tu es un expert en e-commerce africain (marché francophone, FCFA, COD, WhatsApp). Tu es direct, bienveillant, orienté rentabilité et cash-flow.'],
                ['role' => 'user', 'content' => $prompt],
            ], [], [
                'max_tokens' => 900,
                'temperature' => 0.3,
            ]);

            return $response['content'] ?? "Briefing non disponible actuellement.";
        } catch (\Throwable $e) {
            Log::warning("Erreur génération briefing IA : " . $e->getMessage());
            return "Recommandations stratégiques : 1. Réapprovisionnez vos articles phares. 2. Lancez une remise de 15% sur les articles sans vente. 3. Confirmez chaque commande COD par WhatsApp sous 15 minutes.";
        }
    }
}
