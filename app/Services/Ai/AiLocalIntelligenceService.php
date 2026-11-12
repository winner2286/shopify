<?php

namespace App\Services\Ai;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Str;

class AiLocalIntelligenceService
{
    protected AiRagService $rag;
    protected AiShopMemoryService $memory;

    public function __construct(?AiRagService $rag = null, ?AiShopMemoryService $memory = null)
    {
        $this->rag = $rag ?? new AiRagService();
        $this->memory = $memory ?? new AiShopMemoryService();
    }

    /**
     * Génère une réponse experte, humaine et connectée aux données réelles de la boutique,
     * au RAG de la plateforme et à la mémoire commerçant (Moteur Métier Local Souverain).
     */
    public function respond(array $messages, ?Shop $shop = null): array
    {
        $userText = '';
        $userMessagesCount = 0;

        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'user') {
                $userMessagesCount++;
                $userText = (string) ($m['content'] ?? '');
            }
        }

        $isOngoingThread = $userMessagesCount > 1;
        $normalized = mb_strtolower(trim($userText));

        if (! $shop) {
            $user = auth()->user();
            $shop = $user?->shop ?? Shop::withoutGlobalScopes()->first();
        }

        $shopName = $shop?->name ?: 'votre boutique';
        $currency = $shop?->currency ?: 'FCFA';

        // 1. Sécurité & Résistance aux injections de prompt / demandes illégales
        if (preg_match('/(ignore\s+previous|oublie\s+tes\s+r[eè]gles|system\s+prompt|pirater|hack|sql\s+injection|drop\s+table)/ui', $normalized)) {
            return $this->buildResponse(
                "Haha non, je ne pirate rien et je garde mes consignes de sécurité bien verrouillées ! Mon seul super-pouvoir, c'est d'optimiser la rentabilité, le catalogue et les commandes de **{$shopName}**. Sur quel sujet commercial veux-tu qu'on avance ?",
                'security_guardrail'
            );
        }

        // 2. Support Darija / Multilingue naturel
        if (preg_match('/\b(darija|kifash|kayn|mzyan|labas|salam|marhaba|bghit|chhal)\b/ui', $normalized)) {
            $prefix = $isOngoingThread ? '' : 'Marhaba ! ';
            return $this->buildResponse(
                "{$prefix}Ahlan ! Bien sûr, je comprends et je parle le Darija marocain avec plaisir (kanfhem darija mzyan !). Ana m3ak bach n3awnek f l-boutique dialk **{$shopName}** : gestion dial les produits, les commandes, les stocks w le marketing sur WhatsApp. Ach bghiti nchoufou daba ?",
                'darija_multilingual'
            );
        }

        // 3. Diagnostic Causal Approfondi : « Beaucoup de commandes mais bénéfices faibles »
        if (
            (str_contains($normalized, 'commande') && (str_contains($normalized, 'bénéfice') || str_contains($normalized, 'benefice') || str_contains($normalized, 'marge') || str_contains($normalized, 'rentab') || str_contains($normalized, 'gagne')))
            || str_contains($normalized, 'bénéfices sont faibles')
            || str_contains($normalized, 'benefices sont faibles')
            || str_contains($normalized, 'peu de bénéfice')
            || str_contains($normalized, 'marge faible')
        ) {
            return $this->buildResponse(
                $this->generateProfitabilityCausalDiagnosis($shop, $shopName, $currency),
                'profitability_causal_diagnosis'
            );
        }

        // 4. Recherche dans la Base de Connaissances RAG (Questions « Comment ajouter un produit ? », « Comment créer une réduction ? », etc.)
        $ragChunks = $this->rag->search($userText, null, 2);
        $topChunk = $ragChunks->first();

        if ($topChunk && ($topChunk->relevance_score ?? 0) >= 12.0 && $this->isPlatformHowToQuestion($normalized)) {
            $reply = "Voici exactement comment procéder sur **Zanika** concernant **« {$topChunk->title} »** :\n\n"
                . trim($topChunk->content);

            if ($shop) {
                $reply .= $this->appendLiveShopContextForChunk($shop, $topChunk->slug, $currency);
            }

            return $this->buildResponse($reply, 'rag_platform_guide:' . $topChunk->slug);
        }

        // 5. Indicateurs temps réel de la boutique
        $productsCount = $shop ? Product::withoutGlobalScopes()->where('shop_id', $shop->id)->count() : 0;
        $lowStockProducts = $shop
            ? Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('track_stock', true)
                ->whereColumn('stock', '<=', 'low_stock_threshold')
                ->get()
            : collect();
        $pendingOrders = $shop
            ? Order::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', Order::STATUS_PENDING)
                ->get()
            : collect();
        $deliveredOrders = $shop
            ? Order::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', Order::STATUS_DELIVERED)
                ->count()
            : 0;
        $totalGmv = $shop
            ? (int) Order::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', '!=', Order::STATUS_CANCELLED)
                ->sum('total')
            : 0;
        $formattedGmv = number_format($totalGmv, 0, ',', ' ') . " {$currency}";

        // 6. Salutations simples et prise de nouvelles en début de conversation
        $isGreeting = (bool) preg_match(
            '/^(?:bonjour|bjr|bonsoir|bsr|salut|slt|coucou|cc|hello|hey|yo|salam|marhaba|ahlan)(?:\s+(?:zanika|l\s*équipe|lequipe|la\s+team|tout\s+le\s+monde))?[!?.]*$|^(?:comment\s+(?:vas|allez|tu\s+vas|vous\s+allez)|(?:ça|ca)\s+va|tu\s+vas\s+bien|vous\s+allez\s+bien)[!?.]*$/ui',
            $normalized
        );

        if ($isGreeting) {
            $memoryNote = '';
            if ($shop) {
                $goal = $this->memory->getFact($shop, 'objectif_financier');
                if ($goal) {
                    $memoryNote = " Je garde bien en tête ton objectif ({$goal}).";
                }
            }

            $reply = $isOngoingThread
                ? "Je vais très bien et je suis toujours là avec toi pour **{$shopName}** !{$memoryNote} Sur quel produit, commande ou campagne veux-tu qu'on avance ?"
                : "Bonjour ! Je vais très bien, merci, et ravi d'échanger avec toi sur **{$shopName}**.{$memoryNote} Tout est opérationnel ({$productsCount} article(s) au catalogue, {$pendingOrders->count()} commande(s) en attente). Comment puis-je t'aider aujourd'hui ?";

            return $this->buildResponse($reply, 'natural_greeting');
        }

        // 7. Questions sur les stocks & ruptures
        if (str_contains($normalized, 'stock') || str_contains($normalized, 'rupture') || str_contains($normalized, 'inventaire') || str_contains($normalized, 'épuisé')) {
            $reply = "📦 **État des Stocks en direct — {$shopName}**\n\n";
            if ($lowStockProducts->isEmpty()) {
                $reply .= "✅ **Aucun produit en rupture ni sous le seuil critique** parmi tes {$productsCount} article(s).\n\n"
                    . "💡 *Astuce Zanika* : Tu peux vérifier les seuils d'alerte à tout moment dans `/produits` ou me demander de mettre à jour le stock d'un article précis.";
            } else {
                $reply .= "Attention, **{$lowStockProducts->count()} article(s)** ont atteint ou dépassé leur seuil critique :\n";
                foreach ($lowStockProducts->take(5) as $p) {
                    $badge = $p->stock <= 0 ? '🔴 RUPTURE (0 unité)' : "🟠 Stock faible ({$p->stock} unité(s))";
                    $reply .= "• **{$p->title}** : {$badge} — Prix : " . number_format($p->price, 0, ',', ' ') . " {$currency}\n";
                }
                $reply .= "\nVeux-tu que je prépare la mise à jour du stock d'un de ces articles ? Dis-moi simplement la nouvelle quantité.";
            }

            return $this->buildResponse($reply, 'inventory_analysis');
        }

        // 8. Questions sur les commandes, livraisons et Cash on Delivery (COD)
        if (str_contains($normalized, 'commande') || str_contains($normalized, 'livr') || str_contains($normalized, 'cod') || str_contains($normalized, 'coursier')) {
            $reply = "🚚 **Suivi des Commandes & Logistique — {$shopName}**\n\n";
            if ($pendingOrders->isEmpty()) {
                $reply .= "✅ Aucune commande en attente de traitement pour l'instant (Total livré avec succès : **{$deliveredOrders}** — Volume d'affaires : **{$formattedGmv}**).\n\n"
                    . "Tu peux consulter l'historique complet dans `/commandes` ou configurer tes tarifs par quartier dans `/livraison`.";
            } else {
                $reply .= "Tu as **{$pendingOrders->count()} commande(s) en attente** à traiter en priorité :\n";
                foreach ($pendingOrders->take(4) as $o) {
                    $reply .= "• **#{$o->order_number}** — " . number_format($o->total, 0, ',', ' ') . " {$currency} | Client : *{$o->customer_name}* ({$o->delivery_zone_name})\n";
                }
                $reply .= "\n💡 **Protocole COD Afrique** : Appelle ou envoie un vocal WhatsApp au client dans les 30 minutes pour confirmer son repère géographique exact et la disponibilité du montant avant d'envoyer le livreur.";
            }

            return $this->buildResponse($reply, 'orders_cod_analysis');
        }

        // 9. Si un fragment RAG pertinent existe même pour une question générale
        if ($topChunk) {
            $reply = "Pour t'accompagner sur **« {$topChunk->title} »** dans ta boutique **{$shopName}** :\n\n"
                . trim($topChunk->content);

            return $this->buildResponse($reply, 'rag_contextual:' . $topChunk->slug);
        }

        // 10. Demande ambiguë ou ouverte : poser une question de clarification pertinente
        $reply = "Je suis prêt à t'aider sur **{$shopName}** ({$productsCount} produits actifs, {$formattedGmv} de volume d'affaires).\n\n"
            . "Pour te donner une réponse ou une action ultra-précise, préfères-tu qu'on regarde :\n"
            . "1. **Ton catalogue & tes stocks** (ajout de produit, ajustement de prix ou alertes de rupture) ?\n"
            . "2. **Tes commandes & livraisons COD** (relance client WhatsApp, feuille de route coursier) ?\n"
            . "3. **Ta rentabilité & ton marketing** (diagnostic de marges, code promo ou script vidéo TikTok/WhatsApp) ?";

        return $this->buildResponse($reply, 'clarification_prompt');
    }

    /**
     * Analyse causale complète : « J'ai beaucoup de commandes mais mes bénéfices sont faibles ».
     */
    protected function generateProfitabilityCausalDiagnosis(?Shop $shop, string $shopName, string $currency): string
    {
        $orders = $shop
            ? Order::withoutGlobalScopes()->where('shop_id', $shop->id)->get()
            : collect();

        $totalOrders = $orders->count();
        $cancelledOrders = $orders->where('status', Order::STATUS_CANCELLED);
        $validOrders = $orders->where('status', '!=', Order::STATUS_CANCELLED);
        $cancelRate = $totalOrders > 0 ? round(($cancelledOrders->count() / $totalOrders) * 100, 1) : 0;
        $aov = $validOrders->count() > 0 ? (int) round($validOrders->sum('total') / $validOrders->count()) : 0;
        $lostShipping = (int) $cancelledOrders->sum('shipping_cost');

        $metricsIntro = $totalOrders > 0
            ? "J'ai examiné les données réelles de **{$shopName}** : tu comptes **{$totalOrders} commande(s)** avec un panier moyen de **" . number_format($aov, 0, ',', ' ') . " {$currency}** et un taux d'annulation de **{$cancelRate}%**" . ($lostShipping > 0 ? " (environ **" . number_format($lostShipping, 0, ',', ' ') . " {$currency}** de frais de port impactés sur les annulations)." : ".")
            : "C'est un cas classique en e-commerce : avoir du volume de commandes sans voir le cash net suivre dans la caisse de **{$shopName}**.";

        return <<<DIAG
📊 **Diagnostic de Rentabilité — Pourquoi tes commandes génèrent peu de bénéfices nets**

{$metricsIntro}

Voici les **4 causes racines** à vérifier en priorité et leurs solutions concrètes :

1. **Les frais de livraison réels mangent ta marge unitaire** :
   - *Le problème* : Si tu factures 1 000 {$currency} de livraison au client mais que le coursier moto te prend 1 500 ou 2 000 {$currency} pour les quartiers éloignés, tu perds 500 à 1 000 {$currency} sur chaque colis livré.
   - *La solution* : Ajuste tes zones tarifaires dans `/livraison` selon l'éloignement réel et fixe un seuil de panier minimum rentable pour offrir la livraison.

2. **Le coût caché des refus en Paiement à la Livraison (COD)** :
   - *Le problème* : Un colis refusé ou un client injoignable te coûte le déplacement du livreur aller-retour sans encaissement.
   - *La solution* : N'expédie **jamais** une commande COD sans une reconfirmation par appel ou vocal WhatsApp dans les 30 minutes (montant exact prêt + repère géographique précis).

3. **Un panier moyen (AOV) trop bas face au coût d'acquisition** :
   - *Le problème* : Vendre un seul article à faible marge couvre à peine le temps de gestion et la publicité.
   - *La solution* : Propose systématiquement une **Offre Pack Duo** sur WhatsApp (ex: *-15% sur le 2ème article*) au moment de confirmer la commande.

4. **Des remises unitaires trop fréquentes lors des négociations WhatsApp** :
   - *La solution* : Ne baisse plus le prix d'un article seul ; offre plutôt un avantage sur le lot ou sur la livraison.

👉 **Quelle action veux-tu qu'on lance maintenant ?** Je peux analyser tes zones de livraison, vérifier tes produits les moins rentables ou te préparer un script d'upsell WhatsApp.
DIAG;
    }

    protected function isPlatformHowToQuestion(string $normalized): bool
    {
        $patterns = [
            'comment', 'ou ', 'où ', 'ajouter', 'modifier', 'changer', 'creer', 'créer',
            'consulter', 'voir', 'savoir', 'configurer', 'forfait', 'abonnement',
            'studio', 'code promo', 'réduction', 'reduction', 'rupture', 'livreur',
        ];

        foreach ($patterns as $p) {
            if (str_contains($normalized, $p)) {
                return true;
            }
        }

        return false;
    }

    protected function appendLiveShopContextForChunk(Shop $shop, string $slug, string $currency): string
    {
        if ($slug === 'guide-rupture-stock-alertes') {
            $ruptures = Product::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('track_stock', true)
                ->where('stock', '<=', 0)
                ->count();
            return "\n\n📌 **En direct sur ta boutique** : Tu as actuellement **{$ruptures} produit(s)** en rupture totale de stock.";
        }

        if ($slug === 'guide-consulter-commandes') {
            $pending = Order::withoutGlobalScopes()
                ->where('shop_id', $shop->id)
                ->where('status', Order::STATUS_PENDING)
                ->count();
            return "\n\n📌 **En direct sur ta boutique** : Tu as **{$pending} commande(s) en attente** dans `/commandes`.";
        }

        return '';
    }

    protected function buildResponse(string $content, string $mode): array
    {
        return [
            'content' => $content,
            'tool_calls' => null,
            'tokens_input' => 0,
            'tokens_output' => 0,
            'model_used' => 'zanika-local-intelligence',
            'raw' => ['fallback_mode' => $mode],
        ];
    }
}

