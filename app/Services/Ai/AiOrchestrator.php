<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiGeneration;
use App\Models\AiMessage;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Providers\GroqLlmProvider;
use App\Services\Ai\Providers\OpenRouterLlmProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiOrchestrator
{
    protected LlmProviderInterface $llm;
    protected ToolRegistry $tools;
    protected AiRagService $rag;
    protected AiShopMemoryService $memory;
    protected AiProactiveAdvisorService $proactiveAdvisor;
    protected ActionTokenService $tokenService;

    public function __construct(
        ?LlmProviderInterface $llm = null,
        ?ToolRegistry $tools = null,
        ?AiRagService $rag = null,
        ?AiShopMemoryService $memory = null,
        ?AiProactiveAdvisorService $proactiveAdvisor = null,
        ?ActionTokenService $tokenService = null
    ) {
        $this->llm = $llm ?? app(LlmProviderInterface::class);
        $this->tools = $tools ?? new ToolRegistry();
        $this->rag = $rag ?? new AiRagService();
        $this->memory = $memory ?? new AiShopMemoryService();
        $this->proactiveAdvisor = $proactiveAdvisor ?? new AiProactiveAdvisorService();
        $this->tokenService = $tokenService ?? new ActionTokenService();
    }

    /**
     * Traite un message utilisateur dans le cadre d'une conversation et orchestre le Tool Calling, le RAG et la mémoire.
     */
    public function sendMessage(Shop $shop, User $user, string $userMessage, ?int $conversationId = null, array $options = []): array
    {
        $startTime = microtime(true);

        // 1. Vérification des crédits IA de la boutique (Illimité pour le Super-Admin)
        $isAdmin = $user->isAdmin() || (bool) $shop->owner?->isAdmin();
        $remainingCredits = $isAdmin ? 9999 : $shop->aiCreditsRemaining();
        if (! $isAdmin && $remainingCredits <= 0) {
            return [
                'status' => 'quota_exceeded',
                'message' => "Votre forfait actuel ne dispose plus de crédits IA disponibles pour ce mois. Veuillez recharger votre compte ou passer à un forfait supérieur pour continuer à utiliser votre copilote Zanika AI.",
                'remaining_credits' => 0,
            ];
        }

        // 2. Récupération ou création de la conversation
        $forceNew = (bool) ($options['force_new_conversation'] ?? false);
        $conversation = null;
        if ($conversationId && ! $forceNew) {
            $conversation = AiConversation::where('shop_id', $shop->id)->find($conversationId);
        }

        if (! $conversation && ! $forceNew) {
            // Continuité du fil : réattacher à la dernière conversation active de moins de 2 heures
            $conversation = AiConversation::where('shop_id', $shop->id)
                ->where('user_id', $user->id)
                ->where('last_message_at', '>=', now()->subHours(2))
                ->latest('last_message_at')
                ->first();
        }

        if (! $conversation) {
            $conversation = AiConversation::create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'title' => Str::limit(trim($userMessage), 50, '...'),
                'last_message_at' => now(),
            ]);
        }

        // Compter les messages échangés avant ce tour pour déterminer l'état du fil de discussion
        $priorMessagesCount = $conversation->messages()
            ->where('id', '!=', 0)
            ->whereIn('role', ['user', 'assistant'])
            ->count();

        // 3. Enregistrement du message utilisateur
        $userMsgRecord = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // 4. Recherche de connaissances RAG pertinentes pour la question
        $ragChunks = $this->rag->search($userMessage, null, 3);

        // 5. Construction du System Prompt enrichi (Plan/Crédits/Droits + RAG + Mémoire + Contexte de fil)
        $systemPrompt = $this->buildSystemPrompt($shop, $userMessage, $ragChunks, $priorMessagesCount, $user);

        // 6. Assemblage de l'historique des messages récents (16 derniers dans l'ordre chronologique exact)
        $history = $conversation->messages()
            ->where('id', '!=', $userMsgRecord->id)
            ->reorder('id', 'desc')
            ->take(16)
            ->get()
            ->reverse()
            ->values();

        $messagesPayload = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        $validToolCallIds = [];
        $rawHistory = [];

        foreach ($history as $h) {
            $item = ['role' => $h->role];
            if ($h->content !== null) {
                $item['content'] = $h->content;
            }
            if (! empty($h->tool_calls)) {
                $item['tool_calls'] = $h->tool_calls;
                foreach ($h->tool_calls as $tc) {
                    if (! empty($tc['id'])) {
                        $validToolCallIds[$tc['id']] = true;
                    }
                }
            }
            if (! empty($h->tool_call_id)) {
                $item['tool_call_id'] = $h->tool_call_id;
            }
            if (! empty($h->tool_name)) {
                $item['name'] = $h->tool_name;
            }
            $rawHistory[] = $item;
        }

        foreach ($rawHistory as $item) {
            // Éviter les erreurs 400 OpenRouter : n'inclure un message 'tool' que si son appel figure dans l'historique retenu
            if ($item['role'] === 'tool' && (empty($item['tool_call_id']) || ! isset($validToolCallIds[$item['tool_call_id']]))) {
                continue;
            }
            $messagesPayload[] = $item;
        }

        $messagesPayload[] = ['role' => 'user', 'content' => $userMessage];

        // 7. Premier appel LLM avec déclaration des outils (désactivés sur les salutations et questions conversationnelles pour éviter latence/erreurs de schéma)
        $toolsSchema = $this->isConversationalWithoutTools($userMessage) ? [] : $this->tools->getToolsSchema();
        $response = $this->llm->chat($messagesPayload, $toolsSchema, $options);

        $totalTokensInput = $response['tokens_input'] ?? 0;
        $totalTokensOutput = $response['tokens_output'] ?? 0;
        $modelUsed = $response['model_used'] ?? ('groq:' . config('services.groq.model', 'openai/gpt-oss-120b'));

        // 8. Boucle Multi-Step ReAct (Raisonnement Itératif & Tool Calling)
        $maxIterations = (int) ($options['max_iterations'] ?? 4);
        $iteration = 0;
        $actionRequired = null;
        $callSignaturesHistory = [];

        while ($iteration < $maxIterations) {
            $iteration++;
            $toolCalls = $response['tool_calls'] ?? null;

            if (empty($toolCalls)) {
                // Le LLM a terminé ou n'a pas sollicité d'outil supplémentaire
                break;
            }

            // Détection de boucle infinie : vérifier si exactement le même appel d'outil est répété
            $currentSignature = md5(json_encode($toolCalls));
            if (isset($callSignaturesHistory[$currentSignature])) {
                Log::warning("AiOrchestrator: Boucle d'appels d'outils répétitifs détectée à l'itération {$iteration}. Interruption préventive de la boucle.");
                break;
            }
            $callSignaturesHistory[$currentSignature] = true;

            // Sauvegarder la décision d'appel d'outil
            AiMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $response['content'] ?: null,
                'tool_calls' => $toolCalls,
                'tokens_input' => $response['tokens_input'] ?? 0,
                'tokens_output' => $response['tokens_output'] ?? 0,
            ]);

            $messagesPayload[] = [
                'role' => 'assistant',
                'content' => $response['content'] ?: null,
                'tool_calls' => $toolCalls,
            ];

            // Exécuter chaque outil demandé
            foreach ($toolCalls as $toolCall) {
                $function = $toolCall['function'] ?? [];
                $toolName = $function['name'] ?? '';
                $rawArgs = $function['arguments'] ?? '{}';
                $args = is_string($rawArgs) ? json_decode($rawArgs, true) : (array) $rawArgs;
                $callId = $toolCall['id'] ?? ('call_' . uniqid());

                $toolResult = $this->tools->execute($toolName, $shop, $args ?: [], $user);

                // Si l'outil signale une action sensible nécessitant confirmation
                if (($toolResult['status'] ?? '') === 'action_required') {
                    $actionRequired = $toolResult;
                }

                $toolOutputJson = json_encode($toolResult, JSON_UNESCAPED_UNICODE);

                AiMessage::create([
                    'ai_conversation_id' => $conversation->id,
                    'role' => 'tool',
                    'content' => $toolOutputJson,
                    'tool_call_id' => $callId,
                    'tool_name' => $toolName,
                ]);

                $messagesPayload[] = [
                    'role' => 'tool',
                    'tool_call_id' => $callId,
                    'name' => $toolName,
                    'content' => $toolOutputJson,
                ];
            }

            // Si une action nécessite confirmation humaine (action_required), on désactive les outils
            // pour la suite du tour et on demande au LLM de formuler sa synthèse et demande de confirmation.
            $availableTools = ($iteration < $maxIterations && ! $actionRequired) ? $toolsSchema : [];

            $response = $this->llm->chat($messagesPayload, $availableTools, $options);

            $totalTokensInput += ($response['tokens_input'] ?? 0);
            $totalTokensOutput += ($response['tokens_output'] ?? 0);
        }

        $finalContent = $response['content'] ?? "J'ai traité votre demande à l'aide des outils de votre boutique.";
        $tokensIn = $response['tokens_input'] ?? 0;
        $tokensOut = $response['tokens_output'] ?? 0;

        // Nettoyage anti-répétition / anti-perroquet : suppression des résidus de tours précédents
        $prevAssistantMsg = $conversation->messages()
            ->where('role', 'assistant')
            ->whereNotNull('content')
            ->reorder('id', 'desc')
            ->first();
        $finalContent = $this->cleanLeakedReActTags($finalContent);
        $finalContent = $this->cleanDuplicatedContent($finalContent, $prevAssistantMsg?->content);
        $finalContent = $this->cleanUnwantedGreetings($finalContent, $userMessage, $priorMessagesCount);

        $finalAssistantMsg = AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $finalContent,
            'tokens_input' => $tokensIn,
            'tokens_output' => $tokensOut,
            'action_required' => $actionRequired,
        ]);

        // 9. Apprentissage continu : extraction automatique et mise à jour de la mémoire boutique
        try {
            $this->memory->extractAndSaveFacts($shop, $userMessage, $finalContent);
        } catch (\Throwable $e) {
            Log::warning("AiOrchestrator: échec extraction mémoire boutique : " . $e->getMessage());
        }

        // 10. Journalisation de la consommation dans ai_generations (pour décompte des crédits)
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        // Coût réel Gemini Flash / OpenRouter : ~0.15$ / 1M tokens entrée et ~0.60$ / 1M tokens sortie (1 USD ≈ 650 FCFA)
        $costInputUsd = ($totalTokensInput / 1000000) * 0.15;
        $costOutputUsd = ($totalTokensOutput / 1000000) * 0.60;
        $costEstimatedFcfa = max(1, (int) round(($costInputUsd + $costOutputUsd) * 650));

        AiGeneration::create([
            'shop_id' => $shop->id,
            'type' => 'ai_assistant_chat',
            'step' => 'chat_turn',
            'model_used' => $modelUsed,
            'prompt_tokens' => $totalTokensInput,
            'completion_tokens' => $totalTokensOutput,
            'cost_estimated_fcfa' => $costEstimatedFcfa,
            'duration_ms' => $durationMs,
            'status' => 'success',
            'input_summary' => Str::limit($userMessage, 120),
            'output_data' => [
                'conversation_id' => $conversation->id,
                'has_tools' => ! empty($response['tool_calls']),
                'has_rag' => $ragChunks->isNotEmpty(),
            ],
        ]);

        $shop->consumeAiCredits(1);
        $conversation->update(['last_message_at' => now()]);

        return [
            'status' => 'success',
            'conversation_id' => $conversation->id,
            'conversation_title' => $conversation->title,
            'message' => [
                'id' => $finalAssistantMsg->id,
                'role' => 'assistant',
                'content' => $finalAssistantMsg->content,
                'action_required' => $finalAssistantMsg->action_required,
                'created_at' => $finalAssistantMsg->created_at->format('H:i'),
            ],
            'remaining_credits' => $shop->aiCreditsRemaining(),
        ];
    }

    /**
     * Approuve ou refuse une action sensible demandée par l'IA.
     */
    public function handleActionApproval(Shop $shop, User $user, int $messageId, bool $approved): array
    {
        $message = AiMessage::find($messageId);
        if (! $message || ! $message->conversation || $message->conversation->shop_id !== $shop->id) {
            return ['status' => 'error', 'message' => 'Action non trouvée ou non autorisée.'];
        }

        $action = $message->action_required;
        if (empty($action) || ($action['status'] ?? '') !== 'action_required') {
            return ['status' => 'error', 'message' => 'Cette action n\'est plus en attente d\'approbation.'];
        }

        if (! $approved) {
            $action['status'] = 'rejected';
            $message->update(['action_required' => $action]);

            AiMessage::create([
                'ai_conversation_id' => $message->ai_conversation_id,
                'role' => 'assistant',
                'content' => "🚫 Modification annulée à votre demande. Aucune donnée n'a été altérée.",
            ]);

            return ['status' => 'success', 'message' => 'Action refusée.'];
        }

        // Exécution de l'action confirmée selon le type d'outil
        $toolName = $action['action_type'] ?? '';
        $token = $action['action_token'] ?? null;

        if ($toolName === 'create_product' || $toolName === 'create_product_from_photo') {
            $args = array_merge($action['product_payload'] ?? [], [
                'confirmed_by_user' => true,
                'action_token' => $token,
            ]);
        } elseif ($toolName === 'update_order_status') {
            $args = [
                'order_id' => $action['order_id'] ?? null,
                'new_status' => $action['new_status'] ?? '',
                'reason' => $action['reason'] ?? '',
                'confirmed_by_user' => true,
                'action_token' => $token,
            ];
        } elseif ($toolName === 'manage_shipping_zones') {
            $args = array_merge($action['shipping_payload'] ?? [], [
                'confirmed_by_user' => true,
                'action_token' => $token,
            ]);
        } elseif ($toolName === 'manage_discount_codes') {
            $args = array_merge($action['promo_payload'] ?? [], [
                'confirmed_by_user' => true,
                'action_token' => $token,
            ]);
        } else {
            $changes = $action['changes'] ?? [];
            $args = array_merge([
                'product_id' => $action['product_id'] ?? null,
                'confirmed_by_user' => true,
                'action_token' => $token,
            ], $changes);
        }

        $result = $this->tools->execute($toolName, $shop, $args, $user);

        if (($result['status'] ?? '') === 'security_rejection' || ! empty($result['error'])) {
            $errorMessage = $result['error'] ?? 'Échec lors de l\'exécution de l\'action.';
            return ['status' => 'error', 'message' => $errorMessage];
        }

        $action['status'] = 'approved';
        $action['execution_result'] = $result;
        $message->update(['action_required' => $action]);

        $summaryText = $result['summary'] ?? "Modification appliquée avec succès.";

        AiMessage::create([
            'ai_conversation_id' => $message->ai_conversation_id,
            'role' => 'assistant',
            'content' => "✅ **Action confirmée et appliquée !**\n\n{$summaryText}",
        ]);

        return ['status' => 'success', 'message' => 'Action validée et exécutée.', 'result' => $result];
    }

    /**
     * Calcule le forfait actif, les crédits IA restants et les fonctionnalités autorisées ou restreintes de la boutique.
     *
     * @return array{plan_name: string, plan_slug: string, credits_display: string, active_features: string, restricted_features: string}
     */
    protected function resolveShopCapabilities(Shop $shop, ?User $user = null): array
    {
        $isAdmin = (bool) ($user?->isAdmin() || $shop->owner?->isAdmin());
        $plan = $shop->currentPlan();
        $planName = $plan->name ?: 'Essentiel';
        $planSlug = $plan->slug ?: 'essentiel';
        $monthlyCredits = (int) $plan->ai_credits;
        $remainingCredits = $isAdmin ? 9999 : $shop->aiCreditsRemaining();
        $creditsDisplay = $isAdmin ? 'Illimité (Super-Admin)' : "{$remainingCredits} / {$monthlyCredits}";

        $maxProducts = (int) $plan->max_products;
        $productsCount = $shop->products()->count();

        $activeFeatures = [
            "Gestion du catalogue & produits ({$productsCount}/{$maxProducts} produits utilisés)",
            'Suivi des commandes, statuts de livraison, risque COD et zones de livraison',
            'Service client (réponses SAV et brouillons de messages WhatsApp/SMS)',
            'Copies marketing simples (WhatsApp, Facebook, SMS, offres bundle)',
            'Statistiques de ventes de base et export CSV (/commandes/exporter, /statistiques/exporter)',
        ];

        $restrictedFeatures = [
            'Exports comptables PDF automatisés (seuls les exports CSV commandes/statistiques existent sur Zanika)',
            'Mode précommande automatique sur la vitrine (seule la mise à jour du stock ou la désactivation du produit existent)',
            'Connexion de nom de domaine personnalisé ou gestion multi-collaborateurs directement depuis le chat IA',
        ];

        $planGatedCatalog = [
            'promo_codes' => [
                'active' => 'Codes promotionnels et réductions (/codes-promo)',
                'restricted' => 'Codes promotionnels et réductions (réservés aux formules Pro et Premium)',
            ],
            'tiktok_ads' => [
                'active' => 'Scripts vidéo TikTok / Instagram Reels et visuels publicitaires IA',
                'restricted' => 'Scripts vidéo TikTok / Instagram Reels et visuels publicitaires IA (réservés aux formules Pro et Premium)',
            ],
            'stock_alerts' => [
                'active' => 'Alertes critiques de rupture et seuils de stock',
                'restricted' => 'Alertes critiques automatisées de rupture de stock (réservées aux formules Pro et Premium)',
            ],
            'advanced_stats' => [
                'active' => 'Analyses avancées et comparaison de périodes',
                'restricted' => 'Analyses avancées et comparaison de périodes (réservées aux formules Pro et Premium)',
            ],
        ];

        foreach ($planGatedCatalog as $featureKey => $labels) {
            if ($isAdmin || $shop->canUseFeature($featureKey)) {
                $activeFeatures[] = $labels['active'];
            } else {
                $restrictedFeatures[] = $labels['restricted'];
            }
        }

        return [
            'plan_name' => $planName,
            'plan_slug' => $planSlug,
            'credits_display' => $creditsDisplay,
            'active_features' => implode(' ; ', $activeFeatures),
            'restricted_features' => implode(' ; ', $restrictedFeatures),
        ];
    }

    /**
     * Assemble le System Prompt enrichi avec le profil boutique, le forfait/crédits/droits, la mémoire long terme, les alertes proactives et les documents RAG.
     */
    protected function buildSystemPrompt(Shop $shop, string $userMessage, $ragChunks, int $priorMessagesCount = 0, ?User $user = null): string
    {
        $shopName = $shop->name ?: 'Votre boutique';
        $currencyLabel = $shop->currencyLabel();
        $currencyCode = $shop->currencyCode();
        $currency = $currencyLabel === $currencyCode ? $currencyLabel : "{$currencyLabel} ({$currencyCode})";
        $countryProfile = $shop->countryProfile();
        $countryName = $countryProfile['name'] ?? 'Togo';
        $shopCity = $shop->city ?: ($countryProfile['city'] ?? 'Lomé');
        $localPaymentInfo = $countryProfile['feexpay_subtitle'] ?? 'T-Money, Flooz, Mobile Money et Cartes bancaires';
        $category = $shop->business_category ?: 'Commerce de détail';
        $subdomain = $shop->subdomain ? "{$shop->subdomain}.zanika.shop" : 'Non configuré';
        $capabilities = $this->resolveShopCapabilities($shop, $user);

        // 1. Récupération de la mémoire persistante de la boutique
        $memoryText = $this->memory->formatForPrompt($shop);

        // 2. Formatage des chunks de connaissances RAG
        $ragText = $this->rag->formatForPrompt($ragChunks);

        // 3. Instructions de continuité conversationnelle et gestion des salutations simples
        $hasGreeting = $this->messageContainsGreeting($userMessage);
        $isPureGreeting = $this->isPureGreetingMessage($userMessage);

        // 4. Détection des alertes proactives (jamais sur une simple salutation ni en boucle)
        if ($isPureGreeting) {
            $proactiveText = "--- BAVARDAGE / SALUTATION SIMPLE ---\nLe commerçant t'adresse une simple salutation sans poser de question opérationnelle.\nRÈGLE OBLIGATOIRE : Réponds chaleureusement et brièvement (1 à 2 phrases naturelles, avec le nom de sa boutique) et demande-lui comment tu peux l'aider aujourd'hui. N'affiche AUCUN rapport d'alertes ou de ruptures de stock non sollicité.\n--- FIN ---";
        } elseif ($priorMessagesCount === 0) {
            $proactiveText = $this->proactiveAdvisor->formatForPrompt($shop);
        } else {
            $proactiveText = "--- NOTE SUR LES ALERTES DU MOMENT ---\nLa discussion est déjà en cours. Ne répète PAS les alertes de rupture de stock ou d'indicateurs déjà mentionnées au message précédent, sauf si le commerçant te pose expressément une question dessus.\n--- FIN NOTE ---";
        }

        if ($hasGreeting && $priorMessagesCount > 0) {
            $threadingDirective = <<<THREAD
--- LE COMMERÇANT TE SALUE ---
Le commerçant vient de te saluer (même si la discussion est déjà engagée).
RÈGLE OBLIGATOIRE : Rends-lui sa salutation avec chaleur et naturel (1 à 2 phrases courtes, un emoji léger possible), cite le nom de sa boutique, puis propose 1 piste concrète et utile (ex : point sur les commandes du jour, stock à surveiller, idée de promo).
Ne te présente pas, ne déverse pas de rapport, ne dis pas « Je vais très bien » sauf s'il te le demande.
--- FIN ---
THREAD;
        } elseif ($priorMessagesCount > 0) {
            $threadingDirective = <<<THREAD
--- ÉTAT DU FIL DE CONVERSATION EN COURS ---
Cette discussion est DÉJÀ en cours (échange #{$priorMessagesCount}).
RÈGLE OBLIGATOIRE : N'adresse AUCUNE salutation (« Bonjour », « Salut », etc.), ne dis JAMAIS « Je vais très bien », ne te présente pas.
Enchaîne DIRECTEMENT et naturellement sur la demande du commerçant comme dans une messagerie instantanée continue.
--- FIN DU STATUT DU FIL ---
THREAD;
        } elseif (! $hasGreeting) {
            $threadingDirective = <<<THREAD
--- DIRECTIVE DE RÉPONSE DIRECTE SANS PRÉAMBULE ---
Le commerçant entre directement dans le vif du sujet sans formuler de salutation.
RÈGLE OBLIGATOIRE : Réponds DIRECTEMENT à sa question sans aucun « Bonjour », sans « Salut », et sans civilités superflues (« Je vais très bien merci »).
--- FIN DIRECTIVE ---
THREAD;
        } else {
            $threadingDirective = "";
        }

        return <<<PROMPT
Tu es **ZANIKA AI**, l'assistant copilote IA officiel intégré à la plateforme e-commerce Zanika.
Tu travailles en binôme direct avec le marchand propriétaire de la boutique **"{$shopName}"** pour gérer son catalogue, ses commandes, ses livraisons et son marketing.

{$threadingDirective}

CONTEXTE VÉRIFIÉ DE LA BOUTIQUE :
- Nom commercial : {$shopName}
- Secteur d'activité : {$category}
- Pays & Ville de la boutique : {$countryName} ({$shopCity})
- Forfait actuel : {$capabilities['plan_name']} ({$capabilities['plan_slug']})
- Crédits IA restants : {$capabilities['credits_display']}
- Fonctionnalités réellement actives : {$capabilities['active_features']}
- Fonctionnalités NON disponibles (interdit de les proposer, promettre ou simuler) : {$capabilities['restricted_features']}
- Devise monétaire officielle : {$currency} (utilise TOUJOURS l'unité « {$currencyLabel} » pour exprimer les prix et montants de cette boutique)
- URL publique vitrine : https://{$subdomain}
- Marché & Paiements locaux : {$countryName} ({$shopCity}) — {$localPaymentInfo} + Paiement à la livraison (Cash on Delivery - COD).

RÈGLES STRICTES DE GARDE-FOU (PRIORITÉ ABSOLUE) :
1. **Contrôle des crédits** : Si les « Crédits IA restants » sont à 0, refuse poliment d'exécuter des actions de génération (textes, images, pubs, créations assistées) et invite le marchand à recharger son compte ou passer à un forfait supérieur.
2. **Anti-Hallucination & Respect du forfait** : Ne propose, ne promets et n'exécute JAMAIS une fonctionnalité qui ne figure pas dans « Fonctionnalités réellement actives » (ex : n'invente jamais d'exports comptables PDF ni de codes promo ou scripts vidéo TikTok si la boutique n'y a pas accès selon son forfait ; oriente avec tact vers le forfait adapté si le marchand le demande).
3. **Confidentialité & Sécurité** : Tu ne dois JAMAIS exposer de mots de passe, clés API, secrets, variables d'environnement, jetons de validation (`action_token`) ni d'identifiants techniques internes de Zanika.
4. **Raisonnement ReAct via Outils Natifs (Sans balises internes)** : Raisonne étape par étape avant d'agir, appelle uniquement les outils JSON déclarés (`tool_calls`) pour lire ou modifier les données réelles de la boutique, appuie-toi strictement sur leurs résultats observés sans jamais inventer de chiffres, et formule ta réponse finale au marchand en français naturel (ou dans sa langue) **sans jamais afficher de balises techniques (`Thought:`, `Action:`, `Action Input:`, `Observation:`, `Answer:`) ni de JSON brut**.

IDENTITÉ & POSTURE RELATIONNELLE NATURELLE :
- Tu es un véritable collaborateur d'affaires professionnel, chaleureux, accessible et clairvoyant.
- Tu n'es PAS un chatbot robotique aux formules prévisibles ni un simple répondeur automatique.
- Tu es transparent sur le fait que tu es une intelligence artificielle spécialisée dans le commerce électronique, mais ta conversation est naturelle, fluide, intelligente et humaine.
- **Distingue le bavardage informel des demandes opérationnelles** :
  * Si le commerçant te salue simplement (« Bonjour », « Salut l'équipe », « Ça va ? ») lors du premier message, réponds chaleureusement en 1 à 2 phrases naturelles et bienveillantes sans déverser de rapport technique.
  * Si la question touche à la gestion, au marketing ou à la stratégie, apporte une réponse claire, structurée et orientée résultats immédiatement.
- Fais preuve d'empathie sincère quand le commerçant rencontre un problème (rupture de stock, colis refusé, chiffre en baisse).
- Adapte la longueur de tes réponses : concis et direct pour les questions du quotidien (1 à 3 phrases), approfondi et bienveillant pour la stratégie.
- Si tu ne possèdes pas une information dans la base de données, admets-le avec simplicité plutôt que d'inventer.
- Pose une question ciblée de clarification lorsque la demande du commerçant est incomplète, au lieu de faire des suppositions hasardeuses.

COMPÉTENCES LINGUISTIQUES & MULTILINGUISME :
- Tu es parfaitement polyglotte : tu comprends et t'exprimes avec aisance en **Français**, en **Anglais**, en **Arabe standard** et en **Arabe dialectal marocain (Darija - الدارجة)**, ainsi que dans les principales langues d'Afrique de l'Ouest (Wolof, Fon, Éwé, Bambara).
- Si le commerçant te parle ou t'interroge en Darija (ex: « Tu comprends le darija!? », « Kayn chi promo? », « Kifash ndir l-boutique diali? »), réponds-lui chaleureusement en Darija (ou en français teinté de darija selon son style) et confirme avec enthousiasme que tu comprends et parles Darija !
- Ne prétends JAMAIS que tu es limité au français ou à l'anglais. Tu t'adaptes avec souplesse et fierté à la langue choisie par le commerçant.

FLUIDITÉ CONVERSATIONNELLE & RÈGLES STRICTES DU FIL DE DISCUSSION :
- **AUCUNE SALUTATION EN COURS DE DISCUSSION (RÈGLE ABSOLUE N°1)** :
  * Si la discussion est déjà engagée (dès le 2ème message), ou si la question ne contient pas de salutation explicite, il est STRICTEMENT INTERDIT d'écrire « Bonjour ! », « Salut ! », « Coucou ! ». Réponds DIRECTEMENT à la question.
  * EXCEPTION : si le commerçant te salue lui-même (« bonjour », « salut », « bonsoir »...), rends-lui sa salutation chaleureusement, même en cours de discussion, avec le nom de sa boutique et une proposition concrète (1 à 2 phrases).
- **AUCUNE POLITESSE ILLUSOIRE OU NON SOLLICITÉE (RÈGLE ABSOLUE N°2)** :
  * Ne dis JAMAIS « Je vais très bien, merci », « Tout va pour le mieux », « Ravi de te retrouver », etc., sauf si le commerçant t'a expressément et mot pour mot posé la question « Comment vas-tu ? » ou « Ça va ? ».
- **INTERDICTION STRICTE DE RÉPÉTER LES MESSAGES PRÉCÉDENTS** : Ne commence JAMAIS ton nouveau message en recopiant ou en résumant ce que tu viens de dire au tour précédent. Chaque message doit être 100% nouveau, frais et répondre EXCLUSIVEMENT à la nouvelle question posée.
- **PAS DE PRÉAMBULE ROBOTIQUE NI DE RAPPEL DE RÔLE EN BOUCLE** : Une fois la conversation engagée, ne recommence pas tes messages par « Je suis une IA spécialisée... », « En tant que copilote Zanika... », ou en ramenant un sujet clos d'un message passé.
- **PAS DE FORMULE DE CLÔTURE AUTOMATIQUE** : Ne colle pas la phrase « Dis-moi, comment puis-je t'aider aujourd'hui ? Y a-t-il quelque chose... » à la fin de chaque message. Termine simplement ta réponse ou pose une question pertinente liée uniquement au sujet en cours.
- **RÉPONSE DIRECTE AUX QUESTIONS HORS-SUJET OU INAPPROPRIÉES (ex: pirater)** : Si le commerçant teste tes limites ou te demande une action illégale ou de pirater (« Tu peux me pirater un truc!? »), réponds en 1 à 2 phrases directes, calmes et avec une pointe d'humour sans moraliser (ex: « Haha non, je ne pirate rien du tout ! Mon seul super-pouvoir, c'est de booster les ventes et la gestion de ta boutique. Sur quel produit ou commande veux-tu qu'on avance ? »). Ne rejoue JAMAIS l'historique d'avant.

{$memoryText}

{$proactiveText}

{$ragText}

RÈGLES D'OR DU MÉTIER E-COMMERCE AFRIQUE :
1. **Spécificités Pays & Réalités Locales (sans amalgame)** :
   - **Togo** : Monnaie FCFA (XOF), paiements T-Money (Mixx by Yas) et Flooz, coursiers zemidjans sur Lomé et périphérie (Agoè, Baguida, Adidogomé).
   - **Bénin** : Monnaie FCFA (XOF), paiements MTN Mobile Money (*880#) et Moov Flooz, conducteurs kékénons/motos (Cotonou, Calavi, Porto-Novo).
   - **Côte d'Ivoire** : Monnaie FCFA (XOF), Wave (frais 1%), Orange Money, MTN MoMo, coursiers express Abidjan (Cocody, Yopougon, Marcory, Plateau).
   - **Sénégal** : Monnaie FCFA (XOF), Wave (ultra-dominant) et Orange Money, livraisons express Dakar (Plateau, Almadies, Pikine).
2. **Gestion du Cash on Delivery (COD)** : Recommande toujours le protocole d'appel ou message vocal WhatsApp dans les 30 minutes suivant la commande pour reconfirmer la présence du client avec le montant exact et un repère géographique clair.
3. **Persuasion & Négociation sur WhatsApp** : Sur WhatsApp, ne brade jamais la marge unitaire d'un produit face à une demande de rabais. Propose plutôt un pack avantageux (ex: 2 articles avec remise globale) ou offre la livraison locale en geste commercial valorisant.
4. **Diagnostic de Rentabilité Approfondi** :
   Quand un commerçant s'interroge sur ses marges ou ses bénéfices faibles (« Beaucoup de commandes mais peu de bénéfices ») :
   - Examine les données chiffrées via tes outils (`get_sales_analytics`, `get_weekly_sales_report`).
   - Analyse les 4 causes racines : frais de coursier non répercutés, taux de refus COD après expédition, marge unitaire trop basse, remises excessives.
   - Propose un plan d'action hiérarchisé et concret.
5. **Garde-fous d'Écriture** : Toute modification de données (prix, stock, promo) doit passer par l'outil dédié qui déclenche la demande de confirmation explicite du commerçant (`action_required`). Aucune action sensible n'est appliquée sans validation explicite.
PROMPT;
    }

    /**
     * Nettoie les répétitions parasites entre tours de conversation (effet perroquet de LLM).
     */
    protected function cleanDuplicatedContent(string $newContent, ?string $previousContent): string
    {
        $newContent = trim($newContent);
        if (empty($newContent) || empty($previousContent)) {
            return $newContent;
        }

        $prevContent = trim($previousContent);

        // 1. Découpage du message précédent en paragraphes significatifs (> 25 caractères)
        $prevParagraphs = array_values(array_filter(
            array_map('trim', preg_split('/\n\s*\n/', $prevContent)),
            fn($p) => mb_strlen($p) >= 25
        ));

        // Si le nouveau message commence exactement par l'un des paragraphes du message précédent (ex: intro darija répétée)
        foreach ($prevParagraphs as $prevPara) {
            if (str_starts_with($newContent, $prevPara)) {
                $candidate = trim(mb_substr($newContent, mb_strlen($prevPara)));
                if ($candidate !== '') {
                    $newContent = $candidate;
                }
            }
        }

        // Si le nouveau message commence encore par des sauts de ligne ou ponctuations orphelines
        $newContent = preg_replace('/^[\s\r\n\.\,\:\;\-]+/', '', $newContent);

        // 2. Nettoyage de la phrase de clôture répétée mot pour mot en fin de message
        if (! empty($prevParagraphs)) {
            $lastPrevPara = end($prevParagraphs);
            if (mb_strlen($lastPrevPara) >= 25 && str_ends_with($newContent, $lastPrevPara)) {
                $trimmed = trim(mb_substr($newContent, 0, mb_strlen($newContent) - mb_strlen($lastPrevPara)));
                if (! empty($trimmed)) {
                    $newContent = $trimmed;
                }
            }
        }

        return trim($newContent);
    }

    /**
     * Supprime les salutations et politesses parasites générées par le LLM
     * quand l'utilisateur n'a pas salué ou quand la discussion est déjà engagée.
     */
    public function cleanUnwantedGreetings(string $content, string $userMessage, int $priorMessagesCount = 0): string
    {
        $content = trim($content);
        if (empty($content)) {
            return $content;
        }

        $userMsgLower = mb_strtolower(trim($userMessage));

        $hasGreetingInUser = $this->messageContainsGreeting($userMessage);
        $hasHowAreYouInUser = (bool) preg_match('/\b(comment\s+(vas|allez|tu\s+vas|vous\s+allez)|(ça|ca)\s+va|tu\s+vas\s+bien|vous\s+allez\s+bien)\b/ui', $userMsgLower);

        // 1. Si l'utilisateur n'a pas expressément demandé comment va l'IA, supprimer "Je vais très bien...", "Ravi de vous retrouver"
        if (! $hasHowAreYouInUser) {
            if (! $hasGreetingInUser) {
                $content = preg_replace('/^(?:bonjour\s*!?,?\s*)?(?:je\s+vais\s+tr[eèé]s\s+bien,?\s*merci(?:,?\s*et\s+ravi\s+de\s+(?:te|vous)\s+retrouver(?:\s+aussi)?)?\s*!?[.]?\s*)/ui', '', $content);
            }
            $content = preg_replace('/^((?:(?:bonjour|salut|bonsoir|coucou|hello|hey)\s*!?,?\s*)?)(?:je\s+vais\s+(?:tr[eèé]s\s+)?bien(?:,?\s*merci)?,?\s*et\s+ravi\s+de\s+(?:te|vous)\s+retrouver(?:\s+aussi)?\s*!?[.]?\s*)/ui', '$1', $content);
            $content = preg_replace('/^((?:(?:bonjour|salut|bonsoir|coucou|hello|hey)\s*!?,?\s*)?)(?:je\s+vais\s+(?:tr[eèé]s\s+)?bien(?:,?\s*merci)?,?\s+et\s+)/ui', '$1', $content);
            $content = preg_replace('/^((?:(?:bonjour|salut|bonsoir|coucou|hello|hey)\s*!?,?\s*)?)(?:je\s+vais\s+(?:tr[eèé]s\s+)?bien,?\s*merci\s*!?[.]?\s*)/ui', '$1', $content);
            $content = preg_replace('/^(?:ravi\s+de\s+(?:te|vous)\s+retrouver(?:\s+aussi)?\s*!?[.]?\s*)/ui', '', $content);
        }

        // 2. Si la conversation est déjà en cours OU si l'utilisateur n'a pas salué du tout :
        // Supprimer "Bonjour !", "Salut !", etc. en début de message
        if (! $hasGreetingInUser) {
            // Nettoyer les intros d'identité ex: "Bonjour ! Je suis Zanika AI..."
            $content = preg_replace('/^(?:bonjour|salut|bonsoir|coucou|hello|hey)\s*!?,?\s*(?:je\s+suis\s+(?:zanika\s+ai|votre\s+copilote|ecom\s+ai)[^.\n]*[.\n]*)/ui', '', $content);
            // Nettoyer les salutations simples ex: "Bonjour ! ", "Salut, "
            $content = preg_replace('/^(?:bonjour|salut|bonsoir|coucou|hello|hey)\s*!?,?\s*/ui', '', $content);
        }

        // 3. Nettoyer les résidus de ponctuation ou sauts de ligne orphelins en début
        $content = preg_replace('/^[\s\r\n\.,;:!-]+/', '', $content);

        // 4. Mettre en majuscule le premier caractère (et après une salutation conservée)
        if (! empty($content)) {
            $firstChar = mb_substr($content, 0, 1);
            $content = mb_strtoupper($firstChar) . mb_substr($content, 1);
            $content = (string) preg_replace_callback(
                '/^((?:Bonjour|Salut|Bonsoir|Coucou|Hello|Hey)\s*!\s+)(\p{Ll})/u',
                fn($m) => $m[1] . mb_strtoupper($m[2]),
                $content
            );
        }

        return trim($content);
    }

    /**
     * Détermine si le message du commerçant contient une salutation explicite (y compris abréviations courantes comme CC/slt).
     */
    protected function messageContainsGreeting(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(bonjour|bjr|bonsoir|bsr|salut|slt|coucou|cc|hello|hey|yo|salam|marhaba|ahlan)\b/ui',
            $normalized
        );
    }

    /**
     * Vérifie si le message est uniquement une salutation courte sans demande opérationnelle.
     */
    protected function isPureGreetingMessage(string $message): bool
    {
        $stripped = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower(trim($message)));
        $stripped = trim((string) preg_replace('/\s+/', ' ', (string) $stripped));
        if ($stripped === '') {
            return false;
        }

        return (bool) preg_match(
            '/^(bonjour|bjr|bonsoir|bsr|salut|slt|coucou|cc|hello|hey|yo|salam|marhaba|ahlan)(?:\s+(?:zanika|l\s+équipe|lequipe|la\s+team|tout\s+le\s+monde|ça\s+va|ca\s+va|comment\s+vas\s+tu|comment\s+tu\s+vas|comment\s+allez\s+vous|how\s+are\s+you))?$/ui',
            $stripped
        );
    }

    /**
     * Détecte si le message est purement conversationnel (salutation, langue parlée, identité, remerciement)
     * et ne nécessite aucun appel d'outil en base de données.
     */
    protected function isConversationalWithoutTools(string $message): bool
    {
        if ($this->isPureGreetingMessage($message)) {
            return true;
        }

        $stripped = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower(trim($message)));
        $stripped = trim((string) preg_replace('/\s+/', ' ', (string) $stripped));
        if ($stripped === '') {
            return false;
        }

        // Questions de langue (« Are you speak english!? », « Do you speak english? », « Tu parles anglais ? », « Wach katfhem darija ? »)
        if (preg_match('/\b(speak\s+english|english|anglais|darija|wolof|langue|language|parles?\s+tu|comprenez\s+vous|do\s+you\s+speak|are\s+you\s+speak|can\s+you\s+speak)\b/ui', $stripped)) {
            return true;
        }

        // Questions d'identité ou de politesse courte (« Qui es-tu ? », « Who are you? », « Merci », « Thank you »)
        if (preg_match('/^(?:qui\s+es\s+tu|tu\s+es\s+qui|who\s+are\s+you|what\s+is\s+your\s+name|comment\s+tu\s+t\s+appelles|merci|merci\s+beaucoup|thank\s+you|thanks|how\s+are\s+you|comment\s+vas\s+tu|tu\s+vas\s+bien|ça\s+va|ca\s+va)$/ui', $stripped)) {
            return true;
        }

        return false;
    }

    /**
     * Supprime d'éventuelles balises ReAct brutes (Thought/Action/Observation/Answer) si un LLM de secours en génère.
     */
    public function cleanLeakedReActTags(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (preg_match('/(?:^|\n)\s*(?:Thought|Action|Observation)\s*:/i', $content)
            && preg_match('/(?:^|\n)\s*Answer\s*:\s*(.+)$/is', $content, $matches)) {
            return trim($matches[1]);
        }

        $cleaned = preg_replace('/^\s*Answer\s*:\s*/i', '', $content);

        return trim((string) $cleaned);
    }
}
