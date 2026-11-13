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
        $userMessages = [];
        $assistantMessages = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? '';
            $content = trim((string) ($m['content'] ?? ''));
            if ($role === 'user' && $content !== '') {
                $userMessages[] = $content;
            } elseif ($role === 'assistant' && $content !== '') {
                $assistantMessages[] = $content;
            }
        }

        $userMessagesCount = count($userMessages);
        $userText = $userMessagesCount > 0 ? $userMessages[$userMessagesCount - 1] : '';
        $prevUserText = $userMessagesCount >= 2 ? $userMessages[$userMessagesCount - 2] : null;
        $lastAssistantText = ! empty($assistantMessages) ? $assistantMessages[count($assistantMessages) - 1] : null;

        $isOngoingThread = $userMessagesCount > 1;
        $normalized = mb_strtolower(trim($userText));

        if (! $shop) {
            $user = auth()->user();
            $shop = $user?->shop ?? Shop::withoutGlobalScopes()->first();
        }

        $shopName = $shop?->name ?: 'votre boutique';
        $currency = $shop ? $shop->currencyLabel() : 'FCFA';

        // 1. Sécurité & Résistance aux injections de prompt / demandes illégales
        if (preg_match('/(ignore\s+previous|oublie\s+tes\s+r[eè]gles|system\s+prompt|pirater|hack|sql\s+injection|drop\s+table)/ui', $normalized)) {
            return $this->buildResponse(
                "Haha non, je ne pirate rien et je garde mes consignes de sécurité bien verrouillées ! Mon seul super-pouvoir, c'est d'optimiser la rentabilité, le catalogue et les commandes de **{$shopName}**. Sur quel sujet commercial veux-tu qu'on avance ?",
                'security_guardrail',
                $lastAssistantText
            );
        }

        // 2. Support Darija / Anglais / Multilingue naturel
        if (preg_match('/\b(darija|kifash|kayn|mzyan|labas|salam|marhaba|bghit|chhal)\b/ui', $normalized)) {
            $prefix = $isOngoingThread ? '' : 'Marhaba ! ';
            return $this->buildResponse(
                "{$prefix}Ahlan ! Bien sûr, je comprends et je parle le Darija marocain avec plaisir (kanfhem darija mzyan !). Ana m3ak bach n3awnek f l-boutique dialk **{$shopName}** : gestion dial les produits, les commandes, les stocks w le marketing sur WhatsApp. Ach bghiti nchoufou daba ?",
                'darija_multilingual',
                $lastAssistantText
            );
        }

        if (preg_match('/\b(speak\s+english|english|do\s+you\s+speak|are\s+you\s+speak|can\s+you\s+speak|how\s+are\s+you|who\s+are\s+you|help\s+me)\b/ui', $normalized)) {
            return $this->buildResponse(
                "Yes, of course! I speak English fluently (as well as French and Darija). I'm **Zanika AI**, your e-commerce copilot connected live to **{$shopName}**. Would you like to check your **inventory**, review your **pending COD orders**, or boost your **sales & WhatsApp marketing**?",
                'english_multilingual',
                $lastAssistantText
            );
        }

        if (preg_match('/\b(parles?\s+anglais|en\s+anglais|quelles?\s+langues?)\b/ui', $normalized)) {
            return $this->buildResponse(
                "Oui, bien sûr ! Je parle couramment **français**, **anglais** et **darija**. Tu peux échanger avec moi dans la langue qui te convient le mieux pour piloter **{$shopName}** !",
                'language_capability',
                $lastAssistantText
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
                'profitability_causal_diagnosis',
                $lastAssistantText
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

            return $this->buildResponse($reply, 'rag_platform_guide:' . $topChunk->slug, $lastAssistantText);
        }

        // 5. Indicateurs temps réel de la boutique
        $products = $shop
            ? Product::withoutGlobalScopes()->where('shop_id', $shop->id)->get()
            : collect();
        $productsCount = $products->count();
        $lowStockProducts = $products->filter(fn($p) => $p->track_stock && $p->stock <= ($p->low_stock_threshold ?? 3));
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

        // 6. Prise de nouvelles explicite (« tu vas bien ? », « comment vas-tu ? », « ça va ? »)
        $isHowAreYou = (bool) preg_match(
            '/^(?:(?:bonjour|bjr|bonsoir|bsr|salut|slt|coucou|cc|hello|hey|yo)[,!?\s]+)?(?:comment\s+(?:vas|allez|tu\s+vas|vous\s+allez)(?:\s*-?\s*(?:tu|vous))?|(?:ça|ca)\s+va|tu\s+vas\s+bien|vous\s+allez\s+bien|tout\s+va\s+bien)[!?\s.]*$/ui',
            $normalized
        );

        if ($isHowAreYou) {
            $memoryNote = $this->buildMemoryNote($shop);
            $reply = "Je vais très bien, merci ! Ravie d'être à tes côtés pour faire grandir **{$shopName}**.{$memoryNote} "
                . "Ta boutique compte actuellement **{$productsCount} article(s)** et **{$pendingOrders->count()} commande(s) en attente**. Sur quoi veux-tu qu'on avance ?";

            return $this->buildResponse($reply, 'how_are_you_greeting', $lastAssistantText);
        }

        // 7. Salutations pures (« cc », « bonjour », « salut », « bonsoir », « coucou ») SANS « Je vais très bien » non sollicité
        $isPureGreeting = (bool) preg_match(
            '/^(bonjour|bjr|bonsoir|bsr|salut|slt|coucou|cc|hello|hey|yo)(?:\s+(?:zanika|l\s*équipe|lequipe|la\s+team|tout\s+le\s+monde|mon\s+ami|chef))?[!?.]*$/ui',
            $normalized,
            $greetMatches
        );

        if ($isPureGreeting) {
            $rawWord = mb_strtolower($greetMatches[1] ?? 'bonjour');
            $greetingWord = match (true) {
                in_array($rawWord, ['cc', 'coucou'], true) => 'Coucou !',
                in_array($rawWord, ['bonsoir', 'bsr'], true) => 'Bonsoir !',
                in_array($rawWord, ['salut', 'slt', 'hey', 'hello', 'yo'], true) => 'Salut !',
                default => 'Bonjour !',
            };

            $memoryNote = $this->buildMemoryNote($shop);

            // Si l'utilisateur vient déjà de saluer au tour précédent, varier naturellement pour faire avancer la conversation
            $prevWasGreeting = $prevUserText !== null && (bool) preg_match(
                '/^(?:bonjour|bjr|bonsoir|bsr|salut|slt|coucou|cc|hello|hey|yo|ça\s+va|ca\s+va|tu\s+vas\s+bien)/ui',
                mb_strtolower(trim($prevUserText))
            );

            if (! $isOngoingThread) {
                $reply = "{$greetingWord} Ravi de t'accompagner sur **{$shopName}**.{$memoryNote} "
                    . "Tout est prêt côté boutique (**{$productsCount} produit(s)** au catalogue, **{$pendingOrders->count()} commande(s) en attente**). "
                    . "Comment puis-je t'aider aujourd'hui ?";
            } elseif ($prevWasGreeting || ($lastAssistantText && str_contains($lastAssistantText, 'Sur quel produit, commande ou campagne'))) {
                $reply = "{$greetingWord} Je t'écoute pour **{$shopName}** ! Dis-moi directement ce que tu souhaites faire :\n"
                    . "• Vérifier tes **stocks** ({$productsCount} article(s) actifs) ou ajouter un produit\n"
                    . "• Suivre tes **commandes en attente** ({$pendingOrders->count()} à traiter)\n"
                    . "• Lancer une **action marketing** ou analyser ton chiffre d'affaires ({$formattedGmv})";
            } else {
                $reply = "{$greetingWord} Je suis bien là avec toi pour **{$shopName}** !{$memoryNote} Sur quel produit, commande ou campagne veux-tu qu'on avance ?";
            }

            return $this->buildResponse($reply, 'natural_greeting', $lastAssistantText);
        }

        // 8. Remerciements & acquittements courts (« merci », « super », « parfait », « top »)
        if (preg_match('/^(?:merci(?:\s+beaucoup|\s+bien|\s+zanika)?|super|parfait|top|nickel|g[eé]nial|c\s*[\'’]?\s*est\s+bon|ok\s+merci)[!?.]*$/ui', $normalized)) {
            $reply = "Avec grand plaisir ! Je reste disponible pour **{$shopName}** dès que tu veux analyser une commande, ajuster un prix ou préparer un message WhatsApp.";
            return $this->buildResponse($reply, 'courtesy_acknowledgement', $lastAssistantText);
        }

        // 9. Suivi contextuel multi-tours : réponses courtes (« oui », « ok », « vas-y », « 1 », « 2 », « 3 », « montre-moi »)
        $followUpReply = $this->resolveContextualFollowUp(
            $normalized,
            $lastAssistantText,
            $shop,
            $shopName,
            $currency,
            $products,
            $lowStockProducts,
            $pendingOrders,
            $deliveredOrders,
            $formattedGmv
        );
        if ($followUpReply !== null) {
            return $this->buildResponse($followUpReply['content'], $followUpReply['mode'], $lastAssistantText);
        }

        // 10. Questions sur les stocks & ruptures
        if (str_contains($normalized, 'stock') || str_contains($normalized, 'rupture') || str_contains($normalized, 'inventaire') || str_contains($normalized, 'épuisé')) {
            return $this->buildResponse(
                $this->formatInventoryAnalysis($shopName, $currency, $productsCount, $lowStockProducts),
                'inventory_analysis',
                $lastAssistantText
            );
        }

        // 11. Questions sur les commandes, livraisons et Cash on Delivery (COD)
        if (str_contains($normalized, 'commande') || str_contains($normalized, 'livr') || str_contains($normalized, 'cod') || str_contains($normalized, 'coursier')) {
            return $this->buildResponse(
                $this->formatOrdersAnalysis($shopName, $currency, $pendingOrders, $deliveredOrders, $formattedGmv),
                'orders_cod_analysis',
                $lastAssistantText
            );
        }

        // 12. Questions sur le catalogue, les produits ou les prix
        if (str_contains($normalized, 'produit') || str_contains($normalized, 'catalogue') || str_contains($normalized, 'article') || str_contains($normalized, 'prix')) {
            return $this->buildResponse(
                $this->formatCatalogOverview($shopName, $currency, $products),
                'catalog_overview',
                $lastAssistantText
            );
        }

        // 13. Questions sur le bilan des ventes, le chiffre d'affaires ou le marketing
        if (str_contains($normalized, 'vente') || str_contains($normalized, 'bilan') || str_contains($normalized, 'chiffre') || str_contains($normalized, 'revenu') || str_contains($normalized, 'promo') || str_contains($normalized, 'marketing') || str_contains($normalized, 'whatsapp')) {
            $reply = "📈 **Bilan Commercial & Opportunités — {$shopName}**\n\n"
                . "• **Volume d'affaires (GMV)** : **{$formattedGmv}** ({$deliveredOrders} commande(s) livrée(s), {$pendingOrders->count()} en attente)\n"
                . "• **Catalogue actif** : **{$productsCount} produit(s)** ({$lowStockProducts->count()} alerte(s) de stock)\n\n"
                . "💡 **Mes 2 meilleures actions pour aujourd'hui** :\n"
                . "1. **Relance WhatsApp COD** : Confirme les {$pendingOrders->count()} commande(s) en attente par vocal pour sécuriser l'encaissement.\n"
                . "2. **Offre Pack Duo** : Propose -15% sur le 2ème article en statut WhatsApp pour augmenter ton panier moyen.\n\n"
                . "Veux-tu que je te rédige le message WhatsApp prêt à envoyer à tes clients ?";

            return $this->buildResponse($reply, 'sales_and_marketing_summary', $lastAssistantText);
        }

        // 14. Si un fragment RAG pertinent existe avec un score de correspondance élevé (>= 14.0)
        if ($topChunk && ($topChunk->relevance_score ?? 0) >= 14.0) {
            $reply = "Pour t'accompagner sur **« {$topChunk->title} »** dans ta boutique **{$shopName}** :\n\n"
                . trim($topChunk->content);

            return $this->buildResponse($reply, 'rag_contextual:' . $topChunk->slug, $lastAssistantText);
        }

        // 15. Demande ambiguë ou ouverte : poser une question de clarification pertinente
        $reply = "Je suis prêt à t'aider sur **{$shopName}** ({$productsCount} produits actifs, {$formattedGmv} de volume d'affaires).\n\n"
            . "Pour te donner une réponse ou une action ultra-précise, préfères-tu qu'on regarde :\n"
            . "1. **Ton catalogue & tes stocks** (ajout de produit, ajustement de prix ou alertes de rupture) ?\n"
            . "2. **Tes commandes & livraisons COD** (relance client WhatsApp, feuille de route coursier) ?\n"
            . "3. **Ta rentabilité & ton marketing** (diagnostic de marges, code promo ou script vidéo TikTok/WhatsApp) ?";

        return $this->buildResponse($reply, 'clarification_prompt', $lastAssistantText);
    }

    protected function buildMemoryNote(?Shop $shop): string
    {
        if (! $shop) {
            return '';
        }

        $goal = $this->memory->getFact($shop, 'objectif_financier');

        return $goal ? " Je garde bien en tête ton objectif ({$goal})." : '';
    }

    protected function formatInventoryAnalysis(string $shopName, string $currency, int $productsCount, $lowStockProducts): string
    {
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

        return $reply;
    }

    protected function formatOrdersAnalysis(string $shopName, string $currency, $pendingOrders, int $deliveredOrders, string $formattedGmv): string
    {
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

        return $reply;
    }

    protected function formatCatalogOverview(string $shopName, string $currency, $products): string
    {
        if ($products->isEmpty()) {
            return "🛍️ **Catalogue — {$shopName}**\n\nTu n'as pas encore de produit enregistré. Rendez-vous dans `/produits/creer` ou dis-moi le nom, le prix en {$currency} et le stock de ton premier article pour que je prépare sa fiche !";
        }

        $reply = "🛍️ **Aperçu du Catalogue — {$shopName}** ({$products->count()} article(s)) :\n\n";
        foreach ($products->take(6) as $p) {
            $stockInfo = $p->track_stock ? "{$p->stock} en stock" : 'Stock illimité';
            $reply .= "• **{$p->title}** — **" . number_format($p->price, 0, ',', ' ') . " {$currency}** ({$stockInfo})\n";
        }
        $reply .= "\nSouhaites-tu modifier le prix ou le stock d'un de ces articles, ou créer un nouveau produit ?";

        return $reply;
    }

    /**
     * Interprète les réponses courtes de suivi (« oui », « ok », « vas-y », « 1 », « 2 », « 3 »)
     * en fonction du dernier message de l'assistant dans la conversation.
     */
    protected function resolveContextualFollowUp(
        string $normalized,
        ?string $lastAssistantText,
        ?Shop $shop,
        string $shopName,
        string $currency,
        $products,
        $lowStockProducts,
        $pendingOrders,
        int $deliveredOrders,
        string $formattedGmv
    ): ?array {
        if ($lastAssistantText === null) {
            return null;
        }

        // Choix numérotés suite à une proposition 1 / 2 / 3
        if (preg_match('/^(?:1|option\s*1|le\s+premier|la\s+premi[eè]re|catalogue|mes\s+stocks?)$/ui', $normalized)) {
            return [
                'content' => $this->formatInventoryAnalysis($shopName, $currency, $products->count(), $lowStockProducts),
                'mode' => 'followup_option_1_inventory',
            ];
        }

        if (preg_match('/^(?:2|option\s*2|le\s+deuxi[eè]me|la\s+deuxi[eè]me|mes\s+commandes?)$/ui', $normalized)) {
            return [
                'content' => $this->formatOrdersAnalysis($shopName, $currency, $pendingOrders, $deliveredOrders, $formattedGmv),
                'mode' => 'followup_option_2_orders',
            ];
        }

        if (preg_match('/^(?:3|option\s*3|le\s+troisi[eè]me|la\s+troisi[eè]me|ma\s+rentabilit[eé])$/ui', $normalized)) {
            return [
                'content' => $this->generateProfitabilityCausalDiagnosis($shop, $shopName, $currency),
                'mode' => 'followup_option_3_profitability',
            ];
        }

        // Acquiescement court (« oui », « ok », « d'accord », « vas-y », « montre-moi », « je veux bien »)
        $isAffirmative = (bool) preg_match(
            '/^(?:oui|ouais|ok|okay|d\s*[\'’]?\s*accord|vas\s*-?\s*y|allons\s*-?\s*y|go|montre(?:\s*-?\s*moi)?|je\s+veux\s+bien|volontiers|carr[eé]ment|fais\s*-?\s*le|lance)[!?.]*$/ui',
            $normalized
        );

        if (! $isAffirmative) {
            return null;
        }

        $lastLower = mb_strtolower($lastAssistantText);

        if (str_contains($lastLower, 'whatsapp') || str_contains($lastLower, 'pack duo') || str_contains($lastLower, 'upsell')) {
            $sampleProduct = $products->first()?->title ?? 'notre article phare';
            return [
                'content' => "📲 **Script WhatsApp prêt à copier pour {$shopName}** :\n\n"
                    . "> *« Bonjour ! Merci pour votre commande chez **{$shopName}** 😊 Votre colis (**{$sampleProduct}**) est prêt. Avant le départ de notre livreur, pouvez-vous me confirmer votre repère exact et que le montant en **{$currency}** est bien prévu ?\n"
                    . "> 💡 Offre Flash du jour : si vous ajoutez un 2ème article maintenant, je vous offre **-15% immédiat** dessus sans frais de livraison supplémentaires ! »*\n\n"
                    . "Veux-tu aussi qu'on vérifie tes commandes en attente ou tes stocks ?",
                'mode' => 'followup_whatsapp_script',
            ];
        }

        if (str_contains($lastLower, 'stock') || str_contains($lastLower, 'rupture')) {
            return [
                'content' => $this->formatCatalogOverview($shopName, $currency, $products),
                'mode' => 'followup_catalog_for_stock',
            ];
        }

        if (str_contains($lastLower, 'commande') || str_contains($lastLower, 'livreur')) {
            return [
                'content' => $this->formatOrdersAnalysis($shopName, $currency, $pendingOrders, $deliveredOrders, $formattedGmv),
                'mode' => 'followup_orders_detail',
            ];
        }

        return [
            'content' => $this->formatCatalogOverview($shopName, $currency, $products),
            'mode' => 'followup_general_overview',
        ];
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

    protected function buildResponse(string $content, string $mode, ?string $lastAssistantText = null): array
    {
        if ($lastAssistantText !== null && trim($content) === trim($lastAssistantText)) {
            $content .= "\n\n👉 Dis-moi simplement par quel sujet (produits, commandes ou marketing WhatsApp) tu préfères commencer.";
        }

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

