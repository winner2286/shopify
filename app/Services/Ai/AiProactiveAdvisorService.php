<?php

namespace App\Services\Ai;

use App\Models\AiProactiveInsight;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AiProactiveAdvisorService
{
    /**
     * Analyse en profondeur les 7 signaux vitaux de la boutique et renvoie les insights proactifs détectés.
     */
    public function analyzeShop(Shop $shop): array
    {
        $insights = [];
        $now = Carbon::now();

        // ---------------------------------------------------------------------
        // 1. SIGNAL VITAL : Baisse Inhabituelle du Chiffre d'Affaires (7j vs 7j)
        // ---------------------------------------------------------------------
        $curr7Start = $now->copy()->subDays(6)->startOfDay();
        $curr7End = $now->copy()->endOfDay();
        $prev7Start = $now->copy()->subDays(13)->startOfDay();
        $prev7End = $now->copy()->subDays(7)->endOfDay();

        $revCurrent = (int) Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->whereNotIn('status', [Order::STATUS_CANCELLED])
            ->whereBetween('created_at', [$curr7Start, $curr7End])
            ->sum('total');

        $revPrevious = (int) Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->whereNotIn('status', [Order::STATUS_CANCELLED])
            ->whereBetween('created_at', [$prev7Start, $prev7End])
            ->sum('total');

        if ($revPrevious > 25000 && $revCurrent < ($revPrevious * 0.75)) {
            $dropPct = round((($revPrevious - $revCurrent) / $revPrevious) * 100);
            $insights[] = [
                'insight_type' => 'revenue_drop',
                'severity' => AiProactiveInsight::SEVERITY_WARNING,
                'title' => "Baisse de chiffre d'affaires (-{$dropPct}%) sur 7 jours",
                'explanation' => "Le chiffre d'affaires de cette semaine est de " . number_format($revCurrent, 0, ',', ' ') . " {$shop->currency} contre " . number_format($revPrevious, 0, ',', ' ') . " {$shop->currency} la semaine précédente. Un ralentissement des ventes est constaté.",
                'suggested_action' => [
                    'action' => 'launch_whatsapp_broadcast',
                    'label' => "Relancer les clients fidèles avec une offre flash WhatsApp",
                ],
            ];
        }

        // ---------------------------------------------------------------------
        // 2. SIGNAL VITAL : Augmentation des Annulations Cash on Delivery (14j)
        // ---------------------------------------------------------------------
        $orders14 = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('created_at', '>=', $now->copy()->subDays(14))
            ->get();

        $total14 = $orders14->count();
        $cancelled14 = $orders14->where('status', Order::STATUS_CANCELLED)->count();

        if ($total14 >= 5) {
            $cancellationRate = round(($cancelled14 / $total14) * 100);
            if ($cancellationRate >= 15) {
                $insights[] = [
                    'insight_type' => 'cod_cancellation_surge',
                    'severity' => AiProactiveInsight::SEVERITY_CRITICAL,
                    'title' => "Taux d'annulation élevé ({$cancellationRate}%) sur les 14 derniers jours",
                    'explanation' => "Sur {$total14} commandes récentes, {$cancelled14} ont été annulées ou refusées en livraison. Chaque annulation COD entraîne une perte sèche en frais de coursier moto.",
                    'suggested_action' => [
                        'action' => 'apply_cod_confirmation_protocol',
                        'label' => "Instaurer le protocole systématique d'appel vocal WhatsApp dans les 30 minutes",
                    ],
                ];
            }
        }

        // ---------------------------------------------------------------------
        // 3. SIGNAL VITAL : Produits Proches de la Rupture ou Rupture Critique
        // ---------------------------------------------------------------------
        $lowStockProducts = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('track_stock', true)
            ->where(function ($q) {
                $q->where('stock', '<=', 0)
                  ->orWhereColumn('stock', '<=', 'low_stock_threshold');
            })
            ->take(5)
            ->get();

        if ($lowStockProducts->isNotEmpty()) {
            $outOfStock = $lowStockProducts->filter(fn($p) => $p->stock <= 0);
            $names = $lowStockProducts->pluck('title')->take(3)->implode(', ');
            $severity = $outOfStock->isNotEmpty() ? AiProactiveInsight::SEVERITY_CRITICAL : AiProactiveInsight::SEVERITY_WARNING;

            $insights[] = [
                'insight_type' => 'stock_critical',
                'severity' => $severity,
                'title' => $outOfStock->isNotEmpty() ? "Alerte : Ruptures de stock avérées" : "Alerte : Stocks proches de l'épuisement",
                'explanation' => "{$lowStockProducts->count()} produit(s) nécessitent un réassort immédiat : {$names}." . ($lowStockProducts->count() > 3 ? " et d'autres." : ""),
                'suggested_action' => [
                    'action' => 'restock_or_preorder',
                    'label' => "Lancer un réassort ou passer les articles en précommande sur la vitrine",
                ],
            ];
        }

        // ---------------------------------------------------------------------
        // 4. SIGNAL VITAL : Produits Dormants / Slow-Movers (Stock > 0 sans vente)
        // ---------------------------------------------------------------------
        $soldProductIds30 = OrderItem::withoutGlobalScopes()
            ->whereHas('order', function ($q) use ($shop, $now) {
                $q->where('shop_id', $shop->id)
                  ->where('created_at', '>=', $now->copy()->subDays(30))
                  ->whereNotIn('status', [Order::STATUS_CANCELLED]);
            })
            ->pluck('product_id')
            ->unique();

        $dormantProducts = Product::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->where('stock', '>', 0)
            ->whereNotIn('id', $soldProductIds30)
            ->orderByDesc('stock')
            ->take(4)
            ->get();

        if ($dormantProducts->isNotEmpty()) {
            $dormantNames = $dormantProducts->pluck('title')->take(2)->implode(', ');
            $insights[] = [
                'insight_type' => 'sleeping_products',
                'severity' => AiProactiveInsight::SEVERITY_OPPORTUNITY,
                'title' => "Opportunité de déstockage : Produits dormants",
                'explanation' => "Certains articles dorment en stock sans aucune vente depuis plus de 30 jours ({$dormantNames}). Cela immobilise votre trésorerie.",
                'suggested_action' => [
                    'action' => 'prepare_flash_sale',
                    'label' => "Préparer une promotion déstockage avec prix barré (-15% ou -20%)",
                ],
            ];
        }

        // ---------------------------------------------------------------------
        // 5. SIGNAL VITAL : Commandes en Souffrance (Expédiées depuis > 48h)
        // ---------------------------------------------------------------------
        $stalledOrders = Order::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('status', Order::STATUS_SHIPPED)
            ->where('updated_at', '<=', $now->copy()->subHours(48))
            ->get();

        if ($stalledOrders->isNotEmpty()) {
            $insights[] = [
                'insight_type' => 'stalled_deliveries',
                'severity' => AiProactiveInsight::SEVERITY_WARNING,
                'title' => "{$stalledOrders->count()} commande(s) en souffrance chez les livreurs",
                'explanation' => "Des colis sont marqués 'Expédiés' depuis plus de 48 heures sans confirmation de livraison ni encaissement consigné.",
                'suggested_action' => [
                    'action' => 'contact_couriers',
                    'label' => "Relancer les livreurs pour récupérer les fonds encaissés ou récupérer les colis",
                ],
            ];
        }

        // ---------------------------------------------------------------------
        // 6. SIGNAL VITAL : Opportunité Calendaire (Période de Paie Ouest-Africaine)
        // ---------------------------------------------------------------------
        $dayOfMonth = (int) $now->format('j');
        if ($dayOfMonth >= 25 || $dayOfMonth <= 5) {
            $insights[] = [
                'insight_type' => 'sales_opportunity',
                'severity' => AiProactiveInsight::SEVERITY_OPPORTUNITY,
                'title' => "Période de paie en cours : Pouvoir d'achat maximal",
                'explanation' => "Nous sommes en fin/début de mois (période des salaires et virements). C'est le moment idéal pour pousser vos meilleures offres et renouveler vos statuts WhatsApp.",
                'suggested_action' => [
                    'action' => 'boost_catalog_offers',
                    'label' => "Mettre en avant vos packs avantageux pour maximiser le panier moyen",
                ],
            ];
        }

        return $insights;
    }

    /**
     * Synchronise et enregistre les alertes détectées dans la table ai_proactive_insights.
     */
    public function syncInsights(Shop $shop): Collection
    {
        $detected = $this->analyzeShop($shop);

        foreach ($detected as $item) {
            AiProactiveInsight::withoutGlobalScopes()->updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'insight_type' => $item['insight_type'],
                    'is_resolved' => false,
                ],
                [
                    'severity' => $item['severity'],
                    'title' => $item['title'],
                    'explanation' => $item['explanation'],
                    'suggested_action' => $item['suggested_action'] ?? null,
                    'is_dismissed' => false,
                ]
            );
        }

        return AiProactiveInsight::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('is_dismissed', false)
            ->where('is_resolved', false)
            ->orderByRaw("CASE WHEN severity = 'critical' THEN 1 WHEN severity = 'warning' THEN 2 WHEN severity = 'opportunity' THEN 3 ELSE 4 END")
            ->latest()
            ->take(4)
            ->get();
    }

    /**
     * Formate les alertes proactives pour le Prompt Système de l'IA.
     */
    public function formatForPrompt(Shop $shop): string
    {
        $insights = $this->syncInsights($shop);

        if ($insights->isEmpty()) {
            return "--- ALERTES COMMERCIALES PROACTIVES DU MOMENT ---\nTous les indicateurs vitaux de la boutique sont au vert (ventes stables, stocks sous contrôle, livraisons fluides).\n--- FIN DES ALERTES PROACTIVES ---";
        }

        $lines = ["--- ALERTES COMMERCIALES PROACTIVES DU MOMENT (DÉTECTION TEMPS RÉEL) ---"];
        $lines[] = "Tu as détecté de manière proactive les situations suivantes sur la boutique de ton partenaire commerçant :";

        foreach ($insights as $idx => $ins) {
            $num = $idx + 1;
            $sevBadge = strtoupper($ins->severity);
            $lines[] = "[ALERTE {$num} - {$sevBadge}] : {$ins->title}";
            $lines[] = "Détail : {$ins->explanation}";
            if (! empty($ins->suggested_action['label'])) {
                $lines[] = "Recommandation d'action : {$ins->suggested_action['label']}";
            }
            $lines[] = "";
        }

        $lines[] = "DIRECTIVE DE PROACTIVITÉ : Si le commerçant te salue ou te demande un point sur sa boutique, mentionne chaleureusement l'alerte prioritaire ci-dessus en lui proposant la solution d'action sans l'alarmer inutilement.";
        $lines[] = "--- FIN DES ALERTES PROACTIVES ---";

        return implode("\n", $lines);
    }
}
