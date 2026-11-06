<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiGeneration;
use App\Models\AiMessage;
use App\Models\Shop;
use App\Models\User;
use App\Services\Ai\Contracts\LlmProviderInterface;
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
        $this->llm = $llm ?? new OpenRouterLlmProvider();
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
        $isAdmin = $user->isAdmin();
        $remainingCredits = $isAdmin ? 9999 : $shop->aiCreditsRemaining();
        if (! $isAdmin && $remainingCredits <= 0) {
            return [
                'status' => 'quota_exceeded',
                'message' => "Votre forfait actuel ne dispose plus de crédits IA disponibles pour ce mois. Veuillez passer à un forfait supérieur pour continuer à utiliser votre assistant ECOM AI.",
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

        // 5. Construction du System Prompt enrichi (RAG + Mémoire + Rôle + Contexte de fil)
        $systemPrompt = $this->buildSystemPrompt($shop, $userMessage, $ragChunks, $priorMessagesCount);

        // 6. Assemblage de l'historique des messages récents (16 derniers pour conserver du contexte)
        $history = $conversation->messages()
            ->where('id', '!=', $userMsgRecord->id)
            ->latest('id')
            ->take(16)
            ->get()
            ->reverse();

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

        // 7. Premier appel LLM avec déclaration des outils
        $toolsSchema = $this->tools->getToolsSchema();
        $response = $this->llm->chat($messagesPayload, $toolsSchema, $options);

        $totalTokensInput = $response['tokens_input'] ?? 0;
        $totalTokensOutput = $response['tokens_output'] ?? 0;
        $modelUsed = $response['model_used'] ?? 'openrouter';

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
            ->latest('id')
            ->first();
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
     * Assemble le System Prompt enrichi avec le profil boutique, la mémoire long terme, les alertes proactives et les documents RAG.
     */
    protected function buildSystemPrompt(Shop $shop, string $userMessage, $ragChunks, int $priorMessagesCount = 0): string
    {
        $shopName = $shop->name ?: 'Votre boutique';
        $currency = $shop->currency ?: 'FCFA';
        $category = $shop->business_category ?: 'Commerce de détail';
        $subdomain = $shop->subdomain ? "{$shop->subdomain}.zanika.shop" : 'Non configuré';

        // 1. Récupération de la mémoire persistante de la boutique
        $memoryText = $this->memory->formatForPrompt($shop);

        // 2. Formatage des chunks de connaissances RAG
        $ragText = $this->rag->formatForPrompt($ragChunks);

        // 3. Détection des alertes proactives du moment
        $proactiveText = $this->proactiveAdvisor->formatForPrompt($shop);

        // 4. Instructions de continuité conversationnelle
        $userMsgLower = mb_strtolower(trim($userMessage));
        $hasGreeting = (bool) preg_match('/\b(bonjour|salut|hello|bonsoir|coucou|salam|marhaba|ahlan|hey)\b/ui', $userMsgLower);

        if ($priorMessagesCount > 0) {
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
Tu es **ZANIKA AI**, le copilote de direction commerciale et opérationnelle intégré à la plateforme E-Commerce Zanika.
Tu travailles en binôme direct avec le commerçant propriétaire de la boutique **"{$shopName}"**.

{$threadingDirective}

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
- **AUCUNE POLITESSE ILLUSOIRE OU NON SOLLICITÉE (RÈGLE ABSOLUE N°2)** :
  * Ne dis JAMAIS « Je vais très bien, merci », « Tout va pour le mieux », « Ravi de te retrouver », etc., sauf si le commerçant t'a expressément et mot pour mot posé la question « Comment vas-tu ? » ou « Ça va ? ».
- **INTERDICTION STRICTE DE RÉPÉTER LES MESSAGES PRÉCÉDENTS** : Ne commence JAMAIS ton nouveau message en recopiant ou en résumant ce que tu viens de dire au tour précédent. Chaque message doit être 100% nouveau, frais et répondre EXCLUSIVEMENT à la nouvelle question posée.
- **PAS DE PRÉAMBULE ROBOTIQUE NI DE RAPPEL DE RÔLE EN BOUCLE** : Une fois la conversation engagée, ne recommence pas tes messages par « Je suis une IA spécialisée... », « En tant que copilote Zanika... », ou en ramenant un sujet clos d'un message passé.
- **PAS DE FORMULE DE CLÔTURE AUTOMATIQUE** : Ne colle pas la phrase « Dis-moi, comment puis-je t'aider aujourd'hui ? Y a-t-il quelque chose... » à la fin de chaque message. Termine simplement ta réponse ou pose une question pertinente liée uniquement au sujet en cours.
- **RÉPONSE DIRECTE AUX QUESTIONS HORS-SUJET OU INAPPROPRIÉES (ex: pirater)** : Si le commerçant teste tes limites ou te demande une action illégale ou de pirater (« Tu peux me pirater un truc!? »), réponds en 1 à 2 phrases directes, calmes et avec une pointe d'humour sans moraliser (ex: « Haha non, je ne pirate rien du tout ! Mon seul super-pouvoir, c'est de booster les ventes et la gestion de ta boutique. Sur quel produit ou commande veux-tu qu'on avance ? »). Ne rejoue JAMAIS l'historique d'avant.

INFORMATIONS TEMPS RÉEL SUR LA BOUTIQUE :
- Nom commercial : {$shopName}
- Secteur d'activité : {$category}
- Devise monétaire officielle : {$currency}
- URL publique vitrine : https://{$subdomain}
- Marché principal : Afrique de l'Ouest (Togo, Bénin, Côte d'Ivoire, Sénégal...), avec forte prévalence du Paiement à la Livraison (Cash on Delivery - COD) et du Mobile Money.

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
                $newContent = trim(mb_substr($newContent, mb_strlen($prevPara)));
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

        $hasGreetingInUser = (bool) preg_match('/\b(bonjour|salut|hello|bonsoir|coucou|salam|marhaba|ahlan|hey)\b/ui', $userMsgLower);
        $hasHowAreYouInUser = (bool) preg_match('/\b(comment\s+(vas|allez|tu\s+vas|vous\s+allez)|(ça|ca)\s+va)\b/ui', $userMsgLower);

        // 1. Si l'utilisateur n'a pas expressément demandé comment va l'IA, supprimer "Je vais très bien...", "Ravi de vous retrouver"
        if (! $hasHowAreYouInUser) {
            // "Bonjour ! Je vais très bien, merci, et ravi de te retrouver aussi !"
            $content = preg_replace('/^(?:bonjour\s*!?,?\s*)?(?:je\s+vais\s+tr[eèé]s\s+bien,?\s*merci(?:,?\s*et\s+ravi\s+de\s+(?:te|vous)\s+retrouver(?:\s+aussi)?)?\s*!?[.]?\s*)/ui', '', $content);
            $content = preg_replace('/^(?:je\s+vais\s+bien,?\s*merci(?:,?\s*et\s+ravi\s+de\s+(?:te|vous)\s+retrouver(?:\s+aussi)?)?\s*!?[.]?\s*)/ui', '', $content);
            $content = preg_replace('/^(?:ravi\s+de\s+(?:te|vous)\s+retrouver(?:\s+aussi)?\s*!?[.]?\s*)/ui', '', $content);
        }

        // 2. Si la conversation est déjà en cours OU si l'utilisateur n'a pas salué du tout :
        // Supprimer "Bonjour !", "Salut !", etc. en début de message
        if ($priorMessagesCount > 0 || ! $hasGreetingInUser) {
            // Nettoyer les intros d'identité ex: "Bonjour ! Je suis Zanika AI..."
            $content = preg_replace('/^(?:bonjour|salut|bonsoir|coucou|hello|hey)\s*!?,?\s*(?:je\s+suis\s+(?:zanika\s+ai|votre\s+copilote|ecom\s+ai)[^.\n]*[.\n]*)/ui', '', $content);
            // Nettoyer les salutations simples ex: "Bonjour ! ", "Salut, "
            $content = preg_replace('/^(?:bonjour|salut|bonsoir|coucou|hello|hey)\s*!?,?\s*/ui', '', $content);
        }

        // 3. Nettoyer les résidus de ponctuation ou sauts de ligne orphelins en début
        $content = preg_replace('/^[\s\r\n\.,;:!-]+/', '', $content);

        // 4. Mettre en majuscule le premier caractère
        if (! empty($content)) {
            $firstChar = mb_substr($content, 0, 1);
            $content = mb_strtoupper($firstChar) . mb_substr($content, 1);
        }

        return trim($content);
    }
}
