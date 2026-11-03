<?php

namespace App\Services\Ai;

use App\Models\AiAutomation;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Providers\OpenRouterLlmProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AiMarketingAutomationService
{
    protected LlmProviderInterface $llm;

    public function __construct(?LlmProviderInterface $llm = null)
    {
        $this->llm = $llm ?? new OpenRouterLlmProvider();
    }

    /**
     * Génère des copies publicitaires et messages marketing multi-canaux percutants.
     *
     * @param Shop $shop
     * @param array $params ['product_id', 'channel', 'angle', 'target_audience', 'custom_discount']
     * @return array
     */
    public function generateCampaignCopy(Shop $shop, array $params): array
    {
        $channel = $params['channel'] ?? 'whatsapp';
        $angle = $params['angle'] ?? 'urgency';
        $targetAudience = $params['target_audience'] ?? 'Clients locaux en Afrique de l\'Ouest';
        $discount = $params['custom_discount'] ?? null;

        $product = null;
        if (! empty($params['product_id'])) {
            $product = Product::where('shop_id', $shop->id)->find($params['product_id']);
        }

        $productContext = $product
            ? "Produit : '{$product->title}'\nPrix : " . number_format($product->price, 0, ',', ' ') . " {$shop->currency}\nPrix barré : " . ($product->compare_at_price ? number_format($product->compare_at_price, 0, ',', ' ') . " {$shop->currency}" : "Aucun") . "\nDescription : {$product->description}"
            : "Offre générale pour la boutique : '{$shop->name}'\nDescription de la boutique : {$shop->description}";

        $channelInstructions = match ($channel) {
            'facebook_ads' => "Format : Publicité Facebook & Instagram Ads.
Structure attendue pour chaque variation :
- [Hook] : Phrase d'accroche captivante (stop scrolling)
- [Corps] : Problème client + solution + 3 bénéfices clés avec emojis élégants
- [Offre & Prix] : Mention claire du prix en {$shop->currency} et de la livraison
- [CTA] : Appel à l'action incisif (ex: Cliquez sur le lien pour commander sur WhatsApp ou sur notre boutique)
- [Ciblage suggéré] : Intérêts Meta Ads et tranche d'âge recommandés.",

            'whatsapp' => "Format : Statut WhatsApp ou Message de Diffusion Broadcast.
Style : Chaleureux, direct, percutant, prêt à être copié et envoyé.
Emojis bien dosés, phrases courtes, appel clair à répondre directement sur WhatsApp pour réserver avec paiement à la livraison (COD) ou Mobile Money.",

            'sms' => "Format : SMS Marketing (< 160 caractères).
Style : Extrêmement concis, direct, offre avec sentiment d'urgence et lien ou numéro à contacter.",

            'bundle_offer' => "Format : Offre Pack Duo / Bundling Promo.
Style : Proposer une formule 'Achetez le produit + un accessoire' ou 'Pack 2 exemplaires' pour booster le panier moyen et écouler le stock rapidement.",

            default => "Format : Publication commerciale engageante.",
        };

        $angleInstruction = match ($angle) {
            'urgency' => "Angle psychologique : Urgence & Rareté (stocks très limités, offre valable 24h/48h).",
            'social_proof' => "Angle psychologique : Preuve sociale & Succès (déjà adopté par des dizaines de clients, satisfaction garantie).",
            'discount' => "Angle psychologique : Bon plan & Économies" . ($discount ? " (Remise : {$discount})" : "") . " (prix exceptionnel aujourd'hui).",
            'benefit' => "Angle psychologique : Bénéfice produit direct (résolution d'un problème quotidien pénible, gain de temps/confort).",
            default => "Angle psychologique : Promotion efficace.",
        };

        $systemPrompt = "Tu es un expert copywriter d'élite en E-commerce en Afrique francophone (Togo, Bénin, Côte d'Ivoire, Sénégal, Cameroun). Tu maîtrises le marketing direct, les codes de WhatsApp et Facebook Ads, et l'usage du Franc CFA.
Ta mission est de rédiger EXACTEMENT 3 variations uniques et prêtes à l'emploi (Variation 1, Variation 2, Variation 3), hautement vendeuses.";

        $userPrompt = "{$productContext}
Audience cible : {$targetAudience}
Canal : {$channel}
{$channelInstructions}
{$angleInstruction}

Rédige les 3 variations avec le format JSON suivant pour un traitement direct :
{
  \"variations\": [
    {
      \"title\": \"Variation 1 - [Nom de l'angle]\",
      \"hook\": \"Phrase d'accroche\",
      \"content\": \"Texte complet prêt à copier-coller\",
      \"call_to_action\": \"Bouton ou CTA recommandé\",
      \"targeting_tip\": \"Conseil de ciblage ou conseil de diffusion\"
    },
    {
      \"title\": \"Variation 2 - [Nom de l'angle]\",
      \"hook\": \"Phrase d'accroche\",
      \"content\": \"Texte complet prêt à copier-coller\",
      \"call_to_action\": \"Bouton ou CTA recommandé\",
      \"targeting_tip\": \"Conseil de ciblage\"
    },
    {
      \"title\": \"Variation 3 - [Nom de l'angle]\",
      \"hook\": \"Phrase d'accroche\",
      \"content\": \"Texte complet prêt à copier-coller\",
      \"call_to_action\": \"Bouton ou CTA recommandé\",
      \"targeting_tip\": \"Conseil de ciblage\"
    }
  ]
}";

        try {
            $response = $this->llm->chatCompletion([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ], [
                'max_tokens' => 1400,
                'temperature' => 0.5,
            ]);

            $content = $response['content'] ?? '';

            // Nettoyage Markdown JSON éventuel
            if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $content, $matches)) {
                $content = $matches[1];
            }

            $parsed = json_decode($content, true);
            if (isset($parsed['variations']) && is_array($parsed['variations'])) {
                return [
                    'status' => 'success',
                    'channel' => $channel,
                    'angle' => $angle,
                    'product_title' => $product?->title ?? $shop->name,
                    'variations' => $parsed['variations'],
                ];
            }

            // Fallback si le format JSON n'est pas strict
            return [
                'status' => 'success',
                'channel' => $channel,
                'angle' => $angle,
                'product_title' => $product?->title ?? $shop->name,
                'variations' => [
                    [
                        'title' => 'Campagne Marketing Prête',
                        'hook' => 'Offre Spéciale ' . ($product?->title ?? $shop->name),
                        'content' => $content,
                        'call_to_action' => 'Commander maintenant sur WhatsApp',
                        'targeting_tip' => 'Diffusez en statut WhatsApp et en publicité sponsorisée locale.',
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            Log::error("Erreur génération campagne marketing IA : " . $e->getMessage());

            return [
                'status' => 'error',
                'message' => "Impossible de générer le contenu marketing pour l'instant : " . $e->getMessage(),
            ];
        }
    }

    /**
     * Initialise ou synchronise les automatisations standard pour la boutique.
     */
    public function syncDefaultAutomations(Shop $shop): void
    {
        $defaults = [
            [
                'event_type' => AiAutomation::EVENT_LOW_STOCK,
                'name' => 'Alerte Proactive de Stock Faible',
                'is_active' => true,
                'config' => [
                    'threshold_alert' => 3,
                    'notification_channel' => 'in_app',
                ],
            ],
            [
                'event_type' => AiAutomation::EVENT_PENDING_COD,
                'name' => 'Relance Commande COD en Attente (> 2h)',
                'is_active' => true,
                'config' => [
                    'delay_hours' => 2,
                    'action' => 'prepare_whatsapp_confirmation',
                ],
            ],
            [
                'event_type' => AiAutomation::EVENT_DAILY_SUMMARY,
                'name' => 'Briefing Matinal des Ventes (08h00)',
                'is_active' => true,
                'config' => [
                    'hour' => 8,
                    'include_top_sales' => true,
                ],
            ],
            [
                'event_type' => AiAutomation::EVENT_ABANDONED_CART,
                'name' => 'Détection des Paniers Abandonnés & Relance',
                'is_active' => false,
                'config' => [
                    'delay_hours' => 4,
                ],
            ],
        ];

        foreach ($defaults as $item) {
            AiAutomation::firstOrCreate(
                [
                    'shop_id' => $shop->id,
                    'event_type' => $item['event_type'],
                ],
                [
                    'name' => $item['name'],
                    'is_active' => $item['is_active'],
                    'config' => $item['config'],
                ]
            );
        }
    }

    /**
     * Exécute les règles d'automatisations actives pour la boutique.
     *
     * @param Shop $shop
     * @return array Résumé des déclenchements exécutés
     */
    public function runPendingAutomations(Shop $shop): array
    {
        $this->syncDefaultAutomations($shop);

        $automations = AiAutomation::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->get();

        $executionReports = [];

        foreach ($automations as $automation) {
            $report = match ($automation->event_type) {
                AiAutomation::EVENT_LOW_STOCK => $this->handleLowStockAutomation($shop, $automation),
                AiAutomation::EVENT_PENDING_COD => $this->handlePendingCodAutomation($shop, $automation),
                AiAutomation::EVENT_DAILY_SUMMARY => $this->handleDailySummaryAutomation($shop, $automation),
                default => null,
            };

            if ($report) {
                $executionReports[] = $report;
            }
        }

        return [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'executed_at' => Carbon::now()->toDateTimeString(),
            'total_automations_active' => $automations->count(),
            'reports' => $executionReports,
        ];
    }

    /**
     * Gère l'automatisation de stock faible.
     */
    protected function handleLowStockAutomation(Shop $shop, AiAutomation $automation): array
    {
        $lowStockProducts = Product::where('shop_id', $shop->id)
            ->where('track_stock', true)
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->get();

        $count = $lowStockProducts->count();
        if ($count > 0) {
            $automation->increment('trigger_count');
            $automation->update(['last_triggered_at' => Carbon::now()]);

            return [
                'automation' => $automation->name,
                'event' => $automation->event_type,
                'status' => 'triggered',
                'summary' => "{$count} article(s) sous le seuil d'alerte détectés.",
                'items' => $lowStockProducts->map(fn($p) => "{$p->title} (reste {$p->stock})")->take(5)->toArray(),
            ];
        }

        return [
            'automation' => $automation->name,
            'event' => $automation->event_type,
            'status' => 'ok',
            'summary' => "Tous les niveaux de stock sont optimaux.",
        ];
    }

    /**
     * Gère les commandes COD en attente de confirmation > 2h.
     */
    protected function handlePendingCodAutomation(Shop $shop, AiAutomation $automation): array
    {
        $twoHoursAgo = Carbon::now()->subHours(2);

        $pendingCodOrders = Order::where('shop_id', $shop->id)
            ->where('payment_method', 'cash_on_delivery')
            ->where('status', Order::STATUS_PENDING)
            ->where('created_at', '<=', $twoHoursAgo)
            ->get();

        $count = $pendingCodOrders->count();
        if ($count > 0) {
            $automation->increment('trigger_count');
            $automation->update(['last_triggered_at' => Carbon::now()]);

            return [
                'automation' => $automation->name,
                'event' => $automation->event_type,
                'status' => 'triggered',
                'summary' => "{$count} commande(s) COD en attente depuis plus de 2 heures requièrent confirmation.",
                'orders' => $pendingCodOrders->map(fn($o) => "{$o->order_number} ({$o->customer_name} - {$o->customer_phone})")->take(5)->toArray(),
            ];
        }

        return [
            'automation' => $automation->name,
            'event' => $automation->event_type,
            'status' => 'ok',
            'summary' => "Aucune commande COD critique en attente prolongée.",
        ];
    }

    /**
     * Gère le briefing matinal quotidien.
     */
    protected function handleDailySummaryAutomation(Shop $shop, AiAutomation $automation): array
    {
        $yesterday = Carbon::yesterday();
        $yesterdayOrders = Order::where('shop_id', $shop->id)
            ->whereDate('created_at', $yesterday)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->get();

        $yesterdayRevenue = (int) $yesterdayOrders->sum('total');
        $yesterdayCount = $yesterdayOrders->count();

        $automation->increment('trigger_count');
        $automation->update(['last_triggered_at' => Carbon::now()]);

        return [
            'automation' => $automation->name,
            'event' => $automation->event_type,
            'status' => 'triggered',
            'summary' => "Briefing quotidien : {$yesterdayCount} commande(s) pour " . number_format($yesterdayRevenue, 0, ',', ' ') . " {$shop->currency} hier.",
        ];
    }
}
